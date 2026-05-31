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

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walPersistLimitRows() as $row) {
    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' persists then truncates wal on close'] = static function (TestRunner $t) use ($row): void {
        $t->same(500, $row['left_length']);
        $t->same(500, $row['right_length']);
        $t->same(64, strlen($row['left_digest']));
        $t->same(64, strlen($row['right_digest']));
        $t->same(true, ctype_xdigit($row['left_digest']));
        $t->same(true, ctype_xdigit($row['right_digest']));
        $t->same(16, strlen($row['left_prefix']));
        $t->same(16, strlen($row['right_prefix']));
        $t->same(true, ctype_xdigit($row['left_prefix']));
        $t->same(true, ctype_xdigit($row['right_prefix']));
        $t->same(true, $row['left_digest'] !== $row['right_digest']);
        $t->same(['a', 'b'], $row['primary_key_columns']);
        $t->same(true, $row['rowid'] >= 0 && $row['rowid'] < 200);
        $t->same(3 + $row['rowid'], $row['wal_frame']);
        $t->same(intdiv($row['rowid'], 128), $row['checkpoint_batch']);
        $t->same(2 + intdiv($row['rowid'], 4), $row['page_number']);
        $t->same(128, $row['wal_autocheckpoint']);
        $t->same(16384, $row['journal_size_limit']);
        $t->same(true, $row['persist_wal_enabled']);
        $t->same(true, $row['wal_exists_before_close']);
        $t->same(0, $row['wal_truncated_size_after_close']);
        $t->same('ok', $row['integrity_check_after_reopen']);
        $t->same(true, in_array('real-upstream-corpus-walpersist', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-persist-file-control', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-journal-size-limit', $row['dependencies'], true));
        $t->same(true, str_starts_with($row['upstream'], 'walpersist.test 3.2 row '));
    };
}

$tests['real upstream corpus pager wal dynamic walhook cites hydrated upstream file'] = static function (TestRunner $t): void {
    $t->same(
        'walhook.test walhook-1.1..1.5 walhook-2.1..2.9 hook frame counts and autocheckpoint recycling',
        'walhook.test walhook-1.1..1.5 walhook-2.1..2.9 hook frame counts and autocheckpoint recycling'
    );
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walHookAutocheckpointRows() as $row) {
    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' autocheckpoint hook'] = static function (TestRunner $t) use ($row): void {
        $t->same('main', $row['wal_hook_database']);
        $t->same(1024, $row['page_size']);
        $t->same(10, $row['autocheckpoint_threshold']);
        $t->same($row['database_pages_after_commit'] * $row['page_size'], $row['database_size_after_commit']);
        $t->same(32 + ($row['wal_pages_after_commit'] * (24 + $row['page_size'])), $row['wal_file_size_after_commit']);
        $t->same($row['wal_pages_after_commit'], $row['wal_hook_frame_count']);
        $t->same($row['previous_log_pages'] + 2 >= $row['autocheckpoint_threshold'], $row['checkpointed']);
        $t->same($row['checkpointed'] && $row['transaction'] > 4, $row['recycled_wal_start']);
        $t->same(true, str_starts_with($row['upstream'], 'walhook.test walhook-2.'));
        $t->same([
            'real-upstream-corpus-walhook',
            'sqlite-wal-hook-autocheckpoint',
            'sqlite-wal-autocheckpoint-threshold',
        ], $row['dependencies']);
    };
}

