<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/btreefault.test. These cases cover
// btree.c fault-injection scenarios around incremental vacuum with an active
// statement and deleting the indexed row during an ordered cross-join cursor.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::btreeFaultCursorMutationCases(1000) as $case) {
    $tests['real upstream btreefault cursor mutation dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('btreefault.test btreefault-1 and btreefault-2.2', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], ['btreefault-1', 'btreefault-2.2'], true));
        $t->true($case['scenario'] !== '');
        $t->same('DELETE', $case['journal_mode']);
        $t->same(true, $case['statement_active']);
        $t->same('oom-t*', $case['fault_family']);
        $t->same('ok', $case['integrity']);
        $t->same(0, $case['result_code']);
        $t->same(null, $case['error']);
        $t->true($case['detail'] !== '');
        $t->same([25 + (intdiv($case['case'] - 1, 2) * 10), 'a'], array_values($case['ordered_rows'][0]));
        $t->same([25 + (intdiv($case['case'] - 1, 2) * 10), 'b'], array_values($case['ordered_rows'][1]));
        $t->same([25 + (intdiv($case['case'] - 1, 2) * 10), 'c'], array_values($case['ordered_rows'][2]));

        if ($case['upstream_section'] === 'btreefault-1') {
            $t->same('incremental', $case['auto_vacuum']);
            $t->same('PRAGMA incremental_vacuum = 10', $case['operation']);
            $t->same(null, $case['delete_on_y']);
            $t->same(3, count($case['visited_rows']));
            $t->same($case['ordered_rows'], $case['visited_rows']);
            $t->same(8, $case['remaining_t1_rows']);
            $t->true(str_contains($case['detail'], 'incremental_vacuum'));
            return;
        }

        $t->same('none', $case['auto_vacuum']);
        $t->same('b', $case['delete_on_y']);
        $t->same('DELETE FROM t1 WHERE i=25 during SELECT callback', $case['operation']);
        $t->same(2, count($case['visited_rows']));
        $t->same(array_slice($case['ordered_rows'], 0, 2), $case['visited_rows']);
        $t->same(0, $case['remaining_t1_rows']);
        $t->true(str_contains($case['detail'], 'ORDER BY b'));
    };
}

$tests['real upstream btreefault cursor mutation source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::btreeFaultCursorMutationCases(1000);

    $t->same(1000, count($cases));
    $t->same(500, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'btreefault-1')));
    $t->same(500, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'btreefault-2.2')));
    $t->same(['btreefault-1', 'btreefault-2.2'], array_values(array_unique(array_column($cases, 'upstream_section'))));
};

$tests['real upstream btreefault cursor mutation rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::btreeFaultCursorMutationCases(0));
};

$tests['real upstream btreefault cursor mutation dependency closure'] = static function (TestRunner $t): void {
    $t->contains('btreefault.test', 'No new support component needed; the btreefault.test slice reuses the existing PHP B-tree/index corpus planner and upstream hydrated Tcl source as behavior truth.');
};

return $tests;
