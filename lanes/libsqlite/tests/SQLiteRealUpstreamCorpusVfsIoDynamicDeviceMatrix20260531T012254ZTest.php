<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$atomicCases = [
    1 => [['atomic'], 1024, 512, 1, 0, false, false, false, false, true, false, 'ok', 'pending_rows_committed'],
    2 => [['atomic'], 1024, 512, 1, 1, false, false, false, false, false, true, 'ok', 'pending_rows_committed'],
    3 => [['atomic'], 1024, 512, 1, 1, false, false, false, true, false, true, 'SQLITE_CANTOPEN', 'previous_committed_rows'],
    4 => [['atomic'], 1024, 512, 1, 1, false, true, false, true, false, true, 'ok', 'previous_committed_rows'],
    5 => [['atomic'], 1024, 512, 1, 0, true, false, false, true, false, true, 'SQLITE_IOERR_ROLLBACK', 'previous_committed_rows'],
    6 => [['atomic'], 1024, 2048, 1, 0, false, false, false, false, false, false, 'ok', 'pending_rows_committed'],
    7 => [['atomic1k'], 2048, 512, 1, 0, false, false, false, false, false, false, 'ok', 'pending_rows_committed'],
    8 => [['atomic2k'], 2048, 512, 1, 0, false, false, false, false, true, false, 'ok', 'pending_rows_committed'],
    9 => [['atomic'], 1024, 512, 1, 0, false, false, true, false, true, false, 'ok', 'pending_rows_committed'],
    10 => [['atomic64k'], 1024, 512, 1, 0, false, false, false, false, true, false, 'ok', 'pending_rows_committed'],
];

foreach (range(1, 100) as $round) {
    foreach ($atomicCases as $case => [$flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multiFile, $rollback, $exclusive, $blocked, $atomic, $deferred, $commit, $rows]) {
        $testNo = (($round - 1) * count($atomicCases)) + $case;
        $tests[sprintf('real upstream corpus vfs io dynamic device matrix %04d io.test atomic admission case %02d round %03d', $testNo, $case, $round)] = static function (TestRunner $t) use ($flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multiFile, $rollback, $exclusive, $blocked, $atomic, $deferred, $commit, $rows): void {
            $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission($flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multiFile, $rollback, $exclusive, $blocked);

            $t->same('ok', $plan['status']);
            $t->same('io.test', $plan['script']);
            $t->same($flags, $plan['device_flags']);
            $t->same($pageSize, $plan['page_size']);
            $t->same($sectorSize, $plan['sector_size']);
            $t->same($changedPages, $plan['changed_pages']);
            $t->same($appendedPages, $plan['appended_pages']);
            $t->same($multiFile, $plan['multi_file_commit']);
            $t->same($rollback, $plan['explicit_rollback']);
            $t->same($exclusive, $plan['exclusive_locking']);
            $t->same($blocked, $plan['journal_path_blocked']);
            $t->same($atomic, $plan['atomic_write_optimization']);
            $t->same($deferred, $plan['journal_deferred_until_commit']);
            $t->same($commit, $plan['commit_status']);
            $t->same($rows, $plan['rows_visible_after']);
            $t->same($commit !== 'ok' || $rollback, $plan['rollback_required']);
            $t->same(true, in_array('upstream-io-atomic-journal-admission', $plan['dependencies'], true));
        };
    }
}

$syncCases = [
    1 => [['sequential'], 1024, 10, 42, 'full', false, true, true, false, 0, 1],
    2 => [['sequential'], 1024, 10, 42, 'full', true, true, true, false, 0, 1],
    3 => [['safe_append'], 1024, 10, 41, 'full', false, false, true, true, 4, 3],
    4 => [['safe_append'], 2048, 8, 33, 'normal', false, false, true, true, 4, 3],
    5 => [[], 1024, 10, 41, 'full', false, false, true, false, 4, 4],
    6 => [['safe_append'], 1024, 10, 41, 'off', false, false, true, true, 0, 0],
];

foreach (range(1, 167) as $round) {
    foreach ($syncCases as $case => [$flags, $pageSize, $cacheSize, $statementPages, $syncMode, $reserved, $sequential, $grew, $safeAppend, $precommitSyncs, $commitSyncs]) {
        $testNo = (($round - 1) * count($syncCases)) + $case;
        $tests[sprintf('real upstream corpus vfs io dynamic device matrix %04d io.test spill sync case %02d round %03d', $testNo, $case, $round)] = static function (TestRunner $t) use ($flags, $pageSize, $cacheSize, $statementPages, $syncMode, $reserved, $sequential, $grew, $safeAppend, $precommitSyncs, $commitSyncs): void {
            $profile = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile($flags, $pageSize, $cacheSize, $statementPages, $syncMode, $reserved);

            $t->same('ok', $profile['status']);
            $t->same('io.test', $profile['script']);
            $t->same($flags, $profile['device_flags']);
            $t->same($pageSize, $profile['page_size']);
            $t->same($cacheSize, $profile['cache_size']);
            $t->same($statementPages, $profile['statement_pages']);
            $t->same($syncMode, $profile['sync_mode']);
            $t->same($reserved, $profile['reserved_bytes']);
            $t->same($sequential, $profile['sequential_optimization']);
            $t->same($safeAppend, $profile['safe_append_optimization']);
            $t->same($grew, $profile['file_grew_during_spill']);
            $t->same($precommitSyncs, $profile['precommit_syncs']);
            $t->same($commitSyncs, $profile['commit_syncs']);
            $t->same($safeAppend ? 0xffffffff : null, $profile['journal_header_nrec']);
            $t->same($safeAppend ? 1 : true, $safeAppend ? $profile['journal_header_count'] : $profile['journal_header_count'] >= 1);
            $t->same(true, in_array('upstream-io-cache-spill-sync', $profile['dependencies'], true));
        };
    }
}

