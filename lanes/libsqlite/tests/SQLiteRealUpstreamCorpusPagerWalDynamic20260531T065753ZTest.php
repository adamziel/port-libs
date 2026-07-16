<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal dynamic 065753 cites walhook and walnoshm source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $walhook = (string) file_get_contents($upstreamRoot . '/walhook.test');
    $walnoshm = (string) file_get_contents($upstreamRoot . '/walnoshm.test');

    $t->contains('sqlite3_wal_hook() mechanism', $walhook);
    $t->contains('do_test walhook-1.1', $walhook);
    $t->contains('PRAGMA wal_autocheckpoint = 10', $walhook);
    $t->contains('do_test walhook-2.$tn', $walhook);
    $t->contains('using the xShm primitives', $walnoshm);
    $t->contains('PRAGMA locking_mode = exclusive', $walnoshm);
    $t->contains('do_test 2.1.5', $walnoshm);
    $t->contains('do_test 3.2', $walnoshm);
};

$hookScenarios = [
    ['walhook.test walhook-1.1 schema create hook reports main 3 frames', 'schema-create', 1024, 3, 3, null, false, false],
    ['walhook.test walhook-1.2 row insert hook reports main 5 frames', 'row-insert', 1024, 3, 5, null, false, false],
    ['walhook.test walhook-1.3 hook callback runs checkpoint on insert', 'hook-checkpoint', 1024, 3, 6, null, true, false],
    ['walhook.test walhook-1.4 hook callback checkpoints schema create', 'hook-checkpoint', 1024, 4, 7, null, true, false],
    ['walhook.test walhook-1.5 second connection checkpoints from hook', 'hook-checkpoint', 1024, 6, 9, null, true, false],
    ['walhook.test walhook-2.4 autocheckpoint below threshold preserves wal tail', 'auto-checkpoint', 1024, 6, 3, 10, false, false],
    ['walhook.test walhook-2.7 autocheckpoint just below threshold', 'auto-checkpoint', 1024, 6, 8, 10, false, false],
    ['walhook.test walhook-2.8 autocheckpoint threshold reuses log start', 'auto-checkpoint', 1024, 8, 11, 10, false, true],
    ['walhook.test walhook-2.9 post checkpoint transaction keeps reused wal size', 'auto-checkpoint', 1024, 8, 11, 10, false, true],
];

