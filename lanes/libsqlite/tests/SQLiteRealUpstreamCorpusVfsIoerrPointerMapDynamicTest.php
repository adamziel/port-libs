<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$scenarioMatrix = [
    'ioerr-13' => ['xWrite', 78, 1, 2, false, true, false],
    'ioerr-14' => ['xWrite', 10, 1, 1, true, false, false],
    'ioerr-15' => ['xSync', 99, 1, 1, false, false, false],
    'ioerr-16' => ['xTruncate', 202, 0, 1, false, false, true],
];

$case = 0;
foreach ($scenarioMatrix as $scenarioRoot => [$defaultOperation, $setupRows, $overflowPages, $pointerMapPages, $rootSplit, $balanceQuick, $incrementalVacuum]) {
    foreach (range(1, 175) as $faultIndex) {
        foreach (['xRead', $defaultOperation] as $operation) {
            $case++;
            $scenario = sprintf('%s.dynamic.%s.%03d', $scenarioRoot, $operation, $faultIndex);
            $tests[sprintf('real upstream corpus vfs ioerr pointer-map dynamic %04d %s', $case, $scenario)] = static function (TestRunner $t) use ($scenario, $faultIndex, $operation, $setupRows, $overflowPages, $pointerMapPages, $rootSplit, $balanceQuick, $incrementalVacuum): void {
                $plan = SQLiteVfsIoTrafficPlan::ioerrPointerMapFault($scenario, $faultIndex, $operation, $setupRows, $overflowPages);
                $detected = $faultIndex % 23 !== 0;

                $t->same('ioerr.test', $plan['script']);
                $t->same($scenario, $plan['scenario']);
                $t->same($faultIndex, $plan['fault_index']);
                $t->same($operation, $plan['operation']);
                $t->same('incremental', $plan['auto_vacuum']);
                $t->same($setupRows, $plan['setup_rows']);
                $t->same($overflowPages, $plan['overflow_pages']);
                $t->same($pointerMapPages, $plan['pointer_map_pages']);
                $t->same($rootSplit, $plan['root_split']);
                $t->same($balanceQuick, $plan['balance_quick']);
                $t->same($incrementalVacuum, $plan['incremental_vacuum']);
                $t->same($detected ? 'disk I/O error' : 'ok', $plan['result_code']);
                $t->same($detected, $plan['rollback_attempted']);
                $t->same(true, $plan['pointer_map_checked']);
                $t->same(true, $plan['refcount_check']);
                $t->same('ok', $plan['integrity_check']);
                $t->same(0, $plan['open_file_count']);
                $t->same(true, in_array('sqlite-upstream-ioerr-test', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-auto-vacuum-pointer-map', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-vfs-io-error-recovery', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-btree-overflow-parent-update', $plan['dependencies'], true));
                $t->same(true, $plan['upstream'] !== []);
                $t->same(true, str_starts_with($plan['upstream'][0], 'ioerr.test '));
            };
        }
    }
}

$tests['real upstream corpus vfs ioerr pointer-map cites upstream scenarios'] = static function (TestRunner $t): void {
    $cases = [
        ['ioerr-13.dynamic.citation', 'ioerr.test ioerr-13 balance_quick pointer-map pages', true, false, false, 2],
        ['ioerr-14.dynamic.citation', 'ioerr.test ioerr-14 balance_deeper overflow parent pointer-map update', false, true, false, 1],
        ['ioerr-15.dynamic.citation', 'ioerr.test ioerr-15 index delete plus large overflow statement rollback', false, false, false, 1],
        ['ioerr-16.dynamic.citation', 'ioerr.test ioerr-16 incremental_vacuum after delete tkt3762 branch', false, false, true, 1],
    ];

    foreach ($cases as [$scenario, $upstream, $balanceQuick, $rootSplit, $incrementalVacuum, $pointerMapPages]) {
        $plan = SQLiteVfsIoTrafficPlan::ioerrPointerMapFault($scenario, 23);

        $t->same([$upstream], $plan['upstream']);
        $t->same($balanceQuick, $plan['balance_quick']);
        $t->same($rootSplit, $plan['root_split']);
        $t->same($incrementalVacuum, $plan['incremental_vacuum']);
        $t->same($pointerMapPages, $plan['pointer_map_pages']);
        $t->same('ok', $plan['result_code']);
        $t->same(false, $plan['rollback_attempted']);
    }
};

$tests['real upstream corpus vfs ioerr pointer-map rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioerrPointerMapFault('', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioerrPointerMapFault('ioerr-13.bad', 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioerrPointerMapFault('ioerr-12.bad', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioerrPointerMapFault('ioerr-13.bad', 1, 'xOpen'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioerrPointerMapFault('ioerr-13.bad', 1, 'xWrite', 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioerrPointerMapFault('ioerr-13.bad', 1, 'xWrite', 1, -1));
};

return $tests;
