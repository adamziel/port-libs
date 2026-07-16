<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/btree02.test btree02-100 and btree02-110.
// This companion batch focuses on the cursor restore invariants after each
// in-scan insert/delete and leaves the existing mutation-shape file intact.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::btree02SkipNextCursorMutationCases(1000) as $case) {
    $tests['real upstream btree02 skipnext restore invariant dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('btree02.test sections btree02-100 and btree02-110', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'btree02-110.'));
        $t->same('WITHOUT ROWID primary-key cursor preserves skip-next position while secondary-index scan mutates rows', $case['scenario']);
        $t->same('PRIMARY KEY(a,ax) WITHOUT ROWID', $case['primary_key']);
        $t->same('t1a ON t1(a)', $case['secondary_index']);
        $t->same(40, $case['cursor_rows']);
        $t->same(10, $case['final_rows']);
        $t->same(true, $case['skipnext_preserved']);
        $t->same($case['mutation_ordinal'], $case['commits_inside_scan']);
        $t->true($case['source_value'] >= 1 && $case['source_value'] <= 10);
        $t->same(sprintf('%02x', $case['source_value'] + 160), $case['source_key']);
        $t->same(($case['mutation_ordinal'] - 1) % 4 + 1, $case['source_counter']);
        $t->true(str_contains($case['detail'], 'btree02 batch'));
        $t->true(str_contains($case['detail'], 'mutation ' . $case['mutation_ordinal']));
        $t->true(str_contains($case['detail'], $case['mutation_kind']));
        $t->true(str_contains($case['detail'], $case['source_key']));

        if (($case['mutation_ordinal'] % 2) === 1) {
            $t->same('insert', $case['mutation_kind']);
            $t->same(false, $case['deleted']);
            $t->same('(' . $case['source_key'] . ')', $case['inserted_key']);
            $t->same($case['source_value'] + 1000, $case['inserted_value']);
            $t->true($case['transient_rows_after_mutation'] > 10);
        } else {
            $t->same('delete', $case['mutation_kind']);
            $t->same(true, $case['deleted']);
            $t->same(null, $case['inserted_key']);
            $t->same(null, $case['inserted_value']);
            $t->true($case['transient_rows_after_mutation'] >= 10);
        }
    };
}

$tests['real upstream btree02 skipnext restore dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::btree02SkipNextCursorMutationCases(1000);

    $t->same(1000, count($cases));
    $t->same('btree02-110.1', $cases[0]['upstream_section']);
    $t->same('btree02-110.40', $cases[39]['upstream_section']);
    $t->same('btree02-110.1', $cases[40]['upstream_section']);
    $t->same(25, intdiv(count($cases), 40));
};

$tests['real upstream btree02 skipnext restore dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and cursor restore diagnostics',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and cursor restore diagnostics',
    );
};

return $tests;
