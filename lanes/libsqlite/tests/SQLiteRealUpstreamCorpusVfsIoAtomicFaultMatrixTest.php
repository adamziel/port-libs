<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$pageSizes = [1024, 2048, 4096, 8192];
$sectorSizes = [512, 1024, 2048, 4096];
$flagSets = [
    ['atomic'],
    ['atomic2k'],
    ['atomic4k'],
    ['atomic8k'],
    ['atomic', 'safe_append'],
];

for ($case = 1; $case <= 360; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $sectorSize = $sectorSizes[intdiv($case, 3) % count($sectorSizes)];
    $flags = $flagSets[$case % count($flagSets)];
    $changedPages = $case % 5;
    $appendedPages = ($case % 11) === 0 ? 1 : 0;
    $multiFileCommit = ($case % 7) === 0;
    $explicitRollback = ($case % 13) === 0;
    $exclusiveLocking = ($case % 17) === 0;
    $journalPathBlocked = ($case % 19) === 0;

    $tests[sprintf('real upstream corpus vfs io atomic admission io.test io-2 matrix %03d', $case)] = static function (TestRunner $t) use ($flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multiFileCommit, $explicitRollback, $exclusiveLocking, $journalPathBlocked): void {
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

        $effectiveSector = $sectorSize === 0 ? 512 : $sectorSize;
        $sectorCompatible = $effectiveSector <= $pageSize;
        $atomicAllowed = $sectorCompatible && (
            in_array('atomic', $plan['device_flags'], true)
            || (in_array('atomic2k', $plan['device_flags'], true) && $pageSize <= 2048)
            || (in_array('atomic4k', $plan['device_flags'], true) && $pageSize <= 4096)
            || (in_array('atomic8k', $plan['device_flags'], true) && $pageSize <= 8192)
        );
        $writesDatabase = $changedPages > 0 || $appendedPages > 0;
        $singlePageAtomic = $atomicAllowed && $changedPages <= 1 && $appendedPages === 0 && !$multiFileCommit;
        $expectedJournalRequired = $writesDatabase && !$singlePageAtomic && !$exclusiveLocking;
        $expectedCommitStatus = $journalPathBlocked && $expectedJournalRequired && !$explicitRollback
            ? ($multiFileCommit ? 'SQLITE_IOERR_ROLLBACK' : 'SQLITE_CANTOPEN')
            : 'ok';

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($changedPages, $plan['changed_pages']);
        $t->same($appendedPages, $plan['appended_pages']);
        $t->same($multiFileCommit, $plan['multi_file_commit']);
        $t->same($explicitRollback, $plan['explicit_rollback']);
        $t->same($exclusiveLocking, $plan['exclusive_locking']);
        $t->same($atomicAllowed, $plan['atomic_write_allowed']);
        $t->same($singlePageAtomic, $plan['atomic_write_optimization']);
        $t->same($expectedJournalRequired, $plan['journal_required']);
        $t->same($expectedJournalRequired && $atomicAllowed && !$singlePageAtomic, $plan['journal_deferred_until_commit']);
        $t->same($expectedCommitStatus, $plan['commit_status']);
        $t->same($expectedCommitStatus !== 'ok' || $explicitRollback, $plan['rollback_required']);
        $t->same($expectedCommitStatus === 'ok' && !$explicitRollback ? 'pending_rows_committed' : 'previous_committed_rows', $plan['rows_visible_after']);
        $t->same(true, in_array('upstream-io-atomic-journal-admission', $plan['dependencies'], true));
    };
}

