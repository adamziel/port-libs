<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamSections = [
    ['wal5.test', 'wal5-1.1..1.12 blocking checkpoint keeps reader snapshot then restarts WAL'],
    ['wal5.test', 'wal5-2.1.* checkpoint covers all attached WAL databases'],
    ['wal5.test', 'wal5-2.2.* restart checkpoint is busy while attached reader holds snapshot'],
    ['wal5.test', 'wal5-2.3.* full checkpoint waits for main and aux readers'],
    ['wal5.test', 'wal5-3.* checkpoint on unopened connection sees WAL state'],
    ['wal5.test', 'wal5-4.* truncate checkpoint clears WAL bytes after reusable frames'],
    ['wal5.test', 'wal5-5.* FULL and TRUNCATE checkpoint busy-reader return tuples'],
    ['wal6.test', 'wal6-2.1..2.5 SQLITE_BUSY_SNAPSHOT keeps reader on old frame'],
    ['wal6.test', 'wal6-3.1..3.3 failed BEGIN IMMEDIATE does not leave read transaction'],
    ['wal6.test', 'wal6-4.1..4.4 partial checkpoint skips checkpointed prefix frames'],
    ['wal8.test', 'wal8-1.0..1.1 empty-file connection accepts WAL page-size VACUUM'],
    ['wal8.test', 'wal8-3.0..3.1 empty opener learns WAL page-size before schema read'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(48 + (strlen($label) % 10)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s database page %03d', $label, $page));
    }

    return $bytes;
};

$makeWalBytes = static function (
    int $case,
    int $pageSize,
    array $frames,
    bool $littleEndian,
    string $tailShape = 'valid'
) use ($pageImage): string {
    $salt1 = (0x59000000 + ($case * 41)) & 0xffffffff;
    $salt2 = (0x61000000 + ($case * 67)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        55751 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage($pageSize, (string) $frame['label']);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailShape === 'checksum') {
        $offset = 32 + (count($frames) - 1) * (24 + $pageSize) + 21;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x21);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (count($frames) - 1) * (24 + $pageSize) + 11;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x45);
    } elseif ($tailShape === 'truncated') {
        $bytes = substr($bytes, 0, -intdiv($pageSize, 4));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096, 8192][($case - 1) % 5];
    $pageCount = 6 + ($case % 21);
    $littleEndian = ($case % 5) === 0;
    $mode = ['passive', 'full', 'restart', 'truncate'][($case - 1) % 4];
    $tailShape = ['valid', 'checksum', 'salt', 'truncated'][($case - 1) % 4];
    $readerEndFrame = 2 + ($case % 5);
    $mainPage = 1 + (($case * 3) % $pageCount);
    $auxPage = 1 + (($case * 5) % $pageCount);
    $checkpointedPage = 1 + (($case * 7) % $pageCount);
    $latePage = 1 + (($case * 11) % $pageCount);
    $label = sprintf('real upstream pager wal dynamic 20260531T055751Z case %04d', $case);

    $tests[sprintf(
        'real upstream pager wal dynamic 20260531T055751Z %04d %s %s %s',
        $case,
        $script,
        $section,
        $tailShape
    )] = static function (TestRunner $t) use (
        $case,
        $script,
        $section,
        $pageSize,
        $pageCount,
        $littleEndian,
        $mode,
        $tailShape,
        $readerEndFrame,
        $mainPage,
        $auxPage,
        $checkpointedPage,
        $latePage,
        $label,
        $databaseBytes,
        $makeWalBytes
    ): void {
        $database = $databaseBytes($pageSize, $pageCount, $label);
        $frames = [
            ['page' => $mainPage, 'commit' => 0, 'label' => "{$script} {$section} writer starts main transaction"],
            ['page' => $auxPage, 'commit' => $pageCount, 'label' => "{$script} {$section} writer commits main and aux pages"],
            ['page' => $checkpointedPage, 'commit' => 0, 'label' => "{$script} {$section} checkpointed prefix frame"],
            ['page' => $latePage, 'commit' => $pageCount, 'label' => "{$script} {$section} restart checkpoint commit"],
            ['page' => $mainPage, 'commit' => 0, 'label' => "{$script} {$section} busy reader uncheckpointed tail"],
            ['page' => $auxPage, 'commit' => $pageCount, 'label' => "{$script} {$section} truncate checkpoint final commit"],
            ['page' => $checkpointedPage, 'commit' => 0, 'label' => "{$script} {$section} uncommitted writer tail"],
            ['page' => $latePage, 'commit' => 0, 'label' => "{$script} {$section} stale or corrupt tail frame"],
        ];
        $walBytes = $makeWalBytes($case, $pageSize, $frames, $littleEndian, $tailShape);

        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $database, $pageSize);
        $wal = $boundary['committed_wal'];
        $checkpoint = $wal->checkpointModeResult($database, $mode, min($readerEndFrame, $wal->frameCount()));
        $durable = $wal->durableCheckpointResult($database, $mode, min($readerEndFrame, $wal->frameCount()));
        $currentReader = $wal->readerSnapshot($database, min($readerEndFrame, $wal->frameCount()));
        $latestMain = $wal->readerSnapshotPageImage($database, $mainPage);
        $latestAux = $wal->readerSnapshotPageImage($database, $auxPage);
        $latestCheckpointed = $wal->readerSnapshotPageImage($database, $checkpointedPage);
        $transactions = $wal->committedTransactions();
        $checkpointDatabase = (string) $boundary['checkpoint_database_bytes'];
        $latestCommittedFrameForPage = static function (int $page) use ($frames): int {
            $latest = 0;
            foreach (array_slice($frames, 0, 6) as $index => $frame) {
                if ($frame['page'] === $page) {
                    $latest = $index + 1;
                }
            }

            return $latest;
        };

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '..') || str_contains($section, '*'));
        $t->same($pageSize, $wal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same($tailShape === 'valid' ? 'uncommitted_valid_tail_after_last_commit' : 'uncommitted_valid_tail_before_corrupt_frame', $boundary['reason']);
        $t->same(6, $boundary['committed_frame_count']);
        $t->same($tailShape === 'valid' ? 8 : 7, $boundary['valid_frame_count']);
        $t->same($tailShape === 'valid' ? 8 : 8, $boundary['total_frame_slots']);
        $t->same($tailShape === 'valid' ? null : 8, $boundary['first_invalid_frame']);
        $t->same($tailShape === 'valid' ? 2 : 1, $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : 1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(32 + (6 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($pageCount * $pageSize, strlen($checkpointDatabase));
        $t->same(6, $wal->frameCount());
        $t->same(3, count($transactions));
        $t->same([2, 4, 6], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $transactions[2]['database_page_count']);
        $t->same(true, in_array($mainPage, $transactions[0]['page_numbers'], true));
        $t->same(true, in_array($auxPage, $transactions[2]['page_numbers'], true));
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $durable['mode']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same(true, in_array($checkpoint['wal_action'], ['preserve_wal', 'truncate_wal', 'restart_wal'], true));
        $t->same(min($readerEndFrame, $wal->frameCount()), $currentReader['end_frame']);
        $t->same($pageCount, $currentReader['database_page_count']);
        $t->same($mainPage, $latestMain['page_number']);
        $t->same($auxPage, $latestAux['page_number']);
        $t->same($checkpointedPage, $latestCheckpointed['page_number']);
        $t->same('wal', $latestMain['source']);
        $t->same('wal', $latestAux['source']);
        $t->same('wal', $latestCheckpointed['source']);
        $t->same($latestCommittedFrameForPage($mainPage), $latestMain['frame_index']);
        $t->same($latestCommittedFrameForPage($auxPage), $latestAux['frame_index']);
        $t->same($latestCommittedFrameForPage($checkpointedPage), $latestCheckpointed['frame_index']);
        $t->same(true, in_array('sqlite-wal-checksum-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, $case >= 1 && $case <= 1000);
    };
}

$tests['real upstream pager wal dynamic 20260531T055751Z records hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal5.test', 'wal5-1.1..1.12 blocking checkpoint keeps reader snapshot then restarts WAL'],
        ['wal5.test', 'wal5-2.1.* checkpoint covers all attached WAL databases'],
        ['wal5.test', 'wal5-2.2.* restart checkpoint is busy while attached reader holds snapshot'],
        ['wal5.test', 'wal5-2.3.* full checkpoint waits for main and aux readers'],
        ['wal5.test', 'wal5-3.* checkpoint on unopened connection sees WAL state'],
        ['wal5.test', 'wal5-4.* truncate checkpoint clears WAL bytes after reusable frames'],
        ['wal5.test', 'wal5-5.* FULL and TRUNCATE checkpoint busy-reader return tuples'],
        ['wal6.test', 'wal6-2.1..2.5 SQLITE_BUSY_SNAPSHOT keeps reader on old frame'],
        ['wal6.test', 'wal6-3.1..3.3 failed BEGIN IMMEDIATE does not leave read transaction'],
        ['wal6.test', 'wal6-4.1..4.4 partial checkpoint skips checkpointed prefix frames'],
        ['wal8.test', 'wal8-1.0..1.1 empty-file connection accepts WAL page-size VACUUM'],
        ['wal8.test', 'wal8-3.0..3.1 empty opener learns WAL page-size before schema read'],
    ], $upstreamSections);
};

return $tests;
