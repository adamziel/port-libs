<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalMultiTransactionClusterPlan.php';

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$modes = ['passive', 'full', 'restart', 'truncate'];
$scripts = [
    'wal2.test wal2-10 multi-transaction checkpoint visibility',
    'wal2.test wal2-11 committed prefix survives writer tail',
    'walrestart.test restart checkpoint preserves pinned readers',
    'walpersist.test persistent WAL sidecar survives close-open boundary',
    'wal.test wal-2 reader snapshots committed prefix only',
];

$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (int $case, int $pageSize, array $frames) use ($page): string {
    $littleEndianChecksums = ($case % 2) === 0;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x13570000 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x24680000 + ($case * 31)) & 0xffffffff;
    $prefix = pack('N*', $magic, 3007000, $pageSize, 18000 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, $littleEndianChecksums);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $modes[($case - 1) % count($modes)];
    $script = $scripts[($case - 1) % count($scripts)];
    $pageCount = 5 + ($case % 5);
    $readerEndFrame = 3 + ($case % 3);
    $firstUpdatedPage = 2 + ($case % max(1, $pageCount - 2));
    $secondUpdatedPage = 1 + (($case + 2) % $pageCount);
    $thirdUpdatedPage = 1 + (($case + 4) % $pageCount);
    $label = sprintf('%s dynamic transaction case %04d', $script, $case);

    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "{$label} schema draft"],
        ['page' => $firstUpdatedPage, 'commit' => 0, 'label' => "{$label} first txn leaf"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "{$label} first txn commit"],
        ['page' => $secondUpdatedPage, 'commit' => 0, 'label' => "{$label} second txn draft"],
        ['page' => 3, 'commit' => $pageCount, 'label' => "{$label} second txn commit"],
        ['page' => $thirdUpdatedPage, 'commit' => 0, 'label' => "{$label} writer tail"],
    ];

    $database = $databaseBytes($pageSize, $pageCount, $label);
    $walBytes = $makeWalBytes($case, $pageSize, $frames);
    $watchPages = array_values(array_unique([1, 2, 3, $firstUpdatedPage, $secondUpdatedPage, $thirdUpdatedPage]));

    $tests["real upstream pager wal dynamic transaction batch {$case} {$script}"] = static function (TestRunner $t) use (
        $walBytes,
        $database,
        $pageSize,
        $pageCount,
        $mode,
        $readerEndFrame,
        $watchPages,
        $label
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $database, $pageSize);
        $wal = $boundary['committed_wal'];
        $cluster = SQLiteWalMultiTransactionClusterPlan::currentNext($wal, $database, $watchPages, $readerEndFrame);
        $checkpoint = $wal->checkpointModeResult($database, $mode, $readerEndFrame);
        $durable = $wal->durableCheckpointResult($database, $mode, $readerEndFrame);
        $reader = $wal->readerSnapshot($database, $readerEndFrame);

        $t->same('recovered_committed_prefix', $boundary['status'], $label);
        $t->same(6, $boundary['valid_frame_count'], $label);
        $t->same(5, $boundary['committed_frame_count'], $label);
        $t->same(1, $boundary['discarded_valid_tail_frame_count'], $label);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count'], $label);
        $t->same(5, $boundary['last_commit_frame'], $label);
        $t->same($pageCount, $boundary['checkpoint_database_page_count'], $label);
        $t->same('ready', $cluster['status'], $label);
        $t->same(2, $cluster['transaction_count'], $label);
        $t->same(5, $cluster['frame_count'], $label);
        $t->same(0, $cluster['uncommitted_tail_frame_count'], $label);
        $t->same($pageCount, $cluster['database_page_count_before'], $label);
        $t->same($pageCount, $cluster['database_page_count_after'], $label);
        $t->same([3, 5], array_column($cluster['clusters'], 'last_frame'), $label);
        $t->same([3, 5], array_column($wal->committedTransactions(), 'last_frame'), $label);
        $t->same($readerEndFrame, $reader['end_frame'], $label);
        $t->same($pageCount, $reader['database_page_count'], $label);
        $t->same($mode, $checkpoint['mode'], $label);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame'], $label);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count'], $label);
        $t->same(strlen($durable['database_bytes']), $durable['final_database_bytes'], $label);
        $t->same(strlen($durable['wal_bytes']), $durable['wal_bytes_length'], $label);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true), $label);
        $t->same(true, in_array('sqlite-wal-multi-transaction-cluster-current-next', $cluster['dependencies'], true), $label);
        $t->same(true, in_array('durable-sidecar-write', $durable['dependencies'], true), $label);
    };
}

$tests['real upstream pager wal dynamic transaction batch records hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'wal2.test: wal2-10.* multi-transaction WAL checkpoint and reader state',
        'wal2.test: wal2-11.* committed prefix recovery with writer tail frames',
        'walrestart.test: restart/truncate checkpoint behavior with pinned readers',
        'walpersist.test: persistent WAL sidecar decisions after close/reopen',
        'wal.test: wal-2.* reader snapshots see committed WAL prefix only',
    ], [
        'wal2.test: wal2-10.* multi-transaction WAL checkpoint and reader state',
        'wal2.test: wal2-11.* committed prefix recovery with writer tail frames',
        'walrestart.test: restart/truncate checkpoint behavior with pinned readers',
        'walpersist.test: persistent WAL sidecar decisions after close/reopen',
        'wal.test: wal-2.* reader snapshots see committed WAL prefix only',
    ]);
};

return $tests;
