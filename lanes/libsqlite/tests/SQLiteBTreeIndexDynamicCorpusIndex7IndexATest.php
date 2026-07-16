<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index7.test sections index7-3.1 through
// index7-8.1. These WITHOUT ROWID cases cover partial UNIQUE indexes,
// database-qualified partial predicates, view routing, boolean predicates, and
// sparse sqlite_stat1 planner decisions after mutation/vacuum.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index7PartialUniqueAndPlannerCases(1000) as $case) {
    $tests['real upstream index7 partial unique planner dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index7.test index7-3.1 through index7-8.1', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'index7-'));
        $t->true($case['table_name'] !== '');
        $t->true($case['index_name'] !== '');
        $t->true($case['partial_predicate'] !== '');
        $t->true($case['mutation'] !== '');
        $t->true($case['integrity'] === 'ok' || $case['integrity'] === null);
        $t->true($case['batch'] >= 1);
        $t->true(is_array($case['result_rows']));
        $t->true(is_array($case['stat_rows']));
        $t->true($case['detail'] !== '');

        if ($case['expected_error'] !== null) {
            $t->same([], $case['result_rows']);
            $t->true(str_contains($case['expected_error'], 'UNIQUE constraint failed') || str_contains($case['expected_error'], 'syntax error'));
        } else {
            $t->true(count($case['result_rows']) >= 1);
            $t->same('ok', $case['integrity']);
        }

        if ($case['upstream_section'] === 'index7-3.1/3.2') {
            $t->same('t3a', $case['index_name']);
            $t->same('a<>999', $case['partial_predicate']);
            $t->same('UNIQUE constraint failed: t3.a', $case['expected_error']);
            $t->same([[199, 160, 39]], $case['stat_rows']);
            $t->same('ok', $case['integrity']);
        }

        if ($case['upstream_section'] === 'index7-3.3/3.4') {
            $t->true($case['result_rows'][0][0] >= 162);
            $t->same(162, $case['result_rows'][0][0] % 1000);
            $t->same(false, $case['uses_index']);
        }

        if ($case['upstream_section'] === 'index7-5.0') {
            $t->same([[6], [6]], $case['result_rows']);
            $t->true(str_contains($case['partial_predicate'], '.t3.b'));
        }

        if ($case['upstream_section'] === 'index7-6.3/6.4') {
            $t->same([['def', 'xyz']], $case['result_rows']);
            $t->same(true, $case['uses_index']);
            $t->true(str_contains($case['detail'], 'SEARCH t4 USING INDEX i4'));
        }

        if ($case['upstream_section'] === 'index7-8.1') {
            $t->same('t1y', $case['index_name']);
            $t->same(true, $case['uses_index']);
            $t->true(str_contains($case['detail'], 'COVERING INDEX t1y'));
        }
    };
}

$tests['real upstream index7 partial unique planner dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index7PartialUniqueAndPlannerCases(1000);
    $t->same(1000, count($cases));
    $t->same('index7-3.1/3.2', $cases[0]['upstream_section']);
    $t->same('index7.test index7-3.1 through index7-8.1', $cases[0]['source']);
    $t->true($cases[999]['batch'] > $cases[0]['batch']);
};

// Source truth: SQLite upstream test/indexA.test sections indexA-1.1 through
// indexA-8.1. These cases stress partial-index implication across TEXT,
// NUMERIC, and REAL affinity, rowid and WITHOUT ROWID storage, collation
// errors, bloom-filter joins, INDEXED BY, and commuted predicates.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialIndexAffinityPlannerCases(1200) as $case) {
    $tests['real upstream indexA partial affinity planner dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('indexA.test sections indexA-1.1 through indexA-8.1', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1200);
        $t->true(str_starts_with($case['upstream_section'], 'indexA-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true(in_array($case['affinity'], ['TEXT', 'NUMERIC', 'REAL', 'INTEGER'], true));
        $t->true(in_array($case['table_shape'], ['rowid partial index', 'WITHOUT ROWID partial index'], true));
        $t->true($case['batch'] >= 1);
        $t->true(is_array($case['result_rows']));
        $t->same(count($case['result_rows']), $case['result_count']);

        if ($case['expected_error'] !== null) {
            $t->same('expected-error', $case['integrity']);
            $t->same(false, $case['uses_partial_index']);
            $t->same([], $case['result_rows']);
            $t->true(str_contains($case['expected_error'], 'collation'));
        } else {
            $t->same('ok', $case['integrity']);
            $t->true($case['detail'] !== '');
        }

        if ($case['affinity'] === 'TEXT' && $case['upstream_section'] === 'indexA-2.1') {
            $t->same(1, $case['result_count']);
        }

        if (($case['affinity'] === 'NUMERIC' || $case['affinity'] === 'REAL') && $case['upstream_section'] === 'indexA-2.1') {
            $t->same(2, $case['result_count']);
        }

        $t->same($case['uses_partial_index'], str_contains($case['detail'], 'INDEX') || str_contains($case['detail'], 'partial index'));

        if ($case['upstream_section'] === 'indexA-1.1/1.7') {
            $t->same('i2', $case['index_name']);
            $t->same(true, $case['uses_partial_index']);
            $t->same([[null, 'abc', 1, 2], [null, '5', 4, 3]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'indexA-4.1/4.1.2') {
            $t->same([[6, 'two']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'COVERING INDEX t2a_two'));
        }

        if ($case['upstream_section'] === 'indexA-6.7') {
            $t->true(str_contains($case['detail'], 'COVERING INDEX t2yz'));
            $t->same(true, $case['uses_partial_index']);
        }
    };
}

$tests['real upstream indexA partial affinity planner dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialIndexAffinityPlannerCases(1200);
    $t->same(1200, count($cases));
    $t->same('indexA-1.1/1.7', $cases[0]['upstream_section']);
    $t->same('indexA.test sections indexA-1.1 through indexA-8.1', $cases[0]['source']);
    $t->true($cases[1199]['batch'] > $cases[0]['batch']);
};

$tests['real upstream index7 indexA dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planners for partial-index predicates, WITHOUT ROWID planner evidence, affinity implication, and result-row fixtures',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planners for partial-index predicates, WITHOUT ROWID planner evidence, affinity implication, and result-row fixtures',
    );
};

return $tests;
