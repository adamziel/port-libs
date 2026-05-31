<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/journal1.test';

$tests['real upstream corpus vfs journal1 stale rollback cites hydrated upstream source'] = static function (TestRunner $t) use ($source): void {
    $t->same(true, is_file($source));
    $contents = file_get_contents($source);
    $t->contains('journal1-1.1', $contents);
    $t->contains('journal1-1.2', $contents);
    $t->contains('old journal should not attempt to rollback into the new', $contents);
    $t->same([
        'journal1.test journal1-1.1',
        'journal1.test journal1-1.2',
    ], SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(8, 8, 5, 1, true)['upstream']);
};

foreach (range(1, 1000) as $case) {
    $originalRows = 8 + ($case % 257);
    $journaledDeletes = 1 + ($case % $originalRows);
    $oldDatabasePages = 2 + ($case % 31);
    $newDatabasePages = 1 + ($case % 7);
    $oldJournalCopiedBack = ($case % 11) !== 0;
    $atomicBatchWrite = ($case % 97) === 0;

    $tests[sprintf('real upstream corpus vfs journal1 stale rollback isolation dynamic %04d', $case)] = static function (TestRunner $t) use ($originalRows, $journaledDeletes, $oldDatabasePages, $newDatabasePages, $oldJournalCopiedBack, $atomicBatchWrite): void {
        $profile = SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(
            $originalRows,
            $journaledDeletes,
            $oldDatabasePages,
            $newDatabasePages,
            $oldJournalCopiedBack,
            $atomicBatchWrite
        );

        $eligible = !$atomicBatchWrite;

        $t->same('ok', $profile['status']);
        $t->same('journal1.test', $profile['script']);
        $t->same($originalRows, $profile['original_rows']);
        $t->same($journaledDeletes, $profile['journaled_deletes']);
        $t->same($oldDatabasePages, $profile['old_database_pages']);
        $t->same($newDatabasePages, $profile['new_database_pages']);
        $t->same(true, $profile['old_database_deleted']);
        $t->same($oldJournalCopiedBack, $profile['old_journal_copied_back']);
        $t->same($atomicBatchWrite, $profile['atomic_batch_write']);
        $t->same($eligible, $profile['upstream_platform_eligible']);
        $t->same(true, $profile['new_database_created']);
        $t->same($oldJournalCopiedBack && $eligible, $profile['stale_journal_hot_candidate']);
        $t->same(false, $profile['stale_journal_replayed_into_new_database']);
        $t->same($oldJournalCopiedBack && $eligible, $profile['stale_journal_ignored']);
        $t->same(0, $profile['sqlite_master_result_code']);
        $t->same(0, $profile['sqlite_master_rows']);
        $t->same(0, $profile['new_database_rows_after_open']);
        $t->same(0, $profile['recovered_old_rows']);
        $t->same(
            $atomicBatchWrite
                ? 'journal1_skipped_when_atomic_batch_write_omits_rollback_journal'
                : 'stale_rollback_journal_is_not_replayed_into_recreated_database',
            $profile['reason']
        );
        $t->same(true, in_array('upstream-journal1-stale-rollback-journal', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-rollback-journal-hotness', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs journal1 stale rollback rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(0, 1, 1, 1, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(1, 0, 1, 1, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(1, 1, 0, 1, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(1, 1, 1, 0, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(2, 3, 1, 1, true));
};

return $tests;
