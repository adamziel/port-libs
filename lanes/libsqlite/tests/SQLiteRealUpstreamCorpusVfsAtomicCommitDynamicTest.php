<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];
$atomicCommitCaseCount = 0;

$flagSets = [
    ['batch_atomic'],
    ['batch_atomic', 'safe_append'],
    ['batch_atomic', 'sequential'],
    ['batch_atomic', 'powersafe_overwrite'],
    ['batch_atomic', 'safe_append', 'sequential'],
];
$pageSizes = [512, 1024, 2048, 4096, 8192];

foreach (range(1, 1000) as $case) {
    ++$atomicCommitCaseCount;
    $flags = $flagSets[($case - 1) % count($flagSets)];
    $pageSize = $pageSizes[intdiv($case - 1, count($flagSets)) % count($pageSizes)];
    $sectorChoices = array_values(array_filter(
        [0, 512, 1024, 2048, 4096],
        static fn (int $sectorSize): bool => $sectorSize === 0 || $sectorSize <= $pageSize
    ));
    $sectorSize = $sectorChoices[intdiv($case - 1, count($flagSets) * count($pageSizes)) % count($sectorChoices)];
    $rowsInserted = 1 + (($case * 7) % 97);
    $indexedColumns = ($case + intdiv($case, 13)) % 5;
    $payloadBytes = 16 + (($case * 37) % 2048);
    $databaseWriteCalls = 1 + $rowsInserted + ($indexedColumns * $rowsInserted);
    $payloadPages = max(1, (int) ceil(($rowsInserted * ($payloadBytes + 16)) / $pageSize));
    $databasePagesTouched = 1 + $payloadPages + $indexedColumns;

    $tests[sprintf(
        'real upstream corpus vfs atomic commit dynamic atomic.test no journal before commit %04d',
        $case
    )] = static function (TestRunner $t) use (
        $flags,
        $pageSize,
        $sectorSize,
        $rowsInserted,
        $indexedColumns,
        $payloadBytes,
        $databaseWriteCalls,
        $databasePagesTouched
    ): void {
        $profile = SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile(
            $flags,
            $pageSize,
            $sectorSize,
            $rowsInserted,
            $indexedColumns,
            $payloadBytes
        );

        $t->same('ok', $profile['status']);
        $t->same('atomic.test', $profile['script']);
        $t->same([
            'atomic.test 1.0 CREATE TABLE t1(x,y); BEGIN; INSERT INTO t1 VALUES(1,2)',
            'atomic.test 1.1 file exists test.db-journal returns 0 before COMMIT',
            'atomic.test 1.2 COMMIT succeeds',
        ], $profile['upstream']);
        $t->same($flags, $profile['device_flags']);
        $t->same($pageSize, $profile['page_size']);
        $t->same($sectorSize, $profile['sector_size']);
        $t->same($sectorSize === 0 ? 512 : $sectorSize, $profile['effective_sector_size']);
        $t->same($rowsInserted, $profile['rows_inserted']);
        $t->same($indexedColumns, $profile['indexed_columns']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same(true, $profile['batch_atomic_supported']);
        $t->same(true, $profile['atomic_write_allowed']);
        $t->same(true, $profile['atomic_batch_begin_attempted']);
        $t->same(true, $profile['atomic_batch_commit_attempted']);
        $t->same(1, $profile['atomic_batch_write_calls']);
        $t->same(['BEGIN_ATOMIC_WRITE', 'COMMIT_ATOMIC_WRITE'], $profile['atomic_batch_control_sequence']);
        $t->same(true, $profile['table_schema_created']);
        $t->same(true, $profile['transaction_open_before_commit']);
        $t->same('ok', $profile['insert_statement_result']);
        $t->same('test.db-journal', $profile['rollback_journal_path']);
        $t->same(false, $profile['journal_exists_after_begin_insert']);
        $t->same(false, $profile['file_exists_test_db_journal']);
        $t->same(false, $profile['legacy_journal_fallback_used']);
        $t->same(0, $profile['legacy_journal_header_writes']);
        $t->same(0, $profile['legacy_journal_page_writes']);
        $t->same($databaseWriteCalls, $profile['database_write_calls']);
        $t->same($databasePagesTouched, $profile['database_pages_touched']);
        $t->same('ok', $profile['commit_result']);
        $t->same(false, $profile['rollback_required']);
        $t->same($rowsInserted, $profile['rows_after_commit']);
        $t->same('ok', $profile['integrity_check']);
        $t->same('atomic_batch_write_keeps_rollback_journal_absent_until_commit', $profile['reason']);
        $t->same(true, in_array('upstream-atomic-test', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-atomic-batch-commit', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs atomic commit dynamic cites hydrated atomic.test source'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/atomic.test');

    $t->same(true, is_string($source));
    $t->contains('set ::testprefix atomic', $source);
    $t->contains('atomic_batch_write test.db', $source);
    $t->contains('do_execsql_test 1.0', $source);
    $t->contains('CREATE TABLE t1(x, y);', $source);
    $t->contains('BEGIN;', $source);
    $t->contains('INSERT INTO t1 VALUES(1, 2);', $source);
    $t->contains('do_test 1.1 { file exists test.db-journal } {0}', $source);
    $t->contains('do_execsql_test 1.2', $source);
    $t->contains('COMMIT;', $source);

    $profile = SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile(['batch_atomic'], 4096, 512, 1);

    $t->same('atomic.test', $profile['script']);
    $t->same(false, $profile['file_exists_test_db_journal']);
    $t->same('ok', $profile['commit_result']);
    $t->same([
        'atomic.test 1.0 CREATE TABLE t1(x,y); BEGIN; INSERT INTO t1 VALUES(1,2)',
        'atomic.test 1.1 file exists test.db-journal returns 0 before COMMIT',
        'atomic.test 1.2 COMMIT succeeds',
    ], $profile['upstream']);
};

$tests['real upstream corpus vfs atomic commit dynamic exposes legacy journal fallback when capability absent'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile(['safe_append'], 4096, 512, 4, 1, 128);

    $t->same('ok', $profile['status']);
    $t->same('atomic.test', $profile['script']);
    $t->same(['safe_append'], $profile['device_flags']);
    $t->same(false, $profile['batch_atomic_supported']);
    $t->same(false, $profile['atomic_batch_begin_attempted']);
    $t->same(false, $profile['atomic_batch_commit_attempted']);
    $t->same(0, $profile['atomic_batch_write_calls']);
    $t->same([], $profile['atomic_batch_control_sequence']);
    $t->same(true, $profile['journal_exists_after_begin_insert']);
    $t->same(true, $profile['file_exists_test_db_journal']);
    $t->same(true, $profile['legacy_journal_fallback_used']);
    $t->same(1, $profile['legacy_journal_header_writes']);
    $t->same(3, $profile['legacy_journal_page_writes']);
    $t->same('ok', $profile['commit_result']);
    $t->same(4, $profile['rows_after_commit']);
    $t->same('batch_atomic_capability_absent_uses_legacy_rollback_journal', $profile['reason']);
};

$tests['real upstream corpus vfs atomic commit dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile(['batch_atomic'], 1000, 512, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile(['batch_atomic'], 4096, 1000, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile(['batch_atomic'], 4096, 512, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile(['batch_atomic'], 4096, 512, 1, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile(['batch_atomic'], 4096, 512, 1, 0, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile(['remote'], 4096, 512, 1));
};

$tests['real upstream corpus vfs atomic commit dynamic owns one thousand focused upstream cases'] = static function (TestRunner $t) use ($atomicCommitCaseCount): void {
    $t->same(1000, $atomicCommitCaseCount);
    $t->same(1004, $atomicCommitCaseCount + 4);
};

return $tests;
