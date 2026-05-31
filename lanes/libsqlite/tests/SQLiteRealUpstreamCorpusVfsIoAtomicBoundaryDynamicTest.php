<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$scenarios = [
    'io-2.4 single-page atomic visibility' => [
        'scenario' => 'io-2.4.1-2.4.3',
        'changed_pages' => 1,
        'appended_pages' => 0,
        'multi_file' => false,
        'blocked' => false,
        'journal_before' => false,
        'journal_commit' => false,
        'rows_after' => true,
        'rollback_restores' => false,
        'upstream' => 'io.test io-2.4 atomic write journal absence and second-connection visibility',
    ],
    'io-2.6 append-page commit blocked rolls back' => [
        'scenario' => 'io-2.6.1-2.6.4',
        'changed_pages' => 1,
        'appended_pages' => 1,
        'multi_file' => false,
        'blocked' => true,
        'journal_before' => true,
        'journal_commit' => false,
        'rows_after' => false,
        'rollback_restores' => true,
        'upstream' => 'io.test io-2.6 append-page commit opens deferred journal and rolls back on open failure',
    ],
    'io-2.7 attached commit blocked aborts transaction' => [
        'scenario' => 'io-2.7.1-2.7.6',
        'changed_pages' => 1,
        'appended_pages' => 0,
        'multi_file' => true,
        'blocked' => true,
        'journal_before' => false,
        'journal_commit' => true,
        'rows_after' => false,
        'rollback_restores' => true,
        'upstream' => 'io.test io-2.7 multi-file commit opens journals at commit boundary',
    ],
    'io-2.8 rollback before deferred journal restores rows' => [
        'scenario' => 'io-2.8.1-2.8.3',
        'changed_pages' => 1,
        'appended_pages' => 0,
        'multi_file' => false,
        'blocked' => false,
        'journal_before' => false,
        'journal_commit' => false,
        'rows_after' => true,
        'rollback_restores' => true,
        'upstream' => 'io.test io-2.8 rollback before deferred journal creation restores rows',
    ],
    'io-2.11 exclusive locking no journal' => [
        'scenario' => 'io-2.11.1-2.11.2',
        'changed_pages' => 1,
        'appended_pages' => 0,
        'multi_file' => false,
        'blocked' => false,
        'journal_before' => false,
        'journal_commit' => false,
        'rows_after' => true,
        'rollback_restores' => false,
        'exclusive' => true,
        'upstream' => 'io.test io-2.11 exclusive locking keeps atomic write journal-free',
    ],
];

$flagMatrix = [
    'atomic' => ['atomic'],
    'atomic-safe-append' => ['atomic', 'safe_append'],
    'atomic-sequential' => ['atomic', 'sequential'],
    'atomic-safe-append-sequential' => ['atomic', 'safe_append', 'sequential'],
    'atomic2k' => ['atomic2k'],
    'atomic4k' => ['atomic4k'],
];
$pageSectorMatrix = [
    [1024, 1024],
    [1024, 2048],
    [2048, 1024],
    [2048, 2048],
    [4096, 4096],
    [8192, 4096],
];
$changedPageMatrix = [1, 2, 3];
$case = 0;

