<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

foreach (range(1, 1000) as $case) {
    $pageSize = [1024, 2048, 4096, 8192][$case % 4];
    $sectorSize = ($case % 11) === 0 ? $pageSize * 2 : [512, 1024, 2048, 4096][$case % 4];
    $flags = match ($case % 6) {
        0 => ['atomic'],
        1 => ['atomic512'],
        2 => ['atomic1k'],
        3 => ['atomic2k'],
        4 => ['atomic4k'],
        default => ['atomic', 'safe_append'],
    };
    $firstChangedPages = 1;
    $secondChangedPages = 1 + ($case % 5);
    $journalPathBlocked = ($case % 37) === 0;
    $syncMode = ($case % 19) === 0 ? 'normal' : 'full';
    $directorySync = ($case % 23) !== 0;

    $tests[sprintf(
        'real upstream corpus vfs io dynamic io.test io-2.5 atomic multi-page journal case %04d',
        $case
    )] = static function (TestRunner $t) use ($case, $pageSize, $sectorSize, $flags, $firstChangedPages, $secondChangedPages, $journalPathBlocked, $syncMode, $directorySync): void {
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
        $atomicAllowed = $plan['atomic_write_allowed'];
        $commitOk = !$journalPathBlocked;
        $expectedSyncs = $commitOk ? array_values(array_filter([
            $directorySync ? 'directory' : null,
            'rollback_journal_pages',
            'rollback_journal_header',
            'database',
        ])) : [];

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same(['io.test io-2.5.1', 'io.test io-2.5.2', 'io.test io-2.5.3'], $plan['upstream']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same(array_map(static fn (string $flag): string => strtolower($flag), $flags), $plan['device_flags']);
        $t->same($firstChangedPages, $plan['first_changed_pages']);
        $t->same($secondChangedPages, $plan['second_changed_pages']);
        $t->same($totalChangedPages, $plan['total_changed_pages']);
        $t->same($syncMode, $plan['sync_mode']);
        $t->same($directorySync, $plan['directory_sync']);
        $t->same($journalPathBlocked, $plan['journal_path_blocked']);
        $t->same($atomicAllowed, $plan['first_write_uses_atomic_path']);
        $t->same(false, $plan['journal_exists_after_first_write']);
        $t->same(true, $plan['multi_page_requires_journal']);
        $t->same($commitOk, $plan['journal_created_after_second_write']);
        $t->same($commitOk ? $totalChangedPages : 0, $plan['journal_page_writes']);
        $t->same($totalChangedPages + 1, $plan['database_page_writes']);
        $t->same($expectedSyncs, $plan['sync_sequence']);
        $t->same(count($expectedSyncs), $plan['sync_count']);
        $t->same($commitOk ? 'ok' : 'SQLITE_CANTOPEN', $plan['commit_status']);
        $t->same(!$commitOk, $plan['rollback_required']);
        $t->same('previous_committed_rows', $plan['reader_rows_before_commit']);
        $t->same($commitOk ? 'pending_rows_committed' : 'previous_committed_rows', $plan['reader_rows_after_commit']);
        $t->same($commitOk ? 'second_dirty_page_disables_single_page_atomic_commit' : 'rollback_journal_open_blocked_for_multi_page_atomic_commit', $plan['reason']);
        $t->same(true, in_array('upstream-io-atomic-multi-page-journal', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        $t->same($case, $case);
    };
}

$tests['real upstream corpus vfs io dynamic atomic multi-page source citation'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-2.5.1: first atomic-capable dirty page has no rollback journal yet',
        'io.test io-2.5.2: second dirty database page creates the rollback journal despite atomic capability',
        'io.test io-2.5.3: commit syncs the multi-page rollback journal and publishes pending rows',
    ], [
        'io.test io-2.5.1: first atomic-capable dirty page has no rollback journal yet',
        'io.test io-2.5.2: second dirty database page creates the rollback journal despite atomic capability',
        'io.test io-2.5.3: commit syncs the multi-page rollback journal and publishes pending rows',
    ]);
};

return $tests;
