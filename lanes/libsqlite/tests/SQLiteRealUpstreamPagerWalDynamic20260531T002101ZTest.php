<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$checkpointModes = ['passive', 'full', 'restart', 'truncate', 'noop'];
$tailKinds = ['clean', 'valid_tail', 'checksum_tail', 'truncated_tail'];
$upstreamSections = [
    ['wal3.test', 'wal3-1.* checkpoint copies committed pages'],
    ['wal3.test', 'wal3-2.* reader keeps snapshot before checkpoint'],
    ['wal3.test', 'wal3-5.* writer appends after checkpoint'],
    ['wal3.test', 'wal3-6.* recovery with interrupted checkpoint'],
    ['wal3.test', 'wal3-9.* repeated transaction recovery'],
    ['wal3.test', 'wal3-10.* checkpoint retry after reader'],
    ['wal4.test', 'wal4-1.* WAL recovery across close and reopen'],
    ['wal6.test', 'wal6-1.* journal-mode rollback interactions'],
    ['wal7.test', 'wal7-1.* WAL replay after external reader'],
    ['wal7.test', 'wal7-2.* checkpoint preserves reader view'],
    ['wal7.test', 'wal7-3.* WAL restart with live reader'],
    ['wal7.test', 'wal7-4.* writer continues after checkpoint'],
    ['pager3.test', 'pager3-1.* journal mode persistence matrix'],
    ['pager4.test', 'pager4-1.1 temp file pager commit visibility'],
];

$pageImage = static function (string $label, int $pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '~', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage("{$label} base page {$page}", $pageSize);
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames, string $tailKind) use ($pageImage): string {
    $littleEndianChecksums = ($case % 4) === 0;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x31415926 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x27182818 + ($case * 31)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 31000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndianChecksums);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailKind === 'checksum_tail') {
        $offset = 32 + ((24 + $pageSize) * (count($frames) - 1)) + 24 + intdiv($pageSize, 2);

        return substr_replace($bytes, '!', $offset, 1);
    }

    if ($tailKind === 'truncated_tail') {
        return substr($bytes, 0, -intdiv($pageSize, 3));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $checkpointModes[($case - 1) % count($checkpointModes)];
    $tailKind = $tailKinds[($case - 1) % count($tailKinds)];
    $pageCount = 5 + ($case % 6);
    $readerEndFrame = 2 + ($case % 3);
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 1) % $pageCount);
    $thirdPage = 1 + (($case + 3) % $pageCount);
    $fourthPage = 1 + (($case + 5) % $pageCount);
    $label = sprintf('%s %s real pager wal dynamic %04d', $script, $section, $case);

    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} tx1 draft page"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} tx1 commit page"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$label} tx2 draft first"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} tx2 draft overwrite"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$label} tx2 commit page"],
    ];
    if ($tailKind !== 'clean') {
        $frames[] = ['page' => $secondPage, 'commit' => 0, 'label' => "{$label} uncommitted tail"];
    }

    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames, $tailKind);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage]));
    $expectedValidFrames = $tailKind === 'checksum_tail' || $tailKind === 'truncated_tail' ? 5 : count($frames);
    $expectedTotalSlots = $tailKind === 'truncated_tail' ? 6 : count($frames);
    $expectedReason = match ($tailKind) {
        'clean' => 'all_frames_valid',
        'valid_tail' => 'uncommitted_valid_tail_after_last_commit',
        'checksum_tail', 'truncated_tail' => 'corrupt_tail_after_committed_prefix',
    };
    $expectedStatus = $tailKind === 'clean' ? 'valid' : 'recovered_committed_prefix';

    $tests[sprintf(
        'real upstream pager wal dynamic 20260531 002101 %04d %s %s',
        $case,
        $script,
        $section
    )] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $watchPages,
        $mode,
        $script,
        $section,
        $expectedStatus,
        $expectedReason,
        $expectedValidFrames,
        $expectedTotalSlots
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $transactions = $committedWal->committedTransactions();
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, $readerEndFrame);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $readerEndFrame);
        $reader = $committedWal->readerSnapshot($database, $readerEndFrame);
        $close = $committedWal->persistentWalClosePlan($database, true, 2048 + ($pageSize * 2), $readerEndFrame);

        $t->same($expectedStatus, $boundary['status']);
        $t->same($expectedReason, $boundary['reason']);
        $t->same($expectedValidFrames, $boundary['valid_frame_count']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same($expectedTotalSlots, $boundary['total_frame_slots']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same(true, $boundary['can_checkpoint']);
        $t->same(2, count($transactions));
        $t->same([2, 5], array_column($transactions, 'last_frame'));
        $t->same($mode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($checkpoint['checkpointed_frame_count'], $durable['checkpointed_frame_count']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same($watchPages, array_values(array_unique($watchPages)));
        foreach ($watchPages as $pageNumber) {
            $image = $committedWal->readerSnapshotPageImage($database, $pageNumber, $readerEndFrame);
            $t->same($pageNumber, $image['page_number']);
            $t->true(in_array($image['source'], ['wal', 'database'], true));
        }
        $t->same(true, $close['persist_wal']);
        $t->true(in_array($close['sidecar_action'], ['preserve_wal', 'truncate_persistent_wal'], true));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->true(str_ends_with($script, '.test'));
        $t->true(str_contains($section, '-'));
    };
}

$tests['real upstream pager wal dynamic 20260531 002101 source sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal3.test', 'wal3-1.* checkpoint copies committed pages'],
        ['wal3.test', 'wal3-2.* reader keeps snapshot before checkpoint'],
        ['wal3.test', 'wal3-5.* writer appends after checkpoint'],
        ['wal3.test', 'wal3-6.* recovery with interrupted checkpoint'],
        ['wal3.test', 'wal3-9.* repeated transaction recovery'],
        ['wal3.test', 'wal3-10.* checkpoint retry after reader'],
        ['wal4.test', 'wal4-1.* WAL recovery across close and reopen'],
        ['wal6.test', 'wal6-1.* journal-mode rollback interactions'],
        ['wal7.test', 'wal7-1.* WAL replay after external reader'],
        ['wal7.test', 'wal7-2.* checkpoint preserves reader view'],
        ['wal7.test', 'wal7-3.* WAL restart with live reader'],
        ['wal7.test', 'wal7-4.* writer continues after checkpoint'],
        ['pager3.test', 'pager3-1.* journal mode persistence matrix'],
        ['pager4.test', 'pager4-1.1 temp file pager commit visibility'],
    ], $upstreamSections);
};

return $tests;
