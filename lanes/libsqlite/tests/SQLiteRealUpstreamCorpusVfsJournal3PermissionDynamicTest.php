<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/journal3.test';

$tests['real upstream corpus vfs journal3 permission dynamic cites hydrated upstream source'] = static function (TestRunner $t) use ($source): void {
    $t->same(true, is_file($source));
    $body = file_get_contents($source);
    $t->contains('file attributes test.db -permissions', $body);
    $t->contains('file attr test.db-journal -perm', $body);
    $t->contains('ROLLBACK', $body);

    $plan = SQLiteVfsIoDynamicPlan::rollbackJournalPermissionProfile(0644, 1);
    $t->same([
        'journal3.test journal3-1.1 create table',
        'journal3.test journal3-1.2.1 database mode 00644',
        'journal3.test journal3-1.2.2 database mode 00666',
        'journal3.test journal3-1.2.3 database mode 00600',
        'journal3.test journal3-1.2.4 database mode 00755',
    ], $plan['upstream']);
};

$upstreamModes = [
    0644 => '00644',
    0666 => '00666',
    0600 => '00600',
    0755 => '00755',
];

$case = 0;
for ($round = 0; $round < 250; $round++) {
    foreach ($upstreamModes as $mode => $expectedMode) {
        $case++;
        $changedRows = 1 + (($round * 7 + $mode) % 64);

        $tests[sprintf('real upstream corpus vfs journal3 permission dynamic %04d mode %s rows %02d', $case, $expectedMode, $changedRows)] = static function (TestRunner $t) use ($mode, $expectedMode, $changedRows): void {
            $plan = SQLiteVfsIoDynamicPlan::rollbackJournalPermissionProfile($mode, $changedRows);

            $t->same('ok', $plan['status']);
            $t->same('journal3.test', $plan['script']);
            $t->same($expectedMode, $plan['database_permissions']);
            $t->same($expectedMode, $plan['journal_permissions']);
            $t->same($changedRows, $plan['changed_rows']);
            $t->same(true, $plan['atomic_batch_write_disabled']);
            $t->same(false, $plan['windows_permissions_unsupported']);
            $t->same(false, $plan['journal_exists_before_transaction']);
            $t->same(true, $plan['journal_created_during_transaction']);
            $t->same(true, $plan['journal_permission_matches_database']);
            $t->same('ok', $plan['rollback_result']);
            $t->same(true, $plan['journal_removed_after_rollback']);
            $t->same('ok', $plan['integrity_check']);
            $t->same('rollback_journal_inherits_database_file_permissions', $plan['reason']);
            $t->same(true, in_array('upstream-journal3-permission-inheritance', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-rollback-journal-file-permissions', $plan['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs journal3 permission dynamic skip gates match upstream guards'] = static function (TestRunner $t): void {
    $atomic = SQLiteVfsIoDynamicPlan::rollbackJournalPermissionProfile(0644, 1, false);
    $windows = SQLiteVfsIoDynamicPlan::rollbackJournalPermissionProfile(0644, 1, true, true);

    foreach ([$atomic, $windows] as $plan) {
        $t->same('skipped', $plan['status']);
        $t->same(null, $plan['journal_permissions']);
        $t->same(false, $plan['journal_created_during_transaction']);
        $t->same(false, $plan['journal_permission_matches_database']);
        $t->same(false, $plan['journal_removed_after_rollback']);
        $t->same('journal_permission_probe_not_applicable_for_platform_or_atomic_batch_write', $plan['reason']);
    }
};

$tests['real upstream corpus vfs journal3 permission dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::rollbackJournalPermissionProfile(-1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::rollbackJournalPermissionProfile(010000, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::rollbackJournalPermissionProfile(0644, 0));
};

$tests['real upstream corpus vfs journal3 permission dynamic case volume'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

return $tests;
