<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$cases = [];

$add = static function (string $name, array $upstream, callable $plan, array $expected) use (&$cases): void {
    $cases[$name] = [
        'upstream' => $upstream,
        'plan' => $plan,
        'expected' => $expected,
    ];
};

foreach ([
    'io-2.2 rollback delete full sync' => [[], 2, 'delete', 'full', ['directory', 'journal-pages', 'journal-header', 'database'], 4, 2, 2, false, false, false],
    'io-2.3 atomic single-page commit' => [['atomic'], 2, 'delete', 'full', ['database'], 1, 2, 0, true, false, false],
    'io-3 sequential spill commit' => [['sequential'], 30, 'delete', 'full', ['database'], 1, 30, 30, false, false, true],
    'io-4 safe append full sync' => [['safe_append'], 2, 'delete', 'full', ['directory', 'journal-pages', 'database'], 3, 2, 2, false, true, false],
    'io-4 safe append sequential' => [['safe_append', 'sequential'], 12, 'delete', 'full', ['database'], 1, 12, 12, false, true, true],
    'io wal sync target' => [[], 4, 'wal', 'full', ['wal', 'database'], 2, 4, 0, false, false, false],
    'io sync off rollback' => [[], 4, 'delete', 'off', [], 0, 4, 4, false, false, false],
    'io atomic too many pages' => [['atomic'], 5, 'delete', 'full', ['directory', 'journal-pages', 'journal-header', 'database'], 4, 5, 5, false, false, false],
] as $name => [$flags, $changedPages, $journalMode, $syncMode, $syncs, $syncCount, $dbWrites, $journalWrites, $atomic, $safeAppend, $sequential]) {
    $add($name, ['io.test io-2.*', 'io.test io-3.*', 'io.test io-4.*'], static fn (): array => SQLiteVfsIoDynamicPlan::ioTrafficPlan($flags, $changedPages, $journalMode, $syncMode), [
        'status' => 'ok',
        'changed_pages' => $changedPages,
        'journal_mode' => $journalMode,
        'sync_mode' => $syncMode,
        'database_page_writes' => $dbWrites,
        'journal_page_writes' => $journalWrites,
        'sync_sequence' => $syncs,
        'sync_count' => $syncCount,
        'atomic_write_optimization' => $atomic,
        'safe_append_optimization' => $safeAppend,
        'sequential_optimization' => $sequential,
        'dependencies' => ['upstream-io-device-characteristics', 'vfs-io-dynamic-real-corpus'],
    ]);
}

foreach ([
    'io-5 no flags 512 sector' => [[], 512, 1024],
    'io-5 no flags 2048 sector' => [[], 2048, 2048],
    'io-5 no flags 8192 sector' => [[], 8192, 8192],
    'io-5 no flags 16384 sector clamps' => [[], 16384, 8192],
    'io-5 atomic picks max' => [['atomic'], 512, 8192],
    'io-5 atomic512 floor' => [['atomic512'], 512, 1024],
    'io-5 atomic2k sector512' => [['atomic2k'], 512, 2048],
    'io-5 atomic2k sector4096' => [['atomic2k'], 4096, 4096],
    'io-5 atomic64k fallback' => [['atomic64k'], 512, 1024],
] as $name => [$flags, $sectorSize, $pageSize]) {
    $add($name, ['io.test io-5'], static fn (): array => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice($flags, $sectorSize), [
        'status' => 'ok',
        'script' => 'io.test',
        'upstream' => 'io.test io-5',
        'device_flags' => array_map(static fn (string $flag): string => strtolower($flag), $flags),
        'sector_size' => $sectorSize,
        'max_page_size' => 8192,
        'default_page_size' => $pageSize,
        'file_size_after_create' => $pageSize * 2,
        'reason' => 'pager_default_page_size_from_sector_and_atomic_capability',
        'dependencies' => ['upstream-io-default-page-size', 'vfs-io-dynamic-real-corpus'],
    ]);
}

