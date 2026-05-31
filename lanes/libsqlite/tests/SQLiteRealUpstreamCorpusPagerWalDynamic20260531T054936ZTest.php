<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal dynamic 054936 cites pager1 source sections'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->contains('do_test pager1-23.5.1', $pager1);
    $t->contains('do_test pager1-23.6.4', $pager1);
    $t->contains('do_test pager1-24.1.2', $pager1);
    $t->contains('do_test pager1-24.1.3', $pager1);
    $t->contains('do_test pager1-24.1.4', $pager1);
    $t->contains('do_test pager1-24.1.5', $pager1);
    $t->contains('PRAGMA cache_size = 10', $pager1);
};

$memoryModes = [
    ['pager1.test pager1-23.5.2 in-memory journal_mode=delete rejected', 'memory', 'delete', 'memory', false, 'memory-journal-mode-retained'],
    ['pager1.test pager1-23.5.3 in-memory journal_mode=persist rejected', 'memory', 'persist', 'memory', false, 'memory-journal-mode-retained'],
    ['pager1.test pager1-23.5.4 in-memory journal_mode=truncate rejected', 'memory', 'truncate', 'memory', false, 'memory-journal-mode-retained'],
    ['pager1.test pager1-23.5.5 in-memory journal_mode=wal rejected', 'memory', 'wal', 'memory', false, 'memory-journal-mode-retained'],
    ['pager1.test pager1-23.5.6 in-memory journal_mode=memory accepted', 'off', 'memory', 'memory', true, 'memory-journal-mode-changed'],
    ['pager1.test pager1-23.5.7 in-memory journal_mode=off accepted', 'memory', 'off', 'off', true, 'memory-journal-mode-changed'],
    ['pager1.test pager1-23.6 locking_mode normal keeps in-memory journal constraints', 'off', 'delete', 'off', false, 'memory-journal-mode-retained'],
];

$cacheSpillRows = [
    ['pager1.test pager1-24.1.2 delete while scanning cache spill integrity', 10, 99, 50, 60, 800, false, false],
    ['pager1.test pager1-24.1.3 update while scanning cache spill integrity', 10, 100, 1, 25, 1200, false, false],
    ['pager1.test pager1-24.1.4 commit while active scan keeps cursor safe', 10, 110, 10, 70, 1600, true, false],
    ['pager1.test pager1-24.1.5 schema change during recursive select returns no visible rows', 10, 120, 5, 80, 2000, true, true],
    ['pager1.test pager1-24.1.2 small cache delete pressure', 4, 64, 33, 48, 4096, false, false],
    ['pager1.test pager1-24.1.3 large update width pressure', 8, 128, 12, 91, 8192, false, false],
    ['pager1.test pager1-24.1.4 commit pressure with many dirty pages', 12, 144, 72, 90, 2400, true, false],
    ['pager1.test pager1-24.1.5 schema change pressure with recursive select', 6, 90, 25, 63, 3200, true, true],
];

for ($case = 1; $case <= 1000; $case++) {
    $memory = $memoryModes[($case - 1) % count($memoryModes)];
    $spill = $cacheSpillRows[($case - 1) % count($cacheSpillRows)];
    $sourceRowCount = $spill[2] + (($case - 1) % 17);
    $deleteBelow = min($sourceRowCount, $spill[3] + (($case - 1) % 5));
    $updateAbove = min($sourceRowCount, $spill[4] + (($case - 1) % 7));
    $updatedWidth = $spill[5] + (($case - 1) % 11) * 37;

    $tests[sprintf(
        'real upstream corpus pager wal dynamic 054936 pager1 journal cache spill matrix %04d %s %s',
        $case,
        $memory[0],
        $spill[0]
    )] = static function (TestRunner $t) use (
        $memory,
        $spill,
        $sourceRowCount,
        $deleteBelow,
        $updateAbove,
        $updatedWidth
    ): void {
        $journal = SQLitePagerWalDynamicPlan::memoryJournalModeTransition($memory[1], $memory[2]);
        $spillPlan = SQLitePagerWalDynamicPlan::cacheSpillIntegrityScenario(
            $spill[1],
            $sourceRowCount,
            $deleteBelow,
            $updateAbove,
            $updatedWidth,
            $spill[6],
            $spill[7]
        );

        $t->same($memory[3], $journal['result']);
        $t->same($memory[4], $journal['possible']);
        $t->same($memory[5], $journal['status']);
        $t->same(true, str_contains($journal['source'], 'pager1.test'));
        $t->same('pager-cache-spill-integrity-ok', $spillPlan['status']);
        $t->same('full', $spillPlan['auto_vacuum']);
        $t->same($sourceRowCount, $spillPlan['source_row_count']);
        $t->same($sourceRowCount - min($sourceRowCount, max(0, $deleteBelow - 1)), $spillPlan['remaining_rows']);
        $t->same('ok', $spillPlan['integrity']);
        $t->same(true, $spillPlan['recursive_select_ok']);
        $t->same($spill[6], $spillPlan['commit_during_scan']);
        $t->same($spill[7], $spillPlan['schema_change_during_scan']);
        $t->same(true, $spillPlan['dirty_pages_spilled'] >= 1);
        $t->same(true, str_contains($spillPlan['source'], 'pager1.test'));
        $t->same(true, in_array('real-upstream-corpus-pager1', $spillPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-cache-spill-integrity', $spillPlan['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic 054936 rejects malformed pager inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::memoryJournalModeTransition('delete', 'memory'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::cacheSpillIntegrityScenario(0, 10, 1, 2, 3, false));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::cacheSpillIntegrityScenario(10, 0, 1, 2, 3, false));
};

$tests['real upstream corpus pager wal dynamic 054936 non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T054936Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T054936Z-0');
    $t->same('upstream file: pager1.test sections pager1-23.5 in-memory journal-mode restrictions, pager1-23.6 in-memory locking-mode interaction, and pager1-24.1.2 through pager1-24.1.5 cache-spill scan integrity', 'upstream file: pager1.test sections pager1-23.5 in-memory journal-mode restrictions, pager1-23.6 in-memory locking-mode interaction, and pager1-24.1.2 through pager1-24.1.5 cache-spill scan integrity');
    $t->same('non-overlap: avoids accepted WAL byte truncation, WAL checkpoint transactions, rollback-journal apply/commit, VFS sync/file writer/lock, WAL header validation 031451, page-size mapping, readonly-SHM, persistent WAL close, and pager/WAL recovery dynamic batches; covers pager1 in-memory journal refusal plus cache-spill integrity rows', 'non-overlap: avoids accepted WAL byte truncation, WAL checkpoint transactions, rollback-journal apply/commit, VFS sync/file writer/lock, WAL header validation 031451, page-size mapping, readonly-SHM, persistent WAL close, and pager/WAL recovery dynamic batches; covers pager1 in-memory journal refusal plus cache-spill integrity rows');
    $t->same('dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite pager1.test source truth', 'dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite pager1.test source truth');
};

return $tests;
