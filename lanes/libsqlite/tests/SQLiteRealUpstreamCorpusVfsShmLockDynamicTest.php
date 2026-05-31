<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmLockMatrixPlan;

$tests = [];

$tests['real upstream corpus vfs shmlock scripted matrix ports shmlock 1.3'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsShmLockMatrixPlan::run('shmlock-1.3', SQLiteVfsShmLockMatrixPlan::upstreamScriptedOperations());
    $expected = [
        'SQLITE_OK',
        'SQLITE_BUSY',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_BUSY',
        'SQLITE_BUSY',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_BUSY',
        'SQLITE_OK',
        'SQLITE_BUSY',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_BUSY',
        'SQLITE_OK',
        'SQLITE_BUSY',
        'SQLITE_BUSY',
        'SQLITE_BUSY',
        'SQLITE_OK',
        'SQLITE_BUSY',
        'SQLITE_OK',
        'SQLITE_OK',
        'SQLITE_OK',
    ];

    $t->same('shmlock.test', $plan['script']);
    $t->same('shmlock-1.3', $plan['scenario']);
    $t->same($expected, array_column($plan['events'], 'result'));
    $t->same(10, $plan['busy_count']);
    $t->same(22, $plan['ok_count']);
    $t->same(array_fill(0, 8, 'none'), $plan['final_slots']);
    $t->same(true, in_array('sqlite-upstream-shmlock-test', $plan['dependencies'], true));
    $t->same(true, in_array('sqlite-vfs-shm-byte-range-locks', $plan['dependencies'], true));
    $t->same(true, in_array('sqlite-wal-index-locking', $plan['dependencies'], true));
};

$tests['real upstream corpus vfs shmlock shared reader limit ports shmlock 2'] = static function (TestRunner $t): void {
    $operations = [];
    for ($i = 0; $i < 255; $i++) {
        $operations[] = ['connection' => 'reader' . $i, 'mode' => 'shared', 'action' => 'lock', 'offset' => 4, 'count' => 1];
    }
    $operations[] = ['connection' => 'reader255', 'mode' => 'shared', 'action' => 'lock', 'offset' => 4, 'count' => 1];
    $operations[] = ['connection' => 'reader255', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 4, 'count' => 1];
    $operations[] = ['connection' => 'reader0', 'mode' => 'shared', 'action' => 'unlock', 'offset' => 4, 'count' => 1];
    $operations[] = ['connection' => 'reader255', 'mode' => 'shared', 'action' => 'lock', 'offset' => 4, 'count' => 1];
    $operations[] = ['connection' => 'reader255', 'mode' => 'shared', 'action' => 'unlock', 'offset' => 4, 'count' => 1];
    $operations[] = ['connection' => 'reader255', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 4, 'count' => 1];
    for ($i = 1; $i < 255; $i++) {
        $operations[] = ['connection' => 'reader' . $i, 'mode' => 'shared', 'action' => 'unlock', 'offset' => 4, 'count' => 1];
    }
    $operations[] = ['connection' => 'reader255', 'mode' => 'exclusive', 'action' => 'lock', 'offset' => 4, 'count' => 1];
    $operations[] = ['connection' => 'reader255', 'mode' => 'exclusive', 'action' => 'unlock', 'offset' => 4, 'count' => 1];

    $plan = SQLiteVfsShmLockMatrixPlan::run('shmlock-2.0-2.6', $operations);

    $t->same('SQLITE_BUSY', $plan['events'][255]['result']);
    $t->same(['shared-limit:4'], $plan['events'][255]['blocking']);
    $t->same('SQLITE_BUSY', $plan['events'][256]['result']);
    $t->same('SQLITE_OK', $plan['events'][257]['result']);
    $t->same('SQLITE_OK', $plan['events'][258]['result']);
    $t->same('SQLITE_OK', $plan['events'][259]['result']);
    $t->same('SQLITE_BUSY', $plan['events'][260]['result']);
    $t->same('SQLITE_OK', $plan['events'][515]['result']);
    $t->same(array_fill(0, 8, 'none'), $plan['final_slots']);
    $t->same(3, $plan['busy_count']);
};

$randomOperations = SQLiteVfsShmLockMatrixPlan::deterministicRandomOperations(4200, 20181206);
$case = 0;
foreach (array_chunk($randomOperations, 4) as $chunk) {
    ++$case;
    $tests[sprintf('real upstream corpus vfs shmlock randomized conflict oracle shmlock-3 chunk %04d', $case)] = static function (TestRunner $t) use ($chunk, $case): void {
        $plan = SQLiteVfsShmLockMatrixPlan::run('shmlock-3.random.' . $case, $chunk);

        $t->same('shmlock.test', $plan['script']);
        $t->same('shmlock-3.random.' . $case, $plan['scenario']);
        $t->same(count($chunk), count($plan['events']));
        $t->same($plan['ok_count'] + $plan['busy_count'], count($chunk));
        foreach ($plan['events'] as $event) {
            $t->same(true, in_array($event['result'], ['SQLITE_OK', 'SQLITE_BUSY'], true));
            $t->same(true, $event['offset'] >= 0 && ($event['offset'] + $event['count']) <= 8);
            if ($event['result'] === 'SQLITE_BUSY') {
                $t->same(true, $event['blocking'] !== []);
            }
        }
        $t->same(8, count($plan['final_slots']));
        $t->same(true, in_array('sqlite-upstream-shmlock-test', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs shmlock rejects malformed operations'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsShmLockMatrixPlan::run('', []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsShmLockMatrixPlan::run('bad', [['connection' => '../bad', 'mode' => 'shared', 'action' => 'lock', 'offset' => 0, 'count' => 1]]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsShmLockMatrixPlan::run('bad', [['connection' => 'db', 'mode' => 'bad', 'action' => 'lock', 'offset' => 0, 'count' => 1]]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsShmLockMatrixPlan::run('bad', [['connection' => 'db', 'mode' => 'shared', 'action' => 'bad', 'offset' => 0, 'count' => 1]]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsShmLockMatrixPlan::run('bad', [['connection' => 'db', 'mode' => 'shared', 'action' => 'lock', 'offset' => 8, 'count' => 1]]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsShmLockMatrixPlan::run('bad', [['connection' => 'db', 'mode' => 'shared', 'action' => 'lock', 'offset' => 7, 'count' => 2]]));
};

return $tests;