foreach ([
    'io-4 safe append forty one pages' => [1024, 41, 10, 512 + (1024 + 8) * 41, 4, true],
    'io-4 safe append twelve pages' => [1024, 12, 5, 512 + (1024 + 8) * 12, 2, false],
    'io-4 safe append 2048 pages' => [2048, 20, 4, 512 + (2048 + 8) * 20, 4, true],
    'io-4 safe append normal sync' => [1024, 9, 3, 512 + (1024 + 8) * 9, 2, false],
    'io-4 safe append large cache' => [4096, 16, 16, 512 + (4096 + 8) * 16, 0, false],
] as $name => [$pageSize, $changedPages, $cacheSize, $journalBytes, $spillCount, $multipleSpills]) {
    $add($name, ['io.test io-4.2.2', 'io.test io-4.3.4'], static fn (): array => SQLiteVfsIoDynamicPlan::safeAppendJournalSize($pageSize, $changedPages, $cacheSize), [
        'status' => 'ok',
        'script' => 'io.test',
        'page_size' => $pageSize,
        'changed_pages' => $changedPages,
        'cache_size' => $cacheSize,
        'safe_append' => true,
        'journal_header_nrec' => 0xffffffff,
        'journal_header_count' => 1,
        'journal_file_bytes' => $journalBytes,
        'cache_spills' => $spillCount,
        'requires_multiple_cache_spills' => $multipleSpills,
        'extra_headers_after_spill' => 0,
    ]);
}

foreach ([
    'io-1 quick balance canonical nine rows' => [1024, 230, 9, 4, 5, 9, [2, 2, 2, 2, 4, 2, 2, 2, 3], 21, 1, 1, true],
    'io-1 quick balance canonical eight rows' => [1024, 230, 8, 4, 5, 9, [2, 2, 2, 2, 4, 2, 2, 2], 18, 1, 0, true],
    'io-1 quick balance canonical five rows' => [1024, 230, 5, 4, 5, 9, [2, 2, 2, 2, 4], 12, 1, 0, true],
    'io-1 quick balance canonical four rows' => [1024, 230, 4, 4, 5, 9, [2, 2, 2, 2], 8, 0, 0, true],
    'io-1 quick balance smaller payload row thirteen' => [1024, 140, 13, 6, 7, 13, [2, 2, 2, 2, 2, 2, 4, 2, 2, 2, 2, 2, 3], 29, 1, 1, false],
    'io-1 quick balance smaller payload split only' => [1024, 140, 7, 6, 7, 13, [2, 2, 2, 2, 2, 2, 4], 16, 1, 0, false],
    'io-1 quick balance 2048 page eleven rows' => [2048, 230, 11, 8, 9, 17, [2, 2, 2, 2, 2, 2, 2, 2, 4, 2, 2], 24, 1, 0, false],
    'io-1 quick balance 2048 page seventeen rows' => [2048, 230, 17, 8, 9, 17, [2, 2, 2, 2, 2, 2, 2, 2, 4, 2, 2, 2, 2, 2, 2, 2, 3], 37, 1, 1, false],
    'io-1 quick balance large payload early split' => [1024, 470, 5, 2, 3, 5, [2, 2, 4, 2, 3], 13, 1, 1, false],
    'io-1 quick balance large payload pre-split' => [1024, 470, 2, 2, 3, 5, [2, 2], 4, 0, 0, false],
] as $name => [$pageSize, $payloadBytes, $rowCount, $capacity, $splitRow, $quickBalanceRow, $writes, $totalWrites, $splitEvents, $quickBalanceEvents, $canonical]) {
    $add($name, ['io.test io-1.1', 'io.test io-1.2', 'io.test io-1.3', 'io.test io-1.4', 'io.test io-1.5'], static fn (): array => SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile($pageSize, $payloadBytes, $rowCount), [
        'status' => 'ok',
        'script' => 'io.test',
        'page_size' => $pageSize,
        'payload_bytes' => $payloadBytes,
        'row_count' => $rowCount,
        'root_leaf_capacity' => $capacity,
        'split_row' => $splitRow,
        'quick_balance_row' => $quickBalanceRow,
        'total_database_writes' => $totalWrites,
        'split_events' => $splitEvents,
        'quick_balance_events' => $quickBalanceEvents,
        'canonical_io_1_shape' => $canonical,
        'event_database_writes' => $writes,
        'dependencies' => ['sqlite-upstream-io-test', 'sqlite-vfs-quick-balance-traffic', 'sqlite-pager-io-traffic', 'vfs-io-dynamic-real-corpus'],
    ]);
}

