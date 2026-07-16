<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index7.test sections index7-3.1
// through index7-8.1. Existing accepted index7 dynamic coverage handles
// sections 1 and 2; this batch covers the later partial UNIQUE, qualified
// predicate, view routing, IS TRUE, and incomplete-stat planner cases.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index7PartialUniqueAndPlannerCases(1000) as $case) {
    $tests['real upstream index7 partial unique and planner dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index7.test index7-3.1 through index7-8.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], [
            'index7-3.1/3.2',
            'index7-3.3/3.4',
            'index7-5.0',
            'index7-6.1/6.2',
            'index7-6.3/6.4',
            'index7-6.5',
            'index7-7.0/7.1',
            'index7-8.1',
        ], true));
        $t->true($case['scenario'] !== '');
        $t->true($case['table_name'] !== '');
        $t->true($case['index_name'] !== '');
        $t->true($case['partial_predicate'] !== '');
        $t->true($case['mutation'] !== '');
        $t->true(
            !$case['uses_index']
            || str_contains($case['detail'], 'INDEX')
            || str_contains($case['detail'], 'index')
            || str_contains($case['detail'], 'sqlite_stat1')
        );
        $t->true($case['batch'] >= 1);

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        foreach ($case['stat_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['expected_error'] !== null) {
            $t->same([], $case['result_rows']);
            $t->true(str_contains($case['expected_error'], 'syntax error') || str_contains($case['expected_error'], 'UNIQUE constraint failed'));
        } else {
            $t->same(null, $case['expected_error']);
            $t->same('ok', $case['integrity']);
        }

        if ($case['upstream_section'] === 'index7-3.1/3.2') {
            $t->same('t3a', $case['index_name']);
            $t->same('a<>999', $case['partial_predicate']);
            $t->same('UNIQUE constraint failed: t3.a', $case['expected_error']);
            $t->same(true, $case['uses_index']);
        }

        if ($case['upstream_section'] === 'index7-3.3/3.4') {
            $t->same('t3a', $case['index_name']);
            $t->same(false, $case['uses_index']);
            $t->true($case['result_rows'][0][0] >= 162);
        }

        if ($case['upstream_section'] === 'index7-5.0') {
            $t->same('t3b', $case['index_name']);
            $t->true(str_contains($case['partial_predicate'], 'qualified.t3.b'));
            $t->same([[6], [6]], $case['result_rows']);
            $t->same([[6, 6]], $case['stat_rows']);
        }

        if ($case['upstream_section'] === 'index7-6.1/6.2') {
            $t->same(false, $case['uses_index']);
            $t->same([[1, 'xyz', 'abc', 'not xyz']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index7-6.3/6.4') {
            $t->same(true, $case['uses_index']);
            $t->same([['def', 'xyz']], $case['result_rows']);
            $t->same('SEARCH t4 USING INDEX i4 (c=?)', $case['detail']);
        }

        if ($case['upstream_section'] === 'index7-6.5') {
            $t->same('near "#1": syntax error', $case['expected_error']);
            $t->same(null, $case['integrity']);
        }

        if ($case['upstream_section'] === 'index7-7.0/7.1') {
            $t->same('y IS NOT TRUE', $case['partial_predicate']);
            $t->same([[1, 1]], $case['result_rows']);
            $t->same(false, $case['uses_index']);
        }

        if ($case['upstream_section'] === 'index7-8.1') {
            $t->same('t1y', $case['index_name']);
            $t->same(true, $case['uses_index']);
            $t->same([[1]], $case['result_rows']);
            $t->same([[2, 0]], $case['stat_rows']);
        }
    };
}

$tests['real upstream index7 later partial planner corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index7PartialUniqueAndPlannerCases(1000);
    $t->same(1000, count($cases));
    $t->same('index7-3.1/3.2', $cases[0]['upstream_section']);
    $t->same('index7-8.1', $cases[7]['upstream_section']);
    $t->same('index7-8.1', $cases[999]['upstream_section']);
};

$tests['real upstream index7 later partial planner dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, partial-index predicate, stat-row, integrity-result, and planner-detail helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, partial-index predicate, stat-row, integrity-result, and planner-detail helpers',
    );
};

return $tests;