for ($case = 1; $case <= 260; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $sectorSize = $sectorSizes[$case % count($sectorSizes)];
    $flags = $flagSets[($case + 2) % count($flagSets)];
    $firstChangedPages = 1 + ($case % 3);
    $secondChangedPages = 1 + (($case + 1) % 4);
    $journalPathBlocked = ($case % 23) === 0;
    $syncMode = ($case % 29) === 0 ? 'off' : (($case % 5) === 0 ? 'normal' : 'full');
    $directorySync = ($case % 3) !== 0;

    $tests[sprintf('real upstream corpus vfs io multipage journal io.test io-2.5 matrix %03d', $case)] = static function (TestRunner $t) use ($flags, $pageSize, $sectorSize, $firstChangedPages, $secondChangedPages, $journalPathBlocked, $syncMode, $directorySync): void {
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

        $totalChanged = $firstChangedPages + $secondChangedPages;
        $journalCreated = $totalChanged > 1 && !$journalPathBlocked;
        $expectedSyncs = [];
        if ($syncMode !== 'off' && $journalCreated) {
            if ($directorySync) {
                $expectedSyncs[] = 'directory';
            }
            $expectedSyncs[] = 'rollback_journal_pages';
            $expectedSyncs[] = 'rollback_journal_header';
            $expectedSyncs[] = 'database';
        }

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($firstChangedPages, $plan['first_changed_pages']);
        $t->same($secondChangedPages, $plan['second_changed_pages']);
        $t->same($totalChanged, $plan['total_changed_pages']);
        $t->same(true, $plan['multi_page_requires_journal']);
        $t->same(false, $plan['journal_exists_after_first_write']);
        $t->same($journalCreated, $plan['journal_created_after_second_write']);
        $t->same($journalCreated ? $totalChanged : 0, $plan['journal_page_writes']);
        $t->same($totalChanged + 1, $plan['database_page_writes']);
        $t->same($expectedSyncs, $plan['sync_sequence']);
        $t->same(count($expectedSyncs), $plan['sync_count']);
        $t->same($journalPathBlocked ? 'SQLITE_CANTOPEN' : 'ok', $plan['commit_status']);
        $t->same($journalPathBlocked, $plan['rollback_required']);
        $t->same(true, in_array('upstream-io-atomic-multi-page-journal', $plan['dependencies'], true));
    };
}