foreach ([
    'io-6 pager cache two table commit retained' => [1024, 2100, 2048, 2, 5, true, true, 'ok', 5120, 2048, ['BEGIN', "INSERT INTO t1 VALUES('123')", "INSERT INTO t2 VALUES('456')", 'COMMIT'], true],
    'io-6 pager cache one table commit retained' => [1024, 2100, 2048, 1, 5, true, true, 'ok', 5120, 2048, ['BEGIN', "INSERT INTO t1 VALUES('123')", 'COMMIT'], true],
    'io-6 pager cache too small spills' => [1024, 8, 2048, 2, 5, false, false, 'would-read-corrupt-page', 5120, 2048, ['BEGIN', "INSERT INTO t1 VALUES('123')", "INSERT INTO t2 VALUES('456')", 'COMMIT'], true],
    'io-6 pager cache mmap path excluded' => [1024, 2000, 2048, 2, 5, false, false, 'would-read-corrupt-page', 5120, 2048, ['BEGIN', "INSERT INTO t1 VALUES('123')", "INSERT INTO t2 VALUES('456')", 'COMMIT'], false],
    'io-6 pager cache 2048 page retained' => [2048, 2000, 1024, 2, 3, true, true, 'ok', 6144, 2048, ['BEGIN', "INSERT INTO t1 VALUES('123')", "INSERT INTO t2 VALUES('456')", 'COMMIT'], true],
    'io-6 pager cache many transaction pages retained' => [1024, 2000, 1024, 4, 7, true, true, 'ok', 7168, 2048, ['BEGIN', "INSERT INTO t1 VALUES('123')", "INSERT INTO t2 VALUES('456')", 'COMMIT'], true],
    'io-6 pager cache many transaction pages spills' => [1024, 12, 1024, 8, 7, false, false, 'would-read-corrupt-page', 7168, 2048, ['BEGIN', "INSERT INTO t1 VALUES('123')", "INSERT INTO t2 VALUES('456')", 'COMMIT'], true],
    'io-6 pager cache minimal warm read retained' => [1024, 12, 3, 2, 5, true, true, 'ok', 5120, 2048, ['BEGIN', "INSERT INTO t1 VALUES('123')", "INSERT INTO t2 VALUES('456')", 'COMMIT'], true],
] as $name => [$pageSize, $cachePages, $warmReadPages, $transactionPages, $corruptPageOffset, $retained, $flushAvoided, $integrity, $corruptByteOffset, $corruptBytes, $sequence, $mmapDisabled]) {
    $add($name, ['io.test io-6.1', 'io.test io-6.2.1', 'io.test io-6.2.2', 'io.test io-6.2.3'], static fn (): array => SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile($pageSize, $cachePages, $warmReadPages, $transactionPages, $corruptPageOffset, $mmapDisabled), [
        'status' => 'ok',
        'script' => 'io.test',
        'scenario' => 'io-6.2',
        'page_size' => $pageSize,
        'cache_pages' => $cachePages,
        'warm_read_pages' => $warmReadPages,
        'transaction_pages' => $transactionPages,
        'mmap_disabled' => $mmapDisabled,
        'cache_can_hold_warm_read' => $cachePages >= $warmReadPages,
        'transaction_fits_without_spill' => ($warmReadPages + $transactionPages) <= $cachePages,
        'pager_cache_retained' => $retained,
        'dirty_cache_flush_avoided' => $flushAvoided,
        'integrity_check_after_disk_corruption' => $integrity,
        'corrupt_page_offset' => $corruptPageOffset,
        'corrupt_byte_offset' => $corruptByteOffset,
        'corrupt_bytes' => $corruptBytes,
        'warm_read_sequence' => ['SELECT x FROM t3 ORDER BY rowid', 'SELECT x FROM t3 ORDER BY x'],
        'transaction_sequence' => $sequence,
        'dependencies' => ['upstream-io-cache-no-spill-after-warm-read', 'vfs-io-dynamic-real-corpus'],
    ]);
}

