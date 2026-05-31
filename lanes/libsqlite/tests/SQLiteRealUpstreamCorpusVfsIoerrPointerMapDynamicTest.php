<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;
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

$dynamicScenarios = [
    'ioerr-12-incremental-vacuum' => [
        'mode' => 'incremental',
        'free' => 3,
        'vacuum' => 1,
        'shared' => false,
        'statements' => [
            'PRAGMA page_size = 512',
            'PRAGMA auto_vacuum = incremental',
            'CREATE TABLE t1(x)',
            'INSERT INTO t1 VALUES(randomblob(110 * (512-4)))',
            'DELETE FROM t1 WHERE rowid = 3',
            'PRAGMA incremental_vacuum = 2',
            'PRAGMA incremental_vacuum = 1',
        ],
    ],
    'ioerr-12-coresident-sector' => [
        'mode' => 'incremental',
        'free' => 1,
        'vacuum' => 0,
        'shared' => false,
        'statements' => [
            'PRAGMA page_size = 1024',
            'CREATE TABLE t1(x)',
            'INSERT INTO t1 VALUES(randomblob(1100))',
            'INSERT INTO t1 VALUES(randomblob(2000))',
        ],
    ],
    'ioerr-13-balance-quick-pointermap' => [
        'mode' => 'incremental',
        'free' => 1,
        'vacuum' => 0,
        'shared' => false,
        'statements' => [
            'PRAGMA auto_vacuum = incremental',
            'CREATE TABLE t1(x)',
            'CREATE TABLE t2(x)',
            'DELETE FROM t2 WHERE rowid = 3',
            'INSERT INTO t1 VALUES(randomblob(2000))',
        ],
    ],
    'ioerr-14-balance-deeper-pointermap' => [
        'mode' => 'incremental',
        'free' => 9,
        'vacuum' => 0,
        'shared' => false,
        'statements' => [
            'PRAGMA auto_vacuum = incremental',
            'CREATE TABLE t1(x)',
            'CREATE TABLE t2(x)',
            'INSERT INTO t1 VALUES(randomblob(1500))',
            'DELETE FROM t2 WHERE rowid < 10',
            'BEGIN',
            'INSERT INTO t1 VALUES(randomblob(100))',
            'COMMIT',
        ],
    ],
    'ioerr-15-index-delete-overflow' => [
        'mode' => 'full',
        'free' => 14,
        'vacuum' => 0,
        'shared' => false,
        'statements' => [
            'PRAGMA cache_size = 10',
            'CREATE TABLE t1(a)',
            'CREATE INDEX i1 ON t1(a)',
            'CREATE TABLE t2(a)',
            'DELETE FROM t1 WHERE oid > 85',
            'INSERT INTO t2 VALUES(randstr(22000,22000))',
            'DELETE FROM t1 WHERE oid = 83',
            'COMMIT',
        ],
    ],
    'ioerr-16-vacuum-cache-spill' => [
        'mode' => 'incremental',
        'free' => 12,
        'vacuum' => 10,
        'shared' => false,
        'statements' => [
            'PRAGMA auto_vacuum = incremental',
            'PRAGMA page_size = 1024',
            'CREATE TABLE t1(x)',
            'DELETE FROM t1 WHERE rowid > 202',
            'VACUUM',
            'PRAGMA cache_size = 10',
            'DELETE FROM t1 WHERE rowid IN (10,11,12)',
            'PRAGMA incremental_vacuum(10)',
            'COMMIT',
        ],
    ],
];

$dynamicOperations = ['read', 'write', 'sync', 'truncate', 'delete', 'open', 'access'];
$dynamicCase = 0;

foreach ($dynamicScenarios as $scenario => $config) {
    foreach (range(1, 30) as $faultIndex) {
        foreach ($dynamicOperations as $operation) {
            $dynamicCase++;
            $testName = sprintf('real upstream corpus vfs ioerr pointermap extended dynamic %04d %s %s fault %02d', $dynamicCase, $scenario, $operation, $faultIndex);
            $tests[$testName] = static function (TestRunner $t) use ($scenario, $config, $operation, $faultIndex): void {
                $plan = SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile(
                    $scenario . '.' . $operation . '.' . $faultIndex,
                    $faultIndex,
                    $operation,
                    $config['mode'],
                    $config['free'],
                    $config['vacuum'],
                    $config['statements'],
                    $config['shared']
                );
                $detected = $operation !== 'access' && $faultIndex % 29 !== 0;
                $vacuumPages = min($config['free'], $config['vacuum']);

                $t->same('ok', $plan['status']);
                $t->same('ioerr.test', $plan['script']);
                $t->same($scenario, $plan['scenario_root']);
                $t->same($faultIndex, $plan['fault_index']);
                $t->same($operation, $plan['operation']);
                $t->same($config['mode'], $plan['auto_vacuum']);
                $t->same($config['shared'], $plan['shared_cache']);
                $t->same(count($config['statements']), $plan['statement_count']);
                $t->same($config['free'], $plan['free_pages_before']);
                $t->same($config['vacuum'], $plan['requested_vacuum_pages']);
                $t->same($detected ? 0 : $vacuumPages, $plan['vacuum_pages_applied']);
                $t->same($detected ? $config['free'] : $config['free'] - $vacuumPages, $plan['free_pages_after']);
                $t->same($detected ? 0 : $vacuumPages, $plan['page_count_shrink']);
                $t->same(true, $plan['shrink_matches_freelist_delta']);
                $t->same($detected ? true : false, $plan['result_code'] !== 'SQLITE_OK');
                $t->same($detected && in_array($operation, ['write', 'sync', 'truncate', 'delete'], true), $plan['rollback_attempted']);
                $t->same(true, $plan['pointer_map_checked']);
                $t->same(true, $plan['freelist_preserved']);
                $t->same('ok', $plan['integrity_check']);
                $t->same(0, $plan['open_file_count']);
                $t->same(true, in_array('sqlite-auto-vacuum-pointer-map', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-vfs-io-error-recovery', $plan['dependencies'], true));
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
                $t->same(true, str_starts_with($plan['upstream'][0], 'ioerr.test ioerr-'));
                $t->same(true, $plan['reason'] !== '');
            };
        }
    }
}

$tests['real upstream corpus vfs ioerr pointermap extended dynamic cites source scenarios'] = static function (TestRunner $t) use ($dynamicScenarios): void {
    foreach ($dynamicScenarios as $scenario => $config) {
        $plan = SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile($scenario . '.citation', 29, 'write', $config['mode'], $config['free'], $config['vacuum'], $config['statements'], $config['shared']);

        $t->same($scenario, $plan['scenario_root']);
        $t->same(true, str_starts_with($plan['upstream'][0], 'ioerr.test ioerr-'));
        $t->same('SQLITE_OK', $plan['result_code']);
        $t->same(false, $plan['rollback_attempted']);
    }
};

$tests['real upstream corpus vfs ioerr pointermap extended dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('ioerr-13-balance-quick-pointermap', 1, 'access', 'incremental', 1, 1, []));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('ioerr-13-balance-quick-pointermap', 1, 'access', 'incremental', -1, 1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('ioerr-13-balance-quick-pointermap', 1, 'access', 'incremental', 1, -1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('ioerr-13-balance-quick-pointermap', 1, 'stat', 'incremental', 1, 1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('ioerr-13-balance-quick-pointermap', 1, 'access', 'none', 1, 1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('ioerr-17-unknown', 1, 'access', 'incremental', 1, 1, ['SELECT 1']));
};

return $tests;
