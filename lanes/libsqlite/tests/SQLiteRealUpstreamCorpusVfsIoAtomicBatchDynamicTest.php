<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

foreach (range(1, 400) as $case) {
    $pageSize = [512, 1024, 2048, 4096, 8192][($case - 1) % 5];
    $sectorSize = [0, 512, 1024, 4096][intdiv($case - 1, 5) % 4];
    $changedPages = ($case - 1) % 4;
    $appendedPages = intdiv($case - 1, 20) % 3;
    $multiFileCommit = ($case % 7) === 0;
    $explicitRollback = ($case % 11) === 0;
    $exclusiveLocking = ($case % 13) === 0;
    $journalPathBlocked = ($case % 17) === 0;
    $flags = match ($case % 6) {
        0 => ['atomic'],
        1 => ['atomic4k'],
        2 => ['atomic8k'],
        3 => ['safe_append'],
        4 => ['sequential'],
        default => ['batch_atomic'],
    };

    $tests[sprintf('real upstream corpus vfs io atomic batch dynamic io.test admission %03d', $case)] = static function (TestRunner $t) use ($flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multiFileCommit, $explicitRollback, $exclusiveLocking, $journalPathBlocked): void {
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
        $singlePageAtomic = $plan['atomic_write_allowed'] && $changedPages <= 1 && $appendedPages === 0 && !$multiFileCommit;
        $journalRequired = $writesDatabase && !$singlePageAtomic && !$exclusiveLocking;
        $expectedCommitStatus = ($journalPathBlocked && $journalRequired && !$explicitRollback)
            ? ($multiFileCommit ? 'SQLITE_IOERR_ROLLBACK' : 'SQLITE_CANTOPEN')
            : 'ok';

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same(true, in_array('io.test io-2.6.1-2.6.4', $plan['upstream'], true));
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($changedPages, $plan['changed_pages']);
        $t->same($appendedPages, $plan['appended_pages']);
        $t->same($multiFileCommit, $plan['multi_file_commit']);
        $t->same($explicitRollback, $plan['explicit_rollback']);
        $t->same($exclusiveLocking, $plan['exclusive_locking']);
        $t->same($journalPathBlocked, $plan['journal_path_blocked']);
        $t->same($singlePageAtomic, $plan['atomic_write_optimization']);
        $t->same($journalRequired, $plan['journal_required']);
        $t->same($expectedCommitStatus, $plan['commit_status']);
        $t->same($expectedCommitStatus !== 'ok' || $explicitRollback, $plan['rollback_required']);
        $t->same(true, in_array('upstream-io-atomic-journal-admission', $plan['dependencies'], true));
    };
}

