<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$caseIndex = 0;
foreach ([4096, 8192, 12288, 16384, 24576] as $softHeapLimit) {
    foreach ([0, 1, 2, 4, 8] as $cachePages) {
        foreach ([16, 32, 64, 96, 100] as $rowsInserted) {
            foreach ([188, 224, 256, 320] as $rowPayloadBytes) {
                foreach ([1, 2] as $faultPhase) {
                    ++$caseIndex;
                    $faultIndex = (($caseIndex * 7) % 53) + $faultPhase;
                    $scenario = sprintf(
                        'ioerr3-1.dynamic.%04d.heap%d.cache%d.rows%d.payload%d.fault%d',
                        $caseIndex,
                        $softHeapLimit,
                        $cachePages,
                        $rowsInserted,
                        $rowPayloadBytes,
                        $faultIndex
                    );

                    $tests['real upstream corpus vfs ioerr3 soft heap dynamic ' . $scenario] = static function (TestRunner $t) use ($scenario, $softHeapLimit, $cachePages, $rowsInserted, $rowPayloadBytes, $faultIndex): void {
                        $plan = SQLiteVfsIoTrafficPlan::softHeapIoErrorStress($scenario, $softHeapLimit, $cachePages, $rowsInserted, $rowPayloadBytes, $faultIndex);
                        $expectedPressure = $cachePages === 0 || ($rowsInserted * $rowPayloadBytes) > $softHeapLimit;
                        $expectedResult = $faultIndex % 17 === 0 ? 'ok' : 'disk I/O error';

                        $t->same('ioerr3.test', $plan['script']);
                        $t->same($scenario, $plan['scenario']);
                        $t->same($softHeapLimit, $plan['soft_heap_limit']);
                        $t->same($cachePages, $plan['cache_pages']);
                        $t->same($rowsInserted, $plan['rows_inserted']);
                        $t->same($rowPayloadBytes, $plan['row_payload_bytes']);
                        $t->same($faultIndex, $plan['fault_index']);
                        $t->same(false, $plan['temp_table']);
                        $t->same(true, $plan['transaction_opened']);
                        $t->same(true, $plan['commit_attempted']);
                        $t->same($expectedResult !== 'ok', $plan['rollback_attempted']);
                        $t->same($expectedPressure, $plan['pager_cache_pressure']);
                        $t->same($expectedPressure, $plan['memory_reclaim_attempted']);
                        $t->same($expectedResult !== 'ok', $plan['pager_error_state']);
                        $t->same($expectedResult, $plan['result_code']);
                        $t->same('ok', $plan['integrity_check']);
                        $t->same(0, $plan['open_file_count']);
                        $t->same(['ioerr3.test ioerr3-1'], $plan['upstream']);
                        $t->same(true, in_array('sqlite-upstream-ioerr3-test', $plan['dependencies'], true));
                        $t->same(true, in_array('sqlite-soft-heap-io-error-recovery', $plan['dependencies'], true));
                    };
                }
            }
        }
    }
}

foreach (range(1, 24) as $faultIndex) {
    $scenario = sprintf('ioerr3-2.dynamic.temp.%02d', $faultIndex);
    $tests['real upstream corpus vfs ioerr3 temp store dynamic ' . $scenario] = static function (TestRunner $t) use ($scenario, $faultIndex): void {
        $plan = SQLiteVfsIoTrafficPlan::softHeapIoErrorStress($scenario, 8192, 0, 1, 32, $faultIndex, true);

        $t->same('ioerr3.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same(true, $plan['temp_table']);
        $t->same(false, $plan['transaction_opened']);
        $t->same(false, $plan['commit_attempted']);
        $t->same(false, $plan['rollback_attempted']);
        $t->same(true, $plan['pager_cache_pressure']);
        $t->same(true, $plan['memory_reclaim_attempted']);
        $t->same($faultIndex % 17 !== 0, $plan['pager_error_state']);
        $t->same($faultIndex % 17 === 0 ? 'ok' : 'disk I/O error', $plan['result_code']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(0, $plan['open_file_count']);
        $t->same(['ioerr3.test ioerr3-2'], $plan['upstream']);
        $t->same(true, in_array('sqlite-pager-cache-pressure', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs ioerr3 soft heap dynamic owns upstream source files'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoTrafficPlan::softHeapIoErrorStress('ioerr3-1.dynamic.source-check', 8192, 0, 100, 188, 1);

    $t->same('ioerr3.test', $plan['script']);
    $t->same(['ioerr3.test ioerr3-1'], $plan['upstream']);
    $t->same(true, in_array('sqlite-upstream-ioerr3-test', $plan['dependencies'], true));
};

$tests['real upstream corpus vfs ioerr3 soft heap dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::softHeapIoErrorStress('', 8192, 0, 100, 188, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::softHeapIoErrorStress('ioerr3-1.bad', 0, 0, 100, 188, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::softHeapIoErrorStress('ioerr3-1.bad', 8192, -1, 100, 188, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::softHeapIoErrorStress('ioerr3-1.bad', 8192, 0, 0, 188, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::softHeapIoErrorStress('ioerr3-1.bad', 8192, 0, 100, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::softHeapIoErrorStress('ioerr3-1.bad', 8192, 0, 100, 188, 0));
};

return $tests;