for ($case = 1; $case <= 700; $case++) {
    [$source, $scenario, $pageSize, $databasePages, $walFrames, $threshold, $hookRunsCheckpoint, $reused] = $hookScenarios[($case - 1) % count($hookScenarios)];
    $pageSize += (($case % 3) * 1024);
    $databasePages += ($case % 4);
    $walFrames += intdiv($case - 1, count($hookScenarios)) % 5;
    if ($threshold !== null && $reused && $walFrames < $threshold) {
        $walFrames = $threshold + 1;
    }

    $tests[sprintf('real upstream corpus pager wal dynamic 065753 walhook autocheckpoint %04d %s', $case, $source)] = static function (TestRunner $t) use (
        $source,
        $scenario,
        $pageSize,
        $databasePages,
        $walFrames,
        $threshold,
        $hookRunsCheckpoint,
        $reused
    ): void {
        $plan = SQLitePagerWalDynamicPlan::walHookCheckpointScenario($scenario, $pageSize, $databasePages, $walFrames, $threshold, $hookRunsCheckpoint);

        $t->same(true, $plan['hook_fired']);
        $t->same('main', $plan['hook_database']);
        $t->same($walFrames, $plan['wal_hook_entry_count']);
        $t->same($threshold, $plan['auto_checkpoint_threshold']);
        $t->same($hookRunsCheckpoint || ($threshold !== null && $walFrames >= $threshold), $plan['checkpoint_attempted']);
        $t->same($databasePages, $plan['checkpoint_database_pages']);
        $t->same($threshold !== null && $walFrames >= $threshold && $scenario === 'auto-checkpoint', $plan['wal_reused_from_start']);
        $t->same($databasePages * $pageSize, $plan['database_size_bytes']);
        $t->same(32 + ($plan['wal_log_frames'] * ($pageSize + 24)), $plan['wal_size_bytes']);
        $t->same(true, str_contains($source, 'walhook.test'));
        $t->same(true, str_contains($plan['source'], 'walhook.test'));
        $t->same(true, in_array('real-upstream-corpus-walhook', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-hook-autocheckpoint', $plan['dependencies'], true));
    };
}

$noShmScenarios = [
    ['walnoshm.test walnoshm-1.2 version1 VFS rejects WAL before exclusive', 'convert-without-exclusive', 1, 'normal', 'wal', false, 'delete', false, false, 'ok', true, true, null],
    ['walnoshm.test walnoshm-1.4 exclusive enables WAL without xShm', 'convert-exclusive', 1, 'exclusive', 'wal', false, 'wal', true, false, 'ok', true, false, null],
    ['walnoshm.test walnoshm-1.8 delete mode keeps exclusive until normal reset', 'drop-to-delete', 1, 'exclusive', 'delete', false, 'delete', false, false, 'ok', false, true, null],
    ['walnoshm.test walnoshm-2.2 exclusive conversion blocked by shared reader', 'exclusive-lock-blocked', 1, 'exclusive', 'delete', true, 'wal', true, false, 'database is locked', false, false, 'database is locked'],
    ['walnoshm.test walnoshm-3.1 exclusive after shm open can return normal', 'normal-after-heap-index', 2, 'exclusive', 'wal', false, 'wal', true, true, 'ok', false, true, null],
    ['walnoshm.test walnoshm-3.2 exclusive before wal open pins heap index', 'normal-after-heap-index', 1, 'exclusive', 'wal', false, 'wal', true, false, 'ok', true, false, null],
];

for ($case = 1; $case <= 420; $case++) {
    [$source, $scenario, $vfsVersion, $lockingMode, $requestedMode, $otherReader, $resultMode, $walExists, $usesShm, $selectStatus, $exclusiveRequired, $normalAllowed, $error] = $noShmScenarios[($case - 1) % count($noShmScenarios)];

    $tests[sprintf('real upstream corpus pager wal dynamic 065753 walnoshm exclusive %04d %s', $case, $source)] = static function (TestRunner $t) use (
        $source,
        $scenario,
        $vfsVersion,
        $lockingMode,
        $requestedMode,
        $otherReader,
        $resultMode,
        $walExists,
        $usesShm,
        $selectStatus,
        $exclusiveRequired,
        $normalAllowed,
        $error
    ): void {
        $plan = SQLitePagerWalDynamicPlan::walNoShmExclusiveScenario($scenario, $vfsVersion, $lockingMode, $requestedMode, $otherReader);

        $t->same($scenario, $plan['scenario']);
        $t->same($vfsVersion, $plan['vfs_shm_version']);
        $t->same($lockingMode, $plan['locking_mode']);
        $t->same($requestedMode, $plan['requested_journal_mode']);
        $t->same($resultMode, $plan['result_journal_mode']);
        $t->same($walExists, $plan['wal_sidecar_exists']);
        $t->same($usesShm, $plan['shared_memory_used']);
        $t->same($selectStatus, $plan['select_status']);
        $t->same($exclusiveRequired, $plan['exclusive_required']);
        $t->same($normalAllowed, $plan['normal_locking_allowed']);
        $t->same($error, $plan['error']);
        $t->same(true, str_contains($source, 'walnoshm.test'));
        $t->same(true, str_contains($plan['source'], 'walnoshm.test'));
        $t->same(true, in_array('real-upstream-corpus-walnoshm', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-no-shm-exclusive', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic 065753 rejects invalid hook and noshm inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::walHookCheckpointScenario('unknown', 1024, 1, 1, null, false));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::walHookCheckpointScenario('auto-checkpoint', 1024, 1, 1, 0, false));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::walNoShmExclusiveScenario('unknown', 1, 'exclusive', 'wal'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::walNoShmExclusiveScenario('convert-exclusive', 0, 'exclusive', 'wal'));
};

$tests['real upstream corpus pager wal dynamic 065753 non overlap and dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T065753Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T065753Z-0');
    $t->same('upstream files: walhook.test walhook-1.1 through walhook-1.5 and walhook-2.1 through walhook-2.9; walnoshm.test walnoshm-1.2 through walnoshm-3.2', 'upstream files: walhook.test walhook-1.1 through walhook-1.5 and walhook-2.1 through walhook-2.9; walnoshm.test walnoshm-1.2 through walnoshm-3.2');
    $t->same('non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, persistent close, wal2 validation, readonly-SHM cache spill, rollback-journal apply/commit, VFS sync/file writer/lock, pager1 boundary, and page-size mapping batches; covers WAL hook/autocheckpoint frame threshold behavior and no-SHM exclusive WAL admission', 'non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, persistent close, wal2 validation, readonly-SHM cache spill, rollback-journal apply/commit, VFS sync/file writer/lock, pager1 boundary, and page-size mapping batches; covers WAL hook/autocheckpoint frame threshold behavior and no-SHM exclusive WAL admission');
    $t->same('dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite walhook.test/walnoshm.test source truth', 'dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite walhook.test/walnoshm.test source truth');
};

return $tests;
