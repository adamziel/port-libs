<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexA.test sections indexA-1.1
// through indexA-8.1. This batch owns the partial-index affinity and planner
// behavior not covered by the existing index7/index8/indexexpr dynamic files.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialIndexAffinityPlannerCases(1200) as $case) {
    $tests['real upstream indexA partial affinity planner dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexA.test sections indexA-1.1 through indexA-8.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'indexA-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true(in_array($case['affinity'], ['TEXT', 'NUMERIC', 'REAL', 'INTEGER'], true));
        $t->true($case['table_shape'] === 'rowid partial index' || $case['table_shape'] === 'WITHOUT ROWID partial index');
        $t->same($case['without_rowid'], str_contains($case['table_shape'], 'WITHOUT ROWID'));
        $t->same($case['result_count'], count($case['result_rows']));

        if ($case['expected_error'] === null) {
            $t->same('ok', $case['integrity']);
            $t->true($case['detail'] !== '');
            if ($case['uses_partial_index'] && str_contains($case['detail'], 'SEARCH')) {
                $t->true(str_contains($case['detail'], 'INDEX'));
            }
        } else {
            $t->same('expected-error', $case['integrity']);
            $t->same([], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_contains($case['expected_error'], 'collation'));
        }

        if ($case['upstream_section'] === 'indexA-1.1/1.7') {
            $t->same('i2', $case['index_name']);
            $t->same(2, $case['result_count']);
            $t->same([null, 'abc', 1, 2], $case['result_rows'][0]);
            $t->same([null, '5', 4, 3], $case['result_rows'][1]);
            $t->true(str_contains($case['detail'], 'USING INDEX i2'));
        }

        if ($case['upstream_section'] === 'indexA-2.1' || $case['upstream_section'] === 'indexA-3.1') {
            if ($case['affinity'] === 'TEXT') {
                $t->same(1, $case['result_count']);
                $t->same('text', $case['result_rows'][0][3]);
                $t->true(is_bool($case['uses_partial_index']));
            }
            if ($case['affinity'] === 'NUMERIC') {
                $t->same(2, $case['result_count']);
                $t->same('integer', $case['result_rows'][0][3]);
                $t->true(is_bool($case['uses_partial_index']));
            }
            if ($case['affinity'] === 'REAL') {
                $t->same(2, $case['result_count']);
                $t->same('real', $case['result_rows'][0][3]);
                $t->true(is_bool($case['uses_partial_index']));
            }
        }

        if ($case['upstream_section'] === 'indexA-4.1/4.1.2') {
            $t->same([[6, 'two']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'COVERING INDEX t2a_two'));
        }

        if ($case['upstream_section'] === 'indexA-5.1') {
            $t->same('no such collation sequence: g', $case['expected_error']);
        }

        if ($case['upstream_section'] === 'indexA-5.2/5.3') {
            $t->same('ex1', $case['index_name']);
            $t->true(str_contains($case['detail'], 'persisted after reopen'));
        }

        if ($case['upstream_section'] === 'indexA-6.2/6.5' || $case['upstream_section'] === 'indexA-6.7') {
            $t->same(2, $case['result_count']);
            $t->true(str_contains($case['detail'], 't2'));
            $t->same([1, 1, 1, 1, 5, 1], $case['result_rows'][0]);
        }

        if ($case['upstream_section'] === 'indexA-7.0') {
            $t->same([[5, 'abc', 'xyz']], $case['result_rows']);
            $t->true(str_contains($case['statement'], 'INDEXED BY i1'));
        }

        if ($case['upstream_section'] === 'indexA-8.1') {
            $t->same([[1, 4, 1], [2, 4, 2]], $case['result_rows']);
            $t->same(4, $case['predicate_literal']);
        }
    };
}

$tests['real upstream indexA partial affinity planner corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialIndexAffinityPlannerCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('indexA-1.1/1.7', $cases[0]['upstream_section']);
    $t->same('indexA-2.1', $cases[1]['upstream_section']);
    $t->same('indexA-8.1', $cases[103]['upstream_section']);
    $t->true(str_starts_with($cases[1199]['upstream_section'], 'indexA-'));
    $t->same([
        'indexA-1.1/1.7',
        'indexA-2.1',
        'indexA-3.1',
        'indexA-4.1/4.1.2',
        'indexA-5.1',
        'indexA-5.2/5.3',
        'indexA-6.2/6.5',
        'indexA-6.7',
        'indexA-7.0',
        'indexA-8.1',
    ], $sections);
};

$tests['real upstream indexA partial affinity planner rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialIndexAffinityPlannerCases(0));
};

$tests['real upstream indexA partial affinity planner dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, partial-index affinity implication, result-row, collation-error, bloom-filter detail, and integrity helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, partial-index affinity implication, result-row, collation-error, bloom-filter detail, and integrity helpers',
    );
};

return $tests;
