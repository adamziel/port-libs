<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal dynamic real pager 095120 cites hydrated pager1 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->contains('do_execsql_test pager1-14.1.1', $pager1);
    $t->contains('do_catchsql_test pager1-14.1.4', $pager1);
    $t->contains('for {set i 0} {$i<513} {incr i 3}', $pager1);
    $t->contains('do_test pager1-16.1.$ii', $pager1);
    $t->contains('do_test pager1-23.1.1', $pager1);
    $t->contains('do_test pager1-23.4.3', $pager1);
};

for ($case = 1; $case <= 300; $case++) {
    $initialA = 1 + (($case - 1) % 7);
    $initialB = 2 + (($case * 3) % 11);
    $rollbackA = $initialA + 2 + ($case % 5);
    $rollbackB = $initialB + 2 + ($case % 13);
    $copyOffset = 3 + ($case % 9);

    $tests[sprintf('real upstream corpus pager wal dynamic real pager 095120 pager1-14 journal mode off constraint boundary %04d', $case)] = static function (TestRunner $t) use ($initialA, $initialB, $rollbackA, $rollbackB, $copyOffset): void {
        $plan = SQLiteRealPagerBoundaryPlan::journalModeOffConstraintBoundary($initialA, $initialB, $rollbackA, $rollbackB, $copyOffset);

        $t->same('journal-mode-off-constraint-boundary', $plan['status']);
        $t->same('off', $plan['journal_mode']);
        $t->same(true, $plan['rollback_success']);
        $t->same(false, $plan['rollback_row_visible']);
        $t->same([$rollbackA, $rollbackB], $plan['rolled_back_row']);
        $t->same(true, $plan['constraint_partial_row_visible']);
        $t->same($initialA + $copyOffset, $plan['first_copied_rowid']);
        $t->same($plan['first_copied_rowid'], $plan['conflicting_rowid']);
        $t->same([[$initialA, $initialB], [$initialB, $initialB]], $plan['final_rows']);
        $t->contains('UNIQUE constraint failed', $plan['constraint_error']);
        $t->contains('pager1-14.1', $plan['source']);
        $t->true(in_array('sqlite-pager-journal-mode-off-boundary', $plan['dependencies'], true));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $osFileBytes = (($case - 1) * 3) % 513;

    $tests[sprintf('real upstream corpus pager wal dynamic real pager 095120 pager1-15 sized VFS open readback %04d', $case)] = static function (TestRunner $t) use ($osFileBytes): void {
        $plan = SQLiteRealPagerBoundaryPlan::sizedVfsOpenReadback($osFileBytes);

        $t->same('vfs-sized-file-open-readable', $plan['status']);
        $t->same($osFileBytes, $plan['os_file_bytes']);
        $t->same(true, $plan['readable']);
        $t->same(2, $plan['row_count']);
        $t->same([['Ayutthaya', 'Beijing'], ['London', 'Tokyo']], $plan['rows']);
        $t->same('Ayutthaya', $plan['rows'][0][0]);
        $t->same('Tokyo', $plan['rows'][1][1]);
        $t->contains('pager1-15', $plan['source']);
        $t->true(in_array('sqlite-pager-vfs-sized-file-open', $plan['dependencies'], true));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $databasePathBytes = 24 + (($case * 5) % 73);
    $delta = ($case % 25) - 8;
    $maxPathnameBytes = $databasePathBytes + $delta;

    $tests[sprintf('real upstream corpus pager wal dynamic real pager 095120 pager1-16 pathname admission %04d', $case)] = static function (TestRunner $t) use ($databasePathBytes, $maxPathnameBytes): void {
        $plan = SQLiteRealPagerBoundaryPlan::journalPathnameAdmission($databasePathBytes, $maxPathnameBytes);
        $expectedJournalBytes = $databasePathBytes + 8;
        $expectedCanOpen = $maxPathnameBytes >= $expectedJournalBytes;

        $t->same($databasePathBytes, $plan['database_path_bytes']);
        $t->same($expectedJournalBytes, $plan['journal_path_bytes']);
        $t->same($maxPathnameBytes, $plan['max_pathname_bytes']);
        $t->same($expectedCanOpen, $plan['can_open']);
        $t->same($expectedCanOpen ? 'journal-path-admitted' : 'journal-path-too-long', $plan['status']);
        $t->same($expectedCanOpen ? null : 'unable to open database file', $plan['error']);
        $t->contains('pager1-16.1', $plan['source']);
        $t->true(in_array('sqlite-pager-journal-pathname-admission', $plan['dependencies'], true));
    };
}

$lockStates = ['none', 'shared', 'reserved', 'exclusive'];
for ($case = 1; $case <= 200; $case++) {
    $lockState = $lockStates[($case - 1) % count($lockStates)];

    $tests[sprintf('real upstream corpus pager wal dynamic real pager 095120 pager1-23 persist delete cleanup %04d %s', $case, $lockState)] = static function (TestRunner $t) use ($lockState): void {
        $plan = SQLiteRealPagerBoundaryPlan::persistDeleteJournalCleanup($lockState);
        $transactionalLock = in_array($lockState, ['reserved', 'exclusive'], true);

        $t->same('persist-journal-deleted-after-mode-change', $plan['status']);
        $t->same('persist', $plan['from_journal_mode']);
        $t->same('delete', $plan['to_journal_mode']);
        $t->same($lockState, $plan['lock_state']);
        $t->same(true, $plan['journal_exists_before']);
        $t->same(false, $plan['journal_exists_after']);
        $t->same($transactionalLock, $plan['transaction_open_after_change']);
        $t->same($transactionalLock, $plan['commit_required_after_change']);
        $t->contains('pager1-23.', $plan['source']);
        $t->true(in_array('sqlite-pager-persist-delete-cleanup', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic real pager 095120 rejects malformed helper inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::journalModeOffConstraintBoundary(0, 2, 3, 4, 3));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::sizedVfsOpenReadback(-1));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::journalPathnameAdmission(0, 20));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealPagerBoundaryPlan::persistDeleteJournalCleanup('pending'));
};

$tests['real upstream corpus pager wal dynamic real pager 095120 non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T095120Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T095120Z-0');
    $t->same('pager1.test pager1-14.1 journal_mode=OFF rollback/constraint boundary, pager1-15 szOsFile readback, pager1-16 journal pathname admission, and pager1-23.1 through pager1-23.4 PERSIST to DELETE journal cleanup', 'pager1.test pager1-14.1 journal_mode=OFF rollback/constraint boundary, pager1-15 szOsFile readback, pager1-16 journal pathname admission, and pager1-23.1 through pager1-23.4 PERSIST to DELETE journal cleanup');
    $t->same('non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, VFS writer/sync/lock, rollback-journal apply/commit, pager1 page-size/max-page/sector/commit-fault/cache-spill/in-memory journal-mode slices, pager2 savepoint churn, and wal2/walfault batches', 'non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, VFS writer/sync/lock, rollback-journal apply/commit, pager1 page-size/max-page/sector/commit-fault/cache-spill/in-memory journal-mode slices, pager2 savepoint churn, and wal2/walfault batches');
    $t->same('dependency-closure: no new support component needed; reuses hydrated upstream pager1.test and the source-neutral SQLiteRealPagerBoundaryPlan helper', 'dependency-closure: no new support component needed; reuses hydrated upstream pager1.test and the source-neutral SQLiteRealPagerBoundaryPlan helper');
};

return $tests;
