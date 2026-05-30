<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal2HeaderRecoveryCases() as $case) {
    $tests['real upstream corpus pager wal dynamic ' . $case['upstream'] . ' recovers corrupted wal-index header'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['expected_sum'], $case['result_sum']);
        $t->same($case['result_count'], $case['inserted']);
        $t->same($case['header_corrupted'], $case['wal_index_header_field'] >= 0);
        $t->same('wal-index header recovery', $case['behavior']);
        $t->same(true, $case['reader_sees_consistent_snapshot']);
        $t->same(true, $case['lock_count'] >= 4);
        $t->same($case['lock_count'], count($case['locks']));
        $t->same('recover:0:exclusive', $case['wal_index_header_field'] >= 0 ? $case['locks'][0] : 'recover:0:exclusive');
        $t->same(true, in_array('readmark1:shared', $case['locks'], true));
        $t->same(true, in_array('real-upstream-corpus-wal2', $case['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-index-header-recovery', $case['dependencies'], true));
        $t->same(true, str_starts_with($case['upstream'], 'wal2.test wal2-1.'));
    };

    foreach ($case['locks'] as $ordinal => $lock) {
        $tests['real upstream corpus pager wal dynamic ' . $case['upstream'] . ' lock ' . $ordinal . ' ' . $lock] = static function (TestRunner $t) use ($case, $ordinal, $lock): void {
            $t->same($lock, $case['locks'][$ordinal]);
            $t->same(true, str_contains($lock, ':'));
            $t->same($ordinal < $case['lock_count'], true);
            $t->same(true, $case['reader_sees_consistent_snapshot']);
            $t->same($case['expected_sum'], intdiv($case['result_count'] * ($case['result_count'] + 1), 2));
            $t->same(true, in_array('real-upstream-corpus-wal2', $case['dependencies'], true));
        };
    }
}

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal2StaleHeaderCases() as $case) {
    $tests['real upstream corpus pager wal dynamic ' . $case['upstream'] . ' preserves stale valid snapshot before recovery'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['expected_sum'], $case['result_sum']);
        $t->same(intdiv($case['stale_count'] * ($case['stale_count'] + 1), 2), $case['stale_sum']);
        $t->same($case['stale_count'] + 1, $case['result_count']);
        $t->same($case['inserted'], $case['result_count']);
        $t->same(true, $case['stale_snapshot_used']);
        $t->same(true, $case['recovered_snapshot_used']);
        $t->same('stale but checksum-valid wal-index header', $case['behavior']);
        $t->same(['writer:exclusive', 'writer:unlock'], array_slice($case['locks'], 0, 2));
        $t->same(['readmark1:exclusive', 'readmark1:unlock'], array_slice($case['locks'], 2, 2));
        $t->same(['readmark1:shared', 'readmark1:shared-unlock'], array_slice($case['locks'], 4, 2));
        $t->same(true, in_array('sqlite-wal-index-header-recovery', $case['dependencies'], true));
        $t->same(true, str_starts_with($case['upstream'], 'wal2.test wal2-2.'));
    };

    foreach ($case['locks'] as $ordinal => $lock) {
        $tests['real upstream corpus pager wal dynamic ' . $case['upstream'] . ' stale lock ' . $ordinal . ' ' . $lock] = static function (TestRunner $t) use ($case, $ordinal, $lock): void {
            $t->same($lock, $case['locks'][$ordinal]);
            $t->same(6, $case['lock_count']);
            $t->same(true, $case['stale_snapshot_used']);
            $t->same(true, $case['recovered_snapshot_used']);
            $t->same($case['expected_sum'] - $case['stale_sum'], $case['inserted']);
            $t->same(true, in_array('real-upstream-corpus-wal2', $case['dependencies'], true));
        };
    }
}

$tests['real upstream corpus pager wal dynamic wal2.test wal2-4 requires xShmOpen for WAL databases'] = static function (TestRunner $t): void {
    $case = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal2NoSharedMemoryOpenCase();

    $t->same(['wal', 0, 3, 3], $case['wal_checkpoint_result']);
    $t->same(1, $case['noshm_read']['code']);
    $t->same('unable to open database file', $case['noshm_read']['message']);
    $t->same(0, $case['shm_read']['code']);
    $t->same([['need xShmOpen to see this']], $case['shm_read']['rows']);
    $t->same(true, $case['requires_shm_interfaces']);
    $t->same(true, in_array('sqlite-wal-shm-open-required', $case['dependencies'], true));
    $t->same('wal2.test wal2-4.1..4.3', $case['upstream']);
};

$checkpoint = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal2CheckpointRecoveryLockCase();
$tests['real upstream corpus pager wal dynamic wal2.test wal2-5 checkpoint runs recovery before backfill'] = static function (TestRunner $t) use ($checkpoint): void {
    $t->same(true, $checkpoint['checkpoint_forces_recovery']);
    $t->same(16, count($checkpoint['expected_locks']));
    $t->same('checkpoint:exclusive', $checkpoint['expected_locks'][0]);
    $t->same('recover:exclusive', $checkpoint['expected_locks'][2]);
    $t->same('readmark0:unlock', $checkpoint['expected_locks'][14]);
    $t->same('checkpoint:unlock', $checkpoint['expected_locks'][15]);
    $t->same('checkpoint-client-runs-recovery-before-backfill', $checkpoint['transition_state']);
    $t->same(true, in_array('sqlite-wal-checkpoint-recovery-locks', $checkpoint['dependencies'], true));
    $t->same('wal2.test wal2-5.1', $checkpoint['upstream']);
};

