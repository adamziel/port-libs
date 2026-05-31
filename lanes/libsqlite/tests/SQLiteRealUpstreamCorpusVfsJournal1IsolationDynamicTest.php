<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$case = 0;
foreach ([8, 16, 32, 64, 128] as $originalRows) {
    foreach ([1, 2, 3, 5, 8] as $deleteModulo) {
        foreach ([2, 3, 5, 8, 13] as $oldDatabasePages) {
            foreach ([1, 2, 4, 7] as $newDatabasePages) {
                foreach ([true, false] as $oldJournalCopiedBack) {
                    foreach ([false, true] as $atomicBatchWrite) {
                        ++$case;
                        $journaledDeletes = max(1, intdiv($originalRows, $deleteModulo));
                        $tests[sprintf(
                            'real upstream corpus vfs journal1 stale rollback isolation %04d rows %03d deletes %03d oldpages %02d newpages %02d copied %d atomic %d',
                            $case,
                            $originalRows,
                            $journaledDeletes,
                            $oldDatabasePages,
                            $newDatabasePages,
                            $oldJournalCopiedBack ? 1 : 0,
                            $atomicBatchWrite ? 1 : 0
                        )] = static function (TestRunner $t) use ($originalRows, $journaledDeletes, $oldDatabasePages, $newDatabasePages, $oldJournalCopiedBack, $atomicBatchWrite): void {
                            $profile = SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(
                                $originalRows,
                                $journaledDeletes,
                                $oldDatabasePages,
                                $newDatabasePages,
                                $oldJournalCopiedBack,
                                $atomicBatchWrite
                            );

                            $eligible = !$atomicBatchWrite;
                            $staleCandidate = $oldJournalCopiedBack && $eligible;

                            $t->same('ok', $profile['status']);
                            $t->same('journal1.test', $profile['script']);
                            $t->same(['journal1.test journal1-1.1', 'journal1.test journal1-1.2'], $profile['upstream']);
                            $t->same($originalRows, $profile['original_rows']);
                            $t->same($journaledDeletes, $profile['journaled_deletes']);
                            $t->same($oldDatabasePages, $profile['old_database_pages']);
                            $t->same($newDatabasePages, $profile['new_database_pages']);
                            $t->same(true, $profile['old_database_deleted']);
                            $t->same($oldJournalCopiedBack, $profile['old_journal_copied_back']);
                            $t->same($atomicBatchWrite, $profile['atomic_batch_write']);
                            $t->same($eligible, $profile['upstream_platform_eligible']);
                            $t->same(true, $profile['new_database_created']);
                            $t->same($staleCandidate, $profile['stale_journal_hot_candidate']);
                            $t->same(false, $profile['stale_journal_replayed_into_new_database']);
                            $t->same($staleCandidate, $profile['stale_journal_ignored']);
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
                }
            }
        }
    }
}

$tests['real upstream corpus vfs journal1 stale rollback isolation cites hydrated source sections'] = static function (TestRunner $t) use ($case): void {
    $profile = SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(32, 8, 5, 1, true);

    $t->same(2000, $case);
    $t->same(['journal1.test journal1-1.1', 'journal1.test journal1-1.2'], $profile['upstream']);
    $t->same(true, $profile['stale_journal_hot_candidate']);
    $t->same(true, $profile['stale_journal_ignored']);
    $t->same(false, $profile['stale_journal_replayed_into_new_database']);
    $t->same(0, $profile['new_database_rows_after_open']);
};

$tests['real upstream corpus vfs journal1 stale rollback isolation rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(0, 1, 1, 1, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(1, 0, 1, 1, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(1, 1, 0, 1, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(1, 1, 1, 0, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile(4, 5, 1, 1, true));
};

return $tests;
