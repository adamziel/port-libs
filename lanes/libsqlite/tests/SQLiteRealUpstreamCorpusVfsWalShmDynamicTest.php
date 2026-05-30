<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$expectations = [
    'walvfs-4' => [
        'status' => 'error',
        'result' => 'attempt to write a readonly database',
        'error' => 'SQLITE_READONLY',
        'readmarks' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
        'upstream' => ['walvfs.test 4.0', 'walvfs.test 4.1', 'walvfs.test 4.2'],
    ],
    'walvfs-5-ok' => [
        'status' => 'ok',
        'result' => '20',
        'error' => null,
        'readmarks' => [1 => 24, 2 => 100, 3 => 100, 4 => 100],
        'recoverable' => false,
        'upstream' => ['walvfs.test 5.2', 'walvfs.test 5.3', 'walvfs.test 5.4', 'walvfs.test 5.5', 'walvfs.test 5.6'],
    ],
    'walvfs-5-readonly' => [
        'status' => 'error',
        'result' => 'attempt to write a readonly database',
        'error' => 'SQLITE_READONLY',
        'readmarks' => [1 => 100, 2 => 100, 3 => 100, 4 => 100],
        'recoverable' => true,
        'upstream' => ['walvfs.test 5.2', 'walvfs.test 5.3', 'walvfs.test 5.4', 'walvfs.test 5.5', 'walvfs.test 5.6'],
    ],
    'walvfs-6' => [
        'status' => 'error',
        'result' => 'locking protocol',
        'error' => 'SQLITE_PROTOCOL',
        'retry' => 12,
        'upstream' => ['walvfs.test 6.1', 'walvfs.test 6.2'],
    ],
    'walvfs-7' => [
        'status' => 'ok',
        'result' => 'checkpoint busy',
        'error' => 'SQLITE_BUSY',
        'checkpoint' => ['busy' => 1, 'log' => -1, 'checkpointed' => -1],
        'upstream' => ['walvfs.test 7.1'],
    ],
    'walvfs-8' => [
        'status' => 'ok',
        'result' => 'ok',
        'error' => null,
        'visible' => 21,
        'flushed' => true,
        'upstream' => ['walvfs.test 8.2', 'walvfs.test 8.3'],
    ],
    'walvfs-9-readonly' => [
        'status' => 'error',
        'result' => 'disk I/O error',
        'error' => 'SQLITE_READONLY_CANTINIT',
        'upstream' => ['walvfs.test 9.1'],
    ],
    'walvfs-9-ioerr' => [
        'status' => 'error',
        'result' => 'disk I/O error',
        'error' => 'SQLITE_IOERR',
        'upstream' => ['walvfs.test 9.1'],
    ],
];

$case = 0;
foreach (range(0, 169) as $attempt) {
    foreach (['walvfs-4', 'walvfs-6', 'walvfs-7', 'walvfs-8'] as $scenario) {
        $case++;
        $tests[sprintf('real upstream corpus vfs wal shm dynamic %04d %s attempt %03d', $case, $scenario, $attempt)] = static function (TestRunner $t) use ($scenario, $attempt, $expectations): void {
            $profile = SQLiteVfsIoDynamicPlan::walShmFaultProfile($scenario, $attempt);
            $expected = $expectations[$scenario];

            $t->same($expected['status'], $profile['status']);
            $t->same('walvfs.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same('wal', $profile['journal_mode']);
            $t->same(1024, $profile['page_size']);
            $t->same(20, $profile['seed_rows']);
            $t->same($attempt, $profile['busy_attempts']);
            $t->same($expected['result'], $profile['select_result']);
            $t->same($expected['error'], $profile['error']);
            $t->same($expected['upstream'], $profile['upstream']);
            $t->same(true, in_array('upstream-walvfs-shm-readmark-faults', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-wal-shm-locking', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));

            if ($scenario === 'walvfs-4') {
                $t->same($expected['readmarks'], $profile['read_marks']);
            } elseif ($scenario === 'walvfs-6') {
                $t->same($expected['retry'], $profile['protocol_retry_seconds']);
                $t->same(['busy' => 0, 'log' => 5, 'checkpointed' => 5], $profile['checkpoint_result']);
            } elseif ($scenario === 'walvfs-7') {
                $t->same($expected['checkpoint'], $profile['checkpoint_result']);
            } elseif ($scenario === 'walvfs-8') {
                $t->same($expected['flushed'], $profile['cache_flushed_before_checkpoint']);
                $t->same($expected['visible'], $profile['visible_rows_after_checkpoint']);
            }
        };
    }

    foreach ([false, true] as $readonlyShmMap) {
        $case++;
        $name = $readonlyShmMap && $attempt > 0 ? 'walvfs-5-readonly' : 'walvfs-5-ok';
        $tests[sprintf('real upstream corpus vfs wal shm dynamic %04d walvfs-5 readonly %d attempt %03d', $case, $readonlyShmMap ? 1 : 0, $attempt)] = static function (TestRunner $t) use ($attempt, $readonlyShmMap, $name, $expectations): void {
            $profile = SQLiteVfsIoDynamicPlan::walShmFaultProfile('walvfs-5', $attempt, $readonlyShmMap);
            $expected = $expectations[$name];

            $t->same($expected['status'], $profile['status']);
            $t->same('walvfs-5', $profile['scenario']);
            $t->same($attempt, $profile['busy_attempts']);
            $t->same($readonlyShmMap, $profile['readonly_shm_map']);
            $t->same($expected['result'], $profile['select_result']);
            $t->same($expected['error'], $profile['error']);
            $t->same($expected['readmarks'], $profile['read_marks']);
            $t->same($expected['recoverable'], $profile['recoverable_after_readmark_reset']);
            $t->same($expected['upstream'], $profile['upstream']);
            $t->same(true, in_array('walvfs.test 5.6', $profile['upstream'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

foreach (range(0, 149) as $attempt) {
    foreach ([false, true] as $ioerrDuringSharedLock) {
        $case++;
        $name = $ioerrDuringSharedLock ? 'walvfs-9-ioerr' : 'walvfs-9-readonly';
        $tests[sprintf('real upstream corpus vfs wal shm dynamic %04d walvfs-9 ioerr %d attempt %03d', $case, $ioerrDuringSharedLock ? 1 : 0, $attempt)] = static function (TestRunner $t) use ($attempt, $ioerrDuringSharedLock, $name, $expectations): void {
            $profile = SQLiteVfsIoDynamicPlan::walShmFaultProfile('walvfs-9', $attempt, false, $ioerrDuringSharedLock);
            $expected = $expectations[$name];

            $t->same($expected['status'], $profile['status']);
            $t->same('walvfs-9', $profile['scenario']);
            $t->same($attempt, $profile['busy_attempts']);
            $t->same($ioerrDuringSharedLock, $profile['ioerr_during_shared_lock']);
            $t->same($expected['result'], $profile['select_result']);
            $t->same($expected['error'], $profile['error']);
            $t->same($expected['upstream'], $profile['upstream']);
            $t->same(true, in_array('walvfs.test 9.1', $profile['upstream'], true));
            $t->same(true, in_array('upstream-walvfs-shm-readmark-faults', $profile['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs wal shm dynamic rejects unsupported scenario'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::walShmFaultProfile('walvfs-10'));
};

$tests['real upstream corpus vfs wal shm dynamic rejects negative busy attempts'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::walShmFaultProfile('walvfs-5', -1));
};

return $tests;
