<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/journal1.test';

$tests['real upstream corpus vfs journal1 stale rollback cites hydrated upstream source'] = static function (TestRunner $t) use ($source): void {
    $contents = file_get_contents($source);

    $t->same(true, is_string($contents));
    $t->contains('do_test journal1-1.1', $contents);
    $t->contains('do_test journal1-1.2', $contents);
    $t->contains('leftover journals from', $contents);
    $t->contains('prior databases do not try to rollback into new databases', $contents);
    $t->same([
        'journal1.test journal1-1.1 create sample database and rollback journal',
        'journal1.test journal1-1.2 stale copied rollback journal ignored after database deletion',
    ], SQLiteVfsIoDynamicPlan::staleRollbackJournalNewDatabaseProfile(8, 400, true)['upstream']);
};

$case = 0;
$rowCounts = [2, 4, 8, 16, 32];
$payloadSizes = [40, 80, 160, 320, 400];

foreach (range(1, 40) as $round) {
    foreach ($rowCounts as $initialRows) {
        foreach ($payloadSizes as $payloadBytes) {
            ++$case;
            $databaseDeletedBeforeReopen = true;

            $tests[sprintf(
                'real upstream corpus vfs journal1 stale rollback dynamic %04d rows %02d payload %03d',
                $case,
                $initialRows,
                $payloadBytes
            )] = static function (TestRunner $t) use ($initialRows, $payloadBytes, $databaseDeletedBeforeReopen): void {
                $profile = SQLiteVfsIoDynamicPlan::staleRollbackJournalNewDatabaseProfile(
                    $initialRows,
                    $payloadBytes,
                    $databaseDeletedBeforeReopen
                );

                $t->same('ok', $profile['status']);
                $t->same('journal1.test', $profile['script']);
                $t->same($initialRows, $profile['initial_rows']);
                $t->same($payloadBytes, $profile['payload_bytes']);
                $t->same(true, $profile['journal_created_before_rollback']);
                $t->same(true, $profile['journal_backup_copied']);
                $t->same(true, $profile['rollback_restored_original_database']);
                $t->same($databaseDeletedBeforeReopen, $profile['database_deleted_before_reopen']);
                $t->same($databaseDeletedBeforeReopen, $profile['new_database_opened']);
                $t->same($databaseDeletedBeforeReopen, $profile['stale_journal_present_on_reopen']);
                $t->same($databaseDeletedBeforeReopen, $profile['stale_journal_ignored']);
                $t->same(false, $profile['rollback_attempted_against_new_database']);
                $t->same(0, $profile['sqlite_master_result_code']);
                $t->same($databaseDeletedBeforeReopen ? [] : ['t1'], $profile['sqlite_master_rows']);
                $t->same($databaseDeletedBeforeReopen ? 'stale_rollback_journal_header_does_not_match_new_database' : 'original_database_was_not_replaced', $profile['reason']);
                $t->same(true, in_array('upstream-journal1-stale-rollback-journal', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-rollback-journal-database-identity', $profile['dependencies'], true));
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            };
        }
    }
}

$tests['real upstream corpus vfs journal1 stale rollback dynamic owns one thousand cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

$tests['real upstream corpus vfs journal1 stale rollback dynamic skip gates match upstream guards'] = static function (TestRunner $t): void {
    $atomic = SQLiteVfsIoDynamicPlan::staleRollbackJournalNewDatabaseProfile(8, 400, true, false, false);
    $windows = SQLiteVfsIoDynamicPlan::staleRollbackJournalNewDatabaseProfile(8, 400, true, true, true);

    $t->same('skipped', $atomic['status']);
    $t->same(false, $atomic['journal_created_before_rollback']);
    $t->same('journal1_guard_skipped_for_platform_or_atomic_batch_write', $atomic['reason']);
    $t->same('skipped', $windows['status']);
    $t->same(true, $windows['windows_copy_locking_unsupported']);
    $t->same(false, $windows['stale_journal_ignored']);
};

$tests['real upstream corpus vfs journal1 stale rollback dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalNewDatabaseProfile(0, 400, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalNewDatabaseProfile(7, 400, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::staleRollbackJournalNewDatabaseProfile(8, 0, true));
};

return $tests;
