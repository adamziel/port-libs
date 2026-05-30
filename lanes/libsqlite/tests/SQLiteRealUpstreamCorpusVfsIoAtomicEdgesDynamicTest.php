<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$pageSizes = [1024, 2048, 4096, 8192];
$sectorSizes = [512, 1024, 2048, 4096];
$changedPageCounts = [1, 2, 3, 5, 8, 13, 21];
$appendPageCounts = [0, 1, 2];
$caseNumber = 0;

foreach ($pageSizes as $pageSize) {
    foreach ($sectorSizes as $sectorSize) {
        if ($sectorSize > $pageSize) {
            continue;
        }
        foreach ($changedPageCounts as $changedPages) {
            ++$caseNumber;
            $tests[sprintf(
                'real upstream corpus vfs io atomic edges io-2.7 multifile rollback %04d page %d sector %d changed %d',
                $caseNumber,
                $pageSize,
                $sectorSize,
                $changedPages
            )] = static function (TestRunner $t) use ($pageSize, $sectorSize, $changedPages): void {
                $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission(
                    ['atomic'],
                    $pageSize,
                    $sectorSize,
                    $changedPages,
                    0,
                    true,
                    false,
                    false,
                    true
                );

                $t->same('io.test', $plan['script']);
                $t->same(true, in_array('io.test io-2.7.1-2.7.6', $plan['upstream'], true));
                $t->same(true, $plan['multi_file_commit']);
                $t->same(true, $plan['journal_required']);
                $t->same('SQLITE_IOERR_ROLLBACK', $plan['commit_status']);
                $t->same('previous_committed_rows', $plan['rows_visible_after']);
                $t->same(true, $plan['rollback_required']);
                $t->same('multi_file_commit_journal_open_failure_rolls_back_all_files', $plan['reason']);
                $t->same(true, in_array('upstream-io-atomic-journal-admission', $plan['dependencies'], true));
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
            };
        }
    }
}

foreach ($pageSizes as $pageSize) {
    foreach ($sectorSizes as $sectorSize) {
        if ($sectorSize > $pageSize) {
            continue;
        }
        foreach ($changedPageCounts as $changedPages) {
            foreach ($appendPageCounts as $appendedPages) {
                ++$caseNumber;
                $tests[sprintf(
                    'real upstream corpus vfs io atomic edges io-2.8 explicit rollback %04d page %d sector %d changed %d append %d',
                    $caseNumber,
                    $pageSize,
                    $sectorSize,
                    $changedPages,
                    $appendedPages
                )] = static function (TestRunner $t) use ($pageSize, $sectorSize, $changedPages, $appendedPages): void {
                    $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission(
                        ['atomic'],
                        $pageSize,
                        $sectorSize,
                        $changedPages,
                        $appendedPages,
                        false,
                        true
                    );

                    $t->same(true, in_array('io.test io-2.8.1-2.8.3', $plan['upstream'], true));
                    $t->same(true, $plan['explicit_rollback']);
                    $t->same('ok', $plan['commit_status']);
                    $t->same('previous_committed_rows', $plan['rows_visible_after']);
                    $t->same(true, $plan['rollback_required']);
                    $t->same($appendedPages > 0 || $changedPages > 1, $plan['journal_deferred_until_commit']);
                    $t->same('explicit_rollback_restores_rows_before_journal_materialization', $plan['reason']);
                    $t->same(true, $plan['atomic_write_allowed']);
                    $t->same($changedPages <= 1 && $appendedPages === 0, $plan['atomic_write_optimization']);
                };
            }
        }
    }
}

foreach ($pageSizes as $pageSize) {
    foreach ($sectorSizes as $sectorSize) {
        if ($sectorSize > $pageSize) {
            continue;
        }
        foreach ($changedPageCounts as $changedPages) {
            foreach ($appendPageCounts as $appendedPages) {
                ++$caseNumber;
                $tests[sprintf(
                    'real upstream corpus vfs io atomic edges io-2.11 exclusive unlink %04d page %d sector %d changed %d append %d',
                    $caseNumber,
                    $pageSize,
                    $sectorSize,
                    $changedPages,
                    $appendedPages
                )] = static function (TestRunner $t) use ($pageSize, $sectorSize, $changedPages, $appendedPages): void {
                    $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission(
                        ['atomic'],
                        $pageSize,
                        $sectorSize,
                        $changedPages,
                        $appendedPages,
                        false,
                        false,
                        true
                    );

                    $t->same(true, in_array('io.test io-2.11.1-2.11.2', $plan['upstream'], true));
                    $t->same(true, $plan['exclusive_locking']);
                    $t->same(false, $plan['journal_required']);
                    $t->same(false, $plan['journal_exists_before_commit']);
                    $t->same('ok', $plan['commit_status']);
                    $t->same('pending_rows_committed', $plan['rows_visible_after']);
                    $t->same(false, $plan['rollback_required']);
                    $t->same('exclusive_locking_keeps_journal_unlinked_after_commit', $plan['reason']);
                    $t->same(true, $plan['atomic_write_allowed']);
                };
            }
        }
    }
}

$cacheCase = 0;
foreach ($pageSizes as $pageSize) {
    foreach ([128, 256, 512, 1024, 2000] as $cacheSize) {
        foreach ([256, 512, 1024, 1536, 2048] as $indexedRows) {
            foreach ([1, 2] as $tablesModified) {
                ++$cacheCase;
                $tests[sprintf(
                    'real upstream corpus vfs io atomic edges io-6 pager cache retention %04d page %d cache %d rows %d tables %d',
                    $cacheCase,
                    $pageSize,
                    $cacheSize,
                    $indexedRows,
                    $tablesModified
                )] = static function (TestRunner $t) use ($pageSize, $cacheSize, $indexedRows, $tablesModified): void {
                    $plan = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(
                        $pageSize,
                        $cacheSize,
                        $indexedRows,
                        100,
                        $tablesModified,
                        ['atomic']
                    );

                    $fits = $plan['database_pages'] <= $cacheSize;

                    $t->same('io.test', $plan['script']);
                    $t->same(true, in_array('io.test io-6.1', $plan['upstream'], true));
                    $t->same(true, in_array('io.test io-6.2.1.1-6.2.1.3', $plan['upstream'], true));
                    $t->same(true, in_array('io.test io-6.2.2.1-6.2.2.3', $plan['upstream'], true));
                    $t->same(true, $plan['mmap_disabled']);
                    $t->same(['rowid', 'index'], $plan['ordered_cache_warmup']);
                    $t->same($fits, $plan['database_fits_cache']);
                    $t->same(!$fits, $plan['pager_cache_flushed_by_commit']);
                    $t->same($fits ? 'ok' : 'corruption-visible', $plan['post_commit_integrity']);
                    $t->same(true, in_array('upstream-io-atomic-pager-cache-retention', $plan['dependencies'], true));
                };
            }
        }
    }
}

$tests['real upstream corpus vfs io atomic edges rejects malformed upstream parameters'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1000, 512, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1024, 768, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1024, 512, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1000, 2000, 1024, 100, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 0, 1024, 100, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 0, 100, 1));
};

return $tests;
