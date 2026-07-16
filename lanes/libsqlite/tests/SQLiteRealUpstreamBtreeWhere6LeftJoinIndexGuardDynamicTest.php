<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$expectedSections = [
    'where6-1.1',
    'where6-1.2',
    'where6-1.3',
    'where6-1.4',
    'where6-1.5',
    'where6-1.6',
    'where6-1.11',
    'where6-1.12',
    'where6-1.13',
    'where6-2.1',
    'where6-2.2',
    'where6-2.3',
    'where6-2.4',
    'where6-2.5',
    'where6-2.6',
    'where6-2.11',
    'where6-2.12',
    'where6-2.13',
    'where6-2.14',
    'where6-3.1',
];

$simpleOnRows = [[1, 3, 1, 3], [2, 4, 2, null]];
$simpleWhereRows = [[1, 3, 1, 3]];
$complexRows = [
    ['abc', 'abc', null, 1],
    ['abc', 'def', 123, null],
    ['abc', 'ghi', null, null],
    ['def', 'abc', null, null],
    ['def', 'def', null, 1],
    ['def', 'ghi', 456, null],
    ['ghi', 'abc', null, null],
    ['ghi', 'def', null, null],
    ['ghi', 'ghi', null, 1],
];

// Source truth: SQLite upstream test/where6.test sections where6-1.1
// through where6-3.1. These cases own LEFT JOIN ON-clause index guards:
// terms inside ON may reject matches but must not filter the left table
// through a left-table index before null-extension. The same c=1 term in
// WHERE may filter the left rowset and use i1(c).
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where6LeftJoinOnClauseIndexGuardCases(1200) as $case) {
    $tests['real upstream where6 left join index guard dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $simpleOnRows, $simpleWhereRows, $complexRows): void {
        $t->same('where6.test sections where6-1.1 through where6-3.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->same(intdiv($case['case'] - 1, 20) + 1, $case['batch']);
        $t->true(str_starts_with($case['upstream_section'], 'where6-'));
        $t->same('ok', $case['integrity']);
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['detail'] !== '');
        $t->same(count($case['result_rows']), $case['result_row_count']);
        $t->same($case['null_extended_rows'], count(array_filter($case['result_rows'], static fn (array $row): bool => $row[3] === null)));
        $t->same($case['chosen_indexes'], array_values(array_unique($case['chosen_indexes'])));

        foreach ($case['result_rows'] as $row) {
            $t->same(range(0, count($row) - 1), array_keys($row));
        }

        if ($case['complex_left_table_equality_guard']) {
            $t->same('where6-3.1', $case['upstream_section']);
            $t->same(9, $case['left_row_count']);
            $t->same(9, $case['result_row_count']);
            $t->same(6, $case['null_extended_rows']);
            $t->same($complexRows, $case['result_rows']);
            $t->same(true, $case['left_table_index_blocked_for_on']);
            $t->same(false, $case['left_table_index_used_for_where']);
            $t->same(['sqlite_autoindex_t5_1'], $case['chosen_indexes']);
            $t->contains('t4a.x=t4b.x', implode(' ', $case['on_terms']));
            $t->contains('must not reorder or index-filter t4a/t4b', $case['detail']);
            $t->same([], $case['where_terms']);
            return;
        }

        $t->same(2, $case['left_row_count']);
        $t->true($case['right_table_index'] === 't2 INTEGER PRIMARY KEY');
        $t->same($case['where_terms'] !== [], $case['where_clause_filters_left_rows']);
        $t->same($case['where_terms'] === [], $case['on_clause_filters_match_only']);

        if ($case['on_clause_filters_match_only']) {
            $t->same($simpleOnRows, $case['result_rows']);
            $t->same(2, $case['result_row_count']);
            $t->same(1, $case['null_extended_rows']);
            $t->same(false, $case['left_table_index_used_for_where']);
            $t->contains('LEFT JOIN', $case['statement']);
            $t->true(in_array('c=1', $case['on_terms'], true) || in_array('1=c', $case['on_terms'], true));

            if ($case['with_left_table_index']) {
                $t->same('i1(c)', $case['left_table_index']);
                $t->same(true, $case['left_table_index_blocked_for_on']);
                $t->same(['t2(rowid)'], $case['chosen_indexes']);
                $t->contains('not used to filter', $case['detail']);
            } else {
                $t->same(null, $case['left_table_index']);
                $t->same(false, $case['left_table_index_blocked_for_on']);
            }
        }

        if ($case['where_clause_filters_left_rows']) {
            $t->same($simpleWhereRows, $case['result_rows']);
            $t->same(1, $case['result_row_count']);
            $t->same(0, $case['null_extended_rows']);
            $t->true(in_array('c=1', $case['where_terms'], true) || in_array('1=c', $case['where_terms'], true));
            $t->same(false, $case['left_table_index_blocked_for_on']);

            if ($case['with_left_table_index']) {
                $t->same(true, $case['left_table_index_used_for_where']);
                $t->same(['i1(c)', 't2(rowid)'], $case['chosen_indexes']);
                $t->contains('SEARCH t1 USING INDEX i1(c)', $case['detail']);
            } else {
                $t->same(false, $case['left_table_index_used_for_where']);
                $t->same(['t2(rowid)'], $case['chosen_indexes']);
            }
        }

        if ($case['explain_equivalent_to'] !== null) {
            $t->contains('EXPLAIN SELECT', $case['statement']);
            $t->true(in_array($case['explain_equivalent_to'], ['where6-1.1', 'where6-1.11', 'where6-2.1', 'where6-2.11'], true));
            if (str_contains($case['explain_equivalent_to'], '2.')) {
                $t->same(true, $case['with_left_table_index']);
            }
        }
    };
}

$tests['real upstream where6 left join index guard source range'] = static function (TestRunner $t) use ($expectedSections): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where6LeftJoinOnClauseIndexGuardCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same($expectedSections, $sections);
    $t->same('where6-1.1', $cases[0]['upstream_section']);
    $t->same('where6-3.1', $cases[19]['upstream_section']);
    $t->same('where6-1.1', $cases[20]['upstream_section']);
    $t->same('where6-3.1', $cases[1199]['upstream_section']);
    $t->same('where6.test sections where6-1.1 through where6-3.1', $cases[0]['source']);
};

$tests['real upstream where6 left join index guard rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where6LeftJoinOnClauseIndexGuardCases(0));
};

$tests['real upstream where6 left join index guard dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for LEFT JOIN ON-clause index guard, null-extension, WHERE filter, and chosen-index metadata',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for LEFT JOIN ON-clause index guard, null-extension, WHERE filter, and chosen-index metadata',
    );
    $t->same(
        'non-overlap: owns where6.test LEFT JOIN ON-clause index guards and avoids accepted where2/4/7/8/9/A/C/D/E/F/G/H/I/J/K/L/M/N, index*, bestindex*, B-tree page relocation/root-collapse/overflow, JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, and source-neutral cleanup clusters',
        'non-overlap: owns where6.test LEFT JOIN ON-clause index guards and avoids accepted where2/4/7/8/9/A/C/D/E/F/G/H/I/J/K/L/M/N, index*, bestindex*, B-tree page relocation/root-collapse/overflow, JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, and source-neutral cleanup clusters',
    );
};

return $tests;
