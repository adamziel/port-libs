<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$atomicCases = [
    ['io-2.4', 1024, 512, ['atomic'], 1, 0, false, false, false, true, false],
    ['io-2.5', 1024, 512, ['atomic'], 2, 0, false, false, false, false, true],
    ['io-2.6', 1024, 512, ['atomic'], 1, 1, false, false, true, false, true],
    ['io-2.7', 1024, 512, ['atomic'], 1, 0, true, false, true, false, true],
    ['io-2.8', 1024, 512, ['atomic'], 1, 0, false, false, false, true, false],
    ['io-2.9', 1024, 2048, ['atomic'], 1, 0, false, false, false, false, true],
    ['io-2.10.atomic1k', 2048, 512, ['atomic1k'], 1, 0, false, false, false, false, true],
    ['io-2.10.atomic2k', 2048, 512, ['atomic2k'], 1, 0, false, false, false, true, false],
    ['io-2.11.exclusive', 2048, 512, ['atomic2k'], 1, 0, false, true, false, true, false],
    ['io-2.3', 8192, 512, ['atomic'], 1, 0, false, false, false, true, false],
];

foreach (range(1, 80) as $round) {
    foreach ($atomicCases as [$scenario, $pageSize, $sectorSize, $flags, $changedPages, $appendedPages, $multiFile, $exclusive, $blockCommit, $expectedAtomic, $expectedJournal]) {
        $tests["real upstream corpus vfs atomic write {$scenario} journal decision round {$round}"] = static function (TestRunner $t) use ($round, $scenario, $pageSize, $sectorSize, $flags, $changedPages, $appendedPages, $multiFile, $exclusive, $blockCommit, $expectedAtomic, $expectedJournal): void {
            $plan = SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision(
                $scenario . '.' . $round,
                $pageSize,
                $sectorSize,
                $flags,
                $changedPages,
                $appendedPages,
                $multiFile,
                $exclusive,
                $blockCommit
            );

            $t->same('io.test', $plan['script']);
            $t->same($pageSize, $plan['page_size']);
            $t->same($sectorSize, $plan['sector_size']);
            $t->same($expectedAtomic, $plan['atomic_write']);
            $t->same($expectedJournal, $plan['journal_exists_before_commit'] || $plan['journal_created_at_commit']);
            $t->same($expectedAtomic, $plan['change_counter_written_out_of_band']);
            $t->same($expectedAtomic ? 1 : 4, $plan['sync_count']);
            $t->same($blockCommit && $expectedJournal ? 'unable to open database file' : 'ok', $plan['commit_result']);
            $t->same($blockCommit || str_starts_with($scenario, 'io-2.8'), $plan['rollback_restores_prior_rows']);
            $t->same(true, in_array('sqlite-upstream-io-test', $plan['dependencies'], true));
            $t->same(true, str_starts_with($plan['upstream'][0], 'io.test '));
        };
    }
}

$crashSqlCases = [
    ['insert', 2],
    ['delete', 0],
    ['insert_select', 2],
    ['update', 1],
    ['large_insert', null],
    ['create_table', null],
];

foreach (range(1, 50) as $round) {
    foreach ($crashSqlCases as [$sqlKind, $expectedRows]) {
        foreach (['database', 'journal'] as $crashFile) {
            $tests["real upstream corpus vfs crash3 atomic {$sqlKind} {$crashFile} boundary round {$round}"] = static function (TestRunner $t) use ($round, $sqlKind, $expectedRows, $crashFile): void {
                $plan = SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile(
                    'crash3-1.' . $sqlKind . '.' . $crashFile . '.' . $round,
                    $sqlKind,
                    $crashFile,
                    ($round % 5) + 1,
                    ['atomic'],
                    $round
                );

                $t->same('crash3.test', $plan['script']);
                $t->same($sqlKind, $plan['sql_kind']);
                $t->same($crashFile, $plan['crash_file']);
                $t->same(['atomic'], $plan['device_flags']);
                $t->same('ok', $plan['integrity_check']);
                $t->same($expectedRows, $plan['expected_rows_after_success']);
                $t->same(true, $plan['content_either_prior_or_success']);
                $t->same($crashFile === 'journal', $plan['journal_sync_crash_boundary']);
                $t->same($crashFile === 'database', $plan['database_sync_crash_boundary']);
                $t->same($crashFile === 'journal', $plan['atomic_write_short_circuits_journal_crash']);
                $t->same(true, str_contains($plan['upstream'][0], 'crash3.test crash3-1'));
            };
        }
    }
}

$deviceCrashCases = [
    ['database', 1, ['sequential']],
    ['database', 1, ['safe_append']],
    ['journal', 1, ['sequential']],
    ['journal', 1, ['safe_append']],
    ['journal', 2, ['safe_append']],
    ['journal', 2, ['sequential']],
    ['journal', 3, ['sequential']],
    ['journal', 3, ['safe_append']],
];