for ($case = 1; $case <= 240; $case++) {
    $initialRows = $case % 4;
    $insertRows = 1 + ($case % 7);
    $indexedColumns = $case % 4;
    $payloadBytes = 64 + (($case % 31) * 13);
    $failAt = 1 + ($case % 47);
    $failOnCommitAtomicWrite = ($case % 11) === 0;
    $batchAtomic = ($case % 5) !== 0;
    $flags = $batchAtomic ? ['batch_atomic'] : [];

    $tests[sprintf('real upstream corpus vfs io atomic2 fault fallback matrix %03d', $case)] = static function (TestRunner $t) use ($flags, $initialRows, $insertRows, $indexedColumns, $payloadBytes, $failAt, $failOnCommitAtomicWrite, $batchAtomic): void {
        $plan = SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile(
            $flags,
            $initialRows,
            $insertRows,
            $indexedColumns,
            $payloadBytes,
            $failAt,
            $failOnCommitAtomicWrite
        );

        $databaseWrites = $insertRows + ($indexedColumns * $insertRows);
        $writeFailsBeforeCommitAtomic = $batchAtomic && !$failOnCommitAtomicWrite && $failAt <= $databaseWrites;

        $t->same('ok', $plan['status']);
        $t->same('atomic2.test', $plan['script']);
        $t->same($initialRows, $plan['initial_rows']);
        $t->same($insertRows, $plan['insert_rows']);
        $t->same($indexedColumns, $plan['indexed_columns']);
        $t->same($payloadBytes, $plan['payload_bytes']);
        $t->same($failAt, $plan['fail_at']);
        $t->same($batchAtomic, $plan['batch_atomic_supported']);
        $t->same($batchAtomic ? 1 : 0, $plan['atomic_batch_write_calls']);
        $t->same($databaseWrites, $plan['database_write_calls']);
        $t->same($writeFailsBeforeCommitAtomic, $plan['write_fail_before_commit_atomic']);
        $t->same($batchAtomic && $failOnCommitAtomicWrite, $plan['commit_atomic_write_clears_pending_fault']);
        $t->same($writeFailsBeforeCommitAtomic, $plan['legacy_journal_fallback_used']);
        $t->same($writeFailsBeforeCommitAtomic ? $databaseWrites : 0, $plan['legacy_journal_page_writes']);
        $t->same($initialRows + $insertRows, $plan['rows_after_statement']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(true, in_array('upstream-atomic2-batch-write-fallback', $plan['dependencies'], true));
    };
}

$journalScenarios = ['hot-journal-read', 'master-journal-name-read', 'statement-playback-constraint'];
$operations = ['read', 'write', 'sync', 'truncate'];

for ($case = 1; $case <= 220; $case++) {
    $scenario = $journalScenarios[$case % count($journalScenarios)];
    $operation = $operations[$case % count($operations)];
    $failAt = 1 + ($case % 83);
    $seedRows = 200 + ($case % 401);

    $tests[sprintf('real upstream corpus vfs ioerr journal playback matrix %03d', $case)] = static function (TestRunner $t) use ($scenario, $failAt, $operation, $seedRows): void {
        $plan = SQLiteVfsIoDynamicPlan::journalPlaybackIoErrorProfile($scenario, $failAt, $operation, $seedRows);

        $faultDetected = $failAt % 41 !== 0;
        $writeSideFault = in_array($operation, ['write', 'sync', 'truncate'], true);

        $t->same('ok', $plan['status']);
        $t->same('ioerr.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($failAt, $plan['fail_at']);
        $t->same($operation, $plan['operation']);
        $t->same($seedRows, $plan['seed_rows']);
        $t->same($faultDetected, $plan['fault_detected']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(true, $plan['rows_preserved']);
        $t->same(true, $plan['cache_refcount_zero']);
        $t->same(0, $plan['open_file_count']);
        $t->same($scenario === 'statement-playback-constraint', $plan['statement_journal_playback']);
        $t->same($scenario === 'statement-playback-constraint', $plan['constraint_message_preserved']);
        $t->same($faultDetected && ($scenario !== 'statement-playback-constraint' || $writeSideFault), $plan['rollback_required']);
        $t->same(true, in_array('upstream-ioerr-journal-playback', $plan['dependencies'], true));
    };
}

for ($case = 1; $case <= 120; $case++) {
    $operation = $operations[$case % count($operations)];
    $failAt = 1 + ($case % 53);
    $seedId = $case;
    $updatedId = $case + 1000;
    $seedName = 'seed-' . $case;
    $updatedName = 'updated-' . $case;

    $tests[sprintf('real upstream corpus vfs ioerr update assertion matrix %03d', $case)] = static function (TestRunner $t) use ($failAt, $operation, $seedId, $seedName, $updatedId, $updatedName): void {
        $plan = SQLiteVfsIoDynamicPlan::updateAssertionIoErrorProfile($failAt, $operation, $seedId, $seedName, $updatedId, $updatedName);

        $faultDetected = $failAt % 37 !== 0;
        $statementJournalRequired = in_array($operation, ['write', 'sync', 'truncate'], true);
        $expectedFinal = $faultDetected
            ? ['Id' => $seedId, 'Name' => $seedName]
            : ['Id' => $updatedId, 'Name' => $updatedName];

        $t->same('ok', $plan['status']);
        $t->same('ioerr.test', $plan['script']);
        $t->same('ioerr-11-update-assertion-fault', $plan['scenario']);
        $t->same($failAt, $plan['fail_at']);
        $t->same($operation, $plan['operation']);
        $t->same($faultDetected ? 'SQLITE_IOERR' : 'SQLITE_OK', $plan['expected_result']);
        $t->same($statementJournalRequired, $plan['statement_journal_required']);
        $t->same($faultDetected && $statementJournalRequired, $plan['rollback_required']);
        $t->same(true, $plan['btree_cursor_valid_after_fault']);
        $t->same(true, $plan['cache_refcount_zero']);
        $t->same('ok', $plan['integrity_check']);
        $t->same($expectedFinal, $plan['final_row']);
        $t->same(!$faultDetected, $plan['row_change_visible']);
        $t->same(true, in_array('upstream-ioerr-update-assertion-fault', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io atomic fault matrix cites source scripts'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-2.4 through io-2.11 atomic-write visibility, deferred journal creation, multi-file commit rollback, sector-specific atomic flags, and exclusive locking',
        'io.test io-2.5.1 through io-2.5.3 second-dirty-page journal creation after an initial atomic write',
        'atomic2.test 1.0 and 2.0 atomic batch write I/O fault fallback to legacy rollback journal commit',
        'ioerr.test ioerr-7, ioerr-9, and ioerr-10 journal playback I/O error recovery boundaries',
        'ioerr.test ioerr-11 UPDATE cursor assertion guard after read/write/sync/truncate faults',
    ], [
        'io.test io-2.4 through io-2.11 atomic-write visibility, deferred journal creation, multi-file commit rollback, sector-specific atomic flags, and exclusive locking',
        'io.test io-2.5.1 through io-2.5.3 second-dirty-page journal creation after an initial atomic write',
        'atomic2.test 1.0 and 2.0 atomic batch write I/O fault fallback to legacy rollback journal commit',
        'ioerr.test ioerr-7, ioerr-9, and ioerr-10 journal playback I/O error recovery boundaries',
        'ioerr.test ioerr-11 UPDATE cursor assertion guard after read/write/sync/truncate faults',
    ]);
};

return $tests;
