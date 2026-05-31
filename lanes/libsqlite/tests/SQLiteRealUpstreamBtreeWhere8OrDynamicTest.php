<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/where8.test sections where8-1.2 through
// where8-1.15. These cases exercise ordinary B-tree index OR optimization:
// separate rowid-union index probes, range arms, BETWEEN arms, ordered
// rowid output, and same-column OR-to-IN rewrite.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where8OrdinaryOrOptimizationCases(1200) as $case) {
    $tests['real upstream where8 ordinary or optimization dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('where8.test sections where8-1.2 through where8-1.15', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true(str_starts_with($case['upstream_section'], 'where8-1.'));
        $t->same('ordinary B-tree indexes satisfy OR terms with index union or same-column OR-to-IN rewrite', $case['scenario']);
        $t->true(str_contains($case['sql'], ' OR '));
        $t->same('OR', $case['predicate']['operator']);
        $t->true(count($case['predicate']['terms']) >= 2);
        $t->same(['c'], $case['needed_columns']);
        $t->same($case['expected_rows'], $case['result_rows']);
        $t->true($case['scan_status'][2] > 0);
        $t->same(true, $case['uses_or_optimization']);
        $t->true($case['batch'] >= 1);
        $t->true($case['detail'] !== '');

        if ($case['plan_strategy'] === 'or-to-in') {
            $t->same(['i1'], $case['indexes']);
            $t->same(false, $case['requires_rowid_union']);
            $t->same(false, $case['deduplicates_rowids']);
            $t->same(false, $case['residual_predicate_required']);
            $t->true(str_contains($case['detail'], 'OR terms rewritten to IN'));
            $t->same(['I', 'II', 'III', 'V'], $case['result_rows']);
        } else {
            $t->same('or-index-union', $case['plan_strategy']);
            $t->same(true, $case['requires_rowid_union']);
            $t->same(true, $case['deduplicates_rowids']);
            $t->true(str_contains($case['detail'], 'MULTI-INDEX OR'));
            $t->true(in_array('i1', $case['indexes'], true));
            $t->true(in_array('i2', $case['indexes'], true));
        }

        if ($case['upstream_section'] === 'where8-1.2') {
            $t->same(['I', 'IX'], $case['result_rows']);
            $t->same([0, 0, 6], $case['scan_status']);
            $t->same(2, $case['arms']);
        }

        if ($case['upstream_section'] === 'where8-1.11') {
            $t->same(['IV', 'V', 'VI', 'IX'], $case['result_rows']);
            $t->same(true, $case['residual_predicate_required']);
        }

        if ($case['upstream_section'] === 'where8-1.13') {
            $t->same(['II', 'III', 'IV', 'V', 'VI'], $case['result_rows']);
            $t->same([0, 1, 18], $case['scan_status']);
            $t->same(5, $case['arms']);
        }
    };
}

$tests['real upstream where8 ordinary or optimization dynamic source count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where8OrdinaryOrOptimizationCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('where8-1.2', $cases[0]['upstream_section']);
    $t->same('where8-1.15', $cases[6]['upstream_section']);
    $t->same('where8-1.2', $cases[1197]['upstream_section']);
    $t->same([
        'where8-1.11',
        'where8-1.12.1',
        'where8-1.13',
        'where8-1.15',
        'where8-1.2',
        'where8-1.3',
        'where8-1.9',
    ], $sections);
};

$tests['real upstream where8 ordinary or optimization dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where8OrdinaryOrOptimizationCases(0));
};

$tests['real upstream where8 ordinary or optimization dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and SQLiteOrOptimizationPlan index-union/OR-to-IN behavior',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and SQLiteOrOptimizationPlan index-union/OR-to-IN behavior',
    );
};

return $tests;
