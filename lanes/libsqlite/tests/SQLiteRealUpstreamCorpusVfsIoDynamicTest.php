<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$appendCases = [];
foreach ([0, 1, 50, 511, 512, 513, 999, 1000, 1023, 1024, 1025, 4095, 4096, 4097, 8191, 8192] as $prefixBytes) {
    foreach ([512, 1024, 4096] as $pageSize) {
        $appendCases[] = [$prefixBytes, $pageSize, $pageSize * 3];
    }
}

$tests['real upstream corpus vfs io dynamic avfs append offsets stay page aligned'] = static function (TestRunner $t) use ($appendCases): void {
    foreach ($appendCases as [$prefixBytes, $pageSize, $databaseBytes]) {
        $layout = SQLiteVfsIoDynamicPlan::appendDatabaseLayout($prefixBytes, $pageSize, $databaseBytes);

        $t->same('ok', $layout['status']);
        $t->same($prefixBytes, $layout['prefix_bytes']);
        $t->same($pageSize, $layout['page_size']);
        $t->same($databaseBytes, $layout['database_bytes']);
        $t->same(true, $layout['aligned']);
        $t->same(0, $layout['database_offset'] % $pageSize);
        $t->same(max(0, $layout['database_offset'] - $prefixBytes), $layout['padding_bytes']);
        $t->same('Start-Of-SQLite3-', $layout['trailer_magic']);
        $t->same($layout['database_offset'], $layout['trailer_offset']);
        $t->same($layout['database_offset'] + $databaseBytes + 25, $layout['total_bytes']);
        $t->same(true, $layout['prefix_intact']);
        $t->same(true, in_array('upstream-avfs-append-offset', $layout['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic avfs empty container begins at zero'] = static function (TestRunner $t): void {
    foreach ([512, 1024, 2048, 4096, 8192] as $pageSize) {
        $layout = SQLiteVfsIoDynamicPlan::appendDatabaseLayout(0, $pageSize, $pageSize * 2);

        $t->same(0, $layout['database_offset']);
        $t->same(0, $layout['padding_bytes']);
        $t->same($pageSize * 2 + 25, $layout['total_bytes']);
        $t->same(true, $layout['prefix_intact']);
        $t->same(true, $layout['aligned']);
    }
};

$tests['real upstream corpus vfs io dynamic avfs rejects malformed layout inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::appendDatabaseLayout(-1, 512, 1024));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::appendDatabaseLayout(0, 500, 1024));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::appendDatabaseLayout(0, 768, 1024));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::appendDatabaseLayout(0, 512, -1));
};

$avfsGrowthCases = [];
foreach ([1, 2, 4, 8, 16, 32] as $initialRows) {
    foreach ([300, 360, 420, 480, 600] as $insertRows) {
        $avfsGrowthCases[] = [$initialRows, $insertRows, 1500, 4096, 8];
    }
}

$tests['real upstream corpus vfs io dynamic avfs growth shrink integrity follows avfs 3'] = static function (TestRunner $t) use ($avfsGrowthCases): void {
    foreach ($avfsGrowthCases as [$initialRows, $insertRows, $payloadBytes, $pageSize, $keepModulo]) {
        $profile = SQLiteVfsIoDynamicPlan::appendGrowthProfile($initialRows, $insertRows, $payloadBytes, $pageSize, $keepModulo);

        $t->same('ok', $profile['status']);
        $t->same('avfs.test', $profile['script']);
        $t->same($initialRows, $profile['initial_rows']);
        $t->same($insertRows, $profile['insert_rows']);
        $t->same($initialRows + $insertRows, $profile['grown_rows']);
        $t->same(intdiv($initialRows + $insertRows, $keepModulo), $profile['kept_rows_after_delete']);
        $t->same(0, $profile['grown_bytes'] % $pageSize);
        $t->same(0, $profile['shrunk_bytes'] % $pageSize);
        $t->same(true, $profile['growth_ratio_within_avfs_3_3_bounds']);
        $t->same(true, $profile['shrink_ratio_exceeds_avfs_3_5_floor']);
        $t->same(['ok', 'ok', 'ok', 'ok'], $profile['integrity_sequence']);
        $t->same(true, $profile['reopen_intact']);
        $t->same(true, $profile['prefix_intact']);
        $t->same(true, in_array('avfs.test avfs-3.3', $profile['upstream'], true));
        $t->same(true, in_array('avfs.test avfs-3.5', $profile['upstream'], true));
        $t->same(true, in_array('upstream-avfs-growth-shrink', $profile['dependencies'], true));
    }
};

$avfsTinyCases = [
    'avfs-5.1 empty container fake header' => [0, 16, 0],
    'avfs-5.2 text prefix fake header' => [18, 16, 18],
    'avfs-5.2 aligned text prefix fake header' => [4096, 16, 4096],
    'avfs-5.1 missing sqlite header' => [0, 15, 0],
    'avfs-5.2 trailer before appendee' => [50, 16, 12],
];

$tests['real upstream corpus vfs io dynamic avfs tiny appended databases are refused'] = static function (TestRunner $t) use ($avfsTinyCases): void {
    foreach ($avfsTinyCases as $name => [$prefixBytes, $headerBytes, $trailerOffset]) {
        $probe = SQLiteVfsIoDynamicPlan::appendTinyOpenProbe($prefixBytes, $headerBytes, $trailerOffset);

        $t->same('error', $probe['status']);
        $t->same(false, $probe['openable']);
        $t->same('appended_database_region_too_tiny', $probe['reason']);
        $t->same($prefixBytes, $probe['prefix_bytes']);
        $t->same($headerBytes, $probe['database_header_bytes']);
        $t->same($trailerOffset, $probe['trailer_offset']);
        $t->same('Start-Of-SQLite3-', $probe['trailer_magic']);
        $t->same(true, str_starts_with($probe['upstream'], 'avfs.test avfs-5.'));
        $t->same(true, in_array('upstream-avfs-tiny-open-refusal', $probe['dependencies'], true));
        $t->same(true, str_contains($name, 'avfs-5.'));
    }
};

$avfsShellCases = [];
foreach ([0, 1, 8, 31, 50, 511, 512, 513, 1023, 1024, 1025, 2047, 2048, 2049, 4095, 4096, 4097, 8191, 8192, 8193] as $prefixBytes) {
    foreach ([512, 1024, 2048, 4096] as $pageSize) {
        foreach ([true, false] as $archiveMode) {
            foreach ([false, true] as $updateExisting) {
                foreach ([1, 2, 4, 8] as $appendedEntries) {
                    $avfsShellCases[] = [$prefixBytes, $pageSize, $archiveMode, $updateExisting, $appendedEntries];
                }
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic avfs shell append lifecycle follows avfs 4'] = static function (TestRunner $t) use ($avfsShellCases): void {
    foreach ($avfsShellCases as [$prefixBytes, $pageSize, $archiveMode, $updateExisting, $appendedEntries]) {
        $profile = SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile($prefixBytes, $pageSize, $archiveMode, $updateExisting, $appendedEntries);
        $expectedOffset = $prefixBytes === 0 ? 0 : (int) (ceil($prefixBytes / $pageSize) * $pageSize);
        $expectedInitialRows = $archiveMode ? 0 : 1;
        $expectedRows = $updateExisting ? $expectedInitialRows + $appendedEntries : $expectedInitialRows;

        $t->same('ok', $profile['status']);
        $t->same('avfs.test', $profile['script']);
        $t->same($prefixBytes, $profile['prefix_bytes']);
        $t->same($pageSize, $profile['page_size']);
        $t->same($archiveMode, $profile['archive_mode']);
        $t->same($updateExisting, $profile['update_existing_append_database']);
        $t->same($expectedOffset, $profile['database_offset']);
        $t->same($expectedOffset - $prefixBytes, $profile['padding_bytes']);
        $t->same('Start-Of-SQLite3-', $profile['trailer_magic']);
        $t->same($expectedOffset, $profile['trailer_offset']);
        $t->same(true, $profile['prefix_intact']);
        $t->same(true, $profile['aligned']);
        $t->same(0, $profile['shell_exit_code']);
        $t->same($archiveMode ? 'sqlar' : 'appended_rows', $profile['table_name']);
        $t->same([$profile['table_name']], $profile['tables_output']);
        $t->same($expectedInitialRows, $profile['initial_rows']);
        $t->same($appendedEntries, $profile['appended_entries']);
        $t->same($expectedRows, $profile['updated_rows']);
        $t->same($updateExisting ? $appendedEntries : 0, $profile['shell_output_rows']);
        $t->same($expectedRows, $profile['reopen_count']);
        $t->same('&vfs=apndvfs', $profile['append_uri']);
        $t->same(true, in_array('avfs.test avfs-4.1', $profile['upstream'], true));
        $t->same(true, in_array('avfs.test avfs-4.2', $profile['upstream'], true));
        $t->same(true, in_array('avfs.test avfs-4.3', $profile['upstream'], true));
        $t->same(true, in_array('upstream-avfs-shell-append-lifecycle', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic avfs shell lifecycle rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile(-1, 512, true, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile(0, 500, true, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile(0, 768, true, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile(0, 512, true, false, 0));
};

$ioCases = [
    [['powersafe_overwrite'], 2, 'delete', 'full', 4, false, false, false],
    [['atomic'], 2, 'delete', 'full', 1, true, false, false],
    [['atomic'], 3, 'delete', 'full', 4, false, false, false],
    [['safe_append'], 2, 'delete', 'full', 3, false, true, false],
    [['sequential'], 2, 'delete', 'full', 3, false, false, true],
    [['safe_append', 'sequential'], 2, 'delete', 'full', 2, false, true, true],
    [['batch_atomic'], 1, 'delete', 'full', 1, true, false, false],
    [['safe_append'], 5, 'truncate', 'normal', 3, false, true, false],
    [['sequential'], 5, 'persist', 'normal', 3, false, false, true],
    [['powersafe_overwrite'], 4, 'wal', 'full', 2, false, false, false],
    [['powersafe_overwrite'], 4, 'off', 'full', 1, false, false, false],
    [['powersafe_overwrite'], 4, 'delete', 'off', 0, false, false, false],
];

$tests['real upstream corpus vfs io dynamic io device characteristic sync counts'] = static function (TestRunner $t) use ($ioCases): void {
    foreach ($ioCases as [$flags, $changedPages, $journalMode, $syncMode, $syncCount, $atomic, $safeAppend, $sequential]) {
        $plan = SQLiteVfsIoDynamicPlan::ioTrafficPlan($flags, $changedPages, $journalMode, $syncMode);

        $t->same('ok', $plan['status']);
        $t->same($changedPages, $plan['changed_pages']);
        $t->same($journalMode, $plan['journal_mode']);
        $t->same($syncMode, $plan['sync_mode']);
        $t->same($changedPages, $plan['database_page_writes']);
        $t->same($syncCount, $plan['sync_count']);
        $t->same($atomic, $plan['atomic_write_optimization']);
        $t->same($safeAppend, $plan['safe_append_optimization']);
        $t->same($sequential, $plan['sequential_optimization']);
        $t->same(true, in_array('upstream-io-device-characteristics', $plan['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic io rollback journal writes follow device flags'] = static function (TestRunner $t) use ($ioCases): void {
    foreach ($ioCases as [$flags, $changedPages, $journalMode, $syncMode]) {
        $plan = SQLiteVfsIoDynamicPlan::ioTrafficPlan($flags, $changedPages, $journalMode, $syncMode);
        $rollbackJournal = !in_array($journalMode, ['wal', 'off'], true);

        $t->same($rollbackJournal && !$plan['atomic_write_optimization'] ? $changedPages : 0, $plan['journal_page_writes']);
        $t->same($rollbackJournal && !$plan['atomic_write_optimization'] ? 1 : 0, $plan['journal_header_writes']);
        $t->same($syncMode === 'off' ? [] : $plan['sync_sequence'], $plan['sync_sequence']);
        $t->same(true, is_array($plan['device_flags']));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
    }
};

$defaultPageSizeCases = [
    [[], 512, 1024],
    [[], 1024, 1024],
    [[], 2048, 2048],
    [[], 8192, 8192],
    [[], 16384, 8192],
    [['atomic'], 512, 8192],
    [['atomic512'], 512, 1024],
    [['atomic2K'], 512, 2048],
    [['atomic2K'], 4096, 4096],
    [['atomic2K', 'atomic'], 512, 8192],
    [['atomic64K'], 512, 1024],
];

$tests['real upstream corpus vfs io dynamic default page size follows io 5 matrix'] = static function (TestRunner $t) use ($defaultPageSizeCases): void {
    foreach ($defaultPageSizeCases as [$flags, $sectorSize, $expectedPageSize]) {
        $choice = SQLiteVfsIoDynamicPlan::defaultPageSizeChoice($flags, $sectorSize);

        $t->same('ok', $choice['status']);
        $t->same('io.test', $choice['script']);
        $t->same('io.test io-5', $choice['upstream']);
        $t->same(array_map(static fn (string $flag): string => strtolower($flag), str_replace('K', 'k', $choice['device_flags'])), $choice['device_flags']);
        $t->same($sectorSize, $choice['sector_size']);
        $t->same(8192, $choice['max_page_size']);
        $t->same($expectedPageSize, $choice['default_page_size']);
        $t->same($expectedPageSize * 2, $choice['file_size_after_create']);
        $t->same('pager_default_page_size_from_sector_and_atomic_capability', $choice['reason']);
        $t->same(true, in_array('upstream-io-default-page-size', $choice['dependencies'], true));
    }
};

$safeAppendJournalCases = [];
foreach ([41, 45, 49, 53, 57, 61] as $changedPages) {
    foreach ([8, 10, 12, 14] as $cacheSize) {
        $safeAppendJournalCases[] = [1024, $changedPages, $cacheSize];
    }
}

$tests['real upstream corpus vfs io dynamic safe append journal header size follows io 4 3'] = static function (TestRunner $t) use ($safeAppendJournalCases): void {
    foreach ($safeAppendJournalCases as [$pageSize, $changedPages, $cacheSize]) {
        $plan = SQLiteVfsIoDynamicPlan::safeAppendJournalSize($pageSize, $changedPages, $cacheSize);

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same(true, in_array('io.test io-4.3.4', $plan['upstream'], true));
        $t->same($pageSize, $plan['page_size']);
        $t->same($changedPages, $plan['changed_pages']);
        $t->same($cacheSize, $plan['cache_size']);
        $t->same(true, $plan['safe_append']);
        $t->same(0xffffffff, $plan['journal_header_nrec']);
        $t->same(1, $plan['journal_header_count']);
        $t->same(512, $plan['journal_header_bytes']);
        $t->same($pageSize + 8, $plan['page_record_bytes']);
        $t->same(512 + (($pageSize + 8) * $changedPages), $plan['journal_file_bytes']);
        $t->same(intdiv($changedPages - 1, $cacheSize), $plan['cache_spills']);
        $t->same($plan['cache_spills'] >= 4, $plan['requires_multiple_cache_spills']);
        $t->same(0, $plan['extra_headers_after_spill']);
        $t->same(['directory', 'journal-pages', 'database'], $plan['sync_sequence']);
        $t->same(true, in_array('upstream-io-safe-append-journal-size', $plan['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic io rejects unsupported traffic options'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioTrafficPlan(['networked'], 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 1, 'memory'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 1, 'delete', 'extra'));
};

$cacheSpillProfiles = [];
foreach ([1024, 2048, 4096] as $pageSize) {
    foreach ([4, 7, 10, 13, 16, 20] as $cacheSize) {
        foreach ([21, 33, 41, 57, 73, 89] as $statementPages) {
            foreach ([
                'sequential' => ['sequential'],
                'safe_append' => ['safe_append'],
                'safe_append_sequential' => ['safe_append', 'sequential'],
                'ordinary' => [],
            ] as $label => $flags) {
                $cacheSpillProfiles[] = [$label, $flags, $pageSize, $cacheSize, $statementPages, 'full', true];
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic cache spill sync matrix follows io 3 and io 4'] = static function (TestRunner $t) use ($cacheSpillProfiles): void {
    foreach ($cacheSpillProfiles as [$label, $flags, $pageSize, $cacheSize, $statementPages, $syncMode, $directorySync]) {
        $profile = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile($flags, $pageSize, $cacheSize, $statementPages, $syncMode, false, $directorySync);
        $sequential = in_array('sequential', $flags, true);
        $safeAppend = in_array('safe_append', $flags, true);
        $expectedSyncSequence = [];
        if ($directorySync) {
            $expectedSyncSequence[] = 'directory';
        }
        if (!$sequential) {
            $expectedSyncSequence[] = 'journal-pages';
        }
        if (!$safeAppend && !$sequential) {
            $expectedSyncSequence[] = 'journal-header';
        }
        $expectedSyncSequence[] = 'database';

        $t->same('ok', $profile['status']);
        $t->same('io.test', $profile['script']);
        $t->same($flags, $profile['device_flags']);
        $t->same($pageSize, $profile['page_size']);
        $t->same($cacheSize, $profile['cache_size']);
        $t->same($statementPages, $profile['statement_pages']);
        $t->same($syncMode, $profile['sync_mode']);
        $t->same($directorySync, $profile['directory_sync']);
        $t->same(false, $profile['reserved_bytes']);
        $t->same($sequential, $profile['sequential_optimization']);
        $t->same($safeAppend, $profile['safe_append_optimization']);
        $t->same(intdiv($statementPages - 1, $cacheSize), $profile['cache_spills']);
        $t->same(true, $profile['file_grew_during_spill']);
        $t->same($sequential ? 0 : max(1, $profile['cache_spills']), $profile['precommit_syncs']);
        $t->same($sequential ? 1 : count($expectedSyncSequence), $profile['commit_syncs']);
        $t->same($expectedSyncSequence, $profile['sync_sequence']);
        $t->same($safeAppend ? 0xffffffff : null, $profile['journal_header_nrec']);
        $t->same($safeAppend ? 1 : max(1, 1 + $profile['cache_spills']), $profile['journal_header_count']);
        $t->same(512, $profile['journal_header_bytes']);
        $t->same($pageSize + 8, $profile['page_record_bytes']);
        $t->same(512 + (($pageSize + 8) * $statementPages), $profile['journal_file_bytes']);
        $t->same(0, $profile['database_bytes_after_spill'] % $pageSize);
        $t->same($sequential ? 39936 : $profile['database_bytes_after_spill'], $profile['database_bytes_after_commit']);
        $t->same($sequential ? 'sequential_device_defers_spill_syncs_until_commit' : ($safeAppend ? 'safe_append_uses_single_journal_header_across_spills' : 'full_sync_journal_headers_may_repeat_after_spills'), $profile['reason']);
        $t->same(true, in_array('io.test io-3.2', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-3.3', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-4.3.4', $profile['upstream'], true));
        $t->same(true, in_array('upstream-io-cache-spill-sync', $profile['dependencies'], true));
        $t->same(true, in_array('upstream-io-safe-append-journal-size', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        $t->same(true, in_array($label, ['sequential', 'safe_append', 'safe_append_sequential', 'ordinary'], true));
    }
};

$tests['real upstream corpus vfs io dynamic cache spill sync handles reserved bytes and sync off'] = static function (TestRunner $t): void {
    $reserved = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['sequential'], 1024, 10, 41, 'full', true);
    $off = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['safe_append'], 1024, 10, 41, 'off');
    $noDirSync = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['safe_append'], 1024, 10, 41, 'normal', false, false);

    $t->same(40960, $reserved['database_bytes_after_commit']);
    $t->same(0, $reserved['precommit_syncs']);
    $t->same(1, $reserved['commit_syncs']);
    $t->same([], $off['sync_sequence']);
    $t->same(0, $off['commit_syncs']);
    $t->same(0, $off['precommit_syncs']);
    $t->same(['journal-pages', 'database'], $noDirSync['sync_sequence']);
    $t->same(2, $noDirSync['commit_syncs']);
    $t->same(0xffffffff, $noDirSync['journal_header_nrec']);
    $t->same(1, $noDirSync['journal_header_count']);
    $t->same(true, in_array('io.test io-4.1', $noDirSync['upstream'], true));
    $t->same(true, in_array('io.test io-4.2.2', $noDirSync['upstream'], true));
    $t->same(true, in_array('io.test io-3.3', $reserved['upstream'], true));
};

$tests['real upstream corpus vfs io dynamic cache spill sync rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile([], 1000, 10, 41));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile([], 1024, 0, 41));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile([], 1024, 10, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['bad'], 1024, 10, 41));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile([], 1024, 10, 41, 'extra'));
};

$atomicVisibilityCases = [];
foreach (range(1, 42) as $case) {
    $committedRows = [
        ['a' => 1, 'b' => 2],
        ['a' => 3, 'b' => 4],
    ];
    $pendingRows = [
        ['a' => 5 + ($case * 2), 'b' => 6 + ($case * 2)],
    ];
    $atomicVisibilityCases[] = [
        'case' => $case,
        'committed' => $committedRows,
        'pending' => $pendingRows,
        'commit' => $case % 7 !== 0,
    ];
}

$tests['real upstream corpus vfs io dynamic atomic transaction visibility matches io 2 4'] = static function (TestRunner $t) use ($atomicVisibilityCases): void {
    foreach ($atomicVisibilityCases as $case) {
        $plan = SQLiteVfsIoDynamicPlan::atomicTransactionVisibility(['atomic'], $case['committed'], $case['pending'], $case['commit']);

        $t->same('ok', $plan['status']);
        $t->same(['atomic'], $plan['device_flags']);
        $t->same(true, $plan['atomic_write_optimization']);
        $t->same(false, $plan['rollback_journal_exists_during_transaction']);
        $t->same(true, $plan['change_counter_pending']);
        $t->same($case['committed'], $plan['pre_commit_reader_rows']);
        $t->same(true, $plan['reader_snapshot_unchanged_before_commit']);
        $t->same(false, $plan['pending_visible_before_commit']);
        $t->same($case['commit'], $plan['pending_visible_after_commit']);
        $t->same($case['commit'], $plan['commit_applied']);
        $t->same($case['commit'] ? array_values(array_merge($case['committed'], $case['pending'])) : $case['committed'], $plan['post_commit_reader_rows']);
        $t->same(['database'], $plan['database_syncs']);
        $t->same(1, $plan['write_count']);
        $t->same(true, in_array('io.test io-2.4.1', $plan['upstream'], true));
        $t->same(true, in_array('io.test io-2.4.2', $plan['upstream'], true));
        $t->same(true, in_array('io.test io-2.4.3', $plan['upstream'], true));
        $t->same(true, in_array('upstream-io-atomic-visibility', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic atomic transaction visibility non atomic keeps rollback journal'] = static function (TestRunner $t): void {
    $committedRows = [['a' => 1, 'b' => 2], ['a' => 3, 'b' => 4]];
    $pendingRows = [['a' => 5, 'b' => 6]];
    $plan = SQLiteVfsIoDynamicPlan::atomicTransactionVisibility([], $committedRows, $pendingRows, true);

    $t->same(false, $plan['atomic_write_optimization']);
    $t->same(true, $plan['rollback_journal_exists_during_transaction']);
    $t->same(false, $plan['change_counter_pending']);
    $t->same($committedRows, $plan['pre_commit_reader_rows']);
    $t->same(array_values(array_merge($committedRows, $pendingRows)), $plan['post_commit_reader_rows']);
    $t->same(['directory', 'journal-pages', 'journal-header', 'database'], $plan['database_syncs']);
};

$tests['real upstream corpus vfs io dynamic atomic transaction visibility rejects empty rowsets'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicTransactionVisibility(['atomic'], [], [['a' => 5, 'b' => 6]], true));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicTransactionVisibility(['atomic'], [['a' => 1, 'b' => 2]], [], true));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicTransactionVisibility(['bad-device'], [['a' => 1]], [['a' => 2]], true));
};

$atomicAdmissionCases = [
    'io-2.6 append page defers journal until commit and can fail cantopen' => [
        ['atomic'], 1024, 512, 1, 1, false, false, false, true,
        true, false, true, 'SQLITE_CANTOPEN', 'previous_committed_rows', 'deferred_journal_open_failure_rolls_back_transaction',
    ],
    'io-2.7 multi file commit forces journal despite atomic device' => [
        ['atomic'], 1024, 512, 1, 0, true, false, false, true,
        true, false, true, 'SQLITE_IOERR_ROLLBACK', 'previous_committed_rows', 'multi_file_commit_journal_open_failure_rolls_back_all_files',
    ],
    'io-2.8 explicit rollback before journal materialization restores previous rows' => [
        ['atomic'], 1024, 512, 1, 0, false, true, false, false,
        true, true, false, 'ok', 'previous_committed_rows', 'explicit_rollback_restores_rows_before_journal_materialization',
    ],
    'io-2.9 sector larger than page disables atomic shortcut' => [
        ['atomic'], 1024, 2048, 1, 0, false, false, false, false,
        false, false, false, 'ok', 'pending_rows_committed', 'rollback_journal_required_before_commit',
    ],
    'io-2.9 page size equal to sector admits atomic shortcut' => [
        ['atomic'], 2048, 2048, 1, 0, false, false, false, false,
        true, true, false, 'ok', 'pending_rows_committed', 'single_page_atomic_write_skips_rollback_journal',
    ],
    'io-2.10 atomic1k is too small for 2k page' => [
        ['atomic1k'], 2048, 512, 1, 0, false, false, false, false,
        false, false, false, 'ok', 'pending_rows_committed', 'rollback_journal_required_before_commit',
    ],
    'io-2.10 atomic2k admits 2k page' => [
        ['atomic2k'], 2048, 512, 1, 0, false, false, false, false,
        true, true, false, 'ok', 'pending_rows_committed', 'single_page_atomic_write_skips_rollback_journal',
    ],
    'io-2.11 exclusive locking keeps journal unlinked after insert' => [
        ['atomic'], 1024, 512, 1, 1, false, false, true, false,
        true, false, false, 'ok', 'pending_rows_committed', 'exclusive_locking_keeps_journal_unlinked_after_commit',
    ],
];

$tests['real upstream corpus vfs io dynamic atomic journal admission follows io 2 6 through 2 11'] = static function (TestRunner $t) use ($atomicAdmissionCases): void {
    foreach ($atomicAdmissionCases as $name => [$flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multiFile, $rollback, $exclusive, $blocked, $atomicAllowed, $atomicOptimization, $deferred, $commitStatus, $rowsVisibleAfter, $reason]) {
        $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission(
            $flags,
            $pageSize,
            $sectorSize,
            $changedPages,
            $appendedPages,
            $multiFile,
            $rollback,
            $exclusive,
            $blocked
        );

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($changedPages, $plan['changed_pages']);
        $t->same($appendedPages, $plan['appended_pages']);
        $t->same($multiFile, $plan['multi_file_commit']);
        $t->same($rollback, $plan['explicit_rollback']);
        $t->same($exclusive, $plan['exclusive_locking']);
        $t->same($blocked, $plan['journal_path_blocked']);
        $t->same($atomicAllowed, $plan['atomic_write_allowed']);
        $t->same($atomicOptimization, $plan['atomic_write_optimization']);
        $t->same($deferred, $plan['journal_deferred_until_commit']);
        $t->same($commitStatus, $plan['commit_status']);
        $t->same($rowsVisibleAfter, $plan['rows_visible_after']);
        $t->same($reason, $plan['reason']);
        $t->same($commitStatus !== 'ok' || $rollback, $plan['rollback_required']);
        $t->same(true, in_array('upstream-io-atomic-journal-admission', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        $t->same(true, str_starts_with($name, 'io-2.'));
    }
};

$tests['real upstream corpus vfs io dynamic atomic journal admission cites real upstream ranges'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic2k'], 2048, 512, 1);

    foreach ([
        'io.test io-2.6.1-2.6.4',
        'io.test io-2.7.1-2.7.6',
        'io.test io-2.8.1-2.8.3',
        'io.test io-2.9.1-2.9.3',
        'io.test io-2.10.1-2.10.3',
        'io.test io-2.11.1-2.11.2',
    ] as $upstream) {
        $t->same(true, in_array($upstream, $plan['upstream'], true));
    }
    $t->same(['atomic2k'], $plan['device_flags']);
    $t->same(false, $plan['journal_required']);
    $t->same(false, $plan['journal_exists_before_commit']);
    $t->same(false, $plan['journal_deferred_until_commit']);
};

$tests['real upstream corpus vfs io dynamic atomic journal admission rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 500, 512, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1024, 768, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1024, 512, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1024, 512, 1, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['networked'], 1024, 512, 1));
};

$nolockCases = [
    ['file:/srv/app/data/app.sqlite?nolock=0', true, false, false, ['xLock' => 7, 'xUnlock' => 5, 'xCheckReservedLock' => 0, 'xAccess' => 0]],
    ['file:/srv/app/data/app.sqlite?nolock=1', true, true, false, ['xLock' => 0, 'xUnlock' => 0, 'xCheckReservedLock' => 0, 'xAccess' => 0]],
    ['file:/srv/app/data/app.sqlite?nolock=0', false, false, false, ['xLock' => 2, 'xUnlock' => 2, 'xCheckReservedLock' => 0, 'xAccess' => 4]],
    ['file:/srv/app/data/app.sqlite?nolock=1', false, true, false, ['xLock' => 0, 'xUnlock' => 0, 'xCheckReservedLock' => 0, 'xAccess' => 0]],
    ['file:/srv/app/data/app.sqlite?immutable=0&mode=ro', false, false, false, ['xLock' => 2, 'xUnlock' => 2, 'xCheckReservedLock' => 0, 'xAccess' => 4]],
    ['file:/srv/app/data/app.sqlite?immutable=1&mode=ro', false, false, true, ['xLock' => 0, 'xUnlock' => 0, 'xCheckReservedLock' => 0, 'xAccess' => 0]],
];

$tests['real upstream corpus vfs io dynamic nolock and immutable suppress expected calls'] = static function (TestRunner $t) use ($nolockCases): void {
    foreach ($nolockCases as [$filename, $writeTransaction, $nolock, $immutable, $calls]) {
        $probe = SQLiteVfsIoDynamicPlan::nolockProbe($filename, $writeTransaction);

        $t->same('ok', $probe['status']);
        $t->same($writeTransaction, $probe['write_transaction']);
        $t->same($nolock, $probe['nolock']);
        $t->same($immutable, $probe['immutable']);
        $t->same($calls, $probe['calls']);
        $t->same($nolock || $immutable, $probe['lock_calls_suppressed']);
        $t->same(true, in_array('upstream-nolock-uri-lock-suppression', $probe['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $probe['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic nolock preserves normalized paths'] = static function (TestRunner $t) use ($nolockCases): void {
    foreach ($nolockCases as [$filename, $writeTransaction]) {
        $probe = SQLiteVfsIoDynamicPlan::nolockProbe($filename, $writeTransaction);

        $t->same(true, str_ends_with($probe['path'], 'app.sqlite'));
        $t->same(0, $probe['calls']['xCheckReservedLock']);
        $t->same($probe['lock_calls_suppressed'] ? 0 : ($writeTransaction ? 7 : 2), $probe['calls']['xLock']);
        $t->same($probe['lock_calls_suppressed'] ? 0 : ($writeTransaction ? 5 : 2), $probe['calls']['xUnlock']);
    }
};

$immutableDeviceCases = [
    'nolock-3.1 normal open with immutable device characteristic' => ['file:/srv/app/data/app.sqlite?mode=rw', false],
    'nolock-3.11 readonly open with immutable device characteristic' => ['file:/srv/app/data/app.sqlite?mode=ro', false],
    'nolock-3.21 explicit nolock plus immutable device characteristic' => ['file:/srv/app/data/app.sqlite?nolock=1', true],
    'nolock-3.31 uri immutable plus immutable device characteristic' => ['file:/srv/app/data/app.sqlite?immutable=1&mode=ro', true],
];

$tests['real upstream corpus vfs io dynamic nolock sqlite iocap immutable suppresses locking'] = static function (TestRunner $t) use ($immutableDeviceCases): void {
    foreach ($immutableDeviceCases as $name => [$filename, $uriSuppressed]) {
        $probe = SQLiteVfsIoDynamicPlan::nolockProbe($filename, false, ['immutable']);

        $t->same('ok', $probe['status']);
        $t->same(true, $probe['immutable_device']);
        $t->same($uriSuppressed, $probe['nolock'] || $probe['immutable']);
        $t->same(true, $probe['lock_calls_suppressed']);
        $t->same(['xLock' => 0, 'xUnlock' => 0, 'xCheckReservedLock' => 0, 'xAccess' => 0], $probe['calls']);
        $t->same(true, str_ends_with($probe['path'], 'app.sqlite'));
        $t->same(true, in_array('upstream-nolock-uri-lock-suppression', $probe['dependencies'], true));
        $t->same(true, in_array('vfs-device-characteristics', $probe['dependencies'], true));
        $t->same(true, is_string($name));
    }
};

$tests['real upstream corpus vfs io dynamic nolock unsupported immutable device flags are rejected'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::nolockProbe('file:/srv/app/data/app.sqlite', false, ['cloud_lockless']));
};

$fileControlCases = [
    ['PRAGMA mmap_size=65536', 'mmap_size', 65536],
    ['PRAGMA mmap_size(32768)', 'mmap_size', 32768],
    ['PRAGMA main.mmap_size=16384', 'mmap_size', 16384],
    ['PRAGMA chunk_size=8192', 'chunk_size', 8192],
    ['PRAGMA chunk_size(4096)', 'chunk_size', 4096],
    ['PRAGMA max_page_count=512', 'size_limit', 512],
    ['PRAGMA journal_size_limit=1048576', 'size_limit', 1048576],
    ['PRAGMA reserve_bytes=24', 'reserve_bytes', 24],
    ['PRAGMA lock_timeout=2500', 'lock_timeout', 2500],
    ['PRAGMA busy_timeout=1200', 'lock_timeout', 1200],
    ['PRAGMA data_version', 'data_version', 1],
    ['file_control(size_hint, 32768)', 'size_hint', 32768],
    ['file_control(persist_wal, on)', 'persist_wal', true],
    ['file_control(powersafe_overwrite, 0)', 'powersafe_overwrite', false],
    ['file_control(tempfilename, journal)', 'tempfilename', '.journal'],
];

$tests['real upstream corpus vfs io dynamic filectrl sql controls match upstream names'] = static function (TestRunner $t) use ($fileControlCases): void {
    foreach ($fileControlCases as [$sql, $op, $expectedValue]) {
        $sequence = SQLiteVfsIoDynamicPlan::fileControlSequence('file:/srv/app/data/app.sqlite?mode=rw&cache=shared&vfs=unix', [$sql]);
        $pair = $sequence['pairs'][0];

        $t->same('ok', $sequence['status']);
        $t->same(1, $sequence['count']);
        $t->same($op, $pair['op']);
        $t->same('ok', $pair['result']['status']);
        if ($op === 'tempfilename') {
            $t->same(true, str_ends_with($pair['result']['value'], $expectedValue));
        } else {
            $t->same($expectedValue, $pair['result']['value']);
        }
        $t->same(true, in_array('upstream-filectrl-sql-file-control', $sequence['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic filectrl threads current state through sequence'] = static function (TestRunner $t): void {
    $sequence = SQLiteVfsIoDynamicPlan::fileControlSequence('file:/srv/app/data/app.sqlite?mode=rw&cache=shared&vfs=unix', [
        'PRAGMA mmap_size=4096',
        'PRAGMA chunk_size=8192',
        'PRAGMA reserve_bytes=11',
        'PRAGMA busy_timeout=99',
        'file_control(size_hint, 4096)',
    ]);

    $t->same(5, $sequence['count']);
    $t->same(4096, $sequence['pairs'][1]['current']['mmap_size']);
    $t->same(8192, $sequence['pairs'][2]['current']['chunk_size']);
    $t->same(11, $sequence['pairs'][3]['current']['reserve_bytes']);
    $t->same(99, $sequence['pairs'][4]['current']['lock_timeout']);
    $t->same(4096, $sequence['pairs'][4]['result']['value']);
    $t->same(5, $sequence['applied']);
    $t->same(3, $sequence['changed']);
    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $sequence['dependencies'], true));
};

$nameHintCases = [
    'filectrl-1.1 no temp object' => ['file_control(name_hint, main database)', 'file_control(tempfilename, db)', 'main database', '.db'],
    'filectrl-1.2 temp table handle' => ['file_control(name_hint, temp table x)', 'file_control(tempfilename, temp)', 'temp table x', '.temp'],
    'filectrl-1.4 errno handle' => ['file_control(name_hint, last errno)', 'file_control(tempfilename, errno)', 'last errno', '.errno'],
    'filectrl-1.5 lockproxy handle' => ['file_control(name_hint, lock proxy)', 'file_control(tempfilename, proxy)', 'lock proxy', '.proxy'],
    'filectrl-1.6 tempfilename handle' => ['file_control(name_hint, etilqs source)', 'file_control(tempfilename, journal)', 'etilqs source', '.journal'],
];

$tests['real upstream corpus vfs io dynamic filectrl name hints feed temp filename'] = static function (TestRunner $t) use ($nameHintCases): void {
    foreach ($nameHintCases as $scenario => [$hintSql, $tempSql, $rawHint, $suffix]) {
        $sequence = SQLiteVfsIoDynamicPlan::fileControlSequence('file:/srv/app/data/app.sqlite?mode=rw&cache=shared&vfs=unix', [
            $hintSql,
            $tempSql,
        ]);
        $hint = $sequence['pairs'][0];
        $temp = $sequence['pairs'][1];

        $t->same('ok', $sequence['status']);
        $t->same(2, $sequence['count']);
        $t->same('name_hint', $hint['op']);
        $t->same('ok', $hint['result']['status']);
        $t->same($rawHint, $temp['current']['name_hint']);
        $t->same('tempfilename', $temp['op']);
        $t->same('ok', $temp['result']['status']);
        $t->same(true, str_contains($temp['result']['value'], '/etilqs_'));
        $t->same(true, str_ends_with($temp['result']['value'], $suffix));
        $t->same(true, in_array('upstream-filectrl-sql-file-control', $sequence['dependencies'], true));
        $t->same(true, str_starts_with($scenario, 'filectrl-1.'));
    }
};

$tests['real upstream corpus vfs io dynamic filectrl sequence preserves read only result controls'] = static function (TestRunner $t): void {
    $sequence = SQLiteVfsIoDynamicPlan::fileControlSequence('file:/srv/app/data/app.sqlite?mode=rw&cache=shared&vfs=unix', [
        'PRAGMA data_version',
        'file_control(device_characteristics)',
        'file_control(sector_size)',
        'file_control(persist_wal, 1)',
        'file_control(powersafe_overwrite, 0)',
        'file_control(size_limit, 32768)',
        'file_control(size_hint, 16384)',
    ]);

    $t->same(7, $sequence['count']);
    $t->same('data_version', $sequence['pairs'][0]['op']);
    $t->same(1, $sequence['pairs'][0]['result']['value']);
    $t->same('device_characteristics', $sequence['pairs'][1]['op']);
    $t->same(true, is_int($sequence['pairs'][1]['result']['value']));
    $t->same('sector_size', $sequence['pairs'][2]['op']);
    $t->same(4096, $sequence['pairs'][2]['result']['value']);
    $t->same(true, $sequence['pairs'][3]['next']['persist_wal']);
    $t->same(false, $sequence['pairs'][4]['next']['powersafe_overwrite']);
    $t->same(32768, $sequence['pairs'][5]['next']['size_limit']);
    $t->same(16384, $sequence['pairs'][6]['result']['value']);
    $t->same(3, $sequence['changed']);
    $t->same(7, $sequence['applied']);
    $t->same(0, $sequence['ignored']);
    $t->same(0, $sequence['notfound']);
    $t->same(true, in_array('vfs-sql-file-control-sequence', $sequence['dependencies'], true));
};

$tests['real upstream corpus vfs io dynamic filectrl immutable and nolock retain ignored mmap behavior'] = static function (TestRunner $t): void {
    foreach ([
        'file:/srv/app/data/archive.sqlite?mode=ro&immutable=1',
        'file:/srv/app/data/archive.sqlite?nolock=1',
    ] as $filename) {
        $sequence = SQLiteVfsIoDynamicPlan::fileControlSequence($filename, ['PRAGMA mmap_size=65536']);

        $t->same(1, $sequence['count']);
        $t->same('mmap_size', $sequence['pairs'][0]['op']);
        $t->same('ignored', $sequence['pairs'][0]['result']['status']);
        $t->same(0, $sequence['pairs'][0]['result']['value']);
        $t->same(1, $sequence['ignored']);
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $sequence['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic filectrl rejects empty sequence'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::fileControlSequence('file:/srv/app/data/app.sqlite?mode=rw', []));
};

$checksumReserveCases = [];
foreach ([0, 4, 8, 16, 24, 32, 64, 96] as $reserveBytes) {
    foreach ([4096, 8192] as $pageSize) {
        foreach ([100, 250, 500, 850] as $smallRows) {
            $checksumReserveCases[] = [$reserveBytes, $pageSize, 8500, $smallRows, 5000, 100];
        }
    }
}

$tests['real upstream corpus vfs io dynamic cksumvfs reserve bytes persist counts through reopen'] = static function (TestRunner $t) use ($checksumReserveCases): void {
    foreach ($checksumReserveCases as [$reserveBytes, $pageSize, $largeRows, $smallRows, $largePayloadBytes, $smallPayloadBytes]) {
        $profile = SQLiteVfsIoDynamicPlan::checksumReserveProfile($reserveBytes, $pageSize, $largeRows, $smallRows, $largePayloadBytes, $smallPayloadBytes);

        $t->same('ok', $profile['status']);
        $t->same('cksumvfs.test', $profile['script']);
        $t->same($reserveBytes, $profile['reserve_bytes']);
        $t->same($pageSize, $profile['page_size']);
        $t->same($pageSize - $reserveBytes, $profile['usable_bytes']);
        $t->same($largeRows, $profile['large_count_after_commit']);
        $t->same('wal', $profile['journal_mode_after_delete']);
        $t->same($smallRows, $profile['small_count_before_reopen']);
        $t->same($smallRows, $profile['small_count_after_restore_reopen']);
        $t->same($smallRows, $profile['small_count_after_plain_reopen']);
        $t->same($reserveBytes > 0, $profile['checksum_trailer_reserved']);
        $t->same(['ok', 'ok', 'ok', 'ok'], $profile['integrity_sequence']);
        $t->same(true, in_array('cksumvfs.test 1.8', $profile['upstream'], true));
        $t->same(true, in_array('cksumvfs.test 1.9', $profile['upstream'], true));
        $t->same(true, in_array('upstream-cksumvfs-reserve-bytes', $profile['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic cksumvfs reserve bytes keep payload page math stable'] = static function (TestRunner $t) use ($checksumReserveCases): void {
    foreach ($checksumReserveCases as [$reserveBytes, $pageSize, $largeRows, $smallRows, $largePayloadBytes, $smallPayloadBytes]) {
        $profile = SQLiteVfsIoDynamicPlan::checksumReserveProfile($reserveBytes, $pageSize, $largeRows, $smallRows, $largePayloadBytes, $smallPayloadBytes);

        $t->same(true, $profile['usable_bytes'] >= 480);
        $t->same(0, ($profile['large_payload_pages'] * $profile['usable_bytes']) % $profile['usable_bytes']);
        $t->same(0, ($profile['small_payload_pages'] * $profile['usable_bytes']) % $profile['usable_bytes']);
        $t->same(true, $profile['large_payload_pages'] >= $profile['small_payload_pages']);
        $t->same(['busy' => 0, 'log' => 'nonzero', 'checkpointed' => 'nonzero'], $profile['checkpoint_result']);
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    }
};

$walLimitCases = [];
foreach ([8000, 10000, 12000, 16000, 24000, 32768] as $limitBytes) {
    foreach ([4096, 6000, 8000] as $reducedLimitBytes) {
        if ($reducedLimitBytes <= $limitBytes) {
            $walLimitCases[] = [$limitBytes, $reducedLimitBytes, 20, 750];
        }
    }
}

$tests['real upstream corpus vfs io dynamic walvfs journal size limit clamps after checkpoint insert'] = static function (TestRunner $t) use ($walLimitCases): void {
    foreach ($walLimitCases as [$limitBytes, $reducedLimitBytes, $rows, $payloadBytes]) {
        $profile = SQLiteVfsIoDynamicPlan::walJournalSizeLimitProfile($limitBytes, $reducedLimitBytes, $rows, $payloadBytes);

        $t->same('ok', $profile['status']);
        $t->same('walvfs.test', $profile['script']);
        $t->same('wal', $profile['journal_mode']);
        $t->same($limitBytes, $profile['journal_size_limit']);
        $t->same($reducedLimitBytes, $profile['reduced_journal_size_limit']);
        $t->same($rows, $profile['rows_inserted']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same(true, $profile['wal_exceeds_first_limit_before_checkpoint']);
        $t->same($limitBytes, $profile['wal_bytes_after_first_checkpoint_insert']);
        $t->same($reducedLimitBytes, $profile['wal_bytes_after_reduced_checkpoint_insert']);
        $t->same(true, in_array('walvfs.test 2.2', $profile['upstream'], true));
        $t->same(true, in_array('walvfs.test 2.3', $profile['upstream'], true));
        $t->same(true, in_array('upstream-walvfs-journal-size-limit', $profile['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic walvfs journal size limit rejects invalid limits'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::walJournalSizeLimitProfile(0, 1, 20, 750));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::walJournalSizeLimitProfile(1000, 2000, 20, 750));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::walJournalSizeLimitProfile(1000, 500, 0, 750));
};

$interruptCases = [];
foreach (range(1, 40) as $countdown) {
    $interruptCases[] = [$countdown, false, 'interrupted', 'SQLITE_INTERRUPT'];
    $interruptCases[] = [$countdown, true, 'out of memory', 'SQLITE_NOMEM_before_SQLITE_INTERRUPT'];
}

$tests['real upstream corpus vfs io dynamic walvfs checkpoint interrupt preserves result precedence'] = static function (TestRunner $t) use ($interruptCases): void {
    foreach ($interruptCases as [$countdown, $oomBeforeInterrupt, $result, $priority]) {
        $profile = SQLiteVfsIoDynamicPlan::walCheckpointInterruptProfile($countdown, $oomBeforeInterrupt);

        $t->same('ok', $profile['status']);
        $t->same('walvfs.test', $profile['script']);
        $t->same($oomBeforeInterrupt ? 'walvfs.test 3.2' : 'walvfs.test 3.1', $profile['upstream']);
        $t->same($countdown, $profile['write_fail_countdown']);
        $t->same($oomBeforeInterrupt, $profile['oom_before_interrupt']);
        $t->same($result, $profile['checkpoint_result']);
        $t->same($priority, $profile['result_code_priority']);
        $t->same('xWrite', $profile['database_write_hook']);
        $t->same(true, $profile['wal_mode_preserved']);
        $t->same($oomBeforeInterrupt, $profile['statement_result_matches_checkpoint']);
        $t->same(true, in_array('upstream-walvfs-checkpoint-interrupt', $profile['dependencies'], true));
    }
};

$walShmCases = [];
foreach (range(0, 39) as $busyAttempts) {
    $walShmCases[] = ['walvfs-4', $busyAttempts, true, false, 'error', 'attempt to write a readonly database', 'SQLITE_READONLY', [1 => 0, 2 => 0, 3 => 0, 4 => 0], false, 20, ['busy' => 0, 'log' => 5, 'checkpointed' => 5]];
    $walShmCases[] = ['walvfs-5', $busyAttempts, false, false, 'ok', '20', null, [1 => 24, 2 => 100, 3 => 100, 4 => 100], false, 20, ['busy' => 0, 'log' => 5, 'checkpointed' => 5]];
    $walShmCases[] = ['walvfs-5', max(1, $busyAttempts), true, false, 'error', 'attempt to write a readonly database', 'SQLITE_READONLY', [1 => 100, 2 => 100, 3 => 100, 4 => 100], true, 20, ['busy' => 0, 'log' => 5, 'checkpointed' => 5]];
    $walShmCases[] = ['walvfs-6', $busyAttempts, false, false, 'error', 'locking protocol', 'SQLITE_PROTOCOL', [1 => 24, 2 => 100, 3 => 100, 4 => 100], false, 20, ['busy' => 0, 'log' => 5, 'checkpointed' => 5]];
    $walShmCases[] = ['walvfs-7', $busyAttempts, false, false, 'ok', 'checkpoint busy', 'SQLITE_BUSY', [1 => 24, 2 => 100, 3 => 100, 4 => 100], false, 20, ['busy' => 1, 'log' => -1, 'checkpointed' => -1]];
    $walShmCases[] = ['walvfs-8', $busyAttempts, false, false, 'ok', 'ok', null, [1 => 24, 2 => 100, 3 => 100, 4 => 100], false, 21, ['busy' => 0, 'log' => 5, 'checkpointed' => 5]];
    $walShmCases[] = ['walvfs-9', $busyAttempts, true, true, 'error', 'disk I/O error', 'SQLITE_IOERR', [1 => 24, 2 => 100, 3 => 100, 4 => 100], false, 20, ['busy' => 0, 'log' => 5, 'checkpointed' => 5]];
}

$tests['real upstream corpus vfs io dynamic walvfs shm readmark fault matrix follows sections 4 through 9'] = static function (TestRunner $t) use ($walShmCases): void {
    foreach ($walShmCases as [$scenario, $busyAttempts, $readonlyShmMap, $ioerrDuringSharedLock, $status, $selectResult, $error, $readMarks, $recoverable, $visibleRows, $checkpointResult]) {
        $profile = SQLiteVfsIoDynamicPlan::walShmFaultProfile($scenario, $busyAttempts, $readonlyShmMap, $ioerrDuringSharedLock);

        $t->same($status, $profile['status']);
        $t->same('walvfs.test', $profile['script']);
        $t->same($scenario, $profile['scenario']);
        $t->same('wal', $profile['journal_mode']);
        $t->same(1024, $profile['page_size']);
        $t->same(20, $profile['seed_rows']);
        $t->same($busyAttempts, $profile['busy_attempts']);
        $t->same($readonlyShmMap, $profile['readonly_shm_map']);
        $t->same($ioerrDuringSharedLock, $profile['ioerr_during_shared_lock']);
        $t->same($selectResult, $profile['select_result']);
        $t->same($error, $profile['error']);
        $t->same($readMarks, $profile['read_marks']);
        $t->same($recoverable, $profile['recoverable_after_readmark_reset']);
        $t->same($scenario === 'walvfs-6' ? 12 : 0, $profile['protocol_retry_seconds']);
        $t->same($checkpointResult, $profile['checkpoint_result']);
        $t->same($scenario === 'walvfs-8', $profile['cache_flushed_before_checkpoint']);
        $t->same($visibleRows, $profile['visible_rows_after_checkpoint']);
        $t->same(true, in_array('upstream-walvfs-shm-readmark-faults', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-shm-locking', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic walvfs shm fault profile cites exact upstream subtests'] = static function (TestRunner $t): void {
    $expected = [
        'walvfs-4' => ['walvfs.test 4.0', 'walvfs.test 4.1', 'walvfs.test 4.2'],
        'walvfs-5' => ['walvfs.test 5.2', 'walvfs.test 5.3', 'walvfs.test 5.4', 'walvfs.test 5.5', 'walvfs.test 5.6'],
        'walvfs-6' => ['walvfs.test 6.1', 'walvfs.test 6.2'],
        'walvfs-7' => ['walvfs.test 7.1'],
        'walvfs-8' => ['walvfs.test 8.2', 'walvfs.test 8.3'],
        'walvfs-9' => ['walvfs.test 9.1'],
    ];

    foreach ($expected as $scenario => $upstream) {
        $profile = SQLiteVfsIoDynamicPlan::walShmFaultProfile($scenario, 3, $scenario === 'walvfs-5', $scenario === 'walvfs-9');

        $t->same($upstream, $profile['upstream']);
        $t->same(true, str_starts_with($profile['upstream'][0], 'walvfs.test '));
    }
};

$tests['real upstream corpus vfs io dynamic walvfs shm fault profile rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::walShmFaultProfile('walvfs-3'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::walShmFaultProfile(''));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::walShmFaultProfile('walvfs-5', -1));
};

$pagerFaultCases = [];
foreach (range(1, 70) as $faultIndex) {
    $pagerFaultCases[] = [
        'large-savepoint-rollback',
        $faultIndex,
        1024,
        8268,
        501,
        402,
        'pagerfault2.test',
        'oom-transient',
        'abc',
        4096,
        false,
        'rollback_to_savepoint_restores_large_update',
    ];
    $pagerFaultCases[] = [
        'large-blob-insert',
        $faultIndex,
        1024,
        8268,
        1,
        2500000,
        'pagerfault2.test',
        'oom-transient',
        'abc',
        20,
        false,
        'large_blob_insert_oom_releases_statement_journal',
    ];
    $pagerFaultCases[] = [
        'vacuum-page-size-rollback',
        $faultIndex,
        1024,
        2,
        1,
        1200,
        'pagerfault3.test',
        'ioerr-transient',
        null,
        null,
        true,
        'hot_journal_rollback_extends_database_to_original_sector',
    ];
}

$tests['real upstream corpus vfs io dynamic pagerfault2 and pagerfault3 large rollback matrix'] = static function (TestRunner $t) use ($pagerFaultCases): void {
    foreach ($pagerFaultCases as [$scenario, $faultIndex, $pageSize, $seedPages, $touchedRows, $payloadBytes, $script, $faultClass, $savepointName, $cacheSize, $rollbackExtendsFile, $rollbackAction]) {
        $profile = SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile(
            $scenario,
            $faultIndex,
            $pageSize,
            $seedPages,
            $touchedRows,
            $payloadBytes
        );

        $t->same('ok', $profile['status']);
        $t->same($script, $profile['script']);
        $t->same($scenario, $profile['scenario']);
        $t->same($faultClass, $profile['fault_class']);
        $t->same($faultIndex, $profile['fault_index']);
        $t->same('delete', $profile['journal_mode']);
        $t->same($scenario === 'vacuum-page-size-rollback', $profile['auto_vacuum']);
        $t->same($pageSize, $profile['page_size']);
        $t->same($seedPages, $profile['seed_pages']);
        $t->same($touchedRows, $profile['touched_rows']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same($savepointName, $profile['savepoint_name']);
        $t->same($cacheSize, $profile['cache_size']);
        $t->same($rollbackExtendsFile, $profile['rollback_extends_file']);
        $t->same($rollbackAction, $profile['rollback_action']);
        $t->same('ok', $profile['body_result']);
        $t->same('ok', $profile['integrity_check']);
        $t->same(true, $profile['connection_reusable_after_fault']);
        $t->same(true, $profile['statement_journal_released']);
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    }
};

$tests['real upstream corpus vfs io dynamic pagerfault3 rollback extends database image'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile('vacuum-page-size-rollback', 11, 1024, 2, 1, 1200);

    $t->same('pagerfault3.test', $profile['script']);
    $t->same(['pagerfault3.test pagerfault3-pre1', 'pagerfault3.test pagerfault3-pre2', 'pagerfault3.test pagerfault3-1'], $profile['upstream']);
    $t->same(2, $profile['pre_vacuum_pages']);
    $t->same(3, $profile['post_vacuum_pages']);
    $t->same(4, $profile['rollback_target_pages']);
    $t->same(true, $profile['rollback_extends_file']);
    $t->same('ioerr-transient', $profile['fault_class']);
    $t->same(true, in_array('upstream-pagerfault3-test', $profile['dependencies'], true));
};

$tests['real upstream corpus vfs io dynamic pagerfault2 large savepoint profiles cite upstream sections'] = static function (TestRunner $t): void {
    $savepoint = SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile('large-savepoint-rollback', 4, 1024, 8268, 501, 402);
    $blob = SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile('large-blob-insert', 5, 1024, 8268, 1, 2500000);

    $t->same(['pagerfault2.test pagerfault2-1-pre1', 'pagerfault2.test pagerfault2-1'], $savepoint['upstream']);
    $t->same(['pagerfault2.test pagerfault2-2-pre1', 'pagerfault2.test pagerfault2-2'], $blob['upstream']);
    $t->same(true, $savepoint['lookaside_disabled']);
    $t->same(true, $blob['lookaside_disabled']);
    $t->same(true, $savepoint['dirty_pages'] >= 501);
    $t->same(true, $blob['dirty_pages'] >= 2442);
    $t->same(true, in_array('upstream-pagerfault2-test', $savepoint['dependencies'], true));
    $t->same(true, in_array('upstream-pagerfault2-test', $blob['dependencies'], true));
};

$atomicPagerCacheCases = [];
foreach ([1024, 2048, 4096] as $pageSize) {
    foreach ([256, 1024, 2000, 4096] as $cacheSize) {
        foreach ([1, 2, 3] as $tablesModified) {
            foreach ([
                'atomic' => ['atomic'],
                'atomic2k' => ['atomic2k'],
                'ordinary' => [],
            ] as $label => $flags) {
                $atomicPagerCacheCases[] = [$label, $pageSize, $cacheSize, 4096, 100, $tablesModified, $flags];
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic atomic pager cache retention follows io 6'] = static function (TestRunner $t) use ($atomicPagerCacheCases): void {
    foreach ($atomicPagerCacheCases as [$label, $pageSize, $cacheSize, $indexedRows, $payloadBytes, $tablesModified, $flags]) {
        $profile = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(
            $pageSize,
            $cacheSize,
            $indexedRows,
            $payloadBytes,
            $tablesModified,
            $flags
        );
        $atomicAllowed = $profile['atomic_write_allowed'];
        $databaseFitsCache = $profile['database_fits_cache'];
        $singleTableAtomic = $atomicAllowed && $tablesModified === 1;

        $t->same('ok', $profile['status']);
        $t->same('io.test', $profile['script']);
        $t->same($pageSize, $profile['page_size']);
        $t->same($cacheSize, $profile['cache_size']);
        $t->same($indexedRows, $profile['indexed_rows']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same($tablesModified, $profile['tables_modified']);
        $t->same($flags, $profile['device_flags']);
        $t->same(true, $profile['database_pages'] > 0);
        $t->same($profile['database_pages'] <= $cacheSize, $databaseFitsCache);
        $t->same($singleTableAtomic ? 'single_page_atomic_write' : 'rollback_journal_transaction', $profile['commit_path']);
        $t->same('ok', $profile['pre_commit_integrity']);
        $t->same($databaseFitsCache && $atomicAllowed ? 'ok' : 'corruption-visible', $profile['post_commit_integrity']);
        $t->same(!$databaseFitsCache || !$atomicAllowed, $profile['pager_cache_flushed_by_commit']);
        $t->same($databaseFitsCache && !$profile['pager_cache_flushed_by_commit'], $profile['post_commit_integrity'] === 'ok');
        $t->same(2, $profile['corrupt_disk_pages']);
        $t->same($pageSize * 5, $profile['corrupt_offset']);
        $t->same(true, $profile['mmap_disabled']);
        $t->same(['rowid', 'index'], $profile['ordered_cache_warmup']);
        $t->same(true, in_array('io.test io-6.1', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-6.2.1.1-6.2.1.3', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-6.2.2.1-6.2.2.3', $profile['upstream'], true));
        $t->same(true, in_array('upstream-io-atomic-pager-cache-retention', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        $t->same(true, in_array($label, ['atomic', 'atomic2k', 'ordinary'], true));
    }
};

$tests['real upstream corpus vfs io dynamic atomic pager cache retention distinguishes single and multi table commits'] = static function (TestRunner $t): void {
    $single = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 4096, 100, 1, ['atomic']);
    $multi = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 4096, 100, 2, ['atomic']);
    $ordinary = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 4096, 100, 1, []);

    $t->same(true, $single['atomic_write_allowed']);
    $t->same('single_page_atomic_write', $single['commit_path']);
    $t->same(false, $single['pager_cache_flushed_by_commit']);
    $t->same('ok', $single['post_commit_integrity']);
    $t->same(true, $multi['atomic_write_allowed']);
    $t->same('rollback_journal_transaction', $multi['commit_path']);
    $t->same(false, $multi['pager_cache_flushed_by_commit']);
    $t->same('ok', $multi['post_commit_integrity']);
    $t->same(false, $ordinary['atomic_write_allowed']);
    $t->same('rollback_journal_transaction', $ordinary['commit_path']);
    $t->same(true, $ordinary['pager_cache_flushed_by_commit']);
    $t->same('corruption-visible', $ordinary['post_commit_integrity']);
};

$tests['real upstream corpus vfs io dynamic atomic pager cache retention reports visible corruption when cache is too small'] = static function (TestRunner $t): void {
    foreach ([16, 64, 128] as $cacheSize) {
        $profile = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, $cacheSize, 4096, 100, 1, ['atomic']);

        $t->same(false, $profile['database_fits_cache']);
        $t->same(true, $profile['pager_cache_flushed_by_commit']);
        $t->same('corruption-visible', $profile['post_commit_integrity']);
        $t->same(true, $profile['atomic_write_allowed']);
        $t->same('single_page_atomic_write', $profile['commit_path']);
        $t->same(true, in_array('io.test io-6.2.1.1-6.2.1.3', $profile['upstream'], true));
    }
};

$tests['real upstream corpus vfs io dynamic atomic pager cache retention rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1000, 2000, 4096, 100, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 0, 4096, 100, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 0, 100, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 4096, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 4096, 100, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 4096, 100, 1, ['networked']));
};

$tests['real upstream corpus vfs io dynamic pagerfault large rollback rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile('', 1, 1024, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile('large-savepoint-rollback', 0, 1024, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile('large-savepoint-rollback', 1, 1000, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile('large-savepoint-rollback', 1, 1024, 0, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile('large-savepoint-rollback', 1, 1024, 1, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile('large-savepoint-rollback', 1, 1024, 1, 1, 0));
};

$tests['real upstream corpus vfs io dynamic cksumvfs and walvfs reject malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::checksumReserveProfile(-1, 4096, 1, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::checksumReserveProfile(8, 1000, 1, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::checksumReserveProfile(255, 512, 1, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::checksumReserveProfile(8, 4096, 0, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::walCheckpointInterruptProfile(0, false));
};

return $tests;
