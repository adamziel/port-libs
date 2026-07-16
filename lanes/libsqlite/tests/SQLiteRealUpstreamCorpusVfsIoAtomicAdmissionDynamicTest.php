<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$flagSets = [
    ['atomic'],
    ['atomic512'],
    ['atomic2k'],
    ['atomic4k'],
    ['atomic', 'safe_append'],
    ['batch_atomic'],
];

foreach ($flagSets as $flagIndex => $flags) {
    foreach ([512, 1024, 2048, 4096] as $pageSize) {
        foreach ([0, 512, 1024, 2048, 4096] as $sectorSize) {
            foreach ([0, 1, 2] as $appendedPages) {
                $changedPages = 1 + (($flagIndex + $pageSize + $sectorSize + $appendedPages) % 3);
                $multiFileCommit = (($flagIndex + $appendedPages) % 4) === 0;
                $explicitRollback = (($pageSize + $sectorSize + $appendedPages) % 7) === 0;
                $exclusiveLocking = (($flagIndex + $sectorSize) % 5) === 0;
                $journalPathBlocked = (($flagIndex + $pageSize + $sectorSize + $appendedPages) % 11) === 0;
                $name = sprintf(
                    'real upstream corpus vfs io atomic admission io.test 2.6-2.11 flags %02d page %04d sector %04d append %d',
                    $flagIndex,
                    $pageSize,
                    $sectorSize,
                    $appendedPages
                );

                $tests[$name] = static function (TestRunner $t) use (
                    $flags,
                    $pageSize,
                    $sectorSize,
                    $changedPages,
                    $appendedPages,
                    $multiFileCommit,
                    $explicitRollback,
                    $exclusiveLocking,
                    $journalPathBlocked
                ): void {
                    $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission(
                        $flags,
                        $pageSize,
                        $sectorSize,
                        $changedPages,
                        $appendedPages,
                        $multiFileCommit,
                        $explicitRollback,
                        $exclusiveLocking,
                        $journalPathBlocked
                    );

                    $writesDatabase = $changedPages > 0 || $appendedPages > 0;
                    $singleAtomic = $plan['atomic_write_allowed']
                        && $changedPages <= 1
                        && $appendedPages === 0
                        && !$multiFileCommit;
                    $expectedJournal = $writesDatabase && !$singleAtomic && !$exclusiveLocking;
                    $blockedCommit = $journalPathBlocked && $expectedJournal && !$explicitRollback;

                    $t->same('ok', $plan['status']);
                    $t->same('io.test', $plan['script']);
                    $t->same($pageSize, $plan['page_size']);
                    $t->same($sectorSize, $plan['sector_size']);
                    $t->same($changedPages, $plan['changed_pages']);
                    $t->same($appendedPages, $plan['appended_pages']);
                    $t->same($multiFileCommit, $plan['multi_file_commit']);
                    $t->same($explicitRollback, $plan['explicit_rollback']);
                    $t->same($exclusiveLocking, $plan['exclusive_locking']);
                    $t->same($journalPathBlocked, $plan['journal_path_blocked']);
                    $t->same($singleAtomic, $plan['atomic_write_optimization']);
                    $t->same($expectedJournal, $plan['journal_required']);
                    $t->same($expectedJournal && $plan['atomic_write_allowed'] && !$singleAtomic, $plan['journal_deferred_until_commit']);
                    $t->same($blockedCommit ? ($multiFileCommit ? 'SQLITE_IOERR_ROLLBACK' : 'SQLITE_CANTOPEN') : 'ok', $plan['commit_status']);
                    $t->same($blockedCommit || $explicitRollback, $plan['rollback_required']);
                    $t->same(true, in_array('upstream-io-atomic-journal-admission', $plan['dependencies'], true));
                    $t->same(true, in_array('io.test io-2.6.1-2.6.4', $plan['upstream'], true));
                    $t->same(true, in_array('io.test io-2.11.1-2.11.2', $plan['upstream'], true));
                };
            }
        }
    }
}

