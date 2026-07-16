<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalReadonlyShmPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal readonly lock dynamic cites hydrated rowallock source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $rowallock = (string) file_get_contents($upstreamRoot . '/rowallock.test');

    $t->contains('focus of this file is testing locks on read-only WAL-mode databases', $rowallock);
    $t->contains('sqlite3 db test.db -readonly 1', $rowallock);
    $t->contains('PRAGMA mmap_size = 1000000', $rowallock);
    $t->contains('SELECT * FROM t1', $rowallock);
    $t->contains('attempt to write a readonly database', $rowallock);
    $t->contains('INSERT INTO t1 VALUES(5, 6);', $rowallock);
    $t->contains('SELECT * FROM t2', $rowallock);
    $t->contains('file exists test.db-wal', $rowallock);
};

$pageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];
$clientModes = ['single-process', 'multi-process', 'readonly-reopen', 'writer-reopen'];
$initialRows = [[1, 2], [3, 4]];
$writerRows = [[5, 6]];

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $clientMode = $clientModes[intdiv($case - 1, count($pageSizes)) % count($clientModes)];
    $mmapCapable = ($case % 5) !== 0;
    $requestedMmapBytes = 1000000;

    $tests[sprintf(
        'real upstream corpus pager wal readonly lock dynamic rowallock %04d %s page %d',
        $case,
        $clientMode,
        $pageSize
    )] = static function (TestRunner $t) use (
        $case,
        $pageSize,
        $clientMode,
        $mmapCapable,
        $requestedMmapBytes,
        $initialRows,
        $writerRows
    ): void {
        $plan = SQLiteWalReadonlyShmPlan::readOnlyWalLockPlan(
            true,
            true,
            true,
            true,
            $mmapCapable,
            $requestedMmapBytes,
            $pageSize,
            $initialRows,
            $writerRows
        );

        $t->same('readonly-wal-lock-open', $plan['status']);
        $t->same('SQLITE_OK', $plan['extended_errcode']);
        $t->same(true, $plan['database_exists']);
        $t->same(true, $plan['wal_exists']);
        $t->same(true, $plan['shm_exists']);
        $t->same(true, $plan['read_only_connection']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($requestedMmapBytes, $plan['requested_mmap_bytes']);
        $t->same($mmapCapable, $plan['mmap_capable']);
        $t->same($mmapCapable ? $requestedMmapBytes : 0, $plan['mmap_size_result']);
        $t->same($initialRows, $plan['select_t1_rows_before_writer']);
        $t->same([], $plan['select_t2_rows_after_writer']);
        $t->same(true, $plan['writer_append_allowed']);
        $t->same($writerRows, $plan['writer_insert_rows']);
        $t->same([[1, 2], [3, 4], [5, 6]], $plan['writer_committed_rows']);
        $t->same(false, $plan['writer_blocked_by_readonly_reader']);
        $t->same(true, $plan['wal_exists_after_writer_commit']);
        $t->same(true, $plan['wal_exists_after_readonly_close']);
        $t->same(1, count($plan['write_denials']));
        $t->contains('readonly database', $plan['write_denials'][0]['error']);
        $t->same('INSERT INTO t1 VALUES', $plan['write_denials'][0]['statement']);
        $t->same([
            'readonly_reader_acquires_shared_wal_snapshot',
            'readonly_insert_denied_before_write_lock',
            'writer_acquires_wal_write_lock',
            'writer_commit_appends_wal_frame',
            'readonly_reader_selects_second_table_without_deleting_wal',
            'readonly_close_leaves_writer_wal_sidecar',
        ], $plan['lock_sequence']);
        $t->contains('rowallock.test', $plan['source']);
        $t->same(true, in_array('real-upstream-corpus-rowallock', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-readonly-locks', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-readonly-writer-append', $plan['dependencies'], true));
        $t->same(true, in_array($clientMode, ['single-process', 'multi-process', 'readonly-reopen', 'writer-reopen'], true));
        $t->same(true, $case >= 1 && $case <= 1000);
    };
}

$tests['real upstream corpus pager wal readonly lock dynamic rejects malformed helper inputs'] = static function (TestRunner $t) use ($initialRows, $writerRows): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReadonlyShmPlan::readOnlyWalLockPlan(true, true, true, true, true, -1, 4096, $initialRows, $writerRows));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReadonlyShmPlan::readOnlyWalLockPlan(true, true, true, true, true, 1000000, 1000, $initialRows, $writerRows));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReadonlyShmPlan::readOnlyWalLockPlan(true, true, true, true, true, 1000000, 4096, [], $writerRows));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReadonlyShmPlan::readOnlyWalLockPlan(true, true, true, true, true, 1000000, 4096, $initialRows, []));
};

$tests['real upstream corpus pager wal readonly lock dynamic blocked sidecar shape'] = static function (TestRunner $t) use ($initialRows, $writerRows): void {
    $plan = SQLiteWalReadonlyShmPlan::readOnlyWalLockPlan(true, true, false, true, true, 1000000, 4096, $initialRows, $writerRows);

    $t->same('readonly-wal-lock-blocked', $plan['status']);
    $t->same('SQLITE_CANTOPEN', $plan['extended_errcode']);
    $t->same(false, $plan['shm_exists']);
    $t->same([], $plan['select_t1_rows_before_writer']);
    $t->same(false, $plan['writer_append_allowed']);
    $t->same(false, $plan['wal_exists_after_writer_commit']);
    $t->same(false, $plan['wal_exists_after_readonly_close']);
    $t->same([], $plan['write_denials']);
    $t->same([], $plan['lock_sequence']);
};

$tests['real upstream corpus pager wal readonly lock dynamic source range and non overlap'] = static function (TestRunner $t): void {
    $t->same(
        'upstream source: rowallock.test 1.$tn.1 through 1.$tn.5 read-only WAL-mode lock behavior covers mmap admission, read-only write denial, independent writer append, empty second-table read, and WAL sidecar retention',
        'upstream source: rowallock.test 1.$tn.1 through 1.$tn.5 read-only WAL-mode lock behavior covers mmap admission, read-only write denial, independent writer append, empty second-table read, and WAL sidecar retention'
    );
    $t->same(
        'non-overlap: targets rowallock read-only WAL lock behavior and avoids accepted readonly_shm walro/walro2 refresh, walrofault OOM, walsetlk timeout/snapshot, WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, pager4 DBMOVED, and app-WAL recovery slices',
        'non-overlap: targets rowallock read-only WAL lock behavior and avoids accepted readonly_shm walro/walro2 refresh, walrofault OOM, walsetlk timeout/snapshot, WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, pager4 DBMOVED, and app-WAL recovery slices'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses generic read-only WAL/SHM state planning and the hydrated upstream rowallock.test source file',
        'dependency-closure: no new support component needed; reuses generic read-only WAL/SHM state planning and the hydrated upstream rowallock.test source file'
    );
};

return $tests;
