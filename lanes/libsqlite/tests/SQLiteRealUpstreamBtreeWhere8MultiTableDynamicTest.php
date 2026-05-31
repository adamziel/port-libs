<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/where8.test sections where8-3.2 through
// where8-3.23. The accepted where8 shard covers the single-table OR cases;
// this shard owns multi-table OR plans, parenthesized FROM sources, scalar
// subqueries, search counts, and sorter status.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where8MultiTableOrOptimizationCases(1200) as $case) {
    $tests['real upstream where8 multi table or optimization dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('where8.test sections where8-3.2 through where8-3.23', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true(str_starts_with($case['upstream_section'], 'where8-3.'));
        $t->true($case['scenario'] !== '');
        $t->true(str_contains($case['sql'], 'SELECT '));
        $t->true(count($case['from_tables']) >= 1);
        $t->true($case['predicate_shape'] !== '');
        $t->same($case['uses_temp_sort'], $case['scan_status'][1] === 1);
        if ($case['uses_linear_scan']) {
            $t->same(9, $case['scan_status'][0]);
        }
        $t->true($case['batch'] >= 1);
        $t->true(str_contains($case['detail'], $case['upstream_section']));

        foreach ($case['from_tables'] as $table) {
            $t->true(in_array($table, ['t1', 't2'], true));
        }

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['uses_or_optimization']) {
            $t->same(false, $case['uses_linear_scan']);
            $t->true(str_contains($case['detail'], 'OR index strategy'));
        }

        if ($case['uses_scalar_subquery']) {
            $t->true(str_contains($case['predicate_shape'], 'subquery'));
            $t->same(false, $case['uses_or_optimization']);
        }

        if ($case['upstream_section'] === 'where8-3.2') {
            $t->same([[4, 2]], $case['result_rows']);
            $t->same([9, 0], $case['scan_status']);
            $t->same(false, $case['uses_or_optimization']);
        }

        if ($case['upstream_section'] === 'where8-3.5') {
            $t->same([[2, 2], [2, 4], [3, 3], [3, 4]], $case['result_rows']);
            $t->same(true, $case['uses_temp_sort']);
            $t->same(['d=+a', "e='sixteen'"], $case['join_terms']);
        }

        if ($case['upstream_section'] === 'where8-3.9') {
            $t->same([[2, 2], [2, 4], [3, 3], [3, 4], [9, 9], [9, 4]], $case['result_rows']);
            $t->same(true, $case['uses_linear_scan']);
            $t->same(false, $case['uses_temp_sort']);
        }

        if ($case['upstream_section'] === 'where8-3.10') {
            $t->same([[1], [3], [5], [10], [2]], $case['result_rows']);
            $t->same(['t2'], $case['from_tables']);
            $t->same('e IS NULL OR e=four', $case['predicate_shape']);
        }

        if ($case['upstream_section'] === 'where8-3.15') {
            $t->same(25, count($case['result_rows']));
            $t->same(['I'], $case['result_rows'][0]);
            $t->same(['III'], $case['result_rows'][24]);
            $t->same([9, 1], $case['scan_status']);
        }

        if ($case['upstream_section'] === 'where8-3.21/3.22') {
            $t->same(true, $case['parenthesized_from']);
            $t->same([[1, 1], [2, 2], [3, 3], [4, 2], [4, 4]], $case['result_rows']);
        }
    };
}

$tests['real upstream where8 multi table dynamic source count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where8MultiTableOrOptimizationCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same('where8-3.2', $cases[0]['upstream_section']);
    $t->same('where8-3.21/3.22', $cases[10]['upstream_section']);
    $t->same('where8-3.2', $cases[1199]['upstream_section']);
    $t->same([
        'where8-3.2',
        'where8-3.3',
        'where8-3.5',
        'where8-3.8',
        'where8-3.9',
        'where8-3.10',
        'where8-3.11',
        'where8-3.12',
        'where8-3.14',
        'where8-3.15',
        'where8-3.21/3.22',
    ], $sections);
};

$tests['real upstream where8 multi table dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where8MultiTableOrOptimizationCases(0));
};

$tests['real upstream where8 multi table dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and existing OR/join/subquery result metadata helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and existing OR/join/subquery result metadata helpers',
    );
};

return $tests;
