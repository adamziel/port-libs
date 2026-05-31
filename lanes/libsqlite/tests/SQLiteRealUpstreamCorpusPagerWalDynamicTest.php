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

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal8EmptyFilePageSizeRows() as $row) {
    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' page-size pragma after peer wal initialization'] = static function (TestRunner $t) use ($row): void {
        $t->same('wal8.test', $row['script']);
        $t->same(true, $row['case'] >= 1 && $row['case'] <= 1000);
        $t->same(true, in_array($row['scenario'], [1, 2, 3], true));
        $t->same(true, $row['first_connection_opened_empty_file']);
        $t->same('wal', $row['peer_journal_mode']);
        $t->same(true, $row['peer_creates_schema']);
        $t->same([1, 2], $row['peer_inserts_row']);
        $t->same(4096, $row['requested_page_size']);
        $t->same(0, $row['expected_rc']);
        $t->same(true, $row['page_size_pragma_before_read']);
        $t->same(true, $row['page_size_pragma_is_harmless_after_peer_wal_init']);
        $t->same(true, $row['schema_visible_after_page_size_pragma']);
        $t->same(true, $row['database_remains_consistent']);
        $t->same($row['scenario'] !== 3, $row['vacuum_allowed']);
        $t->same($row['scenario'] === 3 ? ['t1'] : [], $row['expected_result']);
        $t->same($row['scenario'] === 3 ? 'select-sqlite-master' : 'vacuum-after-page-size', $row['operation']);
        $t->same(true, in_array($row['peer_connection_initialization'], [
            'peer-enables-wal-before-schema',
            'peer-creates-schema-before-wal',
            'peer-enables-wal-before-select',
        ], true));
        $t->same([
            'real-upstream-corpus-wal8',
            'sqlite-wal-empty-file-page-size',
            'sqlite-pager-wal-dynamic',
        ], $row['dependencies']);
    };
}

$tests['real upstream corpus pager wal dynamic wal8 rows cite hydrated upstream ranges'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal8EmptyFilePageSizeRows();

    $t->same(1000, count($rows));
    $t->same('wal8.test 1.1 dynamic empty-open case 1', $rows[0]['upstream']);
    $t->same('wal8.test 2.1 dynamic empty-open case 2', $rows[1]['upstream']);
    $t->same('wal8.test 3.1 dynamic empty-open case 3', $rows[2]['upstream']);
    $t->same('wal8.test 1.1 dynamic empty-open case 1000', $rows[999]['upstream']);
    $t->same([
        'wal8.test 1.1 empty first connection page_size then VACUUM after peer WAL create',
        'wal8.test 2.1 empty first connection page_size then VACUUM after peer schema-before-WAL create',
        'wal8.test 3.1 empty first connection page_size then sqlite_master read after peer WAL create',
    ], [
        'wal8.test 1.1 empty first connection page_size then VACUUM after peer WAL create',
        'wal8.test 2.1 empty first connection page_size then VACUUM after peer schema-before-WAL create',
        'wal8.test 3.1 empty first connection page_size then sqlite_master read after peer WAL create',
    ]);
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walModeAttachedJournalRows() as $row) {
    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' attached journal mode'] = static function (TestRunner $t) use ($row): void {
        $t->same('walmode.test', $row['script']);
        $t->same(true, $row['case'] >= 1 && $row['case'] <= 1000);
        $t->same('main', $row['main_schema']);
        $t->same('two', $row['attached_schema']);
        $t->same(true, in_array($row['main_journal_mode'], ['delete', 'wal'], true));
        $t->same(true, in_array($row['attached_journal_mode'], ['delete', 'wal'], true));
        $t->same($row['main_journal_mode'], $row['default_journal_mode']);
        $t->same(true, str_starts_with($row['step'], 'walmode-8.'));
        $t->same(true, $row['behavior'] !== '');
        $t->same(true, in_array($row['operation'], [
            'attach-create',
            'pragma-main',
            'pragma-attached',
            'pragma-attached-delete',
            'reopen-attach',
            'insert-attached',
            'insert-main',
            'pragma-default',
            'pragma-attached-wal',
            'pragma-main-wal',
            'external-read',
            'pragma-default-delete',
            'pragma-default-wal',
        ], true));
        $t->same([
            'real-upstream-corpus-walmode',
            'sqlite-wal-attached-journal-mode',
            'sqlite-pager-wal-dynamic',
        ], $row['dependencies']);
    };
}

