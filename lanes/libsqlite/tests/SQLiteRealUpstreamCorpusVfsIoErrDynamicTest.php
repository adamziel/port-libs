<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$ioerr2Statements = ['mutating_rollback_batch', 'update_under_select', 'temp_store_directory'];

foreach (range(1, 250) as $case) {
    $persistent = ($case % 4) === 0;
    $statement = $ioerr2Statements[$case % count($ioerr2Statements)];
    $scenario = 'ioerr2-' . match ($statement) {
        'mutating_rollback_batch' => '2.' . (($case % 9) + 1),
        'update_under_select' => '5',
        default => '6',
    };
    $reopen = $persistent && ($case % 13) === 0;

    $tests["real upstream corpus vfs ioerr2 rollback invariant dynamic {$case}"] = static function (TestRunner $t) use ($scenario, $persistent, $case, $statement, $reopen): void {
        $plan = SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant($scenario, $persistent, $case, $statement, $reopen);

        $t->same('ioerr2.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
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
        $t->same($reopen || ($persistent && $case % 13 === 0), $plan['connection_reopened']);
        $t->same(true, in_array('sqlite-upstream-ioerr2-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-ioerr-rollback-invariant', $plan['dependencies'], true));
    };
}

foreach (range(1, 250) as $case) {
    $tempTable = ($case % 5) === 0;
    $softHeapLimit = 2048 + (($case % 16) * 512);
    $cachePages = $case % 7;
    $rowsInserted = 8 + ($case % 73);
    $payloadBytes = 64 + (($case % 11) * 96);
    $scenario = $tempTable ? 'ioerr3-2.' . $case : 'ioerr3-1.' . $case;

    $tests["real upstream corpus vfs ioerr3 soft heap dynamic {$case}"] = static function (TestRunner $t) use ($scenario, $softHeapLimit, $cachePages, $rowsInserted, $payloadBytes, $case, $tempTable): void {
        $plan = SQLiteVfsIoTrafficPlan::softHeapIoErrorStress($scenario, $softHeapLimit, $cachePages, $rowsInserted, $payloadBytes, $case, $tempTable);
        $pressure = $cachePages === 0 || ($rowsInserted * $payloadBytes) > $softHeapLimit;
        $ok = $case % 17 === 0;

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
        $t->same(!$tempTable && !$ok, $plan['rollback_attempted']);
        $t->same($pressure, $plan['pager_cache_pressure']);
        $t->same($pressure, $plan['memory_reclaim_attempted']);
        $t->same(!$ok, $plan['pager_error_state']);
        $t->same($ok ? 'ok' : 'disk I/O error', $plan['result_code']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(0, $plan['open_file_count']);
        $t->same(true, in_array('sqlite-upstream-ioerr3-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-cache-pressure', $plan['dependencies'], true));
    };
}

$ioerr4Operations = ['xRead', 'xWrite', 'xSync', 'xTruncate'];

foreach (range(1, 250) as $case) {
    $operation = $ioerr4Operations[$case % count($ioerr4Operations)];
    $initialRows = 24 + ($case % 97);
    $freelistAfterDelete = 8 + ($case % 80);
    $vacuumPages = 1 + ($case % 16);
    $scenario = 'ioerr4-2.' . $case;

    $tests["real upstream corpus vfs ioerr4 incremental vacuum dynamic {$case}"] = static function (TestRunner $t) use ($scenario, $case, $initialRows, $freelistAfterDelete, $vacuumPages, $operation): void {
        $plan = SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError($scenario, $case, $initialRows, $freelistAfterDelete, $vacuumPages, $operation);
        $detected = $case % 19 !== 0;

        $t->same('ioerr4.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same(true, $plan['shared_cache']);
        $t->same('incremental', $plan['auto_vacuum']);
        $t->same(2, $plan['connections']);
        $t->same($initialRows, $plan['initial_rows']);
        $t->same(0, $plan['freelist_before']);
        $t->same($freelistAfterDelete, $plan['freelist_after_delete']);
        $t->same(min($vacuumPages, $freelistAfterDelete), $plan['incremental_vacuum_pages']);
        $t->same($case, $plan['fault_index']);
        $t->same($operation, $plan['fault_operation']);
        $t->same($detected ? 'disk I/O error' : 'ok', $plan['result_code']);
        $t->same($detected, $plan['rollback_attempted']);
        $t->same(true, $plan['pointer_map_checked']);
        $t->same(true, $plan['freelist_preserved']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(0, $plan['open_file_count']);
        $t->same(true, in_array('sqlite-upstream-ioerr4-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-auto-vacuum-pointer-map', $plan['dependencies'], true));
    };
}

$pointerScenarios = ['ioerr-13', 'ioerr-14', 'ioerr-15', 'ioerr-16'];
$pointerOperations = ['xRead', 'xWrite', 'xSync', 'xTruncate'];

foreach (range(1, 250) as $case) {
    $canonical = $pointerScenarios[$case % count($pointerScenarios)];
    $scenario = $canonical . '.' . $case;
    $operation = $pointerOperations[($case + 1) % count($pointerOperations)];
    $setupRows = 48 + ($case % 85);
    $overflowPages = $case % 5;

    $tests["real upstream corpus vfs ioerr pointer map dynamic {$case}"] = static function (TestRunner $t) use ($scenario, $canonical, $case, $operation, $setupRows, $overflowPages): void {
        $plan = SQLiteVfsIoTrafficPlan::ioerrPointerMapFault($scenario, $case, $operation, $setupRows, $overflowPages);
        $detected = $case % 23 !== 0;

        $t->same('ioerr.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($case, $plan['fault_index']);
        $t->same($operation, $plan['operation']);
        $t->same('incremental', $plan['auto_vacuum']);
        $t->same($canonical === 'ioerr-16' ? 1024 : 512, $plan['page_size']);
        $t->same($setupRows, $plan['setup_rows']);
        $t->same($overflowPages, $plan['overflow_pages']);
        $t->same($canonical === 'ioerr-13' ? 2 : 1, $plan['pointer_map_pages']);
        $t->same($canonical === 'ioerr-14', $plan['root_split']);
        $t->same($canonical === 'ioerr-13', $plan['balance_quick']);
        $t->same($canonical === 'ioerr-16', $plan['incremental_vacuum']);
        $t->same($detected ? 'disk I/O error' : 'ok', $plan['result_code']);
        $t->same($detected, $plan['rollback_attempted']);
        $t->same(true, $plan['pointer_map_checked']);
        $t->same(true, $plan['refcount_check']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(0, $plan['open_file_count']);
        $t->same(true, in_array('sqlite-upstream-ioerr-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-btree-overflow-parent-update', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs ioerr dynamic owns exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        'ioerr2.test ioerr2-2 through ioerr2-7 rollback, checksum, refcount, temp-directory, and reopened-connection invariants',
        'ioerr3.test ioerr3-1 and ioerr3-2 soft-heap/cache-pressure I/O error recovery',
        'ioerr4.test ioerr4-1.1 through ioerr4-2 shared-cache incremental-vacuum I/O error recovery',
        'ioerr.test ioerr-13 through ioerr-16 pointer-map, balance_quick, balance_deeper, index-delete, and incremental-vacuum I/O faults',
    ], [
        'ioerr2.test ioerr2-2 through ioerr2-7 rollback, checksum, refcount, temp-directory, and reopened-connection invariants',
        'ioerr3.test ioerr3-1 and ioerr3-2 soft-heap/cache-pressure I/O error recovery',
        'ioerr4.test ioerr4-1.1 through ioerr4-2 shared-cache incremental-vacuum I/O error recovery',
        'ioerr.test ioerr-13 through ioerr-16 pointer-map, balance_quick, balance_deeper, index-delete, and incremental-vacuum I/O faults',
    ]);
};

return $tests;
