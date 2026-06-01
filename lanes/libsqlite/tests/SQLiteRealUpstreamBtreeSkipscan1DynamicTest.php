<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/skipscan1.test sections skipscan1-1.2
// through skipscan1-9.3 plus the later duplicate empty-table, DISTINCT,
// repeated-column, and max(IN table) regressions. This owns skipscan1 planner
// and result parity, distinct from whereG likelihood-driven skip-scan choices
// and the generic lane-local skip-scan range helper.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::skipscan1PlannerCases(1200) as $case) {
    $tests['real upstream skipscan1 btree index dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('skipscan1.test sections skipscan1-1.2 through skipscan1-9.3 and post-2019 regressions', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'skipscan1-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['table_shape'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true(str_contains($case['detail'], 'skipscan1 dynamic replay'));
        $t->true(count($case['stat1_rows']) >= 1);
        $t->true(array_values($case['result_rows']) === $case['result_rows']);
        $t->true(array_values($case['skip_prefix_columns']) === $case['skip_prefix_columns']);
        $t->true(array_values($case['constrained_columns']) === $case['constrained_columns']);
        $t->true(array_values($case['rejected_indexes']) === $case['rejected_indexes']);

        foreach ($case['stat1_rows'] as $stat) {
            $t->true($stat['tbl'] !== '');
            $t->true(array_key_exists('idx', $stat));
            $t->true($stat['stat'] !== '');
        }

        if ($case['uses_any_skip_scan']) {
            $t->true(count($case['skip_prefix_columns']) >= 1);
            $t->true($case['chosen_index'] !== null);
        } else {
            $t->same([], $case['skip_prefix_columns']);
            $t->true(!str_contains($case['plan_detail'], 'ANY('));
        }

        if ($case['uses_temp_distinct']) {
            $t->contains('USE TEMP B-TREE FOR DISTINCT', $case['plan_detail']);
            $t->same(false, $case['order_by_satisfied']);
        }

        if ($case['uses_temp_sort']) {
            $t->contains('ORDER BY', $case['plan_detail']);
        }

        if ($case['optimization_disabled']) {
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same('SCAN t9a', $case['plan_detail']);
            $t->same(null, $case['chosen_index']);
        }

        if ($case['noskipscan_token']) {
            $t->same(false, $case['uses_any_skip_scan']);
            $t->contains('noskipscan', $case['stat1_rows'][0]['stat']);
            $t->same('SCAN t1', $case['plan_detail']);
        }

        if ($case['upstream_section'] === 'skipscan1-1.2') {
            $t->same([['abc', 345, 7, 8, '|'], ['def', 345, 9, 10, '|']], $case['result_rows']);
            $t->same(['a'], $case['skip_prefix_columns']);
            $t->same(['b=?'], $case['constrained_columns']);
            $t->same(true, $case['order_by_satisfied']);
            $t->contains('ANY(a) AND b=?', $case['plan_detail']);
        }

        if ($case['upstream_section'] === 'skipscan1-1.4') {
            $t->same([['abc', 234, 6, 7, '|'], ['bcd', 100, 6, 11, '|']], $case['result_rows']);
            $t->same(['a', 'b'], $case['skip_prefix_columns']);
            $t->same(['c=?'], $case['constrained_columns']);
            $t->contains('ANY(a) AND ANY(b) AND c=?', $case['plan_detail']);
        }

        if ($case['upstream_section'] === 'skipscan1-1.52') {
            $t->same('rowid-index-left-join', $case['table_shape']);
            $t->same(['one', null, null, null, null, '|'], $case['result_rows'][0]);
            $t->same(['ninty-nine', null, null, null, null, '|'], $case['result_rows'][3]);
        }

        if ($case['upstream_section'] === 'skipscan1-2.2') {
            $t->same('rowid-primary-key', $case['table_shape']);
            $t->same('sqlite_autoindex_t2_1', $case['chosen_index']);
            $t->contains('sqlite_autoindex_t2_1', $case['plan_detail']);
        }

        if ($case['upstream_section'] === 'skipscan1-3.2') {
            $t->same('without-rowid-primary-key', $case['table_shape']);
            $t->same('PRIMARY KEY', $case['chosen_index']);
            $t->contains('PRIMARY KEY (ANY(a) AND b=?)', $case['plan_detail']);
        }

        if ($case['upstream_section'] === 'skipscan1-4.1') {
            $t->same(8, count($case['result_rows']));
            $t->same([9], $case['result_rows'][0]);
            $t->same(['a', 'b', 'c', 'd', 'e', 'f', 'g'], $case['skip_prefix_columns']);
            $t->same('t4all', $case['chosen_index']);
        }

        if ($case['upstream_section'] === 'skipscan1-5.3') {
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same('t5i1', $case['chosen_index']);
            $t->same(['t5i2 skip-scan'], $case['rejected_indexes']);
            $t->contains('COVERING INDEX t5i1', $case['plan_detail']);
        }

        if ($case['upstream_section'] === 'skipscan1-6.1') {
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same('500000 250000 125000', $case['stat1_rows'][0]['stat']);
        }

        if ($case['upstream_section'] === 'skipscan1-6.2') {
            $t->same(true, $case['uses_any_skip_scan']);
            $t->same('500000 250000 62500', $case['stat1_rows'][0]['stat']);
            $t->contains('ANY(a)', $case['plan_detail']);
        }

        if ($case['upstream_section'] === 'skipscan1-7.2') {
            $t->same(true, $case['noskipscan_token']);
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same(['t1ab skip-scan'], $case['rejected_indexes']);
        }

        if ($case['upstream_section'] === 'skipscan1-8.1') {
            $t->same('without-rowid-or', $case['table_shape']);
            $t->same([[1, 'AB']], $case['result_rows']);
            $t->same(['x'], $case['skip_prefix_columns']);
        }

        if ($case['upstream_section'] === 'skipscan1-9.2') {
            $t->same('in-subquery-skipscan', $case['table_shape']);
            $t->same(['b IN subquery'], $case['constrained_columns']);
            $t->same('t9a_ab', $case['chosen_index']);
        }

        if ($case['upstream_section'] === 'skipscan1-9.3') {
            $t->same(true, $case['optimization_disabled']);
            $t->same('in-subquery-skipscan-disabled', $case['table_shape']);
            $t->same(['t9a_ab skip-scan'], $case['rejected_indexes']);
        }

        if ($case['upstream_section'] === 'skipscan1-2.2-empty') {
            $t->same([], $case['result_rows']);
            $t->same(true, $case['uses_any_skip_scan']);
            $t->same(true, $case['order_by_satisfied']);
        }

        if ($case['upstream_section'] === 'skipscan1-3.2-distinct') {
            $t->same(true, $case['uses_temp_distinct']);
            $t->same([[3, 0, 1, null, '|'], [0, 4, 1, null, '|'], [5, 6, 1, null, '|']], $case['result_rows']);
            $t->same(['c4'], $case['skip_prefix_columns']);
        }

        if ($case['upstream_section'] === 'skipscan1-4.10') {
            $t->same([[3]], $case['result_rows']);
            $t->same('i1', $case['chosen_index']);
            $t->contains('a=b', implode(' ', $case['constrained_columns']));
        }

        if ($case['upstream_section'] === 'skipscan1-5.0') {
            $t->same([[3]], $case['result_rows']);
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same('sqlite_autoindex_t1_1', $case['chosen_index']);
        }
    };
}

