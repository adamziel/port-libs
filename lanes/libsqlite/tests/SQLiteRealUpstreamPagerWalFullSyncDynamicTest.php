<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::wal2CheckpointFullSyncCases() as $case) {
    $tests[sprintf(
        'real upstream pager wal fullsync dynamic %04d %s %s',
        $case['case'],
        $case['upstream'],
        $case['checkpoint_mode']
    )] = static function (TestRunner $t) use ($case): void {
        $t->same('wal2.test', $case['source_file']);
        $t->same(true, str_starts_with($case['upstream'], 'wal2.test wal2-14.'));
        $t->same(true, $case['case'] >= 1 && $case['case'] <= 1000);
        $t->same(true, in_array($case['checkpoint_mode'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->same(true, in_array($case['synchronous'], ['full', 'extra'], true));
        $t->same(true, in_array($case['page_size'], [1024, 2048, 4096, 8192], true));
        $t->same(true, in_array($case['wal_autocheckpoint'], [10, 20, 1000, 4096], true));
        $t->same([[0, 3, 3], [0, 1, 1]], $case['checkpoint_results']);
        $t->same(3, count($case['sync_sequence']));
        $t->same(
            ['initial-ddl-insert-checkpoint-commit-checkpoint', 'large-zeroblob-autocheckpoint', 'close-after-deferred-autocheckpoint'],
            array_column($case['sync_sequence'], 'phase')
        );
        $t->same(array_sum(array_column($case['sync_sequence'], 'sync')), $case['total_syncs']);
        $t->same(array_sum(array_column($case['sync_sequence'], 'fullsync')), $case['total_fullsyncs']);
        $t->same($case['checkpoint_fullfsync'] === true, $case['uses_checkpoint_fullfsync']);
        $t->same($case['uses_checkpoint_fullfsync'] ? 12 : 0, $case['total_fullsyncs']);
        $t->same($case['uses_checkpoint_fullfsync'], $case['total_fullsyncs'] > 0);
        $t->same(true, $case['total_syncs'] >= 14);
        $t->same(true, in_array('sqlite-real-upstream-pager-wal-dynamic', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-wal2-checkpoint-fullfsync', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-sync-plan', $case['dependencies'], true));
    };
}

$tests['real upstream pager wal fullsync dynamic records hydrated upstream wal2 section'] = static function (TestRunner $t): void {
    $cases = SQLiteRealUpstreamPagerWalDynamicPlan::wal2CheckpointFullSyncCases();
    $sources = array_values(array_unique(array_column($cases, 'source_file')));
    $fullSyncTotals = array_values(array_unique(array_column($cases, 'total_fullsyncs')));
    sort($fullSyncTotals);

    $t->same(['wal2.test'], $sources);
    $t->same(1000, count($cases));
    $t->same('wal2.test wal2-14.1', $cases[0]['upstream']);
    $t->same('wal2.test wal2-14.2', $cases[1]['upstream']);
    $t->same('wal2.test wal2-14.3', $cases[2]['upstream']);
    $t->same([0, 12], $fullSyncTotals);
    $t->same(10, $cases[0]['wal_autocheckpoint']);
    $t->same(4096, $cases[127]['wal_autocheckpoint']);
    $t->same('truncate', $cases[999]['checkpoint_mode']);
};

return $tests;