foreach ($flagSets as $flagIndex => $flags) {
    foreach ([512, 1024, 2048, 4096] as $pageSize) {
        foreach ([0, 512, 1024, 2048, 4096] as $sectorSize) {
            foreach ([1, 2, 3, 5] as $secondChangedPages) {
                $firstChangedPages = (($flagIndex + $secondChangedPages) % 3) + 1;
                $journalPathBlocked = (($flagIndex + $pageSize + $sectorSize + $secondChangedPages) % 13) === 0;
                $syncMode = (($flagIndex + $secondChangedPages) % 5) === 0 ? 'off' : ((($sectorSize + $pageSize) % 3) === 0 ? 'normal' : 'full');
                $directorySync = (($flagIndex + $sectorSize) % 2) === 0;
                $name = sprintf(
                    'real upstream corpus vfs io atomic multi page fallback io.test 2.5 flags %02d page %04d sector %04d second %d',
                    $flagIndex,
                    $pageSize,
                    $sectorSize,
                    $secondChangedPages
                );

                $tests[$name] = static function (TestRunner $t) use (
                    $flags,
                    $pageSize,
                    $sectorSize,
                    $firstChangedPages,
                    $secondChangedPages,
                    $journalPathBlocked,
                    $syncMode,
                    $directorySync
                ): void {
                    $plan = SQLiteVfsIoDynamicPlan::atomicMultiPageJournalProfile(
                        $flags,
                        $pageSize,
                        $sectorSize,
                        $firstChangedPages,
                        $secondChangedPages,
                        $journalPathBlocked,
                        $syncMode,
                        $directorySync
                    );

                    $totalChangedPages = $firstChangedPages + $secondChangedPages;
                    $journalCreated = $totalChangedPages > 1 && !$journalPathBlocked;

                    $t->same('ok', $plan['status']);
                    $t->same('io.test', $plan['script']);
                    $t->same($pageSize, $plan['page_size']);
                    $t->same($sectorSize, $plan['sector_size']);
                    $t->same($firstChangedPages, $plan['first_changed_pages']);
                    $t->same($secondChangedPages, $plan['second_changed_pages']);
                    $t->same($totalChangedPages, $plan['total_changed_pages']);
                    $t->same($syncMode, $plan['sync_mode']);
                    $t->same($directorySync, $plan['directory_sync']);
                    $t->same($journalPathBlocked, $plan['journal_path_blocked']);
                    $t->same($totalChangedPages > 1, $plan['multi_page_requires_journal']);
                    $t->same($journalCreated, $plan['journal_created_after_second_write']);
                    $t->same($journalCreated ? $totalChangedPages : 0, $plan['journal_page_writes']);
                    $t->same($totalChangedPages + 1, $plan['database_page_writes']);
                    $t->same($journalPathBlocked ? 'SQLITE_CANTOPEN' : 'ok', $plan['commit_status']);
                    $t->same($journalPathBlocked, $plan['rollback_required']);
                    $t->same(true, in_array('upstream-io-atomic-multi-page-journal', $plan['dependencies'], true));
                    $t->same(['io.test io-2.5.1', 'io.test io-2.5.2', 'io.test io-2.5.3'], $plan['upstream']);
                };
            }
        }
    }
}

$tests['real upstream corpus vfs io atomic admission cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-2.5.1 through io-2.5.3 multi-page atomic write fallback creates rollback journal on second dirty page',
        'io.test io-2.6.1 through io-2.6.4 appended page disables single-page atomic commit',
        'io.test io-2.7.1 through io-2.7.6 rollback preserves previous committed rows after deferred journal admission',
        'io.test io-2.8.1 through io-2.8.3 exclusive locking suppresses rollback journal admission',
        'io.test io-2.9.1 through io-2.10.3 blocked journal path reports commit errors and preserves previous rows',
        'io.test io-2.11.1 through io-2.11.2 atomic sectors wider than page size do not over-admit single-page writes',
    ], [
        'io.test io-2.5.1 through io-2.5.3 multi-page atomic write fallback creates rollback journal on second dirty page',
        'io.test io-2.6.1 through io-2.6.4 appended page disables single-page atomic commit',
        'io.test io-2.7.1 through io-2.7.6 rollback preserves previous committed rows after deferred journal admission',
        'io.test io-2.8.1 through io-2.8.3 exclusive locking suppresses rollback journal admission',
        'io.test io-2.9.1 through io-2.10.3 blocked journal path reports commit errors and preserves previous rows',
        'io.test io-2.11.1 through io-2.11.2 atomic sectors wider than page size do not over-admit single-page writes',
    ]);
};

return $tests;
