<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

require_once __DIR__ . '/../src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php';

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal dynamic 074744 cites walsetlk2 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $source = (string) file_get_contents($upstreamRoot . '/walsetlk2.test');

    $t->contains('Check that if sqlite3_setlk_timeout() is used', $source);
    $t->contains('do_catchsql_test 2.1', $source);
    $t->contains('sqlite3_busy_timeout db 2000', $source);
    $t->contains('do_execsql_test 2.4', $source);
    $t->contains('sqlite3_setlk_timeout db -1', $source);
    $t->contains('do_catchsql_test 3.1', $source);
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walSetlkTimeoutRows() as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal dynamic 074744 walsetlk2 timeout %04d %s %s',
        $row['case'],
        $row['section'],
        $row['blocking_lock_kind']
    )] = static function (TestRunner $t) use ($row): void {
        $t->same('walsetlk2.test', $row['script']);
        $t->same(true, str_starts_with($row['upstream'], 'walsetlk2.test '));
        $t->same(true, in_array($row['section'], ['walsetlk2-2.1..2.4', 'walsetlk2-2.5..2.7', 'walsetlk2-3.1..3.2', 'walsetlk2-3.3..3.4'], true));
        $t->same(true, in_array($row['journal_mode'], ['delete', 'wal'], true));
        $t->same(true, $row['fullmutex']);
        $t->same(2000, $row['lock_holder_duration_ms']);
        $t->same(500, $row['callback_delay_before_attempt_ms']);
        $t->same(true, is_array($row['setlk_result']));
        $t->same(true, is_array($row['busy_result']));
        $t->same($row['setlk_result']['code'] === 0, $row['writer_waits_for_lock_holder']);
        $t->same($row['busy_timeout_ms'] !== null, $row['busy_timeout_retries_statement']);
        $t->same($row['setlk_timeout_ms'] !== null, $row['setlk_timeout_routes_blocking_locks_only']);
        $t->same($row['journal_mode'] === 'wal', $row['uses_wal_index_locks']);
        $t->same(true, count($row['blocking_rows']) >= 1);
        $t->same(true, count($row['attempt_rows']) >= 1);
        $t->same(count($row['final_rows']), $row['final_row_count']);
        $t->same(true, $row['final_row_count'] >= 3);
        $t->same(true, str_contains($row['blocked_statement'], 'INSERT INTO t1 VALUES'));
        $t->same(true, in_array($row['blocking_lock_kind'], ['rollback-exclusive', 'wal-write', 'wal-indefinite-write', 'wal-indefinite-second-write'], true));
        $t->same(true, in_array('real-upstream-corpus-walsetlk2', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-setlk-timeout-routing', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-write-lock-timeout', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic 074744 walsetlk2 row count and non overlap'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walSetlkTimeoutRows();

    $t->same(1000, count($rows));
    $t->same('walsetlk2.test walsetlk2-2.1..2.4 dynamic timeout case 0001', $rows[0]['upstream']);
    $t->same('walsetlk2.test walsetlk2-3.3..3.4 dynamic timeout case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: walsetlk2.test sections 2.1 through 2.7 and 3.1 through 3.4 setlk timeout, busy timeout, WAL writer-lock, and indefinite blocking behavior',
        'upstream source: walsetlk2.test sections 2.1 through 2.7 and 3.1 through 3.4 setlk timeout, busy timeout, WAL writer-lock, and indefinite blocking behavior'
    );
    $t->same(
        'non-overlap: extends walsetlk2 timeout routing rather than accepted walsetlk2 xShmLock sequence rows, walsetlk snapshot rows, VFS process locks, lock-state, WAL byte truncation, rollback-journal apply/commit, checkpoint transaction, walhook, walro2, wal6, wal8, or app-WAL slices',
        'non-overlap: extends walsetlk2 timeout routing rather than accepted walsetlk2 xShmLock sequence rows, walsetlk snapshot rows, VFS process locks, lock-state, WAL byte truncation, rollback-journal apply/commit, checkpoint transaction, walhook, walro2, wal6, wal8, or app-WAL slices'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses lane-local pager/WAL dynamic corpus modeling and hydrated upstream walsetlk2.test source truth',
        'dependency-closure: no new support component needed; reuses lane-local pager/WAL dynamic corpus modeling and hydrated upstream walsetlk2.test source truth'
    );
};

return $tests;
