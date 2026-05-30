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

return $tests;
