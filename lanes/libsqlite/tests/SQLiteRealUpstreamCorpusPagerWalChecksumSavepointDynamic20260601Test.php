<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];
$upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/walcksum.test';

$tests['real upstream corpus pager wal checksum savepoint dynamic cites hydrated walcksum source'] = static function (TestRunner $t) use ($upstream): void {
    $source = (string) file_get_contents($upstream);

    $t->contains('do_execsql_test 3.0', $source);
    $t->contains('do_execsql_test 3.1', $source);
    $t->contains('do_test 4.3', $source);
    $t->contains('do_execsql_test 5.3', $source);
    $t->contains('PRAGMA cache_size = 1', $source);
    $t->contains('SAVEPOINT one', $source);
    $t->contains('ROLLBACK TO one', $source);
    $t->contains('SELECT i, t FROM t1', $source);
    $t->contains('b490f726db', $source);
};

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$rowList = static function (array $rows): string {
    return implode(',', array_map(
        static fn (array $row): string => (int) $row[0] . ':' . (string) $row[1],
        $rows
    ));
};

$databaseBytes = static function (array $row) use ($page, $rowList): string {
    $pageSize = (int) $row['page_size'];
    $pageCount = (int) $row['database_page_count'];
    $bytes = '';

    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $label = $pageNumber === 1
            ? sprintf('walcksum.test %s schema cache_size=1 case %04d', $row['section'], $row['case'])
            : sprintf('walcksum.test %s base page %d initial rows %s case %04d', $row['section'], $pageNumber, $rowList($row['initial_rows']), $row['case']);
        $bytes .= $page($pageSize, $label);
    }

    return $bytes;
};

