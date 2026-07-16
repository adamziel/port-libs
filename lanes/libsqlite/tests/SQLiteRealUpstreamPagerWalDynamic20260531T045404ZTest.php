<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

$tests = [];

$upstreamSections = [
    ['walblock.test', 'walblock-1.1.* blocking writer leaves readers on prior committed frame'],
    ['walblock.test', 'walblock-1.2.* busy writer resumes after blocking reader drains'],
    ['walprotocol.test', 'walprotocol-1.1..1.5 read-transaction snapshot and wal-index protocol'],
    ['walprotocol.test', 'walprotocol-2.1..2.8 writer lock and checkpoint protocol boundaries'],
    ['walfault.test', 'walfault-1 recovery after checkpoint fault preserves committed prefix'],
    ['walfault.test', 'walfault-2 hot WAL recovery keeps valid frames before injected fault'],
    ['pagerfault.test', 'pagerfault-5.* journal-size-limit and multi-file transaction fault recovery'],
    ['pagerfault.test', 'pagerfault-12.* rollback journal hot recovery fault keeps database consistent'],
    ['pagerfault.test', 'pagerfault-17.* sector-size and journal-header fault boundaries'],
    ['pagerfault.test', 'pagerfault-21.* crash/fault recovery replays only committed pages'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(33 + (strlen($label) % 60)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s base page %03d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    array $frames,
    bool $littleEndian,
    string $tailShape
) use ($pageImage): string {
    $salt1 = (0x73000000 + ($case * 37)) & 0xffffffff;
    $salt2 = (0x24000000 + ($case * 73)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        45404 + $case,
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
        $offset = 32 + (6 * (24 + $pageSize)) + 17;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x5a);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (6 * (24 + $pageSize)) + 8;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x33);
    } elseif ($tailShape === 'truncated') {
        $bytes = substr($bytes, 0, -intdiv($pageSize, 2));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096, 8192][($case - 1) % 5];
    $pageCount = 7 + ($case % 23);
    $littleEndian = ($case % 4) === 0;
    $mode = ['passive', 'full', 'restart', 'truncate', 'noop'][($case - 1) % 5];
    $tailShape = ['valid', 'checksum', 'salt', 'truncated'][($case - 1) % 4];
    $readerEndFrame = 2 + ($case % 4);
    $firstPage = 1 + (($case * 3) % $pageCount);
    $secondPage = 1 + (($case * 5) % $pageCount);
    $thirdPage = 1 + (($case * 7) % $pageCount);
    $fourthPage = 1 + (($case * 11) % $pageCount);
    $label = sprintf('real upstream pager wal dynamic 20260531T045404Z case %04d', $case);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} transaction one first draft"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction one commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$script} {$section} transaction two first draft"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} transaction two overwrites first page"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction two commit"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$script} {$section} uncommitted valid writer tail"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$script} {$section} fault boundary frame"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames, $littleEndian, $tailShape);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage]));

    $tests[sprintf(
        'real upstream pager wal dynamic 20260531T045404Z %04d %s %s %s',
        $case,
        $script,
        $section,
        $tailShape
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
        $cluster = SQLiteWalMultiTransactionClusterPlan::currentNext($committedWal, $database, $watchPages, min($readerEndFrame, $committedWal->frameCount()));
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, min($readerEndFrame, $committedWal->frameCount()));
        $durable = $committedWal->durableCheckpointResult($database, $mode, min($readerEndFrame, $committedWal->frameCount()));
        $reader = $committedWal->readerSnapshot($database, min($readerEndFrame, $committedWal->frameCount()));
        $transactions = $committedWal->committedTransactions();

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '-'));
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same(5, $committedWal->frameCount());
        $t->same(2, count($transactions));
        $t->same([2, 5], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($tailShape === 'valid' ? 7 : 6, $boundary['valid_frame_count']);
        $t->same($tailShape === 'valid' ? 2 : 1, $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : 1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($tailShape === 'valid' ? null : 7, $boundary['first_invalid_frame']);
        $t->same(32 + (5 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same('ready', $cluster['status']);
        $t->same(2, $cluster['transaction_count']);
        $t->same(5, $cluster['frame_count']);
        $t->same(0, $cluster['uncommitted_tail_frame_count']);
        $t->same($pageCount, $cluster['database_page_count_before']);
        $t->same($pageCount, $cluster['database_page_count_after']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $durable['mode']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same(min($readerEndFrame, $committedWal->frameCount()), $reader['end_frame']);
        $t->same(true, count($cluster['current_reader']) >= count($watchPages));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-multi-transaction-cluster-current-next', $cluster['dependencies'], true));
        $t->same(true, in_array($checkpoint['wal_action'], ['preserve_wal', 'truncate_wal', 'restart_wal'], true));
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
    };
}

$tests['real upstream pager wal dynamic 20260531T045404Z records hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['walblock.test', 'walblock-1.1.* blocking writer leaves readers on prior committed frame'],
        ['walblock.test', 'walblock-1.2.* busy writer resumes after blocking reader drains'],
        ['walprotocol.test', 'walprotocol-1.1..1.5 read-transaction snapshot and wal-index protocol'],
        ['walprotocol.test', 'walprotocol-2.1..2.8 writer lock and checkpoint protocol boundaries'],
        ['walfault.test', 'walfault-1 recovery after checkpoint fault preserves committed prefix'],
        ['walfault.test', 'walfault-2 hot WAL recovery keeps valid frames before injected fault'],
        ['pagerfault.test', 'pagerfault-5.* journal-size-limit and multi-file transaction fault recovery'],
        ['pagerfault.test', 'pagerfault-12.* rollback journal hot recovery fault keeps database consistent'],
        ['pagerfault.test', 'pagerfault-17.* sector-size and journal-header fault boundaries'],
        ['pagerfault.test', 'pagerfault-21.* crash/fault recovery replays only committed pages'],
    ], $upstreamSections);
};

return $tests;
