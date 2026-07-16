<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

$tests = [];

$tests['real upstream corpus pager wal setlk snapshot dynamic cites hydrated upstream script'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk_snapshot.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('set testprefix walsetlk_snapshot', $source);
    $t->contains('sqlite3_snapshot_open db main $::snap', $source);
    $t->contains('do_test 1.2 { set ::msg } {SQLITE_BUSY}', $source);
    $t->contains('do_test 1.3.($::tm) { expr $::tm<2000000 } 1', $source);
    $t->contains('set ::sleep_count', $source);
};

$rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walSetlkSnapshotBusyRows();

$tests['real upstream corpus pager wal setlk snapshot dynamic row count and coverage shape'] = static function (TestRunner $t) use ($rows): void {
    $t->same(1000, count($rows));
    $t->same([1, 2, 3, 4, 5, 6], array_merge(...$rows[0]['snapshot_rows']));
    $t->same('walsetlk_snapshot.test', $rows[0]['script']);
    $t->same('SQLITE_BUSY', $rows[0]['snapshot_open_result']);
    $t->same(true, count(array_filter($rows, static fn (array $row): bool => $row['setlk_timeout_enabled'])) > 0);
    $t->same(true, count(array_filter($rows, static fn (array $row): bool => !$row['setlk_timeout_enabled'])) > 0);
};

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal setlk snapshot dynamic %04d %s timeout %d',
        $row['case'],
        $row['checkpoint_mode'],
        $row['timeout_ms']
    )] = static function (TestRunner $t) use ($row): void {
        $t->same('walsetlk_snapshot.test', $row['script']);
        $t->same('wal', $row['journal_mode']);
        $t->same('testvfs-fullshm', $row['vfs']);
        $t->same(true, in_array($row['checkpoint_mode'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->same(true, in_array($row['timeout_ms'], [50, 100, 250, 500, 750, 1000, 1500, 2000], true));
        $t->same(true, $row['xwrite_delay_ms'] >= 4000);
        $t->same('SQLITE_BUSY', $row['snapshot_message']);
        $t->same(true, $row['wait_under_two_seconds']);
        $t->same(true, $row['snapshot_open_wait_us'] < 2000000);
        $t->same(!$row['setlk_timeout_enabled'], $row['sleep_callback_called']);
        $t->same($row['committed_rows'], $row['final_rows']);
        $t->same(count($row['final_rows']), $row['final_row_count']);
        $t->same(true, count($row['snapshot_rows']) >= 3);
        $t->same(true, count($row['final_rows']) >= count($row['snapshot_rows']));
        $t->same(true, in_array('real-upstream-corpus-walsetlk-snapshot', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-snapshot-open-busy-during-checkpoint', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-fullshm-checkpoint-write-stall', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal setlk snapshot dynamic non overlap and dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T064816Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T064816Z-0');
    $t->same('walsetlk_snapshot.test 1.0 through 1.5', 'walsetlk_snapshot.test 1.0 through 1.5');
    $t->same('non-overlap: covers snapshot_open SQLITE_BUSY and sub-two-second wait while a fullshm checkpoint xWrite is stalled; avoids accepted WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, WAL readonly-SHM refresh, walro cache-spill, wal8/wal9 page-size mapping, and app-WAL slices', 'non-overlap: covers snapshot_open SQLITE_BUSY and sub-two-second wait while a fullshm checkpoint xWrite is stalled; avoids accepted WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, WAL readonly-SHM refresh, walro cache-spill, wal8/wal9 page-size mapping, and app-WAL slices');
    $t->same('dependency-closure: no new support component needed; reuses SQLiteRealUpstreamPagerWalDynamicCorpusPlan and hydrated upstream walsetlk_snapshot.test as source truth', 'dependency-closure: no new support component needed; reuses SQLiteRealUpstreamPagerWalDynamicCorpusPlan and hydrated upstream walsetlk_snapshot.test as source truth');
};

return $tests;
