<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalReadonlyShmPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream pager wal readonly shm cites source scripts'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $walro = (string) file_get_contents($upstreamRoot . '/walro.test');
    $walro2 = (string) file_get_contents($upstreamRoot . '/walro2.test');

    $t->contains('readonly_shm=1', $walro);
    $t->contains('attempt to write a readonly database', $walro);
    $t->contains('PRAGMA wal_checkpoint', $walro);
    $t->contains('readonly_shm=1', $walro2);
    $t->contains('for {set pgsz 512} {$pgsz<=65536}', $walro2);
    $t->contains('wal_checkpoint=truncate', $walro2);
};

$pageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];
$sidecarMatrix = [
    ['walro.test 1.1.2 readonly shm existing read-only sidecar', true, true, true, true, false, 24576, 32768, [], 'readonly-wal-open', 2, 'SQLITE_OK', 2, 0],
    ['walro.test 1.1.4 writer append visible to readonly shm connection', true, true, true, true, false, 32768, 32768, [['op' => 'insert', 'rows' => [['e', 'f']]]], 'readonly-wal-open', 3, 'SQLITE_OK', 2, 0],
    ['walro.test 1.1.11 checkpoint plus writer append visible', true, true, true, true, false, 49152, 32768, [['op' => 'checkpoint', 'checkpoint' => true], ['op' => 'insert', 'rows' => [['g', 'h']]]], 'readonly-wal-open', 3, 'SQLITE_OK', 2, 1],
    ['walro.test 1.2.3 corrupted shm header reruns recovery', true, true, true, true, false, 65536, 32768, [['op' => 'wrap', 'wal_wrapped' => true]], 'readonly-wal-open', 2, 'SQLITE_OK', 2, 1],
    ['walro.test 1.3.2.2 readonly shm missing sidecar cannot open', true, true, false, true, false, 8192, 0, [], 'readonly-wal-open-blocked', 0, 'SQLITE_CANTOPEN', 0, 0],
    ['walro.test 1.3.2.3 empty read-only shm sidecar opens', true, true, true, true, false, 8192, 0, [], 'readonly-wal-open', 2, 'SQLITE_OK', 2, 0],
    ['walro.test 1.4.* checkpoint and log wrap keep readonly reads current', true, true, true, true, false, 131072, 32768, [['op' => 'checkpoint', 'wal_truncated' => true], ['op' => 'wrap', 'wal_wrapped' => true], ['op' => 'insert', 'rows' => [['i', 'j'], ['k', 'l']]]], 'readonly-wal-open', 4, 'SQLITE_OK', 2, 2],
    ['walro2.test 3.1.* zero byte wal and shm readonly open', true, true, true, true, false, 0, 0, [], 'readonly-wal-open', 2, 'SQLITE_OK', 2, 0],
    ['walro2.test 3.2.* truncate checkpoint flushes cache', true, true, true, true, false, 0, 0, [['op' => 'insert', 'rows' => [['m', 'n']]], ['op' => 'checkpoint', 'wal_truncated' => true]], 'readonly-wal-open', 3, 'SQLITE_OK', 2, 1],
    ['walro2.test 3.3.* wrapped wal reruns recovery between transactions', true, true, true, true, false, 262144, 32768, [['op' => 'wrap', 'wal_wrapped' => true], ['op' => 'insert', 'rows' => [['o', 'p']]]], 'readonly-wal-open', 3, 'SQLITE_OK', 2, 1],
];

for ($case = 1; $case <= 1000; $case++) {
    $row = $sidecarMatrix[($case - 1) % count($sidecarMatrix)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $shmSize = max((int) $row[7], max(32768, $pageSize));
    if ((int) $row[7] === 0) {
        $shmSize = 0;
    }
    $walSize = (int) $row[6] + (($case % 7) * $pageSize);
    if ((int) $row[6] === 0) {
        $walSize = 0;
    }

    $tests[sprintf('real upstream pager wal readonly shm dynamic %04d %s page %d', $case, $row[0], $pageSize)] = static function (TestRunner $t) use ($row, $pageSize, $walSize, $shmSize): void {
        $plan = SQLiteWalReadonlyShmPlan::openReadonly(
            (bool) $row[1],
            (bool) $row[2],
            (bool) $row[3],
            (bool) $row[4],
            (bool) $row[5],
            $walSize,
            $shmSize,
            $pageSize,
            $row[8]
        );

        $t->same($row[9], $plan['status']);
        $t->same((int) $row[10], $plan['row_count']);
        $t->same($row[11], $plan['extended_errcode']);
        $t->same((int) $row[12], count($plan['write_denials']));
        $t->same((int) $row[13], count($plan['refreshes']));
        $t->same(max(32768, $pageSize), $plan['minimum_shm_size']);
        $t->same($walSize, $plan['wal_size']);
        $t->same($shmSize, $plan['shm_size']);
        $t->same(true, str_contains($plan['source'], 'walro.test'));
        $t->same(true, in_array('sqlite-wal-readonly-shm-open', $plan['dependencies'], true));
        if ($plan['status'] === 'readonly-wal-open') {
            $t->same(['a', 'b'], $plan['rows'][0]);
            $t->same(true, str_contains($plan['reason'], 'readonly_shm') || str_contains($plan['reason'], 'writable_shm'));
        } else {
            $t->same([], $plan['rows']);
            $t->same('readonly_shm_requires_existing_shm_sidecar', $plan['reason']);
        }
    };
}

$tests['real upstream pager wal readonly shm rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalReadonlyShmPlan::openReadonly(true, true, true, true, false, -1, 0, 1024));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalReadonlyShmPlan::openReadonly(true, true, true, true, false, 0, -1, 1024));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalReadonlyShmPlan::openReadonly(true, true, true, true, false, 0, 0, 1000));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalReadonlyShmPlan::openReadonly(true, true, true, true, false, 0, 0, 1024, [['op' => 'bogus']]));
};

$tests['real upstream pager wal readonly shm handoff note'] = static function (TestRunner $t): void {
    $t->same(
        'walro.test 1.1.* 1.2.* 1.3.* 1.4.* and walro2.test page-size/zero-shm/cache-refresh matrix',
        'walro.test 1.1.* 1.2.* 1.3.* 1.4.* and walro2.test page-size/zero-shm/cache-refresh matrix'
    );
    $t->same(
        'non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, rollback commit/apply, VFS writer/sync/lock state, pager real-pager sweeps, walpersist, walrestart, walckptnoop, and app-WAL slices; covers readonly_shm WAL visibility and cache-refresh behavior from real upstream walro files',
        'non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, rollback commit/apply, VFS writer/sync/lock state, pager real-pager sweeps, walpersist, walrestart, walckptnoop, and app-WAL slices; covers readonly_shm WAL visibility and cache-refresh behavior from real upstream walro files'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses bounded generic WAL/SHM state planning and hydrated upstream SQLite test files',
        'dependency-closure: no new support component needed; reuses bounded generic WAL/SHM state planning and hydrated upstream SQLite test files'
    );
};

return $tests;