foreach ([
    'io-3 sequential spill no precommit sync' => [['sequential'], 1024, 10, 30, 0, 1, 'sequential_device_defers_spill_syncs_until_commit'],
    'io-3 sequential reserved byte size' => [['sequential'], 1024, 10, 30, 0, 1, 'sequential_device_defers_spill_syncs_until_commit'],
    'io-4 safe append single header spill' => [['safe_append'], 1024, 10, 41, 4, 3, 'safe_append_uses_single_journal_header_across_spills'],
    'io-4 plain full sync repeated headers' => [[], 1024, 10, 41, 4, 4, 'full_sync_journal_headers_may_repeat_after_spills'],
    'io-4 safe append sync off' => [['safe_append'], 1024, 10, 41, 0, 0, 'safe_append_uses_single_journal_header_across_spills'],
] as $idx => [$flags, $pageSize, $cacheSize, $statementPages, $precommitSyncs, $commitSyncs, $reason]) {
    $syncMode = str_contains((string) $idx, 'sync off') ? 'off' : 'full';
    $reserved = str_contains((string) $idx, 'reserved');
    $add((string) $idx, ['io.test io-3.2', 'io.test io-3.3', 'io.test io-4.3.4'], static fn (): array => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile($flags, $pageSize, $cacheSize, $statementPages, $syncMode, $reserved), [
        'status' => 'ok',
        'script' => 'io.test',
        'page_size' => $pageSize,
        'cache_size' => $cacheSize,
        'statement_pages' => $statementPages,
        'sync_mode' => $syncMode,
        'reserved_bytes' => $reserved,
        'precommit_syncs' => $precommitSyncs,
        'commit_syncs' => $commitSyncs,
        'file_grew_during_spill' => true,
        'journal_header_nrec' => in_array('safe_append', $flags, true) ? 0xffffffff : null,
        'reason' => $reason,
    ]);
}

foreach ([
    'io-2.6 deferred append blocked journal' => [['atomic'], 1024, 512, 1, 1, false, false, false, true, 'SQLITE_CANTOPEN', true],
    'io-2.7 multifile blocked journal' => [['atomic'], 1024, 512, 1, 0, true, false, false, true, 'SQLITE_IOERR_ROLLBACK', true],
    'io-2.8 explicit rollback before journal' => [['atomic'], 1024, 512, 1, 0, false, true, false, false, 'ok', true],
    'io-2.9 sector larger disables atomic' => [['atomic'], 1024, 2048, 1, 0, false, false, false, false, 'ok', false],
    'io-2.10 atomic2k page size allowed' => [['atomic2k'], 2048, 512, 1, 0, false, false, false, false, 'ok', true],
    'io-2.11 exclusive keeps journal unlinked' => [['atomic'], 1024, 512, 1, 0, false, false, true, false, 'ok', true],
] as $name => [$flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multi, $rollback, $exclusive, $blocked, $status, $atomicAllowed]) {
    $add($name, ['io.test io-2.6.1-2.6.4', 'io.test io-2.7.1-2.7.6', 'io.test io-2.8.1-2.8.3', 'io.test io-2.9.1-2.9.3', 'io.test io-2.10.1-2.10.3', 'io.test io-2.11.1-2.11.2'], static fn (): array => SQLiteVfsIoDynamicPlan::atomicJournalAdmission($flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multi, $rollback, $exclusive, $blocked), [
        'status' => 'ok',
        'script' => 'io.test',
        'page_size' => $pageSize,
        'sector_size' => $sectorSize,
        'changed_pages' => $changedPages,
        'appended_pages' => $appendedPages,
        'multi_file_commit' => $multi,
        'explicit_rollback' => $rollback,
        'exclusive_locking' => $exclusive,
        'journal_path_blocked' => $blocked,
        'atomic_write_allowed' => $atomicAllowed,
        'commit_status' => $status,
        'rollback_required' => $status !== 'ok' || $rollback,
    ]);
}

