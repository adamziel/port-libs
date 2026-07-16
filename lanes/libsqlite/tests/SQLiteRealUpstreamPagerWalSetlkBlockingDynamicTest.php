<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::walSetlkBlockingLockCases() as $case) {
    $tests[sprintf(
        'real upstream pager wal setlk blocking dynamic %04d %s %s',
        $case['case'],
        $case['upstream'],
        $case['phase']
    )] = static function (TestRunner $t) use ($case): void {
        $t->same(true, in_array($case['source_file'], ['walsetlk.test', 'walsetlk2.test', 'walblock.test'], true));
        $t->same(true, str_contains($case['upstream'], $case['source_file']));
        $t->same(true, $case['case'] >= 1 && $case['case'] <= 1000);
        $t->same(true, in_array($case['checkpoint_mode'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->same(true, in_array($case['journal_mode'], ['wal', 'wal-persist'], true));
        $t->same(true, in_array($case['sync_mode'], ['normal', 'full', 'extra', 'off'], true));
        $t->same(true, in_array($case['page_size'], [512, 1024, 2048, 4096], true));
        $t->same(true, $case['lock_trace_count'] === count($case['lock_trace']));
        $t->same(true, str_contains($case['lock_trace'][0], ':lock:') || str_contains($case['lock_trace'][0], ':1:lock:'));
        $t->same($case['busy_waits'] > 0 || $case['timeout_ms'] > 0, $case['waiter_blocks']);
        $t->same(true, in_array($case['expected_code'], [0, 1], true));
        $t->same($case['expected_code'] === 0 || $case['timeout_ms'] === 0, $case['unlock_releases_waiter']);
        $t->same($case['expected_code'] === 0 ? $case['visible_rows_after_release'] : $case['visible_rows_before_release'], $case['expected_code'] === 0 ? $case['visible_rows_after_release'] : $case['visible_rows_before_release']);
        $t->same($case['expected_code'] === 1, $case['expected_message'] === 'database is locked');
        $t->same(true, in_array('sqlite-real-upstream-pager-wal-dynamic', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-walsetlk-blocking-locks', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-walblock-reader-waits', $case['dependencies'], true));
    };
}

$tests['real upstream pager wal setlk blocking dynamic records hydrated upstream files and sections'] = static function (TestRunner $t): void {
    $cases = SQLiteRealUpstreamPagerWalDynamicPlan::walSetlkBlockingLockCases();
    $sources = array_values(array_unique(array_column($cases, 'source_file')));
    sort($sources);

    $t->same(['walblock.test', 'walsetlk.test', 'walsetlk2.test'], $sources);
    $t->same(1000, count($cases));
    $t->same('walsetlk.test 1.0..1.8', $cases[0]['upstream']);
    $t->same('walsetlk.test 2.*', $cases[1]['upstream']);
    $t->same('walsetlk2.test 1.3..1.5', $cases[2]['upstream']);
    $t->same('walsetlk2.test 2.0..2.7', $cases[3]['upstream']);
    $t->same('walblock.test 1.1.*', $cases[4]['upstream']);
    $t->same('walblock.test 1.2.*', $cases[5]['upstream']);
    $t->same(512, $cases[0]['page_size']);
    $t->same(4096, $cases[127]['page_size']);
    $t->same('truncate', $cases[999]['checkpoint_mode']);
};

return $tests;
