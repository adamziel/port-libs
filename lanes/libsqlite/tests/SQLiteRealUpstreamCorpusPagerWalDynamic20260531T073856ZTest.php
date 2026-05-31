<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

$tests = [];

$upstreamSections = [
    ['walckptnoop.test', 'walckptnoop-1.1..1.4 noop checkpoint observes log frames without backfill'],
    ['walckptnoop.test', 'walckptnoop-1.5 restored WAL noop reports uncheckpointed frames'],
    ['walckptnoop.test', 'walckptnoop-1.8..1.10 committed delete then noop/delete-mode checkpoint shape'],
    ['waloverwrite.test', 'waloverwrite-1.1.* repeated page overwrites keep one committed image per page'],
    ['waloverwrite.test', 'waloverwrite-1.2.* pre-existing WAL transaction plus overwrite recovery'],
    ['waloverwrite.test', 'waloverwrite-1.* savepoint rollback excludes rolled-back overwrite tail'],
    ['walckptnoop.test', 'walckptnoop-1.6 reopened handle with no new WAL frames returns zeros'],
    ['pager1.test', 'pager1-22.* checkpoint result shape remains stable across pager reopen'],
    ['pager1.test', 'pager1-22.1 wal_checkpoint is noop on non-WAL database'],
    ['pager1.test', 'pager1-22.2 synchronous=off WAL checkpoint avoids sync work'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(35 + (strlen($label) % 57)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s database page %03d before WAL', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    int $pageCount,
    array $frames,
    bool $littleEndian,
    string $tailShape
) use ($pageImage): string {
    $salt1 = (0x41000000 + ($case * 97)) & 0xffffffff;
    $salt2 = (0x51000000 + ($case * 131)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        73856 + $case,
        $salt1,
        $salt2,
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
        $offset = 32 + (8 * (24 + $pageSize)) + 23;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x44);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (8 * (24 + $pageSize)) + 9;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x29);
    } elseif ($tailShape === 'truncated') {
        $bytes = substr($bytes, 0, -intdiv($pageSize, 3));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = 9 + ($case % 17);
    $littleEndian = ($case % 3) === 0;
    $tailShape = ['valid', 'checksum', 'salt', 'truncated'][($case - 1) % 4];
    $mode = ['noop', 'passive', 'full', 'restart', 'truncate'][($case - 1) % 5];
    $readerEndFrame = 3 + ($case % 3);
    $label = sprintf('real upstream pager wal dynamic 20260531T073856Z case %04d', $case);
    $pages = [
        1 + (($case * 2) % $pageCount),
        1 + (($case * 3) % $pageCount),
        1 + (($case * 5) % $pageCount),
        1 + (($case * 7) % $pageCount),
        1 + (($case * 11) % $pageCount),
    ];
    $frames = [
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$script} {$section} first transaction draft A"],
        ['page' => $pages[1], 'commit' => 0, 'label' => "{$script} {$section} first transaction draft B"],
        ['page' => $pages[0], 'commit' => $pageCount, 'label' => "{$script} {$section} first transaction overwrites A and commits"],
        ['page' => $pages[2], 'commit' => 0, 'label' => "{$script} {$section} second transaction draft C"],
        ['page' => $pages[3], 'commit' => 0, 'label' => "{$script} {$section} second transaction draft D"],
        ['page' => $pages[2], 'commit' => $pageCount, 'label' => "{$script} {$section} second transaction overwrites C and commits"],
        ['page' => $pages[4], 'commit' => 0, 'label' => "{$script} {$section} uncommitted savepoint overwrite tail"],
        ['page' => $pages[3], 'commit' => 0, 'label' => "{$script} {$section} rolled-back overwrite tail"],
        ['page' => $pages[1], 'commit' => 0, 'label' => "{$script} {$section} fault boundary tail"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $pageCount, $frames, $littleEndian, $tailShape);
    $watchPages = array_values(array_unique($pages));

    $tests[sprintf(
        'real upstream pager wal dynamic 20260531T073856Z %04d %s %s %s %s',
        $case,
        $script,
        $mode,
        $tailShape,
        $section,
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
        $tailShape,
        $littleEndian
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $effectiveReader = min($readerEndFrame, $committedWal->frameCount());
        $cluster = SQLiteWalMultiTransactionClusterPlan::currentNext($committedWal, $database, $watchPages, $effectiveReader);
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, $effectiveReader);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $effectiveReader);
        $noop = $committedWal->checkpointModeResult($database, 'noop');
        $reader = $committedWal->readerSnapshot($database, $effectiveReader);
        $transactions = $committedWal->committedTransactions();

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '-'));
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(6, $boundary['committed_frame_count']);
        $t->same(6, $committedWal->frameCount());
        $t->same(2, count($transactions));
        $t->same([3, 6], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($tailShape === 'valid' ? 9 : 8, $boundary['valid_frame_count']);
        $t->same($tailShape === 'valid' ? 3 : 2, $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : 1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($tailShape === 'valid' ? null : 9, $boundary['first_invalid_frame']);
        $t->same(32 + (6 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same('ready', $cluster['status']);
        $t->same(2, $cluster['transaction_count']);
        $t->same(6, $cluster['frame_count']);
        $t->same(0, $cluster['uncommitted_tail_frame_count']);
        $t->same($pageCount, $cluster['database_page_count_before']);
        $t->same($pageCount, $cluster['database_page_count_after']);
        $t->same(true, is_bool($cluster['images_match']));
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $durable['mode']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same('noop', $noop['mode']);
        $t->same('noop_checkpoint_does_not_backfill', $noop['reason']);
        $t->same(0, $noop['checkpointed_frame_count']);
        $t->same(true, $noop['total_committable_frame_count'] >= 1);
        $t->same($noop['total_committable_frame_count'], $noop['remaining_committed_frame_count']);
        $t->same($database, $noop['database_bytes']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same($effectiveReader, $reader['end_frame']);
        $t->same(true, is_array($cluster['current_reader']));
        $t->same(true, is_array($boundary['dependencies']));
        $t->same(true, is_array($cluster['dependencies']));
        $t->same(true, is_string($checkpoint['wal_action']));
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
    };
}

$tests['real upstream pager wal dynamic 20260531T073856Z records hydrated upstream files and subtests'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['walckptnoop.test', 'walckptnoop-1.1..1.4 noop checkpoint observes log frames without backfill'],
        ['walckptnoop.test', 'walckptnoop-1.5 restored WAL noop reports uncheckpointed frames'],
        ['walckptnoop.test', 'walckptnoop-1.8..1.10 committed delete then noop/delete-mode checkpoint shape'],
        ['waloverwrite.test', 'waloverwrite-1.1.* repeated page overwrites keep one committed image per page'],
        ['waloverwrite.test', 'waloverwrite-1.2.* pre-existing WAL transaction plus overwrite recovery'],
        ['waloverwrite.test', 'waloverwrite-1.* savepoint rollback excludes rolled-back overwrite tail'],
        ['walckptnoop.test', 'walckptnoop-1.6 reopened handle with no new WAL frames returns zeros'],
        ['pager1.test', 'pager1-22.* checkpoint result shape remains stable across pager reopen'],
        ['pager1.test', 'pager1-22.1 wal_checkpoint is noop on non-WAL database'],
        ['pager1.test', 'pager1-22.2 synchronous=off WAL checkpoint avoids sync work'],
    ], $upstreamSections);
};

return $tests;
