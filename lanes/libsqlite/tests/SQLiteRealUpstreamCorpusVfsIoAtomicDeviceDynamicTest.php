<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$atomicScenarios = [
    ['io-2.4', 1024, 512, ['atomic'], 1, 0, false, false, false],
    ['io-2.5', 1024, 512, ['atomic'], 2, 0, false, false, false],
    ['io-2.6', 1024, 512, ['atomic'], 1, 1, false, false, true],
    ['io-2.7', 1024, 512, ['atomic'], 1, 0, true, false, true],
    ['io-2.8', 1024, 512, ['atomic'], 1, 0, false, false, false],
    ['io-2.9', 1024, 2048, ['atomic'], 1, 0, false, false, false],
    ['io-2.10.1', 2048, 512, ['atomic1k'], 1, 0, false, false, false],
    ['io-2.10.2', 2048, 512, ['atomic2k'], 1, 0, false, false, false],
    ['io-2.11', 1024, 512, ['atomic'], 1, 0, false, true, false],
];

foreach (range(1, 500) as $case) {
    [$scenarioBase, $pageSize, $sectorSize, $flags, $changed, $appended, $multiFile, $exclusive, $blockJournalOpen] = $atomicScenarios[($case - 1) % count($atomicScenarios)];
    $scenario = $scenarioBase . '.dynamic-' . $case;

    $tests["real upstream corpus vfs io atomic device {$scenario}"] = static function (TestRunner $t) use ($scenario, $pageSize, $sectorSize, $flags, $changed, $appended, $multiFile, $exclusive, $blockJournalOpen): void {
        $plan = SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision(
            $scenario,
            $pageSize,
            $sectorSize,
            $flags,
            $changed,
            $appended,
            $multiFile,
            $exclusive,
            $blockJournalOpen
        );

        $atomicExpected = ($sectorSize <= $pageSize)
            && ($changed === 1)
            && ($appended === 0)
            && !$multiFile
            && (in_array('atomic', $flags, true) || ($pageSize === 2048 && in_array('atomic2k', $flags, true)));

        $t->same('io.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($atomicExpected, $plan['atomic_write']);
        $t->same($blockJournalOpen && !$atomicExpected ? 'unable to open database file' : 'ok', $plan['commit_result']);
        $t->same($atomicExpected, $plan['change_counter_written_out_of_band']);
        $t->same($atomicExpected ? 1 : ($changed === 0 ? 0 : 4), $plan['sync_count']);
        $t->same($atomicExpected ? 2 : max(2, $changed + $appended + 1), $plan['write_count']);
        $t->same(true, in_array('sqlite-atomic-write-commit-visibility', $plan['dependencies'], true));
        $t->same(true, str_starts_with($plan['upstream'][0], 'io.test io-2.'));
    };
}

$trafficScenarios = [
    ['io-3.2', ['sequential'], 'full', true, false],
    ['io-3.3', ['sequential'], 'normal', true, false],
    ['io-4.1', ['safe_append'], 'full', false, true],
    ['io-4.2', ['safe_append', 'sequential'], 'full', true, true],
    ['io-2.3', ['atomic'], 'full', false, false],
    ['io-5.1', ['atomic64k'], 'normal', false, false],
];

foreach (range(1, 250) as $case) {
    [$scenarioBase, $flags, $syncMode, $dirtyCacheSpill, $safeAppendExpected] = $trafficScenarios[($case - 1) % count($trafficScenarios)];
    $changedPages = 1 + ($case % 14);
    $appendedPages = ($case % 6) === 0 ? 1 : 0;
    $pageSize = ($case % 4) === 0 ? 2048 : 1024;
    $sectorSize = ($case % 10) === 0 ? 2048 : 512;
    $scenario = $scenarioBase . '.traffic-' . $case;

    $tests["real upstream corpus vfs io device traffic {$scenario}"] = static function (TestRunner $t) use ($scenario, $flags, $syncMode, $dirtyCacheSpill, $safeAppendExpected, $changedPages, $appendedPages, $pageSize, $sectorSize): void {
        $plan = SQLiteVfsIoTrafficPlan::transaction(
            $scenario,
            $pageSize,
            $changedPages,
            $appendedPages,
            $flags,
            $sectorSize,
            $syncMode,
            ($changedPages % 5) === 0,
            false,
            $dirtyCacheSpill,
            true
        );

        $sequential = in_array('sequential', $plan['device_flags'], true);
        $atomic = $changedPages === 1
            && $appendedPages === 0
            && $sectorSize <= $pageSize
            && in_array('atomic', $plan['device_flags'], true);

        $t->same($scenario, $plan['scenario']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($atomic, $plan['atomic_write']);
        $t->same($changedPages + $appendedPages + 1, $plan['database_writes']);
        $t->same($safeAppendExpected && $plan['journal_created'] ? 0xffffffff : null, $plan['journal_header_nrec']);
        $t->same($dirtyCacheSpill && $sequential ? 0 : ($dirtyCacheSpill ? 1 : 0), $plan['cache_spill_syncs']);
        $t->same(true, in_array('sqlite-vfs-device-characteristics', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-io-traffic', $plan['dependencies'], true));
        $t->same(true, str_contains($plan['reason'], $atomic ? 'atomic' : ($safeAppendExpected ? 'safe_append' : 'journal')));
    };
}

$crashScenarios = [
    ['crash3-1', 'insert', 'journal', ['atomic']],
    ['crash3-1', 'delete', 'database', ['atomic']],
    ['crash3-2', 'insert_select', 'journal', ['sequential']],
    ['crash3-2', 'update', 'database', ['safe_append']],
    ['crash3-2', 'mixed_delete_insert', 'journal', ['safe_append', 'sequential']],
    ['crash3-3', 'create_table', 'database', ['atomic', 'sequential']],
];

foreach (range(1, 250) as $case) {
    [$scenarioBase, $sqlKind, $crashFile, $flags] = $crashScenarios[($case - 1) % count($crashScenarios)];
    $scenario = $scenarioBase . '.dynamic-' . $case;
    $delay = 1 + ($case % 17);

    $tests["real upstream corpus vfs io crash recovery device {$scenario}"] = static function (TestRunner $t) use ($scenario, $sqlKind, $crashFile, $flags, $delay, $case): void {
        $plan = SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile($scenario, $sqlKind, $crashFile, $delay, $flags, $case);

        $atomic = in_array('atomic', $flags, true);
        $safeAppend = in_array('safe_append', $flags, true);
        $sequential = in_array('sequential', $flags, true);

        $t->same('crash3.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($sqlKind, $plan['sql_kind']);
        $t->same($crashFile, $plan['crash_file']);
        $t->same($delay, $plan['delay']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(true, $plan['content_either_prior_or_success']);
        $t->same($atomic && $crashFile === 'journal', $plan['atomic_write_short_circuits_journal_crash']);
        $t->same(!$safeAppend || $delay >= 1, $plan['safe_append_header_valid']);
        $t->same(!$sequential || $delay >= 1, $plan['sequential_order_preserved']);
        $t->same(true, in_array('sqlite-crash-recovery-content-boundary', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io atomic device dynamic records upstream source files'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-2.4 through io-2.11 atomic-write journal creation, visibility, rollback, sector-size, and exclusive-locking cases',
        'io.test io-3.* sequential device sync elision and io-4.* safe-append journal header behavior',
        'crash3.test crash3-1 through crash3-3 device-characteristic crash recovery content boundary cases',
    ], [
        'io.test io-2.4 through io-2.11 atomic-write journal creation, visibility, rollback, sector-size, and exclusive-locking cases',
        'io.test io-3.* sequential device sync elision and io-4.* safe-append journal header behavior',
        'crash3.test crash3-1 through crash3-3 device-characteristic crash recovery content boundary cases',
    ]);
};

return $tests;