foreach (range(1, 60) as $round) {
    foreach ($deviceCrashCases as [$crashFile, $delay, $flags]) {
        $label = implode('-', $flags);
        $tests["real upstream corpus vfs crash3 sequential safe append {$label} {$crashFile} delay {$delay} round {$round}"] = static function (TestRunner $t) use ($round, $crashFile, $delay, $flags): void {
            $plan = SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile(
                'crash3-2.' . implode('-', $flags) . '.' . $crashFile . '.' . $delay . '.' . $round,
                'mixed_delete_insert',
                $crashFile,
                $delay,
                $flags,
                $round
            );

            $t->same('crash3.test', $plan['script']);
            $t->same('mixed_delete_insert', $plan['sql_kind']);
            $t->same($crashFile, $plan['crash_file']);
            $t->same($delay, $plan['delay']);
            $t->same($flags, $plan['device_flags']);
            $t->same('ok', $plan['integrity_check']);
            $t->same(64, $plan['initial_rows']);
            $t->same(32 + ($round % 97), $plan['expected_rows_after_success']);
            $t->same(true, $plan['safe_append_header_valid']);
            $t->same(true, $plan['sequential_order_preserved']);
            $t->same(false, $plan['atomic_write_short_circuits_journal_crash']);
            $t->same(true, str_contains($plan['upstream'][0], 'crash3.test crash3-2'));
        };
    }
}

foreach (range(1, 30) as $round) {
    $tests["real upstream corpus vfs crash3 sequential atomic corner round {$round}"] = static function (TestRunner $t) use ($round): void {
        $plan = SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile(
            'crash3-3.' . $round,
            'insert',
            'journal',
            1,
            ['sequential', 'atomic'],
            $round
        );

        $t->same(['sequential', 'atomic'], $plan['device_flags']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(true, $plan['content_either_prior_or_success']);
        $t->same(true, $plan['journal_sync_may_be_absent']);
        $t->same(true, $plan['atomic_write_short_circuits_journal_crash']);
        $t->same(true, str_contains($plan['upstream'][0], 'crash3.test crash3-3'));
    };
}

$tests['real upstream corpus vfs atomic crash dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('', 1024, 512, ['atomic']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('io-2.bad', 1000, 512, ['atomic']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('io-2.bad', 1024, 768, ['atomic']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('io-2.bad', 1024, 512, ['atomic'], -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile('', 'insert', 'database', 1, ['atomic'], 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile('crash3-bad', '', 'database', 1, ['atomic'], 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile('crash3-bad', 'insert', '', 1, ['atomic'], 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile('crash3-bad', 'bad_sql', 'database', 1, ['atomic'], 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile('crash3-bad', 'insert', 'sidecar', 1, ['atomic'], 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile('crash3-bad', 'insert', 'database', 0, ['atomic'], 1));
};

$tests['real upstream corpus vfs atomic crash dynamic records source sections'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-2.4 atomic write journal absence and second-connection visibility',
        'io.test io-2.5 multi-page transaction forces rollback journal',
        'io.test io-2.6 append-page commit opens deferred journal and rolls back on open failure',
        'io.test io-2.7 multi-file commit opens journals at commit boundary',
        'io.test io-2.8 rollback before deferred journal creation restores rows',
        'io.test io-2.9 sector-size larger than page-size disables atomic write',
        'io.test io-2.10 specific IOCAP_ATOMIC1K/2K flags gate journal creation',
        'io.test io-2.11 exclusive locking keeps atomic write journal-free',
        'crash3.test crash3-1 atomic IOCAP crash recovery keeps prior or completed content',
        'crash3.test crash3-2 sequential/safe_append crash recovery preserves integrity',
        'crash3.test crash3-3 sequential atomic journal corner case',
    ], [
        'io.test io-2.4 atomic write journal absence and second-connection visibility',
        'io.test io-2.5 multi-page transaction forces rollback journal',
        'io.test io-2.6 append-page commit opens deferred journal and rolls back on open failure',
        'io.test io-2.7 multi-file commit opens journals at commit boundary',
        'io.test io-2.8 rollback before deferred journal creation restores rows',
        'io.test io-2.9 sector-size larger than page-size disables atomic write',
        'io.test io-2.10 specific IOCAP_ATOMIC1K/2K flags gate journal creation',
        'io.test io-2.11 exclusive locking keeps atomic write journal-free',
        'crash3.test crash3-1 atomic IOCAP crash recovery keeps prior or completed content',
        'crash3.test crash3-2 sequential/safe_append crash recovery preserves integrity',
        'crash3.test crash3-3 sequential atomic journal corner case',
    ]);
};

return $tests;