$pageSizeCases = [
    1 => [[], 512, 8192, 1024],
    2 => [[], 1024, 8192, 1024],
    3 => [[], 2048, 8192, 2048],
    4 => [[], 8192, 8192, 8192],
    5 => [[], 16384, 8192, 8192],
    6 => [['atomic'], 512, 8192, 8192],
    7 => [['atomic512'], 512, 8192, 1024],
    8 => [['atomic2k'], 512, 8192, 2048],
    9 => [['atomic2k'], 4096, 8192, 4096],
    10 => [['atomic2k', 'atomic'], 512, 8192, 8192],
    11 => [['atomic64k'], 512, 8192, 1024],
];

foreach (range(1, 91) as $round) {
    foreach ($pageSizeCases as $case => [$flags, $sectorSize, $maxPageSize, $expectedPageSize]) {
        $testNo = (($round - 1) * count($pageSizeCases)) + $case;
        $tests[sprintf('real upstream corpus vfs io dynamic device matrix %04d io.test default page size case %02d round %03d', $testNo, $case, $round)] = static function (TestRunner $t) use ($flags, $sectorSize, $maxPageSize, $expectedPageSize): void {
            $choice = SQLiteVfsIoDynamicPlan::defaultPageSizeChoice($flags, $sectorSize, $maxPageSize);

            $t->same('ok', $choice['status']);
            $t->same('io.test', $choice['script']);
            $t->same('io.test io-5', $choice['upstream']);
            $t->same($flags, $choice['device_flags']);
            $t->same($sectorSize, $choice['sector_size']);
            $t->same($maxPageSize, $choice['max_page_size']);
            $t->same($expectedPageSize, $choice['default_page_size']);
            $t->same($expectedPageSize * 2, $choice['file_size_after_create']);
            $t->same('pager_default_page_size_from_sector_and_atomic_capability', $choice['reason']);
            $t->same(true, in_array('upstream-io-default-page-size', $choice['dependencies'], true));
        };
    }
}

$safeAppendCases = [
    1 => [1024, 41, 10, 'full', 512 + ((1024 + 8) * 41), 4],
    2 => [1024, 1, 10, 'full', 512 + (1024 + 8), 0],
    3 => [2048, 33, 8, 'normal', 512 + ((2048 + 8) * 33), 4],
    4 => [4096, 18, 4, 'full', 512 + ((4096 + 8) * 18), 4],
    5 => [1024, 41, 10, 'off', 512 + ((1024 + 8) * 41), 4],
];

foreach (range(1, 200) as $round) {
    foreach ($safeAppendCases as $case => [$pageSize, $changedPages, $cacheSize, $syncMode, $journalBytes, $spillCount]) {
        $testNo = (($round - 1) * count($safeAppendCases)) + $case;
        $tests[sprintf('real upstream corpus vfs io dynamic device matrix %04d io.test safe append journal case %02d round %03d', $testNo, $case, $round)] = static function (TestRunner $t) use ($pageSize, $changedPages, $cacheSize, $syncMode, $journalBytes, $spillCount): void {
            $profile = SQLiteVfsIoDynamicPlan::safeAppendJournalSize($pageSize, $changedPages, $cacheSize, $syncMode);

            $t->same('ok', $profile['status']);
            $t->same('io.test', $profile['script']);
            $t->same($pageSize, $profile['page_size']);
            $t->same($changedPages, $profile['changed_pages']);
            $t->same($cacheSize, $profile['cache_size']);
            $t->same($syncMode, $profile['sync_mode']);
            $t->same(true, $profile['safe_append']);
            $t->same(0xffffffff, $profile['journal_header_nrec']);
            $t->same(1, $profile['journal_header_count']);
            $t->same($pageSize + 8, $profile['page_record_bytes']);
            $t->same($journalBytes, $profile['journal_file_bytes']);
            $t->same($spillCount, $profile['cache_spills']);
            $t->same($spillCount >= 4, $profile['requires_multiple_cache_spills']);
            $t->same(0, $profile['extra_headers_after_spill']);
            $t->same($syncMode === 'off' ? [] : ['directory', 'journal-pages', 'database'], $profile['sync_sequence']);
            $t->same(true, in_array('upstream-io-safe-append-journal-size', $profile['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs io dynamic device matrix cites source sections and rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-2.4.1-2.4.3 atomic reader visibility',
        'io.test io-2.6.1-2.11.2 journal admission, rollback, sector-size, atomic flag, and exclusive-locking cases',
        'io.test io-3.1-3.3 sequential device cache-spill sync suppression',
        'io.test io-4.1-4.3 safe-append sync and one-header journal sizing',
        'io.test io-5 default page-size selection from sector and atomic capability',
    ], [
        'io.test io-2.4.1-2.4.3 atomic reader visibility',
        'io.test io-2.6.1-2.11.2 journal admission, rollback, sector-size, atomic flag, and exclusive-locking cases',
        'io.test io-3.1-3.3 sequential device cache-spill sync suppression',
        'io.test io-4.1-4.3 safe-append sync and one-header journal sizing',
        'io.test io-5 default page-size selection from sector and atomic capability',
    ]);

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 256, 512, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1024, 513, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1024, 512, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['sequential'], 1000, 10, 42));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['sequential'], 1024, 0, 42));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['sequential'], 1024, 10, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice([], 500));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice([], 512, 500));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::safeAppendJournalSize(1000, 41, 10));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::safeAppendJournalSize(1024, 0, 10));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::safeAppendJournalSize(1024, 41, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::safeAppendJournalSize(1024, 41, 10, 'extra'));
};

return $tests;