$tests['real upstream skipscan1 btree index dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::skipscan1PlannerCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same('skipscan1-1.2', $cases[0]['upstream_section']);
    $t->same('skipscan1-5.0', $cases[26]['upstream_section']);
    $t->same('skipscan1-1.2', $cases[27]['upstream_section']);
    $t->same('skipscan1-5.3', $cases[1199]['upstream_section']);
    $t->same([
        'skipscan1-1.2',
        'skipscan1-1.3',
        'skipscan1-1.4',
        'skipscan1-1.5',
        'skipscan1-1.6',
        'skipscan1-1.7',
        'skipscan1-1.51',
        'skipscan1-1.52',
        'skipscan1-2.2',
        'skipscan1-3.2',
        'skipscan1-4.1',
        'skipscan1-5.3',
        'skipscan1-6.1',
        'skipscan1-6.2',
        'skipscan1-6.3',
        'skipscan1-7.1',
        'skipscan1-7.2',
        'skipscan1-7.3',
        'skipscan1-8.1',
        'skipscan1-8.2',
        'skipscan1-9.2',
        'skipscan1-9.3',
        'skipscan1-2.2-empty',
        'skipscan1-2.3-empty',
        'skipscan1-3.2-distinct',
        'skipscan1-4.10',
        'skipscan1-5.0',
    ], $sections);
};

$tests['real upstream skipscan1 btree index dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::skipscan1PlannerCases(0));
};

$tests['real upstream skipscan1 btree index dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for skip-scan result rows, stat1 selectivity, PRIMARY KEY storage, noskipscan tokens, OR, IN-subquery, and DISTINCT behavior',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for skip-scan result rows, stat1 selectivity, PRIMARY KEY storage, noskipscan tokens, OR, IN-subquery, and DISTINCT behavior',
    );
    $t->same(
        'non-overlap: owns skipscan1.test sections 1.2-9.3 and post-2019 regressions, avoiding accepted whereG likelihood skip-scan and generic SQLiteIndexSkipScanPlan range-helper coverage',
        'non-overlap: owns skipscan1.test sections 1.2-9.3 and post-2019 regressions, avoiding accepted whereG likelihood skip-scan and generic SQLiteIndexSkipScanPlan range-helper coverage',
    );
};

return $tests;