foreach ($scenarios as $label => $scenario) {
    foreach ($flagMatrix as $flagLabel => $flags) {
        foreach ($pageSectorMatrix as [$pageSize, $sectorSize]) {
            foreach ($changedPageMatrix as $changedPages) {
                $case++;
                $tests[sprintf('real upstream corpus vfs io atomic boundary dynamic %04d %s %s page %d sector %d changed %d', $case, $label, $flagLabel, $pageSize, $sectorSize, $changedPages)] =
                    static function (TestRunner $t) use ($scenario, $flags, $pageSize, $sectorSize, $changedPages): void {
                        $changed = max($changedPages, (int) $scenario['changed_pages']);
                        $plan = SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision(
                            (string) $scenario['scenario'],
                            $pageSize,
                            $sectorSize,
                            $flags,
                            $changed,
                            (int) $scenario['appended_pages'],
                            (bool) $scenario['multi_file'],
                            (bool) ($scenario['exclusive'] ?? false),
                            (bool) $scenario['blocked']
                        );
                        $normalizedFlags = array_map('strtolower', $flags);
                        $atomicFlag = in_array('atomic', $normalizedFlags, true);
                        $specificAtomic = match ($pageSize) {
                            512 => 'atomic512',
                            1024 => 'atomic1k',
                            2048 => 'atomic2k',
                            4096 => 'atomic4k',
                            8192 => 'atomic8k',
                            default => 'atomic' . ($pageSize / 1024) . 'k',
                        };
                        $expectedAtomic = $changed === 1
                            && (int) $scenario['appended_pages'] === 0
                            && !(bool) $scenario['multi_file']
                            && $sectorSize <= $pageSize
                            && ($atomicFlag || in_array($specificAtomic, $normalizedFlags, true));
                        $expectedJournalBefore = !$expectedAtomic
                            && $changed > 0
                            && ((int) $scenario['appended_pages'] > 0 || $changed > 1 || $sectorSize > $pageSize);
                        $expectedJournalCommit = !$expectedAtomic && $changed > 0 && !$expectedJournalBefore;
                        $expectedCommit = (bool) $scenario['blocked'] && ($expectedJournalBefore || $expectedJournalCommit)
                            ? 'unable to open database file'
                            : 'ok';

                        $t->same('io.test', $plan['script']);
                        $t->same((string) $scenario['scenario'], $plan['scenario']);
                        $t->same($pageSize, $plan['page_size']);
                        $t->same($sectorSize, $plan['sector_size']);
                        $t->same($changed, $plan['changed_pages']);
                        $t->same((int) $scenario['appended_pages'], $plan['appended_pages']);
                        $t->same((bool) $scenario['multi_file'], $plan['multi_file_commit']);
                        $t->same((bool) ($scenario['exclusive'] ?? false), $plan['exclusive_locking']);
                        $t->same($expectedAtomic, $plan['atomic_write']);
                        $t->same($expectedJournalBefore, $plan['journal_exists_before_commit']);
                        $t->same($expectedJournalCommit, $plan['journal_created_at_commit']);
                        $t->same($expectedCommit, $plan['commit_result']);
                        $t->same(false, $plan['rows_visible_before_commit']);
                        $t->same($expectedCommit === 'ok', $plan['rows_visible_after_commit']);
                        $t->same((bool) $scenario['rollback_restores'] || $expectedCommit !== 'ok', $plan['rollback_restores_prior_rows']);
                        $t->same($expectedAtomic, $plan['change_counter_written_out_of_band']);
                        $t->same(true, in_array('sqlite-upstream-io-test', $plan['dependencies'], true));
                        $t->same(true, in_array('sqlite-atomic-write-commit-visibility', $plan['dependencies'], true));
                        $t->same(true, in_array((string) $scenario['upstream'], $plan['upstream'], true));
                    };
            }
        }
    }
}

$guardCases = [
    'rejects empty atomic scenario' => static fn (): array => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('', 1024, 1024, ['atomic']),
    'rejects subminimum page size' => static fn (): array => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('io-2.7.1-2.7.6', 256, 1024, ['atomic']),
    'rejects non-power page size' => static fn (): array => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('io-2.7.1-2.7.6', 1536, 1024, ['atomic']),
    'rejects non-power sector size' => static fn (): array => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('io-2.7.1-2.7.6', 1024, 1536, ['atomic']),
    'rejects negative changed pages' => static fn (): array => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('io-2.7.1-2.7.6', 1024, 1024, ['atomic'], -1),
    'rejects negative appended pages' => static fn (): array => SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision('io-2.7.1-2.7.6', 1024, 1024, ['atomic'], 1, -1),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus vfs io atomic boundary dynamic ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

$tests['real upstream corpus vfs io atomic boundary dynamic owns expected source rows'] = static function (TestRunner $t) use ($case, $scenarios): void {
    $t->same(540, $case);
    $t->same(
        [
            'io.test io-2.4 atomic write journal absence and second-connection visibility',
            'io.test io-2.6 append-page commit opens deferred journal and rolls back on open failure',
            'io.test io-2.7 multi-file commit opens journals at commit boundary',
            'io.test io-2.8 rollback before deferred journal creation restores rows',
            'io.test io-2.11 exclusive locking keeps atomic write journal-free',
        ],
        array_values(array_unique(array_column($scenarios, 'upstream')))
    );
};

return $tests;
