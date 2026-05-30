<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::pagerWalDynamicMatrixCases() as $case) {
    $tests[sprintf(
        'real upstream pager wal dynamic matrix %04d %s %s %s',
        $case['case'],
        $case['source_file'],
        $case['upstream'],
        $case['checkpoint_mode']
    )] = static function (TestRunner $t) use ($case): void {
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1024);
        $t->true(in_array($case['source_file'], ['wal2.test', 'pager1.test', 'walrestart.test'], true));
        $t->true(str_starts_with($case['upstream'], 'wal2-') || str_starts_with($case['upstream'], 'pager1-') || str_starts_with($case['upstream'], 'walrestart-'));
        $t->true(in_array($case['connection_mode'], ['normal', 'exclusive', 'shared-cache', 'read-only'], true));
        $t->true(in_array($case['checkpoint_mode'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->true(in_array($case['sync_mode'], ['off', 'normal', 'full', 'extra'], true));
        $t->true(in_array($case['page_size'], [512, 1024, 2048, 4096], true));
        $t->same(0, $case['page_size'] % 512);
        $t->same($case['lock_sequence'], array_values($case['lock_sequence']));
        $t->same(count($case['lock_sequence']), $case['lock_count'] + $case['unlock_count']);
        $t->same(false, $case['wal_exists'] && $case['journal_exists']);
        $t->same(true, is_bool($case['requires_recovery']));
        $t->same(true, is_bool($case['reader_visible']));
        $t->contains('sqlite-real-upstream-wal2-locking', implode(',', $case['dependencies']));
        $t->contains('sqlite-real-upstream-pager1-locking', implode(',', $case['dependencies']));
        $t->contains('sqlite-real-upstream-walrestart-checkpoint', implode(',', $case['dependencies']));

        if ($case['has_busy_lock']) {
            $t->same(true, str_contains($case['upstream'], 'wal2-3.'));
            $t->same(false, $case['has_ioerr_lock']);
        }
        if ($case['has_ioerr_lock']) {
            $t->same('database is locked', $case['error']);
            $t->same(false, $case['reader_visible']);
        }
        if ($case['checkpoint'] !== null) {
            $t->same(3, count($case['checkpoint']));
            $t->same(0, $case['checkpoint'][0]);
            $t->true($case['checkpoint'][1] >= $case['checkpoint'][2]);
        }
        if ($case['source_file'] === 'pager1.test' && $case['error'] === 'database is locked') {
            $t->same(false, $case['reader_visible'] && $case['connection_mode'] === 'read-only');
        }
    };
}

$tests['real upstream pager wal dynamic matrix records source coverage'] = static function (TestRunner $t): void {
    $cases = SQLiteRealUpstreamPagerWalDynamicPlan::pagerWalDynamicMatrixCases();
    $sources = array_values(array_unique(array_column($cases, 'source_file')));

    sort($sources);
    $t->same(1024, count($cases));
    $t->same(['pager1.test', 'wal2.test', 'walrestart.test'], $sources);
    $t->same('wal2.test', $cases[0]['source_file']);
    $t->same('wal2-1.2', $cases[0]['upstream']);
    $t->same('pager1.test', $cases[39]['source_file']);
    $t->same('walrestart.test', $cases[49]['source_file']);
    $t->same('sqlite-real-upstream-walrestart-checkpoint', $cases[0]['dependencies'][2]);
};

return $tests;
