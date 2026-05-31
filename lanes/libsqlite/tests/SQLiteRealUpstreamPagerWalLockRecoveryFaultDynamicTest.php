<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

$sourceFiles = [
    'rowallock.test' => '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowallock.test',
    'walsetlk3.test' => '/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk3.test',
    'walsetlk_recover.test' => '/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk_recover.test',
    'walseh1.test' => '/home/claude/port-libs/.upstream-cache/libsqlite/test/walseh1.test',
];

$tests['real upstream pager wal lock recovery fault dynamic cites hydrated upstream sources'] = static function (TestRunner $t) use ($sourceFiles): void {
    foreach ($sourceFiles as $script => $path) {
        $t->same(true, is_file($path));
        $text = file_get_contents($path);
        $t->same(true, is_string($text) && str_contains($text, 'set testprefix'));
        $t->same(true, str_contains($text, str_replace('.test', '', $script)));
    }
};

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::walLockRecoveryFaultRows() as $row) {
    $tests[sprintf(
        'real upstream pager wal lock recovery fault dynamic %04d %s %s',
        $row['case'],
        $row['source_file'],
        $row['phase']
    )] = static function (TestRunner $t) use ($row): void {
        $t->same(true, $row['case'] >= 1 && $row['case'] <= 1000);
        $t->same(true, in_array($row['source_file'], ['rowallock.test', 'walsetlk3.test', 'walsetlk_recover.test', 'walseh1.test'], true));
        $t->same(true, str_starts_with($row['upstream'], str_replace('.test', '', $row['source_file']) . '.test'));
        $t->same(true, in_array($row['database_mode'], ['wal', 'delete'], true));
        $t->same(true, in_array($row['connect_mode'], ['readonly', 'readwrite'], true));
        $t->same($row['message'] === 'database is locked', $row['would_block']);
        $t->same($row['connect_mode'] === 'readonly', $row['readonly']);
        $t->same(count($row['rows_before']), $row['row_count_before']);
        $t->same(count($row['rows_after']), $row['row_count_after']);
        $t->same($row['database_mode'] === 'wal' && $row['wal_exists'], $row['wal_reader_stable']);
        $t->same($row['system_errno'] > 0, $row['requires_system_errno_check']);
        $t->same(true, $row['result_code'] === 0 || $row['result_code'] === 1);
        $t->same($row['result_code'] === 0 ? 'ok' : $row['message'], $row['message']);
        $t->same($row['timeout_ms'] > 0, $row['busy_delay_ms'] > 0);
        $t->same(true, str_contains($row['operation'], 'SELECT') || str_contains($row['operation'], 'INSERT') || $row['operation'] === 'ROLLBACK' || str_contains($row['operation'], 'PRAGMA'));
        $t->same(true, in_array('sqlite-real-upstream-pager-wal-dynamic', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-rowallock-readonly-wal', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-walsetlk-blocking-connect', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-wal-recovery-timeout', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-walseh-system-error-recovery', $row['dependencies'], true));
    };
}

$tests['real upstream pager wal lock recovery fault dynamic records non-overlapping source sections'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicPlan::walLockRecoveryFaultRows();
    $sources = array_values(array_unique(array_column($rows, 'source_file')));
    sort($sources);
    $sections = array_values(array_unique(array_column($rows, 'upstream')));
    sort($sections);

    $t->same(1000, count($rows));
    $t->same(['rowallock.test', 'walseh1.test', 'walsetlk3.test', 'walsetlk_recover.test'], $sources);
    $t->same([
        'rowallock.test 1.* readonly WAL clients keep read locks while writers append',
        'walseh1.test 1-2 system-error handler preserves read-only WAL rows',
        'walseh1.test 3-4 system-error handler preserves WAL write/checkpoint rows',
        'walseh1.test 5 rollback after cache-spill fault leaves transaction empty',
        'walseh1.test 6 truncate checkpoint fault still permits later WAL write',
        'walsetlk3.test 1.1 nonblocking connect returns busy while close checkpoints WAL',
        'walsetlk3.test 1.2 block-on-connect waits for close checkpoint',
        'walsetlk3.test 2.2 rollback-mode shared lock is not block-on-connect',
        'walsetlk3.test 2.3 rollback-mode reader succeeds after exclusive writer commits',
        'walsetlk_recover.test 1.2-1.5 recovery reader times out behind WAL xRead',
    ], $sections);
    $t->same('rowallock.test', $rows[0]['source_file']);
    $t->same('walseh1.test', $rows[999]['source_file']);
};

return $tests;
