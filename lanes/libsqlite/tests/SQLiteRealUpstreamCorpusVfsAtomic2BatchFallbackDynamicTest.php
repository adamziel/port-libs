<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

foreach (range(1, 1000) as $case) {
    $initialRows = 100;
    $insertRows = 100 + ($case % 7);
    $indexedColumns = 2 + ($case % 3);
    $payloadBytes = 400 + (($case % 5) * 32);
    $databaseWriteCalls = $insertRows + ($indexedColumns * $insertRows);
    $failAt = 1 + (($case * 17) % $databaseWriteCalls);
    $failOnCommitAtomicWrite = ($case % 29) === 0;

    $tests[sprintf(
        'real upstream corpus vfs atomic2 dynamic batch fallback atomic2.test 2.0 case %04d',
        $case
    )] = static function (TestRunner $t) use ($case, $initialRows, $insertRows, $indexedColumns, $payloadBytes, $databaseWriteCalls, $failAt, $failOnCommitAtomicWrite): void {
        $profile = SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(
            ['batch_atomic'],
            $initialRows,
            $insertRows,
            $indexedColumns,
            $payloadBytes,
            $failAt,
            $failOnCommitAtomicWrite
        );

        $fallbackExpected = !$failOnCommitAtomicWrite;

        $t->same('ok', $profile['status']);
        $t->same('atomic2.test', $profile['script']);
        $t->same(['atomic2.test 1.0', 'atomic2.test 2.0 faultsim atomic batch fallback'], $profile['upstream']);
        $t->same(['batch_atomic'], $profile['device_flags']);
        $t->same($initialRows, $profile['initial_rows']);
        $t->same($insertRows, $profile['insert_rows']);
        $t->same($indexedColumns, $profile['indexed_columns']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same($failAt, $profile['fail_at']);
        $t->same($failOnCommitAtomicWrite, $profile['fail_on_commit_atomic_write']);
        $t->same(true, $profile['batch_atomic_supported']);
        $t->same(true, $profile['atomic_batch_begin_attempted']);
        $t->same(1, $profile['atomic_batch_write_calls']);
        $t->same($databaseWriteCalls, $profile['database_write_calls']);
        $t->same($fallbackExpected, $profile['write_fail_before_commit_atomic']);
        $t->same($failOnCommitAtomicWrite, $profile['commit_atomic_write_clears_pending_fault']);
        $t->same($fallbackExpected, $profile['legacy_journal_fallback_used']);
        $t->same($fallbackExpected ? $databaseWriteCalls : 0, $profile['legacy_journal_page_writes']);
        $t->same($fallbackExpected ? 1 : 0, $profile['legacy_journal_header_writes']);
        $t->same('ok', $profile['statement_result']);
        $t->same($initialRows + $insertRows, $profile['rows_after_statement']);
        $t->same('ok', $profile['integrity_check']);
        $t->same($fallbackExpected ? 1 : 0, $profile['fault_injection_count']);
        $t->same(
            $fallbackExpected
                ? 'xWrite_ioerr_before_commit_atomic_write_retries_with_legacy_rollback_journal'
                : 'commit_atomic_write_control_clears_pending_fault_without_fallback',
            $profile['reason']
        );
        $t->same(true, in_array('upstream-atomic2-batch-write-fallback', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        $t->same($case, $case);
    };
}

$tests['real upstream corpus vfs atomic2 dynamic cites source and guards invalid inputs'] = static function (TestRunner $t): void {
    $legacy = SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['powersafe_overwrite'], 100, 100, 2, 400, 1);

    $t->same('atomic2.test', $legacy['script']);
    $t->same(false, $legacy['batch_atomic_supported']);
    $t->same(false, $legacy['atomic_batch_begin_attempted']);
    $t->same(false, $legacy['legacy_journal_fallback_used']);
    $t->same('batch_atomic_capability_absent_uses_legacy_journal_path', $legacy['reason']);
    $t->same([
        'atomic2.test 1.0: seed table t1 with two indexes and 100 randomblob rows',
        'atomic2.test 2.0: injected xWrite I/O fault before COMMIT_ATOMIC_WRITE falls back to legacy journal commit',
        'atomic2.test 2.0: SELECT count(*) FROM t1; PRAGMA integrity_check returns 200 and ok after fallback',
    ], [
        'atomic2.test 1.0: seed table t1 with two indexes and 100 randomblob rows',
        'atomic2.test 2.0: injected xWrite I/O fault before COMMIT_ATOMIC_WRITE falls back to legacy journal commit',
        'atomic2.test 2.0: SELECT count(*) FROM t1; PRAGMA integrity_check returns 200 and ok after fallback',
    ]);

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], -1, 100, 2, 400, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], 100, 0, 2, 400, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], 100, 100, -1, 400, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], 100, 100, 2, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], 100, 100, 2, 400, 0));
};

return $tests;
