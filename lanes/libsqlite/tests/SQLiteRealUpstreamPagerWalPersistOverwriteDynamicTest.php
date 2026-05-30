<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::walPersistOverwriteRecoveryCases() as $case) {
    $tests[sprintf(
        'real upstream pager wal persist overwrite dynamic %04d %s %s',
        $case['case'],
        $case['source_file'],
        $case['phase']
    )] = static function (TestRunner $t) use ($case): void {
        $t->same(true, $case['case'] >= 1 && $case['case'] <= 1000);
        $t->same(true, in_array($case['source_file'], ['walpersist.test', 'waloverwrite.test'], true));
        $t->same(true, str_starts_with($case['upstream'], $case['source_file']));
        $t->same(true, in_array($case['checkpoint_mode'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->same(true, in_array($case['synchronous'], ['normal', 'full', 'extra', 'off'], true));
        $t->same(true, in_array($case['cache_spill'], ['default', 'enabled', 'disabled'], true));
        $t->same('ok', $case['integrity']);
        $t->same(true, $case['wal_bytes_before_close'] >= $case['wal_bytes_after_close']);
        $t->same(true, $case['wal_frame_count'] >= 0);
        $t->same([
            'sqlite-real-upstream-pager-wal-dynamic',
            'sqlite-upstream-walpersist-persistent-sidecars',
            'sqlite-upstream-waloverwrite-frame-recovery',
        ], $case['dependencies']);

        if ($case['source_file'] === 'walpersist.test') {
            $t->same(false, $case['recovery_required']);
            $t->same(false, $case['savepoint_rolled_back']);
            $t->same(true, $case['sidecars_persist']);
            $t->same($case['wal_bytes_after_close'] === 0, $case['wal_truncated_on_close']);
            $t->same(true, in_array($case['journal_mode'], ['wal', 'persist'], true));
            $t->same(true, count($case['persist_wal_sequence']) >= 1);
            $t->same($case['persist_wal_sequence'][count($case['persist_wal_sequence']) - 1], $case['wal_exists_after_close'] ? 1 : 0);
            $t->same(true, isset($case['query_after_reopen']));
        } else {
            $t->same(true, $case['recovery_required']);
            $t->same('wal', $case['journal_mode']);
            $t->same(1024, $case['page_size']);
            $t->same(5, $case['cache_size']);
            $t->same(20, $case['row_count']);
            $t->same(true, $case['wal_frame_count'] >= $case['wal_frame_min']);
            $t->same(true, $case['wal_frame_count'] <= $case['wal_frame_max']);
            $t->same($case['row_count'] * $case['blob_bytes_before_wal_recovery'], $case['pre_recovery_blob_sum']);
            $t->same($case['row_count'] * $case['blob_bytes_after_wal_recovery'], $case['recovered_blob_sum']);
            $t->same($case['blob_bytes_after_wal_recovery'], $case['savepoint_rolled_back'] ? 798 : 799);
            $t->same($case['wal_frame_min'] > 55, $case['savepoint_rolled_back']);
        }
    };
}

$tests['real upstream pager wal persist overwrite dynamic records hydrated upstream sections'] = static function (TestRunner $t): void {
    $cases = SQLiteRealUpstreamPagerWalDynamicPlan::walPersistOverwriteRecoveryCases();
    $sources = array_values(array_unique(array_column($cases, 'source_file')));
    sort($sources);
    $upstream = array_values(array_unique(array_column($cases, 'upstream')));
    sort($upstream);

    $t->same(1000, count($cases));
    $t->same(['waloverwrite.test', 'walpersist.test'], $sources);
    $t->same([
        'waloverwrite.test 1.1.1..1.1.6',
        'waloverwrite.test 1.1.7..1.1.10',
        'waloverwrite.test 1.2.1..1.2.6',
        'waloverwrite.test 1.2.7..1.2.10',
        'walpersist.test 4.1',
        'walpersist.test walpersist-1.0..1.11',
        'walpersist.test walpersist-2.1..2.3',
        'walpersist.test walpersist-3.1..3.4',
    ], $upstream);
    $t->same('persistent-wal-file-control-keeps-wal-and-shm-after-close', $cases[0]['phase']);
    $t->same('persistent-wal-honors-journal-size-limit-on-close', $cases[1]['phase']);
    $t->same('empty-wal-overwrites-repeated-page-updates-before-recovery', $cases[4]['phase']);
    $t->same('nonempty-wal-savepoint-rollback-restores-pre-savepoint-page-image', $cases[999]['phase']);
};

return $tests;
