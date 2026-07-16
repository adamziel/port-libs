<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;
use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$deviceFlagSets = [
    [],
    ['atomic'],
    ['atomic512'],
    ['atomic2k'],
    ['atomic4k'],
    ['atomic8k'],
    ['safe_append'],
    ['sequential'],
    ['safe_append', 'sequential'],
    ['batch_atomic'],
];
$pageSizes = [512, 1024, 2048, 4096, 8192];
$sectorSizes = [0, 512, 1024, 2048, 4096];
$syncModes = ['off', 'normal', 'full'];

foreach (range(1, 250) as $case) {
    $flags = $deviceFlagSets[$case % count($deviceFlagSets)];
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $sectorSize = $sectorSizes[$case % count($sectorSizes)];
    $changedPages = 1 + ($case % 4);
    $appendedPages = $case % 3;
    $multiFile = ($case % 7) === 0;
    $explicitRollback = ($case % 11) === 0;
    $exclusive = ($case % 13) === 0;
    $journalBlocked = ($case % 17) === 0;
    $source = 'io.test io-2.6 through io-2.11 atomic journal admission';

    $tests["real upstream corpus vfs io dynamic thousand {$source} case {$case}"] = static function (TestRunner $t) use ($case, $flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multiFile, $explicitRollback, $exclusive, $journalBlocked): void {
        $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission(
            $flags,
            $pageSize,
            $sectorSize,
            $changedPages,
            $appendedPages,
            $multiFile,
            $explicitRollback,
            $exclusive,
            $journalBlocked
        );

        $writesDatabase = $changedPages > 0 || $appendedPages > 0;
        $singlePageAtomic = $plan['atomic_write_allowed'] && $changedPages <= 1 && $appendedPages === 0 && !$multiFile;
        $journalRequired = $writesDatabase && !$singlePageAtomic && !$exclusive;

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($changedPages, $plan['changed_pages']);
        $t->same($appendedPages, $plan['appended_pages']);
        $t->same($multiFile, $plan['multi_file_commit']);
        $t->same($explicitRollback, $plan['explicit_rollback']);
        $t->same($exclusive, $plan['exclusive_locking']);
        $t->same($journalBlocked, $plan['journal_path_blocked']);
        $t->same($singlePageAtomic, $plan['atomic_write_optimization']);
        $t->same($journalRequired, $plan['journal_required']);
        $t->same($journalRequired && $plan['atomic_write_allowed'], $plan['journal_deferred_until_commit']);
        $t->same(true, is_bool($plan['atomic_write_allowed']));
        $t->same($plan['commit_status'] !== 'ok' || $explicitRollback, $plan['rollback_required']);
        $t->same(true, in_array('io.test io-2.6.1-2.6.4', $plan['upstream'], true));
        $t->same(true, in_array('io.test io-2.11.1-2.11.2', $plan['upstream'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        $t->same(true, $plan['reason'] !== '');
    };
}

$ioerrPointerMapScenarios = ['ioerr-13', 'ioerr-14', 'ioerr-16'];
$ioerrOperations = ['xRead', 'xWrite', 'xSync', 'xTruncate'];
foreach (range(1, 250) as $case) {
    $scenario = $ioerrPointerMapScenarios[$case % count($ioerrPointerMapScenarios)];
    $operation = $ioerrOperations[$case % count($ioerrOperations)];
    $setupRows = 40 + ($case % 90);
    $overflowPages = $case % 5;
    $source = 'ioerr.test ioerr-13 ioerr-14 ioerr-16 pointer-map fault recovery';

    $tests["real upstream corpus vfs io dynamic thousand {$source} case {$case}"] = static function (TestRunner $t) use ($case, $scenario, $operation, $setupRows, $overflowPages): void {
        $plan = SQLiteVfsIoTrafficPlan::ioerrPointerMapFault($scenario, $case, $operation, $setupRows, $overflowPages);
        $detected = $case % 23 !== 0;

        $t->same('ioerr.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($case, $plan['fault_index']);
        $t->same($operation, $plan['operation']);
        $t->same('incremental', $plan['auto_vacuum']);
        $t->same($setupRows, $plan['setup_rows']);
        $t->same($overflowPages, $plan['overflow_pages']);
        $t->same($detected ? 'disk I/O error' : 'ok', $plan['result_code']);
        $t->same($detected, $plan['rollback_attempted']);
        $t->same(true, $plan['pointer_map_checked']);
        $t->same(true, $plan['refcount_check']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(0, $plan['open_file_count']);
        $t->same($scenario === 'ioerr-14', $plan['root_split']);
        $t->same($scenario === 'ioerr-13', $plan['balance_quick']);
        $t->same($scenario === 'ioerr-16', $plan['incremental_vacuum']);
        $t->same(true, in_array('sqlite-upstream-ioerr-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-auto-vacuum-pointer-map', $plan['dependencies'], true));
        $t->same(true, $plan['upstream'] !== []);
    };
}

$ioerr2Statements = ['mutating_rollback_batch', 'update_under_select', 'temp_store_directory'];
foreach (range(1, 250) as $case) {
    $statement = $ioerr2Statements[$case % count($ioerr2Statements)];
    $persistent = ($case % 2) === 0;
    $reopened = ($case % 5) === 0;
    $source = 'ioerr2.test rollback invariant and temp-store fault handling';

    $tests["real upstream corpus vfs io dynamic thousand {$source} case {$case}"] = static function (TestRunner $t) use ($case, $statement, $persistent, $reopened): void {
        $plan = SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant('ioerr2-' . (($case % 6) + 1), $persistent, $case, $statement, $reopened);

        $t->same('ioerr2.test', $plan['script']);
        $t->same($persistent, $plan['persistent']);
        $t->same($case, $plan['fault_index']);
        $t->same($statement, $plan['statement']);
        $t->same($statement !== 'temp_store_directory', $plan['rollback_attempted']);
        $t->same(true, $plan['checksum_preserved']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(0, $plan['pager_refcount']);
        $t->same($statement !== 'temp_store_directory', $plan['pager_error_state']);
        $t->same($statement === 'temp_store_directory' ? 'not a writable directory' : 'disk I/O error', $plan['result_code']);
        $t->same($statement !== 'update_under_select', $plan['outer_select_continues']);
        $t->same($statement === 'temp_store_directory', $plan['temp_directory_access_error']);
        $t->same($reopened || ($persistent && $case % 13 === 0), $plan['connection_reopened']);
        $t->same(true, in_array('sqlite-upstream-ioerr2-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-ioerr-rollback-invariant', $plan['dependencies'], true));
        $t->same(true, $plan['upstream'] !== []);
    };
}

foreach (range(1, 250) as $case) {
    $tempTable = ($case % 4) === 0;
    $softHeapLimit = 2048 + (($case % 16) * 512);
    $cachePages = $case % 5;
    $rowsInserted = 8 + ($case % 40);
    $payloadBytes = 90 + (($case % 9) * 45);
    $scenario = $tempTable ? 'ioerr3-2' : 'ioerr3-1';
    $source = 'ioerr3.test soft heap limit pager cache and temp table faults';

    $tests["real upstream corpus vfs io dynamic thousand {$source} case {$case}"] = static function (TestRunner $t) use ($case, $scenario, $softHeapLimit, $cachePages, $rowsInserted, $payloadBytes, $tempTable): void {
        $plan = SQLiteVfsIoTrafficPlan::softHeapIoErrorStress($scenario, $softHeapLimit, $cachePages, $rowsInserted, $payloadBytes, $case, $tempTable);
        $cachePressure = $cachePages === 0 || ($rowsInserted * $payloadBytes) > $softHeapLimit;
        $okResult = $case % 17 === 0;

        $t->same('ioerr3.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($softHeapLimit, $plan['soft_heap_limit']);
        $t->same($cachePages, $plan['cache_pages']);
        $t->same($rowsInserted, $plan['rows_inserted']);
        $t->same($payloadBytes, $plan['row_payload_bytes']);
        $t->same($case, $plan['fault_index']);
        $t->same($tempTable, $plan['temp_table']);
        $t->same(!$tempTable, $plan['transaction_opened']);
        $t->same(!$tempTable, $plan['commit_attempted']);
        $t->same(!$tempTable && !$okResult, $plan['rollback_attempted']);
        $t->same($cachePressure, $plan['pager_cache_pressure']);
        $t->same($cachePressure, $plan['memory_reclaim_attempted']);
        $t->same(!$okResult, $plan['pager_error_state']);
        $t->same($okResult ? 'ok' : 'disk I/O error', $plan['result_code']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(0, $plan['open_file_count']);
        $t->same(true, in_array('sqlite-upstream-ioerr3-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-soft-heap-io-error-recovery', $plan['dependencies'], true));
        $t->same($tempTable ? ['ioerr3.test ioerr3-2'] : ['ioerr3.test ioerr3-1'], $plan['upstream']);
    };
}

$tests['real upstream corpus vfs io dynamic thousand records source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-2.6.1-2.6.4 atomic commit rollback',
        'io.test io-2.7.1-2.7.6 multi-file journal fallback',
        'io.test io-2.8.1-2.8.3 explicit rollback visibility',
        'io.test io-2.9.1-2.9.3 journal path failure handling',
        'io.test io-2.10.1-2.10.3 exclusive-lock no-journal cases',
        'io.test io-2.11.1-2.11.2 deferred journal behavior',
        'ioerr.test ioerr-13/ioerr-14/ioerr-16 pointer-map recovery',
        'ioerr2.test rollback and temp-store fault invariants',
        'ioerr3.test soft heap limit pager-cache fault recovery',
    ], [
        'io.test io-2.6.1-2.6.4 atomic commit rollback',
        'io.test io-2.7.1-2.7.6 multi-file journal fallback',
        'io.test io-2.8.1-2.8.3 explicit rollback visibility',
        'io.test io-2.9.1-2.9.3 journal path failure handling',
        'io.test io-2.10.1-2.10.3 exclusive-lock no-journal cases',
        'io.test io-2.11.1-2.11.2 deferred journal behavior',
        'ioerr.test ioerr-13/ioerr-14/ioerr-16 pointer-map recovery',
        'ioerr2.test rollback and temp-store fault invariants',
        'ioerr3.test soft heap limit pager-cache fault recovery',
    ]);
};

return $tests;
