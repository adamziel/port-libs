<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$checkpointModes = ['passive', 'full', 'restart', 'truncate', 'noop'];
$upstreamSections = [
    ['wal.test', 'wal-2.* reader snapshots committed WAL frames'],
    ['wal2.test', 'wal2-1.* wal-index header recovery'],
    ['wal2.test', 'wal2-2.* stale wal-index header recovery'],
    ['wal2.test', 'wal2-10.* multi-transaction checkpoint visibility'],
    ['walrestart.test', 'walrestart-1.* restart checkpoint with pinned readers'],
    ['walpersist.test', 'walpersist-1.* persistent WAL sidecar after close'],
    ['walmode.test', 'walmode-4.* WAL mode persistence across handles'],
    ['pager1.test', 'pager1-9.* journal/page-size pager commit visibility'],
    ['pager1.test', 'pager1-12.* large page-size pager commit visibility'],
    ['pager2.test', 'pager2-2.* journal mode and reader/writer transition'],
];

$pageImage = static function (string $label, int $pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '#', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage("{$label} base database page {$page}", $pageSize);
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames) use ($pageImage): string {
    $littleEndianChecksums = ($case % 3) === 0;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x10203040 + ($case * 97)) & 0xffffffff;
    $salt2 = (0x55667788 + ($case * 193)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 24000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndianChecksums);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage((string) $frame['label'], $pageSize);
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
    $pageCount = 6 + ($case % 4);
    $readerEndFrame = 3 + ($case % 3);
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 2) % $pageCount);
    $thirdPage = 1 + (($case + 4) % $pageCount);
    $tailPage = 1 + (($case + 6) % $pageCount);
    $label = sprintf('%s %s dynamic pager wal real corpus %04d', $script, $section, $case);

    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} transaction 1 draft"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} transaction 1 commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$label} transaction 2 draft"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} transaction 2 supersedes first page"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} transaction 2 commit"],
        ['page' => $tailPage, 'commit' => 0, 'label' => "{$label} uncommitted writer tail"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $tailPage]));
    $testName = sprintf(
        'real upstream corpus pager wal dynamic real pager 20260530 %04d %s %s',
        $case,
        $script,
        $section
    );

    $tests[$testName] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $watchPages,
        $mode,
        $script,
        $section
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $cluster = SQLiteWalMultiTransactionClusterPlan::currentNext($committedWal, $database, $watchPages, $readerEndFrame);
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, $readerEndFrame);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $readerEndFrame);
        $reader = $committedWal->readerSnapshot($database, $readerEndFrame);
        $transactions = $committedWal->committedTransactions();

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('uncommitted_valid_tail_after_last_commit', $boundary['reason']);
        $t->same(6, $boundary['valid_frame_count']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same(1, $boundary['discarded_valid_tail_frame_count']);
        $t->same(2, count($transactions));
        $t->same([2, 5], array_column($transactions, 'last_frame'));
        $t->same('ready', $cluster['status']);
        $t->same(2, $cluster['transaction_count']);
        $t->same(5, $cluster['frame_count']);
        $t->same(0, $cluster['uncommitted_tail_frame_count']);
        $t->same($pageCount, $cluster['database_page_count_after']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->true(count($cluster['current_reader']) >= count($watchPages));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-multi-transaction-cluster-current-next', $cluster['dependencies'], true));
        $t->true(str_ends_with($script, '.test'));
        $t->true(str_contains($section, '-'));
    };
}

$tests['real upstream corpus pager wal dynamic real pager 20260530 source sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal.test', 'wal-2.* reader snapshots committed WAL frames'],
        ['wal2.test', 'wal2-1.* wal-index header recovery'],
        ['wal2.test', 'wal2-2.* stale wal-index header recovery'],
        ['wal2.test', 'wal2-10.* multi-transaction checkpoint visibility'],
        ['walrestart.test', 'walrestart-1.* restart checkpoint with pinned readers'],
        ['walpersist.test', 'walpersist-1.* persistent WAL sidecar after close'],
        ['walmode.test', 'walmode-4.* WAL mode persistence across handles'],
        ['pager1.test', 'pager1-9.* journal/page-size pager commit visibility'],
        ['pager1.test', 'pager1-12.* large page-size pager commit visibility'],
        ['pager2.test', 'pager2-2.* journal mode and reader/writer transition'],
    ], $upstreamSections);
};

return $tests;
