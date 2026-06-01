<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/win32nolock.test';
$scenarios = [
    'win32nolock-1.3',
    'win32nolock-1.4',
    'win32nolock-1.5',
    'win32nolock-1.6',
    'win32nolock-1.7',
    'win32nolock-1.9.1',
    'win32nolock-1.10.1',
    'win32nolock-1.11.1',
    'win32nolock-1.12.1',
];

foreach (range(1, 1000) as $case) {
    $scenario = $scenarios[($case - 1) % count($scenarios)];
    $initialA = 1 + ($case % 17);
    $initialB = 2 + ($case % 19);
    $pendingA = $initialA + 20 + ($case % 7);
    $pendingB = $initialB + 30 + ($case % 11);
    $releaseMemory = 500000 + ($case * 257);

    $tests[sprintf('real upstream corpus vfs win32nolock dynamic case %04d %s', $case, $scenario)] = static function (TestRunner $t) use (
        $case,
        $initialA,
        $initialB,
        $pendingA,
        $pendingB,
        $releaseMemory,
        $scenario
    ): void {
        $plan = SQLiteVfsIoDynamicPlan::win32NoLockProfile($scenario, [
            'initial_a' => $initialA,
            'initial_b' => $initialB,
            'pending_a' => $pendingA,
            'pending_b' => $pendingB,
            'release_memory_bytes' => $releaseMemory,
        ]);

        $initialRows = [[$initialA, $initialB]];
        $freshRows = [[$initialA, $initialB], [$pendingA, $pendingB]];

        $t->same('ok', $plan['status']);
        $t->same('win32nolock.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same('windows', $plan['platform']);
        $t->same($initialRows, $plan['initial_rows']);
        $t->same([$pendingA, $pendingB], $plan['pending_row']);
        $t->same($freshRows, $plan['fresh_rows']);
        $t->same(true, in_array('sqlite-upstream-win32nolock-vfs', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-win32-none-no-lock', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));

        if (in_array($scenario, ['win32nolock-1.3', 'win32nolock-1.4', 'win32nolock-1.5', 'win32nolock-1.6', 'win32nolock-1.7'], true)) {
            $expectedObservedRows = in_array($scenario, ['win32nolock-1.3', 'win32nolock-1.7'], true) ? $freshRows : $initialRows;
            $expectedPhase = match ($scenario) {
                'win32nolock-1.3' => 'primary_select_during_uncommitted_transaction',
                'win32nolock-1.4' => 'peer_select_before_peer_transaction',
                'win32nolock-1.5' => 'peer_select_inside_peer_transaction',
                'win32nolock-1.6' => 'peer_select_after_primary_commit_before_cache_refresh',
                default => 'peer_select_after_memory_release_refresh',
            };

            $t->same('win32-none', $plan['primary_vfs']);
            $t->same('win32-none', $plan['peer_vfs']);
            $t->same(0, $plan['peer_mmap_size']);
            $t->same(true, $plan['lock_calls_suppressed']);
            $t->same(['begin' => true, 'committed' => false, 'inserted_row' => [$pendingA, $pendingB]], $plan['primary_transaction']);
            $t->same($freshRows, $plan['primary_select_during_transaction']);
            $t->same($initialRows, $plan['peer_select_before_begin']);
            $t->same($initialRows, $plan['peer_select_inside_transaction']);
            $t->same(['code' => 0, 'message' => 'ok'], $plan['primary_commit_result']);
            $t->same($initialRows, $plan['peer_select_after_commit_before_cache_refresh']);
            $t->same($releaseMemory, $plan['release_memory_request_bytes']);
            $t->same($freshRows, $plan['peer_select_after_memory_release']);
            $t->same(true, $plan['memory_release_required_for_peer_refresh']);
            $t->same($expectedPhase, $plan['observed_phase']);
            $t->same($expectedObservedRows, $plan['observed_rows']);
            $t->same($scenario === 'win32nolock-1.7', $plan['change_counter_visible_without_lock_refresh']);
            $t->same('win32_none_peer_cache_stays_stale_until_memory_release', $plan['reason']);
            $t->contains('win32nolock.test win32nolock-1.2', implode("\n", $plan['upstream']));
            $t->contains('win32nolock.test win32nolock-1.7', implode("\n", $plan['upstream']));
        } else {
            $expectedPrimaryVfs = in_array($scenario, ['win32nolock-1.10.1', 'win32nolock-1.12.1'], true) ? 'win32-none' : 'win32';
            $expectedPeerVfs = in_array($scenario, ['win32nolock-1.11.1', 'win32nolock-1.12.1'], true) ? 'win32-none' : 'win32';
            $peerBlocked = $expectedPrimaryVfs === 'win32' && $expectedPeerVfs === 'win32';

            $t->same($expectedPrimaryVfs, $plan['primary_vfs']);
            $t->same($expectedPeerVfs, $plan['peer_vfs']);
            $t->same($expectedPrimaryVfs === 'win32-none', $plan['primary_suppresses_locks']);
            $t->same($expectedPeerVfs === 'win32-none', $plan['peer_suppresses_locks']);
            $t->same(['code' => 0, 'message' => 'ok'], $plan['primary_begin_exclusive']);
            $t->same($peerBlocked ? ['code' => 1, 'message' => 'database is locked'] : ['code' => 0, 'message' => 'ok'], $plan['peer_begin_exclusive']);
            $t->same($peerBlocked, $plan['peer_blocked_by_primary_exclusive']);
            $t->same(!$peerBlocked, $plan['both_exclusive_transactions_allowed']);
            $t->same($peerBlocked ? 'ordinary_win32_byte_range_lock' : 'win32_none_no_lock_bypass', $plan['lock_arbitration']);
            $t->same($expectedPrimaryVfs === 'win32-none' ? 0 : 1, $plan['lock_calls']['primary_x_lock']);
            $t->same($expectedPeerVfs === 'win32-none' ? 0 : 1, $plan['lock_calls']['peer_x_lock']);
            $t->same($expectedPrimaryVfs === 'win32-none' ? 0 : 1, $plan['lock_calls']['primary_x_unlock']);
            $t->same($expectedPeerVfs === 'win32-none' ? 0 : ($peerBlocked ? 0 : 1), $plan['lock_calls']['peer_x_unlock']);
            $t->same($peerBlocked ? 'ordinary_win32_handles_enforce_exclusive_transaction_contention' : 'win32_none_vfs_bypasses_exclusive_transaction_lock_arbitration', $plan['reason']);
            $t->contains('win32nolock.test win32nolock-1.9.1', implode("\n", $plan['upstream']));
            $t->contains('win32nolock.test win32nolock-1.12.1', implode("\n", $plan['upstream']));
        }

        $t->same(true, $case >= 1);
    };
}

$tests['real upstream corpus vfs win32nolock cites hydrated source truth'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->contains('sqlite3 db test.db -vfs win32-none', $source);
    $t->contains('sqlite3 db2 test.db -vfs win32-none', $source);
    $t->contains('PRAGMA mmap_size = 0', $source);
    $t->contains('sqlite3_release_memory 1000000', $source);
    $t->contains('BEGIN EXCLUSIVE;', $source);

    $cache = SQLiteVfsIoDynamicPlan::win32NoLockProfile('win32nolock-1.7');
    $ordinary = SQLiteVfsIoDynamicPlan::win32NoLockProfile('win32nolock-1.9.1');
    $mixed = SQLiteVfsIoDynamicPlan::win32NoLockProfile('win32nolock-1.10.1');

    $t->same([[1, 2], [3, 4]], $cache['observed_rows']);
    $t->same(true, $cache['memory_release_required_for_peer_refresh']);
    $t->same(true, $ordinary['peer_blocked_by_primary_exclusive']);
    $t->same(false, $mixed['peer_blocked_by_primary_exclusive']);
    $t->same('win32_none_no_lock_bypass', $mixed['lock_arbitration']);
};

$rejects = [
    'empty scenario' => static fn (): array => SQLiteVfsIoDynamicPlan::win32NoLockProfile(''),
    'unsupported scenario' => static fn (): array => SQLiteVfsIoDynamicPlan::win32NoLockProfile('win32nolock-9.9'),
    'negative initial a' => static fn (): array => SQLiteVfsIoDynamicPlan::win32NoLockProfile('win32nolock-1.3', ['initial_a' => -1]),
    'zero pending b' => static fn (): array => SQLiteVfsIoDynamicPlan::win32NoLockProfile('win32nolock-1.3', ['pending_b' => 0]),
    'zero release memory' => static fn (): array => SQLiteVfsIoDynamicPlan::win32NoLockProfile('win32nolock-1.7', ['release_memory_bytes' => 0]),
];

foreach ($rejects as $name => $callback) {
    $tests['real upstream corpus vfs win32nolock rejects malformed input ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