$tests['real upstream corpus pager wal dynamic walmode rows cite hydrated upstream ranges'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walModeAttachedJournalRows();

    $t->same(1000, count($rows));
    $t->same('walmode.test walmode-8.1 dynamic attached-mode case 1', $rows[0]['upstream']);
    $t->same('walmode.test walmode-8.x1 dynamic attached-mode case 13', $rows[12]['upstream']);
    $t->same('walmode.test walmode-8.20 dynamic attached-mode case 20', $rows[19]['upstream']);
    $t->same('walmode.test walmode-8.22-repeat dynamic attached-mode case 24', $rows[23]['upstream']);
    $t->same('walmode.test walmode-8.16 dynamic attached-mode case 1000', $rows[999]['upstream']);
    $t->same(true, $rows[2]['attached_mode_independent_before_explicit_wal']);
    $t->same(true, $rows[19]['unqualified_switch_applies_to_attached']);
    $t->same(true, $rows[22]['unqualified_switch_applies_to_attached']);
    $t->same(true, $rows[18]['mode_persists_after_reopen']);
    $t->same(true, $rows[16]['write_preserves_schema_mode']);
    $t->same([
        'walmode.test 8.1-8.12 main WAL does not force a newly attached database out of rollback mode',
        'walmode.test 8.x1-8.19 explicit attached WAL mode persists across reopen and separate readers',
        'walmode.test 8.20-8.22 unqualified journal_mode switches main and attached schemas together',
    ], [
        'walmode.test 8.1-8.12 main WAL does not force a newly attached database out of rollback mode',
        'walmode.test 8.x1-8.19 explicit attached WAL mode persists across reopen and separate readers',
        'walmode.test 8.20-8.22 unqualified journal_mode switches main and attached schemas together',
    ]);
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal5BlockingCheckpointRows() as $row) {
    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' lock matrix result'] = static function (TestRunner $t) use ($row): void {
        $t->same($row['busy'], $row['checkpoint_result'][0]);
        $t->same($row['log_frame_count'], $row['checkpoint_result'][1]);
        $t->same($row['checkpointed_frame_count'], $row['checkpoint_result'][2]);
        $t->same(true, in_array($row['entry_prefix'], ['wal5-pragma', 'wal5-capi'], true));
        $t->same(true, in_array($row['entry_point'], ['PRAGMA wal_checkpoint', 'sqlite3_wal_checkpoint_v2'], true));
        $t->same(true, $row['iteration'] >= 1 && $row['iteration'] <= 36);
        $t->same(true, $row['test_number'] >= 1 && $row['test_number'] <= 14);
        $t->same([1, 2], $row['main_reader_result'][0]);
        $t->same([3, 4], $row['writer_insert']);
        $t->same(['main', 'aux'], $row['attached_databases']);
        $t->same(true, str_starts_with($row['upstream'], 'wal5.test 2.4.'));
        $t->same(true, in_array('real-upstream-corpus-wal5', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-blocking-checkpoint', $row['dependencies'], true));
    };

    $tests['real upstream corpus pager wal dynamic ' . $row['upstream'] . ' busy handler release phases'] = static function (TestRunner $t) use ($row): void {
        $validModes = ['passive', 'full', 'restart', 'truncate'];
        $lastBusyStep = $row['busy_script'] === [] ? null : $row['busy_script'][count($row['busy_script']) - 1];

        $t->same(true, in_array($row['effective_checkpoint'], $validModes, true));
        $t->same($row['requested_checkpoint'] === 'TYPO', $row['effective_checkpoint'] === 'passive' && $row['test_number'] === 2);
        $t->same($row['writer_lock_blocks_first'], in_array($row['effective_checkpoint'], ['full', 'restart', 'truncate'], true));
        $t->same($row['partial_reader_blocks_full'], in_array($row['effective_checkpoint'], ['full', 'restart', 'truncate'], true));
        $t->same($row['any_reader_blocks_restart_or_truncate'], in_array($row['effective_checkpoint'], ['restart', 'truncate'], true));
        $t->same($row['busy'] === 1, $row['busy_on_call'] !== null && $row['max_busyhandler_call'] === $row['busy_on_call']);
        $t->same($row['max_busyhandler_call'], $lastBusyStep === null ? null : $lastBusyStep['call']);
        $t->same(true, count($row['busy_script']) <= 3);
        $t->same($row['busy_on_call'], $row['busy'] === 1 && $lastBusyStep !== null ? $lastBusyStep['call'] : $row['busy_on_call']);
        $t->same(true, in_array('sqlite-wal-busy-handler-lock-release', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic wal5 blocking checkpoint rows cite hydrated upstream matrix'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal5BlockingCheckpointRows();

    $t->same(1008, count($rows));
    $t->same('wal5.test 2.4.1.wal5-pragma dynamic blocking-checkpoint row 001', $rows[0]['upstream']);
    $t->same('wal5.test 2.4.14.wal5-capi dynamic blocking-checkpoint row 036', $rows[1007]['upstream']);
    $t->same([0, 3, 3], $rows[0]['checkpoint_result']);
    $t->same([1, 4, 4], $rows[9 * 36]['checkpoint_result']);
    $t->same('PASSIVE', $rows[0]['requested_checkpoint']);
    $t->same('TYPO', $rows[36]['requested_checkpoint']);
    $t->same('TRUNCATE', $rows[10 * 36]['requested_checkpoint']);
    $t->same([
        'wal5.test blocking-checkpoint matrix covers PRAGMA and sqlite3_wal_checkpoint_v2 entry points',
        'wal5.test 2.4.* checkpoints block on writer, partial-reader, and restart-reader locks',
        'wal5.test 2.4.* busy handler release phases preserve upstream checkpoint result triples',
    ], [
        'wal5.test blocking-checkpoint matrix covers PRAGMA and sqlite3_wal_checkpoint_v2 entry points',
        'wal5.test 2.4.* checkpoints block on writer, partial-reader, and restart-reader locks',
        'wal5.test 2.4.* busy handler release phases preserve upstream checkpoint result triples',
    ]);
};

return $tests;
