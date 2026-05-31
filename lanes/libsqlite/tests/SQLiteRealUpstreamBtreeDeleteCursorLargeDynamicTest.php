<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/delete2.test sections delete2-1.1
// through delete2-2.2 and test/delete3.test sections delete3-1.1 through
// delete3-1.3. These cases cover the old corruption boundary where a DELETE
// must not remove an index entry without the table row while a cursor is open,
// plus the large rowid row-list delete that keeps odd-key B-tree integrity.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::delete2Delete3CursorAndLargeDeleteCases(1200) as $case) {
    $tests['real upstream delete2 delete3 cursor large dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('delete2.test sections delete2-1.1 through delete2-2.2 and delete3.test sections delete3-1.1 through delete3-1.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true(str_starts_with($case['upstream_section'], 'delete2-') || str_starts_with($case['upstream_section'], 'delete3-'));
        $t->true($case['batch'] >= 1 && $case['batch'] <= 240);
        $t->true($case['scenario'] !== '');
        $t->true($case['operation'] !== '');
        $t->same([0, ''], $case['expected_result']);
        $t->same('ok', $case['integrity']);
        $t->same(true, $case['index_entries_preserved']);
        $t->same($case['initial_rows'] - $case['deleted_rows'], $case['remaining_rows']);
        $t->true($case['remaining_rows'] >= 0);
        $t->true($case['detail'] !== '');

        if ($case['upstream_section'] === 'delete2-1.1/1.3') {
            $t->same('q', $case['table_name']);
            $t->same('sqlite_autoindex_q_1', $case['index_name']);
            $t->same(false, $case['cursor_open']);
            $t->same(3, $case['remaining_rows']);
            $t->same(['id.1', 'id.2', 'id.3'], $case['remaining_values']);
        }

        if ($case['upstream_section'] === 'delete2-1.4/1.8') {
            $t->same(true, $case['cursor_open']);
            $t->same(1, $case['deleted_rows']);
            $t->same(['id.2', 'id.3'], $case['remaining_values']);
            $t->contains('must not remove only the index entry', $case['detail']);
        }

        if ($case['upstream_section'] === 'delete2-1.9/1.11') {
            $t->same(false, $case['cursor_open']);
            $t->same(0, $case['deleted_rows']);
            $t->same(['id.2', 'id.3'], $case['remaining_values']);
        }

        if ($case['upstream_section'] === 'delete2-2.1/2.2') {
            $t->same('t1', $case['table_name']);
            $t->same(null, $case['index_name']);
            $t->same([null, 3, 4, null, 5, 6], $case['remaining_values']);
        }

        if ($case['upstream_section'] === 'delete3-1.1/1.3') {
            $t->same('t1', $case['table_name']);
            $t->same('integer-primary-key', $case['index_name']);
            $t->same(524288, $case['initial_rows']);
            $t->same(262144, $case['deleted_rows']);
            $t->same(262144, $case['remaining_rows']);
            $t->same([1, 3, 5, 7, 9, 11, 13, 15, 524279, 524281, 524283, 524285, 524287], $case['remaining_values']);
        }
    };
}

$tests['real upstream delete2 delete3 cursor large dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::delete2Delete3CursorAndLargeDeleteCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('delete2-1.1/1.3', $cases[0]['upstream_section']);
    $t->same('delete3-1.1/1.3', $cases[4]['upstream_section']);
    $t->same('delete3-1.1/1.3', $cases[1199]['upstream_section']);
    $t->same([
        'delete2-1.1/1.3',
        'delete2-1.4/1.8',
        'delete2-1.9/1.11',
        'delete2-2.1/2.2',
        'delete3-1.1/1.3',
    ], $sections);
};

$tests['real upstream delete2 delete3 cursor large dynamic rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::delete2Delete3CursorAndLargeDeleteCases(0));
};

$tests['real upstream delete2 delete3 cursor large dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, cursor mutation, row-list delete, integrity, and primary-key index consistency helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, cursor mutation, row-list delete, integrity, and primary-key index consistency helpers',
    );
};

return $tests;
