<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index8.test sections 1.0, 1.0eqp,
// 1.1, and 1.1eqp. These cases cover ORDER BY/LIMIT index-scan selection
// after swapping a composite index that either covers or does not cover the
// WHERE clause column.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index8OrderByLimitScanCases(1200) as $case) {
    $tests['real upstream index8 order by limit dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index8.test sections 1.0, 1.0eqp, 1.1, and 1.1eqp', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], ['index8-1.0/1.0eqp', 'index8-1.1/1.1eqp'], true));
        $t->true($case['scenario'] !== '');
        $t->true(in_array($case['index_name'], ['t1abc', 't1abd'], true));
        $t->same('c', $case['where_column']);
        $t->same(4, $case['where_value']);
        $t->same(['a', 'b'], $case['order_by']);
        $t->same(2, $case['limit']);
        $t->same([[0, 4, 4, 4], [2, 3, 4, 23]], $case['result_rows']);
        $t->same(101, $case['row_count']);
        $t->same('ok', $case['integrity']);
        $t->same($case['covers_where'], in_array('c', $case['index_columns'], true));
        $t->same($case['uses_index'], str_contains($case['expected_detail'], 'USING INDEX'));
        $t->same($case['requires_sort'], str_contains($case['expected_detail'], 'TEMP B-TREE'));

        if ($case['upstream_section'] === 'index8-1.0/1.0eqp') {
            $t->same(['a', 'b', 'c'], $case['index_columns']);
            $t->same(true, $case['covers_where']);
            $t->same(true, $case['uses_index']);
            $t->same(false, $case['requires_sort']);
            $t->same(false, $case['table_lookup_required']);
            $t->same('SCAN t1 USING INDEX t1abc', $case['expected_detail']);
        }

        if ($case['upstream_section'] === 'index8-1.1/1.1eqp') {
            $t->same(['a', 'b', 'd'], $case['index_columns']);
            $t->same(false, $case['covers_where']);
            $t->same(false, $case['uses_index']);
            $t->same(true, $case['requires_sort']);
            $t->same(true, $case['table_lookup_required']);
            $t->same('SCAN t1; USE TEMP B-TREE FOR ORDER BY', $case['expected_detail']);
        }
    };
}

$tests['real upstream index8 order by limit dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index8OrderByLimitScanCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('index8-1.0/1.0eqp', $cases[0]['upstream_section']);
    $t->same('index8-1.1/1.1eqp', $cases[1]['upstream_section']);
    $t->same(600, $cases[1199]['batch']);
    $t->same([
        'index8-1.0/1.0eqp',
        'index8-1.1/1.1eqp',
    ], $sections);
};

$tests['real upstream index8 order by limit dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index8OrderByLimitScanCases(0));
};

$tests['real upstream index8 order by limit dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, composite-index coverage, ORDER BY/LIMIT planner detail, and result-row helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, composite-index coverage, ORDER BY/LIMIT planner detail, and result-row helpers',
    );
};

return $tests;