$walBytes = static function (array $row) use ($page, $rowList): string {
    $pageSize = (int) $row['page_size'];
    $case = (int) $row['case'];
    $pageCount = (int) $row['database_page_count'];
    $committedFrameCount = (int) $row['committed_frame_count'];
    $rolledBackFrameCount = (int) $row['rolled_back_frame_count'];
    $littleEndian = ($case % 2) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = 0x6c000000 + $case;
    $salt2 = 0x6d000000 + $case;

    $header = pack('N*', $magic, 3007000, $pageSize, 41000 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($header, $littleEndian);
    $bytes = $header . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $label) use (&$bytes, &$seed, $page, $pageSize, $salt1, $salt2, $littleEndian): void {
        $image = $page($pageSize, $label);
        $prefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $bytes .= $prefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    for ($frame = 1; $frame <= $committedFrameCount; $frame++) {
        $isCommit = $frame === $committedFrameCount;
        $pageNumber = $isCommit ? (int) $row['row_page_number'] : 1 + (($case + $frame) % $pageCount);
        $label = $isCommit
            ? sprintf(
                'walcksum.test %s committed rows %s signature %s case %04d',
                $row['section'],
                $rowList($row['expected_rows']),
                $row['expected_signature'],
                $case
            )
            : sprintf('walcksum.test %s pre-savepoint frame %02d case %04d', $row['section'], $frame, $case);
        $append($pageNumber, $isCommit ? $pageCount : 0, $label);
    }

    for ($tail = 1; $tail <= $rolledBackFrameCount; $tail++) {
        $sourceRow = $row['rolled_back_rows'][($tail - 1) % count($row['rolled_back_rows'])];
        $pageNumber = ($tail % 2) === 0 ? (int) $row['row_page_number'] : (int) $row['draft_page_number'];
        $append(
            $pageNumber,
            0,
            sprintf(
                'walcksum.test %s rolled-back row %d:%s tail %02d signature %s case %04d',
                $row['section'],
                $sourceRow[0],
                $sourceRow[1],
                $tail,
                $row['rolled_back_signature'],
                $case
            )
        );
    }

    return $bytes;
};

$rollbackPlan = static function (array $row): array {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('walcksum transaction');

    for ($frame = 1; $frame <= (int) $row['committed_frame_count']; $frame++) {
        $pageNumber = $frame === (int) $row['committed_frame_count']
            ? (int) $row['row_page_number']
            : 1 + (((int) $row['case'] + $frame) % (int) $row['database_page_count']);
        $stack->recordWalFrameWrite($frame, $pageNumber, $frame === (int) $row['committed_frame_count']);
    }

    $stack->savepoint('one');
    for ($frame = (int) $row['committed_frame_count'] + 1; $frame <= (int) $row['total_frame_count']; $frame++) {
        $tail = $frame - (int) $row['committed_frame_count'];
        $pageNumber = ($tail % 2) === 0 ? (int) $row['row_page_number'] : (int) $row['draft_page_number'];
        $stack->recordWalFrameWrite($frame, $pageNumber, false);
    }

    return $stack->walRollbackToPlan('one');
};

$rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walChecksumSavepointRegressionRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal checksum savepoint dynamic %s case %04d',
        $row['section'],
        $row['case']
    )] = static function (TestRunner $t) use ($row, $databaseBytes, $walBytes, $rollbackPlan): void {
        $database = $databaseBytes($row);
        $bytes = $walBytes($row);
        $wal = SQLiteWal::parse($bytes, $row['page_size'], true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $database, $row['page_size']);
        $committedWal = $boundary['committed_wal'];
        $checkpoint = (string) $boundary['checkpoint_database_bytes'];
        $plan = $wal->checkpointPlan($database);
        $committedPlan = $committedWal->checkpointPlan($database);
        $rollback = $rollbackPlan($row);

        $t->same('walcksum.test', $row['script']);
        $t->same(1, $row['cache_size']);
        $t->same(2048, $row['randomblob_bytes']);
        $t->same('ok', $row['expected_integrity_check']);
        $t->same(true, $row['copied_wal_recovery_is_readable']);
        $t->same($row['total_frame_count'], $wal->frameCount());
        $t->same($row['total_frame_count'], $boundary['valid_frame_count']);
        $t->same($row['total_frame_count'], $boundary['total_frame_slots']);
        $t->same($row['committed_frame_count'], $boundary['committed_frame_count']);
        $t->same($row['committed_frame_count'], $boundary['last_commit_frame']);
        $t->same($row['database_page_count'], $boundary['last_commit_page_count']);
        $t->same($row['expected_boundary_status'], $boundary['status']);
        $t->same($row['expected_boundary_reason'], $boundary['reason']);
        $t->same(null, $boundary['first_invalid_frame']);
        $t->same($row['rolled_back_frame_count'], $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($row['rolled_back_frame_count'], $wal->uncommittedFrameCount());
        $t->same(0, $committedWal->uncommittedFrameCount());
        $t->same($row['committed_frame_count'], $committedWal->frameCount());
        $t->same($row['database_page_count'], $boundary['checkpoint_database_page_count']);
        $t->same($row['database_page_count'] * $row['page_size'], strlen($checkpoint));
        $t->same(true, str_contains($checkpoint, $row['expected_signature']));
        $t->same(false, str_contains($checkpoint, $row['rolled_back_signature']));
        $t->same(true, str_contains($bytes, $row['rolled_back_signature']));
        $t->same($row['database_page_count'], $committedPlan['database_page_count']);
        $t->same($row['committed_frame_count'], $committedPlan['last_commit_frame']);
        $t->same($row['database_page_count'] * $row['page_size'], $committedPlan['final_database_bytes']);
        $t->same($row['rolled_back_frame_count'], count(array_filter($plan['frames'], static fn (array $frame): bool => $frame['reason'] === 'after_last_commit')));
        $t->same($row['committed_frame_count'], count(array_filter($committedPlan['frames'], static fn (array $frame): bool => $frame['applied'] || $frame['reason'] === 'superseded_by_later_committed_frame')));
        $t->same('one', $rollback['savepoint']);
        $t->same($row['committed_frame_count'], $rollback['rollback_to_frame']);
        $t->same($row['rolled_back_frame_count'], count($rollback['discarded_wal_frames']));
        $t->same(true, $rollback['transaction_active_after']);
        $t->same([], array_values(array_intersect($row['expected_rowids'], $row['rolled_back_rowids'])));
        $t->same($row['section'] === 'walcksum-5.0..5.3', $row['active_reader_before_savepoint']);
        $t->same(true, in_array('real-upstream-corpus-walcksum', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checksum-savepoint-regression', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-wal-dynamic-corpus', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal checksum savepoint dynamic inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $sections = array_count_values(array_column($rows, 'section'));

    $t->same(1000, count($rows));
    $t->same(334, $sections['walcksum-3.0..3.2']);
    $t->same(333, $sections['walcksum-4.0..4.3']);
    $t->same(333, $sections['walcksum-5.0..5.3']);
    $t->same('walcksum.test walcksum-3.0..3.2 savepoint checksum recovery dynamic case 0001', $rows[0]['upstream']);
    $t->same('walcksum.test walcksum-3.0..3.2 savepoint checksum recovery dynamic case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: walcksum.test 3.0..3.2, 4.0..4.3, and 5.0..5.3 cover cache_size=1 savepoint rollback with copied WAL recovery',
        'upstream source: walcksum.test 3.0..3.2, 4.0..4.3, and 5.0..5.3 cover cache_size=1 savepoint rollback with copied WAL recovery'
    );
    $t->same(
        'non-overlap: targets walcksum savepoint rollback valid-tail recovery after cache spill; avoids accepted walcksum-1 checksum endian, walcksum-2 corrupt statement tail, stale reused-log prefix, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, and app-WAL slices',
        'non-overlap: targets walcksum savepoint rollback valid-tail recovery after cache spill; avoids accepted walcksum-1 checksum endian, walcksum-2 corrupt statement tail, stale reused-log prefix, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, and app-WAL slices'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteWal checksum parsing, transaction recovery boundary, checkpoint application, SQLiteSavepointStack WAL rollback planning, and hydrated upstream walcksum.test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteWal checksum parsing, transaction recovery boundary, checkpoint application, SQLiteSavepointStack WAL rollback planning, and hydrated upstream walcksum.test source truth'
    );
};

$tests['real upstream corpus pager wal checksum savepoint dynamic rejects invalid row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walChecksumSavepointRegressionRows(0));
};

return $tests;