foreach ([
    'journal2-1.1 create table delete lifecycle' => ['journal2-1.1', 'delete', 'create-table', 0, 0, ['xOpen', 'xClose', 'xDelete'], 'SQLITE_OK', false],
    'journal2-1.2 truncate opens once' => ['journal2-1.2-1.4', 'truncate', 'insert', 0, 0, [], 'SQLITE_OK', false],
    'journal2-1.5 delete blocked by open handle' => ['journal2-1.5-1.9', 'delete', 'second-connection-delete', 1, 1, ['xOpen', 'xClose', 'xDelete'], 'SQLITE_IOERR', true],
    'journal2-1.13 large commit fault leaves hot journal' => ['journal2-1.10-1.21', 'delete', 'large-commit', 0, 64, ['xOpen', 'xClose', 'xDelete'], 'SQLITE_IOERR', true],
    'journal2-2.4 wal switch closes deletes journal' => ['journal2-2.1-2.4', 'persist', 'switch-to-wal', 0, 1, ['xOpen', 'xClose', 'xDelete'], 'SQLITE_OK', false],
    'journal2 truncate reuse no delete' => ['journal2-1.2-1.4', 'truncate', 'insert', 0, 2, [], 'SQLITE_OK', false],
    'journal2 delete insert closes deletes' => ['journal2-1.1', 'delete', 'insert', 0, 1, ['xOpen', 'xClose', 'xDelete'], 'SQLITE_OK', false],
] as $name => [$scenario, $mode, $operation, $handles, $dirty, $oplog, $rc, $hot]) {
    $add($name, ['journal2.test journal2-1.*', 'journal2.test journal2-2.*'], static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle($scenario, $mode, $operation, $handles, $dirty), [
        'status' => $rc === 'SQLITE_OK' ? 'ok' : 'ioerr',
        'script' => 'journal2.test',
        'scenario' => $scenario,
        'journal_mode' => $mode,
        'operation' => $operation,
        'device_flags' => ['undeletable_when_open', 'powersafe_overwrite'],
        'open_journal_handles' => $handles,
        'dirty_pages' => $dirty,
        'oplog' => $oplog,
        'expected_rc' => $rc,
        'hot_journal_left' => $hot,
        'post_recovery_integrity' => $hot ? 'ok_after_hot_journal_rollback' : 'ok',
    ]);
}

foreach ($cases as $name => $case) {
    $tests["real upstream vfs io dynamic {$name}"] = static function (TestRunner $t) use ($case): void {
        $plan = ($case['plan'])();
        $t->same(true, is_array($plan));
        $t->same(true, count($plan) >= count($case['expected']));
        $t->same(true, isset($plan['dependencies']));

        foreach ($case['expected'] as $key => $expected) {
            if ($key === 'event_database_writes') {
                $t->same($expected, array_column($plan['events'], 'database_writes'));
                continue;
            }

            $t->same($expected, $plan[$key]);
        }
    };
}

return $tests;
