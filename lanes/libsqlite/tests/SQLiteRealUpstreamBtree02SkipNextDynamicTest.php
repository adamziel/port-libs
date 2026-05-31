<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

foreach (SQLiteBTreeIndexDynamicCorpusPlan::btree02SkipNextCursorMutationCases(1200) as $case) {
    $tests['real upstream btree02 skipnext cursor mutation case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('btree02.test sections btree02-100 and btree02-110', $case['source']);
        $t->contains('btree02-110.', $case['upstream_section']);
        $t->same('PRIMARY KEY(a,ax) WITHOUT ROWID', $case['primary_key']);
        $t->same('t1a ON t1(a)', $case['secondary_index']);
        $t->same(10, $case['initial_rows']);
        $t->same(4, $case['cross_join_rows']);
        $t->same(40, $case['cursor_rows']);
        $t->same(true, $case['skipnext_preserved']);
        $t->same(10, $case['final_rows']);
        $t->same($case['mutation_ordinal'], $case['commits_inside_scan']);
        $t->same(($case['mutation_ordinal'] % 2) === 1 ? 'insert' : 'delete', $case['mutation_kind']);
        $t->same(($case['mutation_ordinal'] % 2) === 0, $case['deleted']);
        $t->same(sprintf('%02x', $case['source_value'] + 160), $case['source_key']);
        $t->same(($case['mutation_ordinal'] - 1) % 4 + 1, $case['source_counter']);

        if ($case['mutation_kind'] === 'insert') {
            $t->same('(' . $case['source_key'] . ')', $case['inserted_key']);
            $t->same($case['source_value'] + 1000, $case['inserted_value']);
            $t->true($case['transient_rows_after_mutation'] > 10);
        } else {
            $t->same(null, $case['inserted_key']);
            $t->same(null, $case['inserted_value']);
            $t->true($case['transient_rows_after_mutation'] >= 10);
        }
        $t->true($case['transient_rows_after_mutation'] <= 20);
    };
}

$tests['real upstream btree02 skipnext dynamic corpus preserves template cycle'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::btree02SkipNextCursorMutationCases(1200);
    $t->same(1200, count($cases));
    $t->same('btree02-110.1', $cases[0]['upstream_section']);
    $t->same('btree02-110.40', $cases[39]['upstream_section']);
    $t->same('btree02-110.1', $cases[40]['upstream_section']);
    $t->same(30, intdiv(count($cases), 40));
};

$tests['real upstream btree02 skipnext dynamic corpus rejects empty case count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::btree02SkipNextCursorMutationCases(0));
};

return $tests;