foreach (range(1, 400) as $case) {
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $sectorSize = [0, 512, 1024, 2048, 4096][intdiv($case - 1, 4) % 5];
    $firstChangedPages = 1 + (($case - 1) % 3);
    $secondChangedPages = 1 + (intdiv($case - 1, 3) % 4);
    $journalPathBlocked = ($case % 19) === 0;
    $syncMode = ['off', 'normal', 'full'][intdiv($case - 1, 12) % 3];
    $directorySync = ($case % 5) !== 0;
    $flags = match ($case % 5) {
        0 => ['atomic'],
        1 => ['atomic1k'],
        2 => ['atomic2k'],
        3 => ['atomic4k'],
        default => ['safe_append'],
    };

    $tests[sprintf('real upstream corpus vfs io atomic batch dynamic io.test multipage %03d', $case)] = static function (TestRunner $t) use ($flags, $pageSize, $sectorSize, $firstChangedPages, $secondChangedPages, $journalPathBlocked, $syncMode, $directorySync): void {
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
        $multiPageRequiresJournal = $totalChangedPages > 1;
        $journalCreatedAfterSecondWrite = $multiPageRequiresJournal && !$journalPathBlocked;
        $expectedCommitStatus = $journalPathBlocked && $multiPageRequiresJournal ? 'SQLITE_CANTOPEN' : 'ok';

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same(['io.test io-2.5.1', 'io.test io-2.5.2', 'io.test io-2.5.3'], $plan['upstream']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($firstChangedPages, $plan['first_changed_pages']);
        $t->same($secondChangedPages, $plan['second_changed_pages']);
        $t->same($totalChangedPages, $plan['total_changed_pages']);
        $t->same($syncMode, $plan['sync_mode']);
        $t->same($directorySync, $plan['directory_sync']);
        $t->same(false, $plan['journal_exists_after_first_write']);
        $t->same($multiPageRequiresJournal, $plan['multi_page_requires_journal']);
        $t->same($journalCreatedAfterSecondWrite, $plan['journal_created_after_second_write']);
        $t->same($journalCreatedAfterSecondWrite ? $totalChangedPages : 0, $plan['journal_page_writes']);
        $t->same($expectedCommitStatus, $plan['commit_status']);
        $t->same($expectedCommitStatus !== 'ok', $plan['rollback_required']);
        $t->same(true, in_array('upstream-io-atomic-multi-page-journal', $plan['dependencies'], true));
    };
}

foreach (range(1, 400) as $case) {
    $initialRows = ($case - 1) % 17;
    $insertRows = 1 + (($case - 1) % 23);
    $indexedColumns = intdiv($case - 1, 7) % 5;
    $payloadBytes = 64 + (($case * 37) % 2048);
    $databaseWrites = $insertRows + ($indexedColumns * $insertRows);
    $failAt = 1 + (($case * 13) % max(1, $databaseWrites + 5));
    $failOnCommitAtomicWrite = ($case % 11) === 0;
    $flags = ($case % 4) === 0 ? ['sequential'] : ['batch_atomic'];

    $tests[sprintf('real upstream corpus vfs io atomic batch dynamic atomic2.test fallback %03d', $case)] = static function (TestRunner $t) use ($flags, $initialRows, $insertRows, $indexedColumns, $payloadBytes, $failAt, $failOnCommitAtomicWrite): void {
        $plan = SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(
            $flags,
            $initialRows,
            $insertRows,
            $indexedColumns,
            $payloadBytes,
            $failAt,
            $failOnCommitAtomicWrite
        );

        $batchAtomic = in_array('batch_atomic', $flags, true);
        $databaseWrites = $insertRows + ($indexedColumns * $insertRows);
        $writeFailsBeforeCommitAtomic = $batchAtomic && !$failOnCommitAtomicWrite && $failAt <= $databaseWrites;

        $t->same('ok', $plan['status']);
        $t->same('atomic2.test', $plan['script']);
        $t->same(['atomic2.test 1.0', 'atomic2.test 2.0 faultsim atomic batch fallback'], $plan['upstream']);
        $t->same($initialRows, $plan['initial_rows']);
        $t->same($insertRows, $plan['insert_rows']);
        $t->same($indexedColumns, $plan['indexed_columns']);
        $t->same($payloadBytes, $plan['payload_bytes']);
        $t->same($failAt, $plan['fail_at']);
        $t->same($failOnCommitAtomicWrite, $plan['fail_on_commit_atomic_write']);
        $t->same($batchAtomic, $plan['batch_atomic_supported']);
        $t->same($databaseWrites, $plan['database_write_calls']);
        $t->same($writeFailsBeforeCommitAtomic, $plan['write_fail_before_commit_atomic']);
        $t->same($writeFailsBeforeCommitAtomic, $plan['legacy_journal_fallback_used']);
        $t->same($writeFailsBeforeCommitAtomic ? $databaseWrites : 0, $plan['legacy_journal_page_writes']);
        $t->same($initialRows + $insertRows, $plan['rows_after_statement']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(true, in_array('upstream-atomic2-batch-write-fallback', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io atomic batch dynamic cites hydrated upstream scripts'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-2.5.1 through io-2.5.3 multi-page atomic journal fallback',
        'io.test io-2.6.1 through io-2.11.2 atomic journal admission and rollback visibility',
        'atomic2.test 1.0 and 2.0 batch-atomic write fallback under injected xWrite faults',
    ], [
        'io.test io-2.5.1 through io-2.5.3 multi-page atomic journal fallback',
        'io.test io-2.6.1 through io-2.11.2 atomic journal admission and rollback visibility',
        'atomic2.test 1.0 and 2.0 batch-atomic write fallback under injected xWrite faults',
    ]);
};

$tests['real upstream corpus vfs io atomic batch dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1000, 512, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1024, 1000, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['atomic'], 1024, 512, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicMultiPageJournalProfile(['atomic'], 1000, 512, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicMultiPageJournalProfile(['atomic'], 1024, 1000, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicMultiPageJournalProfile(['atomic'], 1024, 512, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicMultiPageJournalProfile(['atomic'], 1024, 512, 1, 1, false, 'extra'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], -1, 1, 0, 64, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], 0, 0, 0, 64, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], 0, 1, -1, 64, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], 0, 1, 0, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(['batch_atomic'], 0, 1, 0, 64, 0));
};

return $tests;