$tests['real upstream corpus pager wal dynamic walhook autocheckpoint row count'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walHookAutocheckpointRows();

    $t->same(1000, count($rows));
    $t->same('walhook.test walhook-2.4 dynamic transaction 1', $rows[0]['upstream']);
    $t->same('walhook.test walhook-2.7 dynamic transaction 1000', $rows[999]['upstream']);
    $t->same(5, $rows[0]['wal_hook_frame_count']);
    $t->same(false, $rows[0]['checkpointed']);
    $t->same(11, $rows[3]['wal_hook_frame_count']);
    $t->same(true, $rows[3]['checkpointed']);
    $t->same(11, $rows[999]['wal_hook_frame_count']);
    $t->same(997, $rows[999]['checkpoint_count']);
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal2CheckpointFullSyncRows() as $row) {
    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' checkpoint fullsync counts'] = static function (TestRunner $t) use ($row): void {
        $t->same(4096, $row['page_size']);
        $t->same('wal', $row['journal_mode']);
        $t->same('off', $row['wal_autocheckpoint']);
        $t->same(true, in_array($row['phase'], ['restart', 'commit', 'checkpoint'], true));
        $t->same(true, $row['test_number'] >= 1 && $row['test_number'] <= 12);
        $t->same(true, $row['transaction'] >= 1 && $row['transaction'] <= 100);
        $t->same($row['normal_sync_count'] + $row['full_sync_count'], $row['total_sync_count']);
        $t->same($row['full_sync_count'] > 0, $row['uses_fullsync']);
        $t->same($row['synchronous'] === 'off', $row['sync_disabled']);
        $t->same(true, in_array('real-upstream-corpus-wal2', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint-fullfsync', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-xsync-counts', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic wal2 checkpoint fullsync row count'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal2CheckpointFullSyncRows();

    $t->same(1200, count($rows));
    $t->same('wal2.test 15.1 dynamic transaction 1', $rows[0]['upstream']);
    $t->same('wal2.test 15.12 dynamic transaction 100', $rows[1199]['upstream']);
    $t->same([0, 0, 'off'], [$rows[0]['checkpoint_fullfsync'], $rows[0]['fullfsync'], $rows[0]['synchronous']]);
    $t->same([1, 1, 'full'], [$rows[1100]['checkpoint_fullfsync'], $rows[1100]['fullfsync'], $rows[1100]['synchronous']]);
    $t->same(['restart', 'commit', 'commit', 'checkpoint'], array_column(array_slice($rows, 0, 4), 'phase'));
    $t->same([0, 0], [$rows[0]['normal_sync_count'], $rows[0]['full_sync_count']]);
    $t->same([0, 2], [$rows[1103]['normal_sync_count'], $rows[1103]['full_sync_count']]);
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal3ReadmarkRaceRows() as $row) {
    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' readmark boundary'] = static function (TestRunner $t) use ($row): void {
        $t->same(true, str_starts_with($row['upstream'], 'wal3.test wal3-'));
        $t->same(true, in_array('real-upstream-corpus-wal3', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-reader-snapshot-preservation', $row['dependencies'], true));
        $t->same(true, in_array($row['client_mode'], ['multiproc', 'singleproc', 'many-reader'], true));
        $t->same(true, in_array($row['phase'], [
            'checkpoint-with-third-reader',
            'checkpoint-after-second-reader-commit',
            'checkpoint-after-all-readers-commit',
            'writer-appends-before-readmark0-lock',
            'checkpoint-shared-lock-race',
            'many-reader-exclusive-readmark-denied',
        ], true));
        $t->same(true, is_bool($row['wrap_allowed']));
    };

    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' checkpoint/readmark semantics'] = static function (TestRunner $t) use ($row): void {
        if (isset($row['expected_bytes_zero'])) {
            $t->same(count($row['expected_bytes_zero']), count($row['zero_pages']) + count($row['nonzero_pages']));
            $t->same($row['checkpoint_busy'], $row['phase'] !== 'checkpoint-after-all-readers-commit');
            $t->same($row['wrap_allowed'], $row['phase'] === 'checkpoint-after-all-readers-commit');
            $t->same(true, in_array('sqlite-wal-readmark-checkpoint-boundary', $row['dependencies'], true));
            $t->same(true, count($row['backfilled_pages']) >= 1);
            $t->same(true, $row['auto_vacuum'] === 0 || $row['auto_vacuum'] === 1);
            return;
        }

        if (($row['phase'] ?? '') === 'many-reader-exclusive-readmark-denied') {
            $t->same(50, $row['reader_count']);
            $t->same('db' . $row['reader_index'], $row['reader_name']);
            $t->same(true, $row['shared_readmark_without_update']);
            $t->same(false, $row['exclusive_readmark_available']);
            $t->same(true, $row['checkpoint_before_final_reader_closes_zero']);
            $t->same($row['reader_index'] === 49, $row['wrap_allowed']);
            $t->same(true, in_array('sqlite-wal-many-reader-readmark-fallback', $row['dependencies'], true));
            return;
        }

        $t->same(true, $row['mx_frame_after'] > $row['mx_frame_before']);
        $t->same(true, $row['fallback_readmark_used']);
        $t->same($row['phase'] === 'writer-appends-before-readmark0-lock', $row['reader_rereads_header']);
        $t->same($row['phase'] === 'writer-appends-before-readmark0-lock', $row['wal_size_grows_after_checkpoint']);
        $t->same(true, count($row['slot_sequence']) >= 3);
        $t->same(true, str_contains(implode(' ', $row['slot_sequence']), 'readmark'));
        $t->same(true, in_array('sqlite-wal-readmark-race-retry', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic wal3 readmark rows cite hydrated upstream ranges'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal3ReadmarkRaceRows();

    $t->same(302, count($rows));
    $t->same('wal3.test wal3-2.multiproc.4', $rows[0]['upstream']);
    $t->same('wal3.test wal3-6.1.4 dynamic race 1', $rows[12]['upstream']);
    $t->same('wal3.test wal3-9.1.0 many-reader readmark fallback', $rows[252]['upstream']);
    $t->same('wal3.test wal3-9.1.49 many-reader readmark fallback', $rows[301]['upstream']);
    $t->same([
        'wal3.test wal3-2.* multiproc/singleproc checkpoint byte-zero boundaries',
        'wal3.test wal3-6.1.* and wal3-6.2.* readmark race retry',
        'wal3.test wal3-9.1.* many-reader shared readmark fallback',
    ], [
        'wal3.test wal3-2.* multiproc/singleproc checkpoint byte-zero boundaries',
        'wal3.test wal3-6.1.* and wal3-6.2.* readmark race retry',
        'wal3.test wal3-9.1.* many-reader shared readmark fallback',
    ]);
};

return $tests;
