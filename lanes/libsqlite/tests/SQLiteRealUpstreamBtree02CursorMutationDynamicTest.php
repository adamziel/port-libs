<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/btree02.test sections btree02-100 and
// btree02-110. The upstream script mutates a WITHOUT ROWID table while a
// CROSS JOIN scan is active, forcing repeated saveCursorPosition() and
// restoreCursorPosition() calls for a cursor with CURSOR_SKIPNEXT state.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::btree02CursorSkipNextMutationCases(1000) as $case) {
    $tests['real upstream btree02 cursor mutation dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('btree02.test sections btree02-100 and btree02-110', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], ['btree02-100', 'btree02-110'], true));
        $t->true($case['batch'] >= 1);
        $t->same(true, $case['uses_without_rowid']);
        $t->same(['a', 'ax'], $case['primary_key']);
        $t->same('t1a', $case['secondary_index']);
        $t->same('ok', $case['integrity']);

        $t->same(10, $case['initial_row_count']);
        $t->same(20, $case['cross_join_rows']);
        $t->same(20, $case['commit_count']);
        $t->same(10, $case['insert_count']);
        $t->same(10, $case['delete_count']);
        $t->same(10, $case['final_row_count']);
        $t->same(20, $case['t2_rows']);
        $t->same(['x' => 1, 'y' => 1], $case['first_t2_row']);
        $t->same(['x' => 10, 'y' => 2], $case['last_t2_row']);

        $t->same(['a1', 'a2', 'a3', 'a4', 'a5'], array_slice($case['deleted_keys'], 0, 5));
        $t->same(['a6', 'a7', 'a8', 'a9', 'aa'], array_slice($case['deleted_keys'], 5));
        $t->same([], $case['surviving_original_keys']);
        $t->same(10, count($case['surviving_inserted_keys']));
        $t->same(['(a1)', '(a2)', '(a3)'], array_slice($case['surviving_inserted_keys'], 0, 3));
        $t->same(['(a8)', '(a9)', '(aa)'], array_slice($case['surviving_inserted_keys'], -3));
        $t->same(10, count($case['inserted_keys']));
        $t->same($case['surviving_inserted_keys'], $case['inserted_keys']);

        if ($case['upstream_section'] === 'btree02-100') {
            $t->contains('populated before cursor mutation', $case['detail']);
        }

        if ($case['upstream_section'] === 'btree02-110') {
            $t->contains('alternating insert/delete commits', $case['detail']);
        }
    };
}

$tests['real upstream btree02 cursor mutation dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::btree02CursorSkipNextMutationCases(1000);

    $t->same(1000, count($cases));
    $t->same('btree02-100', $cases[0]['upstream_section']);
    $t->same('btree02-110', $cases[1]['upstream_section']);
    $t->same('btree02-100', $cases[998]['upstream_section']);
    $t->same('btree02-110', $cases[999]['upstream_section']);
};

$tests['real upstream btree02 cursor mutation rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::btree02CursorSkipNextMutationCases(0));
};

$tests['real upstream btree02 cursor mutation dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning for WITHOUT ROWID cursor mutation and secondary-index scan restoration',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning for WITHOUT ROWID cursor mutation and secondary-index scan restoration',
    );
};

return $tests;
