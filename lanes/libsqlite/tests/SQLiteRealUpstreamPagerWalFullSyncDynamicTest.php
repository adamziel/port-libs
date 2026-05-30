<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::wal2CheckpointFullSyncCases() as $case) {
    $tests[sprintf(
        'real upstream pager wal fullsync dynamic %04d %s %s %s page %d',
        $case['case'],
        $case['upstream'],
        $case['checkpoint_mode'],
        $case['synchronous'],
        $case['page_size']
    )] = static function (TestRunner $t) use ($case): void {
        $t->same(true, $case['case'] >= 1 && $case['case'] <= 1000);
        $t->same('wal2.test', $case['source_file']);
        $t->same(true, in_array($case['upstream'], ['wal2.test wal2-14.1', 'wal2.test wal2-14.2', 'wal2.test wal2-14.3'], true));
        $t->same(true, in_array($case['checkpoint_mode'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->same(true, in_array($case['synchronous'], ['full', 'extra'], true));
        $t->same(true, in_array($case['page_size'], [1024, 2048, 4096, 8192], true));
        $t->same(true, in_array($case['wal_autocheckpoint'], [10, 20, 1000, 4096], true));
        $t->same([[0, 3, 3], [0, 1, 1]], $case['checkpoint_results']);
        $t->same(20, $case['total_syncs']);
        $t->same($case['uses_checkpoint_fullfsync'] ? 12 : 0, $case['total_fullsyncs']);
        $t->same($case['total_syncs'], array_sum(array_column($case['sync_sequence'], 'sync')));
        $t->same($case['total_fullsyncs'], array_sum(array_column($case['sync_sequence'], 'fullsync')));
        $t->same('initial-ddl-insert-checkpoint-commit-checkpoint', $case['sync_sequence'][0]['phase']);
        $t->same('large-zeroblob-autocheckpoint', $case['sync_sequence'][1]['phase']);
        $t->same('close-after-deferred-autocheckpoint', $case['sync_sequence'][2]['phase']);
        $t->same($case['uses_checkpoint_fullfsync'], $case['sync_sequence'][0]['fullsync'] === 6);
        $t->same($case['uses_checkpoint_fullfsync'], $case['sync_sequence'][1]['fullsync'] === 3);
        $t->same($case['uses_checkpoint_fullfsync'], $case['sync_sequence'][2]['fullsync'] === 3);
        $t->same(true, in_array('sqlite-real-upstream-pager-wal-dynamic', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-wal2-checkpoint-fullfsync', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-sync-plan', $case['dependencies'], true));
    };
}

$tests['real upstream pager wal fullsync dynamic records hydrated upstream wal2 section'] = static function (TestRunner $t): void {
    $cases = SQLiteRealUpstreamPagerWalDynamicPlan::wal2CheckpointFullSyncCases();
    $sources = array_values(array_unique(array_column($cases, 'source_file')));
    $upstream = array_values(array_unique(array_column($cases, 'upstream')));
    sort($upstream);

    $t->same(1000, count($cases));
    $t->same(['wal2.test'], $sources);
    $t->same(['wal2.test wal2-14.1', 'wal2.test wal2-14.2', 'wal2.test wal2-14.3'], $upstream);
    $t->same(null, $cases[0]['checkpoint_fullfsync']);
    $t->same(true, $cases[1]['checkpoint_fullfsync']);
    $t->same(false, $cases[2]['checkpoint_fullfsync']);
    $t->same(12, $cases[1]['total_fullsyncs']);
    $t->same(0, $cases[2]['total_fullsyncs']);
    $t->same('sqlite-upstream-wal2-checkpoint-fullfsync', $cases[999]['dependencies'][1]);
};

return $tests;
