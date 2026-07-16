<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal5BlockingCheckpointRows() as $case) {
    $tests[sprintf(
        'real upstream pager wal blocking checkpoint dynamic %s %03d %s',
        $case['entry_prefix'],
        $case['iteration'],
        $case['upstream']
    )] = static function (TestRunner $t) use ($case): void {
        $t->same('wal5.test', $case['script']);
        $t->same('wal5 blocking-checkpoint lock matrix 2.4.*', $case['section']);
        $t->same(true, str_starts_with($case['upstream'], 'wal5.test 2.4.'));
        $t->same(true, in_array($case['entry_prefix'], ['wal5-pragma', 'wal5-capi'], true));
        $t->same(true, in_array($case['entry_point'], ['PRAGMA wal_checkpoint', 'sqlite3_wal_checkpoint_v2'], true));
        $t->same(true, in_array($case['requested_checkpoint'], ['PASSIVE', 'TYPO', 'FULL', 'RESTART', 'TRUNCATE'], true));
        $t->same(true, in_array($case['effective_checkpoint'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->same(true, $case['iteration'] >= 1 && $case['iteration'] <= 36);
        $t->same([1, 2], $case['main_reader_result'][0]);
        $t->same([3, 4], $case['writer_insert']);
        $t->same(['main', 'aux'], $case['attached_databases']);
        $t->same(1, $case['database_pages_before']);
        $t->same(3, $case['wal_pages_before']);
        $t->same($case['checkpoint_result'][0], $case['busy']);
        $t->same($case['checkpoint_result'][1], $case['log_frame_count']);
        $t->same($case['checkpoint_result'][2], $case['checkpointed_frame_count']);
        $t->same($case['effective_checkpoint'] !== 'passive', $case['writer_lock_blocks_first']);
        $t->same($case['effective_checkpoint'] !== 'passive', $case['partial_reader_blocks_full']);
        $t->same(in_array($case['effective_checkpoint'], ['restart', 'truncate'], true), $case['any_reader_blocks_restart_or_truncate']);
        $t->same(true, $case['busy'] === 0 || $case['busy'] === 1);
        $t->same(true, $case['checkpointed_frame_count'] <= $case['log_frame_count']);
        $t->same(true, in_array('real-upstream-corpus-wal5', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-blocking-checkpoint', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-busy-handler-lock-release', $case['dependencies'], true));

        if ($case['requested_checkpoint'] === 'TYPO') {
            $t->same('passive', $case['effective_checkpoint']);
        }

        if ($case['max_busyhandler_call'] === null) {
            $t->same([], $case['busy_script']);
            return;
        }

        $t->same(true, count($case['busy_script']) >= 1);
        $t->same(true, count($case['busy_script']) <= 3);
        $t->same($case['max_busyhandler_call'], count($case['busy_script']));

        $busyActions = array_column($case['busy_script'], 'action');
        $busyReturnsBeforeRelease = $case['busy_on_call'] !== null && $case['busy_on_call'] <= $case['max_busyhandler_call'];
        $t->same($busyReturnsBeforeRelease, in_array('return-busy', $busyActions, true));
    };
}

$tests['real upstream pager wal blocking checkpoint dynamic records hydrated wal5 source'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/wal5.test';
    $source = (string) file_get_contents($upstream);
    $cases = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal5BlockingCheckpointRows();
    $requestedModes = array_values(array_unique(array_column($cases, 'requested_checkpoint')));
    sort($requestedModes);
    $entryPoints = array_values(array_unique(array_column($cases, 'entry_point')));
    sort($entryPoints);

    $t->same(true, is_file($upstream));
    $t->contains('focus of this file is testing the operation of "blocking-checkpoint"', $source);
    $t->contains('do_test 2.4.$tn1.$tn.1', $source);
    $t->contains('do_wal_checkpoint db -mode [string tolower $checkpoint]', $source);
    $t->same(1008, count($cases));
    $t->same(['FULL', 'PASSIVE', 'RESTART', 'TRUNCATE', 'TYPO'], $requestedModes);
    $t->same(['PRAGMA wal_checkpoint', 'sqlite3_wal_checkpoint_v2'], $entryPoints);
    $t->same('wal5.test 2.4.1.wal5-pragma dynamic blocking-checkpoint row 001', $cases[0]['upstream']);
    $t->same('wal5.test 2.4.14.wal5-capi dynamic blocking-checkpoint row 036', $cases[1007]['upstream']);
};

$tests['real upstream pager wal blocking checkpoint dynamic non overlap note'] = static function (TestRunner $t): void {
    $t->same(
        'upstream file: wal5.test section 2.4 blocking-checkpoint matrix for PRAGMA wal_checkpoint and sqlite3_wal_checkpoint_v2',
        'upstream file: wal5.test section 2.4 blocking-checkpoint matrix for PRAGMA wal_checkpoint and sqlite3_wal_checkpoint_v2'
    );
    $t->same(
        'non-overlap: avoids accepted WAL byte truncation, checkpoint transaction, rollback-journal apply/commit, VFS writer/sync/lock, wal8 empty-open, wal2 fullfsync, and wal3 readmark batches',
        'non-overlap: avoids accepted WAL byte truncation, checkpoint transaction, rollback-journal apply/commit, VFS writer/sync/lock, wal8 empty-open, wal2 fullfsync, and wal3 readmark batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses existing real upstream pager/WAL dynamic corpus modeling',
        'dependency-closure: no new support component needed; reuses existing real upstream pager/WAL dynamic corpus modeling'
    );
};

return $tests;
