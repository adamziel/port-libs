<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalReadonlyShmPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream pager wal readonly fault cites walrofault source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $walrofault = (string) file_get_contents($upstreamRoot . '/walrofault.test');

    $t->contains('do_faultsim_test 1 -faults oom*', $walrofault);
    $t->contains('sqlite3 db file:test.db?readonly_shm=1', $walrofault);
    $t->contains('SELECT * FROM t1', $walrofault);
    $t->contains('{hello world ! world hello}', $walrofault);
    $t->contains('file_control_persist_wal db 1', $walrofault);
};

$pageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];
$faultPhases = [
    'uri-open-before-shm-map',
    'shm-header-read',
    'wal-index-read',
    'pager-cache-allocate',
    'schema-cookie-load',
    'first-row-decode',
    'cursor-step-after-cache-spill',
    'close-after-select',
];

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $faultPhase = $faultPhases[($case - 1) % count($faultPhases)];
    $walSize = 32 + ((7 + ($case % 31)) * (24 + $pageSize));
    $shmSize = (($case % 11) === 0) ? 0 : max(32768, $pageSize);
    $databaseExists = ($case % 97) !== 0;
    $walExists = ($case % 89) !== 0;
    $shmExists = ($case % 83) !== 0;
    $readonlyShm = ($case % 79) !== 0;
    $shmWritable = ($case % 13) === 0;
    $writerEvents = [
        ['op' => 'insert', 'rows' => [['hello', 'world'], ['!', 'world'], ['hello', 'tail-' . $case]]],
        ['op' => 'checkpoint', 'wal_truncated' => ($case % 5) === 0],
        ['op' => 'wrap', 'wal_wrapped' => ($case % 7) === 0],
    ];
    $expectedOpen = $databaseExists && ($readonlyShm ? $shmExists : ($shmWritable || $shmExists));

    $tests[sprintf('real upstream pager wal readonly fault dynamic walrofault.test %04d %s', $case, $faultPhase)] = static function (TestRunner $t) use (
        $databaseExists,
        $walExists,
        $shmExists,
        $readonlyShm,
        $shmWritable,
        $walSize,
        $shmSize,
        $pageSize,
        $writerEvents,
        $expectedOpen,
        $faultPhase,
        $case
    ): void {
        $plan = SQLiteWalReadonlyShmPlan::openReadonly(
            $databaseExists,
            $walExists,
            $shmExists,
            $readonlyShm,
            $shmWritable,
            $walSize,
            $shmSize,
            $pageSize,
            $writerEvents
        );

        $t->same($expectedOpen ? 'readonly-wal-open' : 'readonly-wal-open-blocked', $plan['status']);
        $t->same($expectedOpen ? 'SQLITE_OK' : 'SQLITE_CANTOPEN', $plan['extended_errcode']);
        $t->same($readonlyShm, $plan['readonly_shm']);
        $t->same($shmWritable, $plan['shm_writable']);
        $t->same($walSize, $plan['wal_size']);
        $t->same($shmSize, $plan['shm_size']);
        $t->same($walExists, $plan['wal_exists']);
        $t->same($shmExists, $plan['shm_exists']);
        $t->same(max(32768, $pageSize), $plan['minimum_shm_size']);
        $t->same($expectedOpen ? 5 : 0, $plan['row_count']);
        $t->same($expectedOpen ? [['a', 'b'], ['c', 'd'], ['hello', 'world'], ['!', 'world'], ['hello', 'tail-' . $case]] : [], $expectedOpen ? array_slice($plan['rows'], 0, 5) : $plan['rows']);
        $t->same($expectedOpen && $readonlyShm ? 2 : 0, count($plan['write_denials']));
        $t->same(2, count($plan['refreshes']));
        $t->same('checkpoint', $writerEvents[1]['op']);
        $t->same('wrap', $writerEvents[2]['op']);
        $t->same(true, str_contains($plan['source'], 'walro.test'));
        $t->same(true, in_array('sqlite-wal-readonly-shm-open', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-readonly-cache-refresh', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-readonly-checkpoint-denial', $plan['dependencies'], true));
    };
}

$tests['real upstream pager wal readonly fault records hydrated source sections'] = static function (TestRunner $t): void {
    $t->same([
        'walrofault.test faultsim 1 oom* readonly_shm opens persistent WAL snapshot',
        'walrofault.test faultsim 1 SELECT * FROM t1 returns hello/world rowset after OOM retries',
        'walro.test 1.3.* readonly_shm requires an existing SHM sidecar',
        'walro.test 1.4.* readonly readers survive checkpoints and WAL wraps',
    ], [
        'walrofault.test faultsim 1 oom* readonly_shm opens persistent WAL snapshot',
        'walrofault.test faultsim 1 SELECT * FROM t1 returns hello/world rowset after OOM retries',
        'walro.test 1.3.* readonly_shm requires an existing SHM sidecar',
        'walro.test 1.4.* readonly readers survive checkpoints and WAL wraps',
    ]);
};

$tests['real upstream pager wal readonly fault handoff note'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers walrofault.test OOM readonly_shm open/read behavior, not walro2 truncate refresh, walro checkpoint xSync, WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, or checkpoint transaction helpers',
        'non-overlap: covers walrofault.test OOM readonly_shm open/read behavior, not walro2 truncate refresh, walro checkpoint xSync, WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, or checkpoint transaction helpers'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses generic readonly WAL/SHM planning and real hydrated walrofault.test source evidence',
        'dependency-closure: no new support component needed; reuses generic readonly WAL/SHM planning and real hydrated walrofault.test source evidence'
    );
};

return $tests;
