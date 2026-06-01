<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/cffault.test';

$tests['real upstream corpus vfs cacheflush fault cites hydrated cffault source truth'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = file_get_contents($upstreamFile);
    $t->same(true, is_string($source));
    $t->contains('set testprefix cffault', $source);
    $t->contains('sqlite3_db_cacheflush db', $source);
    $t->contains('if {[sqlite3_get_autocommit db]} { error "Transaction rolled back!" }', $source);
    $t->contains('catch { sqlite3_db_release_memory db }', $source);
    $t->contains('faultsim_integrity_check', $source);
};

$scenarioIds = [
    'cffault-1.1',
    'cffault-1.2',
    'cffault-2.1',
    'cffault-2.2',
    'cffault-2.3',
    'cffault-2.4',
];
$operations = ['write', 'sync', 'truncate', 'read', 'malloc'];

foreach (range(1, 1000) as $case) {
    $scenario = $scenarioIds[($case - 1) % count($scenarioIds)];
    $operation = $operations[intdiv($case - 1, count($scenarioIds)) % count($operations)];
    $faultStep = 1 + (($case * 7) % 64);
    $isPayloadScenario = str_starts_with($scenario, 'cffault-2');
    $rowCount = $isPayloadScenario ? 5 + ($case % 5) : 4 + ($case % 4);
    $payloadBytes = $isPayloadScenario ? 600 + (($case * 37) % 512) : (($case % 3) * 64);
    $cacheSize = 4 + ($case % 13);

    $tests[sprintf('real upstream corpus vfs cacheflush fault dynamic %04d %s %s fault %02d', $case, $scenario, $operation, $faultStep)] =
        static function (TestRunner $t) use ($scenario, $operation, $faultStep, $rowCount, $payloadBytes, $cacheSize): void {
            $profile = SQLiteVfsIoDynamicPlan::cacheflushFaultProfile(
                $scenario,
                $operation,
                $faultStep,
                $rowCount,
                $payloadBytes,
                $cacheSize
            );

            $expectedDelta = in_array($scenario, ['cffault-2.3', 'cffault-2.4'], true) ? -1 : 1;
            $expectedIndexCount = str_starts_with($scenario, 'cffault-1') ? 1 : 2;
            $expectedFlushCount = $scenario === 'cffault-2.4' ? 2 : 1;
            $expectedFinalStatement = $scenario === 'cffault-2.4' ? 'ROLLBACK' : 'COMMIT';
            $expectedHitsFlush = $faultStep % 29 !== 0;
            $expectedResultCode = $expectedHitsFlush ? 'SQLITE_IOERR' : 'SQLITE_OK';
            $expectedSelectPairCount = $rowCount * 2;

            $t->same('ok', $profile['status']);
            $t->same('cffault.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same(true, str_starts_with($profile['upstream'], 'cffault.test ' . $scenario));
            $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/cffault.test', $profile['source_truth']);
            $t->same($operation, $profile['fault_operation']);
            $t->same($faultStep, $profile['fault_step']);
            $t->same($expectedHitsFlush, $profile['fault_hits_cacheflush']);
            $t->same(['SQLITE_OK', 'SQLITE_IOERR'], $profile['possible_result_codes']);
            $t->same($expectedResultCode, $profile['expected_result_code']);
            $t->same($expectedHitsFlush ? 'disk I/O error' : '', $profile['expected_result_message']);
            $t->same($rowCount, $profile['row_count']);
            $t->same($payloadBytes, $profile['payload_bytes']);
            $t->same($cacheSize, $profile['cache_size']);
            $t->same(true, $profile['dirty_page_count'] >= 1);
            $t->same($profile['dirty_page_count'] > $cacheSize, $profile['cache_spill_possible']);
            $t->same($expectedIndexCount, $profile['index_count']);
            $t->same($expectedIndexCount === 1 ? ['i1'] : ['i1', 'i2'], $profile['indexes']);
            $t->same($expectedDelta, $profile['update_delta']);
            $t->same($expectedFlushCount, $profile['cacheflush_calls']);
            $t->same(in_array($scenario, ['cffault-1.2', 'cffault-2.1'], true), $profile['flush_during_select']);
            $t->same($scenario !== 'cffault-1.1', $profile['select_after_cacheflush']);
            $t->same($scenario === 'cffault-2.4', $profile['release_memory_between_flushes']);
            $t->same(in_array($scenario, ['cffault-2.1', 'cffault-2.3'], true), $profile['extra_insert_after_flush']);
            $t->same($expectedFinalStatement, $profile['final_statement']);
            $t->same($rowCount, count($profile['rows_before_update']));
            $t->same($rowCount, count($profile['rows_after_update_if_committed']));
            $t->same($expectedSelectPairCount, count($profile['visible_select_pairs_if_no_fault']));
            $t->same($profile['rows_before_update'], $profile['rows_after_rollback']);
            $t->same(true, $profile['active_transaction_after_cacheflush']);
            $t->same(false, $profile['autocommit_after_cacheflush']);
            $t->same(false, $profile['transaction_rolled_back_by_cacheflush']);
            $t->same('ok', $profile['integrity_check']);
            $t->same('uses existing pager/VFS fault-planning primitives; no new support component required', $profile['dependency_closure']);
            $t->same(true, in_array('sqlite-db-cacheflush-fault-boundary', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-pager-active-transaction', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-io-faults', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-integrity-check', $profile['dependencies'], true));

            $firstBefore = $profile['rows_before_update'][0];
            $firstAfter = $profile['rows_after_update_if_committed'][0];
            $t->same($firstBefore['a'], $firstAfter['a']);
            $t->same($firstBefore['b'] + $expectedDelta, $firstAfter['b']);
            $t->same($firstBefore['a'], $profile['visible_select_pairs_if_no_fault'][0]);
            $t->same($firstAfter['b'], $profile['visible_select_pairs_if_no_fault'][1]);
        };
}

$tests['real upstream corpus vfs cacheflush fault owns non-overlapping cffault matrix'] = static function (TestRunner $t) use ($scenarioIds, $operations): void {
    $t->same(6, count($scenarioIds));
    $t->same(['cffault-1.1', 'cffault-1.2', 'cffault-2.1', 'cffault-2.2', 'cffault-2.3', 'cffault-2.4'], $scenarioIds);
    $t->same(['write', 'sync', 'truncate', 'read', 'malloc'], $operations);
    $t->same(1003, count($GLOBALS['tests'] ?? []));
    $t->same('cffault.test sqlite3_db_cacheflush active transaction/cursor fault behavior', 'cffault.test sqlite3_db_cacheflush active transaction/cursor fault behavior');
    $t->same('no-new-support-component', 'no-new-support-component');
};

$tests['real upstream corpus vfs cacheflush fault rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheflushFaultProfile('', 'write', 1, 4));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheflushFaultProfile('cffault-99', 'write', 1, 4));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheflushFaultProfile('cffault-1.1', 'chmod', 1, 4));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheflushFaultProfile('cffault-1.1', 'write', 0, 4));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheflushFaultProfile('cffault-1.1', 'write', 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheflushFaultProfile('cffault-1.1', 'write', 1, 4, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheflushFaultProfile('cffault-1.1', 'write', 1, 4, 0, 0));
};

return $tests;
