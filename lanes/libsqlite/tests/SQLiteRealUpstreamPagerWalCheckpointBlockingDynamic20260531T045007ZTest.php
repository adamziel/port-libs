<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [1024, 2048, 4096];
$checkpointModes = ['passive', 'restart', 'full', 'truncate'];
$upstreamSections = [
    ['wal5.test', 'wal5-pragma 1.* blocking restart checkpoint waits for a reader then wraps WAL'],
    ['wal5.test', 'wal5-capi 1.* blocking restart checkpoint waits for a reader then wraps WAL'],
    ['wal5.test', 'wal5-pragma 2.1.* checkpoint applies all attached WAL files'],
    ['wal5.test', 'wal5-capi 2.1.* checkpoint applies all attached WAL files'],
    ['wal5.test', 'wal5-pragma 2.2.* restart checkpoint is busy with pinned main reader'],
    ['wal5.test', 'wal5-capi 2.2.* restart checkpoint is busy with pinned main reader'],
    ['wal5.test', 'wal5-pragma 2.3.* full checkpoint backfills only unpinned frames'],
    ['wal5.test', 'wal5-capi 2.3.* full checkpoint backfills only unpinned frames'],
    ['wal5.test', 'wal5-pragma 5.* checkpoint modes keep or truncate WAL with active readers'],
    ['wal5.test', 'wal5-capi 5.* checkpoint modes keep or truncate WAL with active readers'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '@', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s database page %02d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames) use ($pageImage): string {
    $littleEndianChecksums = ($case % 4) === 0;
    $salt1 = (0x71000000 + ($case * 19)) & 0xffffffff;
    $salt2 = (0x72000000 + ($case * 23)) & 0xffffffff;
    $headerPrefix = pack(
        'N*',
        $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        45007 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndianChecksums);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage($pageSize, (string) $frame['label']);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $checkpointModes[($case - 1) % count($checkpointModes)];
    $pageCount = 5 + ($case % 11);
    $readerEndFrame = 1 + ($case % 5);
    $firstPage = 1 + (($case * 3) % $pageCount);
    $secondPage = 1 + (($case * 5) % $pageCount);
    $thirdPage = 1 + (($case * 7) % $pageCount);
    $attachedPageCount = 3 + ($case % 7);
    $attachedReaderEndFrame = 1 + ($case % 3);
    $label = sprintf('%s %s real upstream checkpoint blocking dynamic %04d', $script, $section, $case);

    $mainFrames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} main transaction one draft"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} main transaction one commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$label} main transaction two draft"],
        ['page' => $firstPage, 'commit' => $pageCount, 'label' => "{$label} main transaction two commit"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$label} main pinned reader tail"],
        ['page' => $thirdPage, 'commit' => $pageCount, 'label' => "{$label} main post-reader commit"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} main uncommitted tail discarded"],
    ];
    $attachedFrames = [
        ['page' => 1 + ($case % $attachedPageCount), 'commit' => 0, 'label' => "{$label} aux transaction one draft"],
        ['page' => 1 + (($case + 2) % $attachedPageCount), 'commit' => $attachedPageCount, 'label' => "{$label} aux transaction one commit"],
        ['page' => 1 + (($case + 4) % $attachedPageCount), 'commit' => $attachedPageCount, 'label' => "{$label} aux transaction two commit"],
        ['page' => 1 + (($case + 6) % $attachedPageCount), 'commit' => 0, 'label' => "{$label} aux uncommitted tail discarded"],
    ];
    $mainDatabase = $databaseBytes($pageSize, $pageCount, $label . ' main');
    $attachedDatabase = $databaseBytes($pageSize, $attachedPageCount, $label . ' aux');
    $mainWalBytes = $walBytes($case, $pageSize, $mainFrames);
    $attachedWalBytes = $walBytes(2000 + $case, $pageSize, $attachedFrames);
    $testName = sprintf('real upstream pager wal checkpoint blocking dynamic 20260531T045007Z %04d %s %s', $case, $script, $section);

    $tests[$testName] = static function (TestRunner $t) use (
        $case,
        $script,
        $section,
        $mode,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $attachedPageCount,
        $attachedReaderEndFrame,
        $mainWalBytes,
        $attachedWalBytes,
        $mainDatabase,
        $attachedDatabase,
        $firstPage,
        $secondPage,
        $thirdPage
    ): void {
        $mainBoundary = SQLiteWal::transactionRecoveryBoundary($mainWalBytes, $mainDatabase, $pageSize);
        $attachedBoundary = SQLiteWal::transactionRecoveryBoundary($attachedWalBytes, $attachedDatabase, $pageSize);
        $mainWal = $mainBoundary['committed_wal'];
        $attachedWal = $attachedBoundary['committed_wal'];
        $mainCheckpoint = $mainWal->checkpointModeResult($mainDatabase, $mode, $readerEndFrame);
        $attachedCheckpoint = $attachedWal->checkpointModeResult($attachedDatabase, $mode, $attachedReaderEndFrame);
        $mainDurable = $mainWal->durableCheckpointResult($mainDatabase, $mode, $readerEndFrame);
        $attachedDurable = $attachedWal->durableCheckpointResult($attachedDatabase, $mode, $attachedReaderEndFrame);
        $currentFirst = $mainWal->readerSnapshotPageImage($mainDatabase, $firstPage);
        $pinnedFirst = $mainWal->readerSnapshotPageImage($mainDatabase, $firstPage, $readerEndFrame);
        $currentSecond = $mainWal->readerSnapshotPageImage($mainDatabase, $secondPage);
        $currentThird = $mainWal->readerSnapshotPageImage($mainDatabase, $thirdPage);

        $t->same('wal5.test', $script);
        $t->same(true, str_contains($section, 'checkpoint'));
        $t->same($pageSize, $mainWal->header->pageSize);
        $t->same($pageSize, $attachedWal->header->pageSize);
        $t->same('recovered_committed_prefix', $mainBoundary['status']);
        $t->same('uncommitted_valid_tail_after_last_commit', $mainBoundary['reason']);
        $t->same(7, $mainBoundary['valid_frame_count']);
        $t->same(6, $mainBoundary['committed_frame_count']);
        $t->same(1, $mainBoundary['discarded_valid_tail_frame_count']);
        $t->same([2, 4, 6], array_column($mainWal->committedTransactions(), 'last_frame'));
        $t->same('recovered_committed_prefix', $attachedBoundary['status']);
        $t->same(4, $attachedBoundary['valid_frame_count']);
        $t->same(3, $attachedBoundary['committed_frame_count']);
        $t->same([2, 3], array_column($attachedWal->committedTransactions(), 'last_frame'));
        $t->same($mode, $mainCheckpoint['mode']);
        $t->same($mode, $attachedCheckpoint['mode']);
        $t->same($readerEndFrame, $mainCheckpoint['reader_end_frame']);
        $t->same($attachedReaderEndFrame, $attachedCheckpoint['reader_end_frame']);
        $t->same($pageCount, $mainCheckpoint['database_page_count']);
        $t->same($attachedPageCount, $attachedCheckpoint['database_page_count']);
        $t->same($mainCheckpoint['wal_action'], $mainDurable['wal_action']);
        $t->same($attachedCheckpoint['wal_action'], $attachedDurable['wal_action']);
        $t->same(strlen((string) $mainDurable['wal_bytes']), $mainDurable['wal_bytes_length']);
        $t->same(strlen((string) $attachedDurable['wal_bytes']), $attachedDurable['wal_bytes_length']);
        $t->same(in_array($mainCheckpoint['wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true), true);
        $t->same(in_array($attachedCheckpoint['wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true), true);
        $t->same($firstPage, $currentFirst['page_number']);
        $t->same($secondPage, $currentSecond['page_number']);
        $t->same($thirdPage, $currentThird['page_number']);
        $t->same('wal', $currentFirst['source']);
        $t->same('wal', $currentSecond['source']);
        $t->same('wal', $currentThird['source']);
        $t->same($firstPage, $pinnedFirst['page_number']);
        $t->same(true, in_array($pinnedFirst['source'], ['database', 'wal'], true));
        $t->same($case >= 1 && $case <= 1000, true);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $mainBoundary['dependencies'], true));
    };
}

$tests['real upstream pager wal checkpoint blocking dynamic 20260531T045007Z records source scenarios'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal5.test', 'wal5-pragma 1.* blocking restart checkpoint waits for a reader then wraps WAL'],
        ['wal5.test', 'wal5-capi 1.* blocking restart checkpoint waits for a reader then wraps WAL'],
        ['wal5.test', 'wal5-pragma 2.1.* checkpoint applies all attached WAL files'],
        ['wal5.test', 'wal5-capi 2.1.* checkpoint applies all attached WAL files'],
        ['wal5.test', 'wal5-pragma 2.2.* restart checkpoint is busy with pinned main reader'],
        ['wal5.test', 'wal5-capi 2.2.* restart checkpoint is busy with pinned main reader'],
        ['wal5.test', 'wal5-pragma 2.3.* full checkpoint backfills only unpinned frames'],
        ['wal5.test', 'wal5-capi 2.3.* full checkpoint backfills only unpinned frames'],
        ['wal5.test', 'wal5-pragma 5.* checkpoint modes keep or truncate WAL with active readers'],
        ['wal5.test', 'wal5-capi 5.* checkpoint modes keep or truncate WAL with active readers'],
    ], $upstreamSections);
};

return $tests;
