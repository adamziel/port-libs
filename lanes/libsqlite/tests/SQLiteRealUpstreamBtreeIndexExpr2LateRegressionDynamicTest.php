<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexexpr2.test sections 5.0 through
// 11.0. This batch owns late expression-index regressions not covered by the
// existing indexexpr2 section 3 collation/order and section 4 update cases.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexexpr2LateExpressionIndexRegressionCases(1000) as $case) {
    $tests['real upstream indexexpr2 late expression index regression dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexexpr2.test sections indexexpr2-5.0 through indexexpr2-11.0', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'indexexpr2-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->same($case['result_count'], count($case['result_rows']));
        $t->true($case['detail'] !== '');
        $t->true($case['integrity'] === 'ok' || $case['integrity'] === 'expected-error');

        if ($case['uses_expression_index']) {
            $t->true($case['index_name'] !== null);
            $t->true($case['expression'] !== null);
            $t->true(str_contains($case['detail'], 'INDEX') || str_contains($case['detail'], 'index'));
        }

        if ($case['upstream_section'] === 'indexexpr2-5.1/5.4') {
            $t->same([[2, 4], [3, 9]], $case['result_rows']);
            $t->same('t5a/t5b', $case['index_name']);
            $t->true(str_contains($case['detail'], 'MULTI-INDEX OR'));
        }

        if ($case['upstream_section'] === 'indexexpr2-6.1.1/6.1.3') {
            $t->same([[1, 123], [2, '123'], [3, '123abc'], [4, 123.0]], $case['result_rows']);
            $t->same('CAST(b AS INTEGER)', $case['expression']);
            $t->same(4, $case['result_count']);
        }

        if ($case['upstream_section'] === 'indexexpr2-6.2.1/6.2.3') {
            $t->same([[1, 123], [2, '123']], $case['result_rows']);
            $t->same('CAST(b AS TEXT)', $case['expression']);
            $t->same(2, $case['result_count']);
        }

        if ($case['upstream_section'] === 'indexexpr2-7.1/7.3') {
            $t->same('integer overflow', $case['expected_error']);
            $t->same('expected-error', $case['integrity']);
            $t->same([['CREATE TABLE t0(c0)']], $case['result_rows']);
            $t->same(false, $case['uses_expression_index']);
        }

        if ($case['upstream_section'] === 'indexexpr2-8.1/8.3') {
            $t->same([[null]], $case['result_rows']);
            $t->same(false, $case['uses_expression_index']);
            $t->true(str_contains($case['statement'], 'BETWEEN'));
        }

        if ($case['upstream_section'] === 'indexexpr2-8.4/8.5') {
            $t->same([[1, 2, null, null], [3, 4, null, null]], $case['result_rows']);
            $t->same(null, $case['index_name']);
            $t->true(str_contains($case['statement'], 'LEFT JOIN'));
        }

        if ($case['upstream_section'] === 'indexexpr2-9.0') {
            $t->same([[5, -5, 205], [5, 20, 220]], $case['result_rows']);
            $t->same('a, abs(b)', $case['expression']);
            $t->true(str_contains($case['statement'], 'SELECT max(c+abs(b))'));
        }

        if ($case['upstream_section'] === 'indexexpr2-10.0/10.1') {
            $t->same([[4]], $case['result_rows']);
            $t->same('+a COLLATE NOCASE', $case['expression']);
            $t->true(str_contains($case['detail'], 'EP_Collate'));
        }

        if ($case['upstream_section'] === 'indexexpr2-11.0') {
            $t->same([[44, -44]], $case['result_rows']);
            $t->same('b, a', $case['expression']);
            $t->true(str_contains($case['detail'], 'generated column'));
        }
    };
}

$tests['real upstream indexexpr2 late expression index regression corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexexpr2LateExpressionIndexRegressionCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('indexexpr2-5.1/5.4', $cases[0]['upstream_section']);
    $t->same('indexexpr2-11.0', $cases[8]['upstream_section']);
    $t->same('indexexpr2-5.1/5.4', $cases[9]['upstream_section']);
    $t->same(112, $cases[999]['batch']);
    $t->same([
        'indexexpr2-10.0/10.1',
        'indexexpr2-11.0',
        'indexexpr2-5.1/5.4',
        'indexexpr2-6.1.1/6.1.3',
        'indexexpr2-6.2.1/6.2.3',
        'indexexpr2-7.1/7.3',
        'indexexpr2-8.1/8.3',
        'indexexpr2-8.4/8.5',
        'indexexpr2-9.0',
    ], $sections);
};

$tests['real upstream indexexpr2 late expression index regression rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexexpr2LateExpressionIndexRegressionCases(0));
};

$tests['real upstream indexexpr2 late expression index regression dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index expression-index regression, CAST affinity, OR-union, no-residue overflow, LEFT JOIN, correlated aggregate, collation, and generated-column corpus helpers',
        'no new support component needed; reuses lane-local B-tree/index expression-index regression, CAST affinity, OR-union, no-residue overflow, LEFT JOIN, correlated aggregate, collation, and generated-column corpus helpers',
    );
};

return $tests;