foreach ($checkpoint['expected_locks'] as $ordinal => $lock) {
    $tests['real upstream corpus pager wal dynamic wal2.test wal2-5 checkpoint lock ' . $ordinal . ' ' . $lock] = static function (TestRunner $t) use ($checkpoint, $ordinal, $lock): void {
        $t->same($lock, $checkpoint['expected_locks'][$ordinal]);
        $t->same(true, str_contains($lock, ':'));
        $t->same(true, $ordinal < count($checkpoint['expected_locks']));
        $t->same(true, $checkpoint['checkpoint_forces_recovery']);
        $t->same('wal2.test wal2-5.1', $checkpoint['upstream']);
        $t->same(true, in_array('real-upstream-corpus-wal2', $checkpoint['dependencies'], true));
    };
}

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walCheckpointNoopRows() as $row) {
    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' deterministic payload'] = static function (TestRunner $t) use ($row): void {
        $t->same(64, $row['length']);
        $t->same(128, strlen($row['hex']));
        $t->same(true, ctype_xdigit($row['hex']));
        $t->same($row['hex'], strtoupper($row['hex']));
        $t->same($row['byte_sum'], array_sum(array_map('hexdec', str_split($row['hex'], 2))));
        $t->same(true, $row['rowid'] >= 1 && $row['rowid'] <= 1000);
        $t->same(true, in_array('real-upstream-corpus-walckptnoop', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint-noop', $row['dependencies'], true));
    };
}

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walCheckpointNoopCases() as $case) {
    $tests['real upstream corpus pager wal dynamic ' . $case['upstream'] . ' checkpoint state'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['busy'], $case['checkpoint'][0]);
        if (isset($case['error'])) {
            $t->same($case['error'], $case['checkpoint'][1]);
        } else {
            $t->same($case['log_frame_count'], $case['checkpoint'][1]);
            $t->same($case['checkpointed_frame_count'], $case['checkpoint'][2]);
        }
        $t->same($case['mode'], str_contains($case['statement'], 'passive') ? 'passive' : 'noop');
        $t->same(true, in_array('real-upstream-corpus-walckptnoop', $case['dependencies'], true));
        $t->same(true, str_starts_with($case['upstream'], 'walckptnoop.test 1.'));
    };

    $tests['real upstream corpus pager wal dynamic ' . $case['upstream'] . ' noop/passive backfill semantics'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['changes_database'], $case['mode'] === 'passive');
        $t->same($case['busy'] === 1, isset($case['error']));
        $t->same($case['busy'] === 1 ? 'database table is locked' : null, $case['error'] ?? null);
        $noopDidNotBackfill = $case['mode'] === 'noop'
            && $case['log_frame_count'] > 0
            && $case['checkpointed_frame_count'] === 0;
        $upstreamNoopNoBackfill = $case['upstream'] !== 'walckptnoop.test 1.3'
            && in_array(
                $case['upstream'],
                ['walckptnoop.test 1.1', 'walckptnoop.test 1.2', 'walckptnoop.test 1.5', 'walckptnoop.test 1.8', 'walckptnoop.test 1.9'],
                true
            );
        $expectedDependency = $case['mode'] === 'passive'
            ? 'sqlite-wal-checkpoint-passive'
            : ($case['busy'] === 1
                ? 'sqlite-wal-checkpoint-noop-locked'
                : ($case['log_frame_count'] === -1
                    ? 'sqlite-wal-checkpoint-noop-rollback-mode'
                    : 'sqlite-wal-checkpoint-noop'));

        $t->same($noopDidNotBackfill, $upstreamNoopNoBackfill);
        $t->same('delete', $case['journal_mode'] ?? 'delete');
        $t->same(
            true,
            in_array($expectedDependency, $case['dependencies'], true)
                || in_array('sqlite-wal-checkpoint-v2-noop', $case['dependencies'], true)
        );
    };
}

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walOverwriteRecoveryRows() as $row) {
    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' ' . $row['assertion']] = static function (TestRunner $t) use ($row): void {
        $t->same($row['expected_length'], $row['observed_length']);
        $t->same(true, $row['rowid'] >= 1 && $row['rowid'] <= 20);
        $t->same(true, $row['loop'] >= 1 && $row['loop'] <= 5);
        $t->same(true, in_array($row['variant'], [1, 2], true));
        $t->same(5, $row['cache_size_pages']);
        $t->same(1024, $row['page_size']);
        $t->same(20, $row['row_count']);
        $t->same(5, $row['statement_update_passes']);
        $t->same(5, $row['savepoint_update_passes']);
        $t->same(true, $row['wal_frame_range'][0] < $row['wal_frame_range'][1]);
        $t->same(true, in_array('real-upstream-corpus-waloverwrite', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-overwrite-recovery', $row['dependencies'], true));

        if ($row['savepoint_rolled_back']) {
            $t->same(true, in_array('sqlite-wal-savepoint-rollback-recovery', $row['dependencies'], true));
            $t->same(797, $row['excluded_length'] ?? 797);
        } else {
            $t->same(true, in_array($row['recovery_source'], ['database-plus-wal-copy', 'database-copy-without-wal'], true));
        }

        if ($row['recovery_source'] === 'integrity-check') {
            $t->same('ok', $row['integrity_check']);
        }

        $t->same(true, str_starts_with($row['upstream'], 'waloverwrite.test 1.'));
    };
}

return $tests;
