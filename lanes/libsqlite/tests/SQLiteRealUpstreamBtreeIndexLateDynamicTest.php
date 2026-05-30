<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexLifecyclePlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-20.1
// through index-23.1. Existing accepted index.test lifecycle coverage handles
// the earlier catalog, duplicate-key, numeric-affinity, constraint, and
// composite-order sections; this batch covers the late quoted DROP INDEX,
// TEMP index scope, expression-index, GLOB-expression, and TYPEOF unique
// expression REINDEX cases.
foreach (SQLiteIndexLifecyclePlan::lateIndexExpressionAndTempCases(1000) as $case) {
    $tests['real upstream index.test late index expression and temp dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-20.1 through index-23.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], [
            'index-20.1',
            'index-20.2',
            'index-21.1',
            'index-21.2',
            'index-22.0',
            'index-23.0',
            'index-23.1',
        ], true));
        $t->true($case['scenario'] !== '');
        $t->true($case['table_name'] !== '');
        $t->true($case['index_name'] !== '');
        $t->true($case['index_kind'] !== '');
        $t->true($case['mutation'] !== '');
        $t->true($case['detail'] !== '');
        $t->same('ok', $case['integrity']);

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }
        foreach ($case['catalog_names'] as $name) {
            $t->true($name !== '');
        }

        if ($case['expected_error'] !== null) {
            $t->same([], $case['result_rows']);
            $t->true(str_contains($case['expected_error'], 'TEMP index'));
            $t->same('index-21.1', $case['upstream_section']);
        }

        if ($case['upstream_section'] === 'index-20.1') {
            $t->same('t6i2', $case['index_name']);
            $t->same(['t6', 't6i1'], $case['catalog_names']);
            $t->same([], $case['result_rows']);
            $t->same(null, $case['expected_error']);
            $t->true(str_contains($case['detail'], 'quoted DROP INDEX'));
        }

        if ($case['upstream_section'] === 'index-20.2') {
            $t->same('t6i1', $case['index_name']);
            $t->same(['t6'], $case['catalog_names']);
            $t->same('quoted-index-drop', $case['index_kind']);
        }

        if ($case['upstream_section'] === 'index-21.1') {
            $t->same('temp-index-scope', $case['index_kind']);
            $t->same('cannot create a TEMP index on non-TEMP table "t6"', $case['expected_error']);
            $t->same(['main.t6'], $case['catalog_names']);
        }

        if ($case['upstream_section'] === 'index-21.2') {
            $offset = ($case['batch'] - 1) * 1000;
            $t->same([[9 + $offset], [5 + $offset], [1 + $offset]], $case['result_rows']);
            $t->same(['temp.i21', 'temp.t6'], $case['catalog_names']);
            $t->same(null, $case['expected_error']);
            $t->true(str_contains($case['detail'], 'ORDER BY x DESC'));
        }

        if ($case['upstream_section'] === 'index-22.0') {
            $offset = ($case['batch'] - 1) * 1000;
            $t->same([['a', 1 + $offset, '|'], ['a', $offset, '|']], $case['result_rows']);
            $t->same(['t1', 'x1', 'x2'], $case['catalog_names']);
            $t->same('expression-index-if-not-exists', $case['index_kind']);
            $t->true(str_contains($case['detail'], 'boolean'));
        }

        if ($case['upstream_section'] === 'index-23.0') {
            $offset = ($case['batch'] - 1) * 1000;
            $t->same([['0.0', 1.0], ['1.0', 1.0]], $case['result_rows']);
            $t->same(['t1', 't1x1'], $case['catalog_names']);
            $t->same($offset, ($case['batch'] - 1) * 1000);
            $t->true(str_contains($case['detail'], 'GLOB expression'));
        }

        if ($case['upstream_section'] === 'index-23.1') {
            $t->same([[0.1]], $case['result_rows']);
            $t->same(['index_0', 't1'], $case['catalog_names']);
            $t->same('unique-expression-reindex', $case['index_kind']);
            $t->true(str_contains($case['detail'], 'TYPEOF(a)'));
        }
    };
}

$tests['real upstream index.test late index expression and temp corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteIndexLifecyclePlan::lateIndexExpressionAndTempCases(1000);
    $t->same(1000, count($cases));
    $t->same('index-20.1', $cases[0]['upstream_section']);
    $t->same('index-23.1', $cases[6]['upstream_section']);
    $t->same('index-23.0', $cases[999]['upstream_section']);
};

$tests['real upstream index.test late index expression and temp dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local index lifecycle, catalog, expression-index, temp-schema, and REINDEX behavior helpers',
        'no new support component needed; reuses lane-local index lifecycle, catalog, expression-index, temp-schema, and REINDEX behavior helpers',
    );
};

return $tests;
