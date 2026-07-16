<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    'autovacuum-ioerr2-1' => [
        'mode' => 'full',
        'free' => 3,
        'vacuum' => 1,
        'shared' => false,
        'statements' => [
            'PRAGMA auto_vacuum = 1',
            'CREATE TABLE abc(a)',
            'DELETE FROM abc',
            'INSERT INTO abc VALUES(randstr(1500,1500))',
            'CREATE TABLE abc3(a)',
            'COMMIT',
        ],
    ],
    'autovacuum-ioerr2-2' => [
        'mode' => 'full',
        'free' => 12,
        'vacuum' => 5,
        'shared' => false,
        'statements' => [
            'PRAGMA auto_vacuum = 1',
            'PRAGMA cache_size = 10',
            'DELETE FROM abc WHERE length(a)>100',
            'UPDATE abc SET a = randstr(90,90)',
            'CREATE TABLE abc3(a)',
            'COMMIT',
        ],
    ],
    'autovacuum-ioerr2-3' => [
        'mode' => 'full',
        'free' => 4,
        'vacuum' => 2,
        'shared' => false,
        'statements' => [
            'PRAGMA auto_vacuum = 1',
            'CREATE TABLE abc(a)',
            'CREATE TABLE abc2(b)',
            'DROP TABLE abc',
            'COMMIT',
            'DROP TABLE abc2',
        ],
    ],
    'autovacuum-ioerr2-4' => [
        'mode' => 'full',
        'free' => 32,
        'vacuum' => 9,
        'shared' => false,
        'statements' => [
            'PRAGMA auto_vacuum = 1',
            'PRAGMA cache_size = 10',
            'DELETE FROM abc WHERE oid < 3',
            'UPDATE abc SET a = randstr(100,100) WHERE oid > 2300',
            'UPDATE abc SET a = randstr(1100,1100) WHERE oid = (select max(oid) from abc)',
            'COMMIT',
        ],
    ],
    'incrvacuum-ioerr-1' => [
        'mode' => 'incremental',
        'free' => 5,
        'vacuum' => 1,
        'shared' => false,
        'statements' => [
            "PRAGMA auto_vacuum = 'incremental'",
            'CREATE TABLE abc(a)',
            'CREATE TABLE abc2(a)',
            'DELETE FROM abc',
            'PRAGMA incremental_vacuum',
            'COMMIT',
        ],
    ],
    'incrvacuum-ioerr-2' => [
        'mode' => 'full',
        'free' => 25,
        'vacuum' => 7,
        'shared' => false,
        'statements' => [
            "PRAGMA auto_vacuum = 'full'",
            'PRAGMA incremental_vacuum',
            'DELETE FROM abc WHERE (oid%3)==0',
            'INSERT INTO abc SELECT a || "1234567890" FROM abc WHERE oid%2',
            'CREATE INDEX abc_i ON abc(a)',
            'DROP INDEX abc_i',
            'COMMIT',
        ],
    ],
    'incrvacuum-ioerr-3' => [
        'mode' => 'incremental',
        'free' => 8,
        'vacuum' => 5,
        'shared' => false,
        'statements' => [
            "PRAGMA auto_vacuum = 'incremental'",
            'CREATE TABLE a(i integer, b blob)',
            'DELETE FROM a WHERE oid',
            'PRAGMA incremental_vacuum(5)',
        ],
    ],
    'incrvacuum-ioerr-4' => [
        'mode' => 'incremental',
        'free' => 20,
        'vacuum' => 5,
        'shared' => true,
        'statements' => [
            'PRAGMA page_size = 1024',
            'PRAGMA locking_mode = exclusive',
            "PRAGMA auto_vacuum = 'incremental'",
            'DELETE FROM a WHERE oid',
            'PRAGMA incremental_vacuum(5)',
        ],
    ],
];

$operations = ['read', 'write', 'sync', 'truncate', 'delete'];
$case = 0;
foreach ($scenarios as $scenario => $config) {
    foreach (range(1, 30) as $faultIndex) {
        foreach ($operations as $operation) {
            $case++;
            $testName = sprintf('real upstream corpus vfs autovacuum ioerr dynamic %04d %s %s fault %02d', $case, $scenario, $operation, $faultIndex);
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
                $detected = $faultIndex % 29 !== 0;

                $t->same('ok', $plan['status']);
                $t->same($scenario, $plan['scenario_root']);
                $t->same($faultIndex, $plan['fault_index']);
                $t->same($operation, $plan['operation']);
                $t->same($config['mode'], $plan['auto_vacuum']);
                $t->same($config['shared'], $plan['shared_cache']);
                $t->same(count($config['statements']), $plan['statement_count']);
                $t->same($config['free'], $plan['free_pages_before']);
                $t->same($config['vacuum'], $plan['requested_vacuum_pages']);
                $t->same($detected ? 0 : min($config['free'], $config['vacuum']), $plan['vacuum_pages_applied']);
                $t->same($detected ? $config['free'] : $config['free'] - min($config['free'], $config['vacuum']), $plan['free_pages_after']);
                $t->same(!$detected ? min($config['free'], $config['vacuum']) : 0, $plan['page_count_shrink']);
                $t->same(true, $plan['shrink_matches_freelist_delta']);
                $t->same($detected ? true : false, $plan['result_code'] !== 'SQLITE_OK');
                $t->same($detected && in_array($operation, ['write', 'sync', 'truncate', 'delete'], true), $plan['rollback_attempted']);
                $t->same(true, $plan['pointer_map_checked']);
                $t->same(true, $plan['freelist_preserved']);
                $t->same('ok', $plan['integrity_check']);
                $t->same(0, $plan['open_file_count']);
                $t->same(true, in_array('sqlite-auto-vacuum-pointer-map', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-vfs-io-error-recovery', $plan['dependencies'], true));
                $t->same(true, str_starts_with($plan['upstream'][0], $plan['script'] . ' ' . $scenario));
            };
        }
    }
}

$tests['real upstream corpus vfs autovacuum ioerr dynamic records source scenarios'] = static function (TestRunner $t) use ($scenarios): void {
    foreach (array_keys($scenarios) as $scenario) {
        $config = $scenarios[$scenario];
        $plan = SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile($scenario . '.citation', 29, 'write', $config['mode'], $config['free'], $config['vacuum'], $config['statements'], $config['shared']);

        $t->same([$plan['script'] . ' ' . $scenario], $plan['upstream']);
        $t->same('SQLITE_OK', $plan['result_code']);
        $t->same(false, $plan['rollback_attempted']);
        $t->same(min($config['free'], $config['vacuum']), $plan['page_count_shrink']);
        $t->same(true, $plan['reason'] !== '');
    }
};

$tests['real upstream corpus vfs autovacuum ioerr dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('', 1, 'write', 'full', 1, 1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('incrvacuum-ioerr-1', 0, 'write', 'full', 1, 1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('incrvacuum-ioerr-1', 1, 'xWrite', 'full', 1, 1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('incrvacuum-ioerr-1', 1, 'write', 'none', 1, 1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('incrvacuum-ioerr-1', 1, 'write', 'full', -1, 1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('incrvacuum-ioerr-1', 1, 'write', 'full', 1, -1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('incrvacuum-ioerr-1', 1, 'write', 'full', 1, 1, []));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile('unknown-ioerr', 1, 'write', 'full', 1, 1, ['SELECT 1']));
};

return $tests;
