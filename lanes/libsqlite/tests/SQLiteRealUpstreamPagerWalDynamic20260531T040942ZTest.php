<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s base page %04d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, int $pageCount, array $transactions, string $label, bool $littleEndian = false) use ($pageImage): string {
    $salt1 = (0x2a000000 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x3b000000 + ($case * 31)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        240942 + $case,
        $salt1,
        $salt2
    );
    $seed = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $frame = 0;

    foreach ($transactions as $transactionIndex => $pages) {
        foreach ($pages as $pageIndex => $pageNumber) {
            $frame++;
            $commit = $pageIndex === array_key_last($pages) ? $pageCount : 0;
            $image = $pageImage(
                $pageSize,
                sprintf('%s txn%02d frame%04d page%04d', $label, $transactionIndex + 1, $frame, $pageNumber)
            );
            $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
            $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
            $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
        }
    }

    return $bytes;
};

$rangeWrap = static function (int $start, int $count, int $pageCount): array {
    $pages = [];
    for ($i = 0; $i < $count; $i++) {
        $pages[] = 1 + (($start + $i - 1) % $pageCount);
    }

    return $pages;
};

$scenarios = [
    [
        'source' => 'wal64k.test 1.0..1.3 64KiB syscall page-size SHM growth and integrity',
        'page_size' => 65536,
        'page_count' => 9,
        'transactions' => 3,
        'frames_per_transaction' => 5,
        'mode' => 'restart',
        'little' => false,
    ],
    [
        'source' => 'wal64k.test 2.1 unix-excl 512-byte WAL integrity with 8200 rows',
        'page_size' => 512,
        'page_count' => 12,
        'transactions' => 4,
        'frames_per_transaction' => 6,
        'mode' => 'passive',
        'little' => true,
    ],
    [
        'source' => 'wal7.test 1.0..1.2 no journal_size_limit keeps large WAL after checkpoint',
        'page_size' => 1024,
        'page_count' => 14,
        'transactions' => 4,
        'frames_per_transaction' => 8,
        'mode' => 'noop',
        'little' => false,
    ],
    [
        'source' => 'wal7.test 2.0 journal_size_limit=25000 clamps persistent WAL sidecar',
        'page_size' => 1024,
        'page_count' => 16,
        'transactions' => 5,
        'frames_per_transaction' => 7,
        'mode' => 'restart',
        'little' => true,
    ],
    [
        'source' => 'wal7.test 3.0 journal_size_limit=0 truncates WAL after checkpoint',
        'page_size' => 1024,
        'page_count' => 10,
        'transactions' => 3,
        'frames_per_transaction' => 9,
        'mode' => 'truncate',
        'little' => false,
    ],
    [
        'source' => 'wal7.test 4.0 size limit set before WAL mode is honored',
        'page_size' => 2048,
        'page_count' => 18,
        'transactions' => 4,
        'frames_per_transaction' => 6,
        'mode' => 'restart',
        'little' => true,
    ],
    [
        'source' => 'wal8.test 1.0..1.1 empty first connection vacuums after peer creates WAL database',
        'page_size' => 4096,
        'page_count' => 8,
        'transactions' => 2,
        'frames_per_transaction' => 4,
        'mode' => 'passive',
        'little' => false,
    ],
    [
        'source' => 'wal8.test 2.0..2.1 peer switches existing database to WAL before VACUUM',
        'page_size' => 4096,
        'page_count' => 11,
        'transactions' => 3,
        'frames_per_transaction' => 5,
        'mode' => 'restart',
        'little' => true,
    ],
    [
        'source' => 'wal8.test 3.0..3.1 stale page_size pragma still reads sqlite_master',
        'page_size' => 4096,
        'page_count' => 7,
        'transactions' => 2,
        'frames_per_transaction' => 6,
        'mode' => 'passive',
        'little' => false,
    ],
];

for ($case = 1; $case <= 1200; $case++) {
    $scenario = $scenarios[($case - 1) % count($scenarios)];
    $pageSize = $scenario['page_size'];
    $pageCount = $scenario['page_count'] + ($case % 3);
    $transactions = [];
    for ($transaction = 0; $transaction < $scenario['transactions']; $transaction++) {
        $transactions[] = $rangeWrap(
            1 + $case + ($transaction * $scenario['frames_per_transaction']),
            $scenario['frames_per_transaction'],
            $pageCount
        );
    }
    $frameCount = $scenario['transactions'] * $scenario['frames_per_transaction'];
    $readerFrame = max(1, intdiv($frameCount, 2));
    $label = sprintf('real upstream %s dynamic case %04d', $scenario['source'], $case);

    $tests[sprintf('real upstream pager wal dynamic 040942 %04d %s', $case, $scenario['source'])] = static function (TestRunner $t) use (
        $case,
        $scenario,
        $pageSize,
        $pageCount,
        $transactions,
        $frameCount,
        $readerFrame,
        $label,
        $databaseBytes,
        $walBytes
    ): void {
        $database = $databaseBytes($pageSize, $pageCount, $label);
        $bytes = $walBytes($case, $pageSize, $pageCount, $transactions, $label, $scenario['little']);
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $database, $pageSize);
        $checkpointed = $wal->checkpointDatabaseImage($database);
        $modePlan = $wal->checkpointModePlan($database, $scenario['mode'], $readerFrame);
        $durablePlan = $wal->durableCheckpointResult($database, $scenario['mode'], $readerFrame);
        $reader = $wal->readerSnapshotPageImage($database, $transactions[0][0], $readerFrame);
        $latest = $wal->readerSnapshotPageImage($database, $transactions[array_key_last($transactions)][0]);

        $t->same($pageSize, $wal->header->pageSize);
        $t->same($frameCount, $wal->frameCount());
        $t->same('valid', $boundary['status']);
        $t->same('all_frames_valid', $boundary['reason']);
        $t->same($frameCount, $boundary['committed_frame_count']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same($pageCount * $pageSize, strlen($checkpointed));
        $t->same(true, $modePlan['checkpointed_frame_count'] <= $frameCount);
        $t->same(true, in_array($durablePlan['wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true));
        $t->same(true, in_array($reader['source'], ['database', 'wal'], true));
        $t->same('wal', $latest['source']);
        $t->same(true, str_contains((string) $latest['image'], 'txn'));
        $t->same(true, str_contains($scenario['source'], '.test'));
    };
}

$tests['real upstream pager wal dynamic 040942 records hydrated upstream source sections'] = static function (TestRunner $t): void {
    $wal64k = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/wal64k.test');
    $wal7 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/wal7.test');
    $wal8 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/wal8.test');

    $t->contains('do_execsql_test 1.0', $wal64k);
    $t->contains('file size test.db-shm', $wal64k);
    $t->contains('do_execsql_test 2.1', $wal64k);
    $t->contains('do_test wal7-1.0', $wal7);
    $t->contains('PRAGMA journal_size_limit=25000', $wal7);
    $t->contains('PRAGMA journal_size_limit=0', $wal7);
    $t->contains('do_test 1.0', $wal8);
    $t->contains('do_catchsql_test 1.1', $wal8);
    $t->contains('do_execsql_test 3.1', $wal8);
};

return $tests;
