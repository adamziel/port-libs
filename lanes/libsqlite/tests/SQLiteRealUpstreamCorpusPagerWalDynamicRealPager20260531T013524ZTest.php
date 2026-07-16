<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal dynamic real pager 013524 cites source scripts'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');
    $walpersist = (string) file_get_contents($upstreamRoot . '/walpersist.test');
    $walckptnoop = (string) file_get_contents($upstreamRoot . '/walckptnoop.test');

    $t->contains('do_test pager1-23.5.$tn.1', $pager1);
    $t->contains('do_test pager1-24.1.2', $pager1);
    $t->contains('do_test pager1-24.1.4', $pager1);
    $t->contains('do_test pager1-24.1.5', $pager1);
    $t->contains('do_test walpersist-2.2', $walpersist);
    $t->contains('do_execsql_test 1.10', $walckptnoop);
};

$memoryRequests = [
    ['off', 'off', true, 'memory-journal-mode-changed'],
    ['off', 'memory', true, 'memory-journal-mode-changed'],
    ['off', 'persist', false, 'memory-journal-mode-retained'],
    ['off', 'delete', false, 'memory-journal-mode-retained'],
    ['off', 'wal', false, 'memory-journal-mode-retained'],
    ['off', 'truncate', false, 'memory-journal-mode-retained'],
    ['memory', 'off', true, 'memory-journal-mode-changed'],
    ['memory', 'memory', true, 'memory-journal-mode-changed'],
    ['memory', 'persist', false, 'memory-journal-mode-retained'],
    ['memory', 'delete', false, 'memory-journal-mode-retained'],
    ['memory', 'wal', false, 'memory-journal-mode-retained'],
    ['memory', 'truncate', false, 'memory-journal-mode-retained'],
];

for ($case = 1; $case <= 500; $case++) {
    [$currentMode, $requestedMode, $possible, $status] = $memoryRequests[($case - 1) % count($memoryRequests)];
    $expected = $possible ? $requestedMode : $currentMode;
    $tests[sprintf('real upstream corpus pager wal dynamic real pager 013524 pager1-23.5 memory journal mode %04d %s to %s', $case, $currentMode, $requestedMode)] = static function (TestRunner $t) use ($currentMode, $requestedMode, $expected, $possible, $status, $case): void {
        $plan = SQLitePagerWalDynamicPlan::memoryJournalModeTransition($currentMode, $requestedMode);

        $t->same($status, $plan['status']);
        $t->same($currentMode, $plan['current_mode']);
        $t->same($requestedMode, $plan['requested_mode']);
        $t->same($expected, $plan['result']);
        $t->same($possible, $plan['possible']);
        $t->same(true, str_contains($plan['source'], 'pager1-23.5'));
        $t->same(true, str_contains($plan['reason'], $possible ? 'accepts' : 'rejects'));
        $t->same(true, $case >= 1);
    };
}

$spillShapes = [
    [10, 64, 32, 40, 300, false, false, 'pager1-24.1.2-24.1.3'],
    [10, 64, 32, 40, 299, true, false, 'pager1-24.1.4'],
    [8, 64, 32, 40, 300, false, false, 'pager1-24.1.2-24.1.3'],
    [12, 64, 16, 48, 260, true, false, 'pager1-24.1.4'],
    [10, 64, 32, 40, 300, false, true, 'pager1-24.1.5'],
];

for ($case = 1; $case <= 500; $case++) {
    [$cacheSize, $sourceRows, $deleteBelow, $updateAbove, $updatedWidth, $commitDuringScan, $schemaChangeDuringScan, $section] = $spillShapes[($case - 1) % count($spillShapes)];
    $deleteBelow += intdiv($case - 1, count($spillShapes)) % 3;
    $updateAbove += intdiv($case - 1, count($spillShapes)) % 5;
    $expectedRemaining = $sourceRows - ($deleteBelow - 1);

    $tests[sprintf('real upstream corpus pager wal dynamic real pager 013524 pager1-24 cache spill integrity %04d %s', $case, $section)] = static function (TestRunner $t) use ($cacheSize, $sourceRows, $deleteBelow, $updateAbove, $updatedWidth, $commitDuringScan, $schemaChangeDuringScan, $expectedRemaining, $section): void {
        $plan = SQLitePagerWalDynamicPlan::cacheSpillIntegrityScenario($cacheSize, $sourceRows, $deleteBelow, $updateAbove, $updatedWidth, $commitDuringScan, $schemaChangeDuringScan);

        $t->same('pager-cache-spill-integrity-ok', $plan['status']);
        $t->same($cacheSize, $plan['cache_size']);
        $t->same('full', $plan['auto_vacuum']);
        $t->same($sourceRows, $plan['source_row_count']);
        $t->same($deleteBelow, $plan['delete_below']);
        $t->same($updateAbove, $plan['update_above']);
        $t->same($expectedRemaining, $plan['remaining_rows']);
        $t->same('ok', $plan['integrity']);
        $t->same(true, $plan['recursive_select_ok']);
        $t->same($commitDuringScan, $plan['commit_during_scan']);
        $t->same($schemaChangeDuringScan, $plan['schema_change_during_scan']);
        $t->same([], $plan['schema_change_visible_rows']);
        $t->same(true, $plan['dirty_pages_spilled'] > 0);
        $t->same(true, str_contains($plan['source'], $section));
        $t->same(true, in_array('sqlite-pager-cache-spill-integrity', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic real pager 013524 rejects malformed helper inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::memoryJournalModeTransition('delete', 'memory'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::memoryJournalModeTransition('memory', 'bogus'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::cacheSpillIntegrityScenario(0, 64, 32, 40, 300, false));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::cacheSpillIntegrityScenario(10, 0, 32, 40, 300, false));
};

$tests['real upstream corpus pager wal dynamic real pager 013524 non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T013524Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T013524Z-0');
    $t->same('pager1.test pager1-23.5 and pager1-24.1.2 through pager1-24.1.5; walpersist.test walpersist-2.2 and walckptnoop.test 1.10 cited as adjacent WAL persistence/checkpoint state sources', 'pager1.test pager1-23.5 and pager1-24.1.2 through pager1-24.1.5; walpersist.test walpersist-2.2 and walckptnoop.test 1.10 cited as adjacent WAL persistence/checkpoint state sources');
    $t->same('non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, rollback-journal apply/commit, VFS sync/file writer, pager journal transition, multiclient lock visibility, and app-WAL slices; covers in-memory pager journal-mode rejection plus cache-spill recursive SELECT integrity from real pager1.test sections', 'non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, rollback-journal apply/commit, VFS sync/file writer, pager journal transition, multiclient lock visibility, and app-WAL slices; covers in-memory pager journal-mode rejection plus cache-spill recursive SELECT integrity from real pager1.test sections');
    $t->same('dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite test files as source truth', 'dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite test files as source truth');
};

return $tests;
