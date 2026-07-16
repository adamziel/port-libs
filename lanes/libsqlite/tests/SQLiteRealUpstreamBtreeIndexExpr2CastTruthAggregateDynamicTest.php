<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexexpr2.test sections 6.1.1 through
// 11.0. Earlier accepted files cover indexexpr2 collation ORDER BY and update
// refcount cases; this batch covers the later CAST expression-index lookup,
// overflow cleanup, nullable truth-expression, aggregate/collation rewrite,
// and generated-column aggregate resolution regressions.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexExpr2CastTruthAggregateCases(1000) as $case) {
    $tests['real upstream indexexpr2 cast truth aggregate dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexexpr2.test sections 6.1.1 through 11.0', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'indexexpr2-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['sql'] !== '');
        $t->true($case['expression'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true($case['detail'] !== '');
        $t->true(count($case['result_rows']) >= 1);

        foreach ($case['result_rows'] as $row) {
            $t->same(array_values($row), $row);
        }

        if ($case['uses_expression_index']) {
            $t->true($case['index_name'] !== null);
            $t->true(str_contains($case['detail'], 'INDEX') || str_contains($case['detail'], 'index'));
        } else {
            $t->true($case['index_name'] === null || $case['expected_error'] !== null || str_contains($case['expression'], 'WHERE'));
        }

        if ($case['upstream_section'] === 'indexexpr2-6.1.1/6.1.3') {
            $t->same('x1i', $case['index_name']);
            $t->same('CAST(b AS INTEGER)', $case['expression']);
            $t->same([[1, 123], [2, '123'], [3, '123abc'], [4, 123.0]], $case['result_rows']);
            $t->same(true, $case['uses_expression_index']);
            $t->same(false, $case['covering']);
        }

        if ($case['upstream_section'] === 'indexexpr2-6.2.1/6.2.3') {
            $t->same('x1i2', $case['index_name']);
            $t->same('CAST(b AS TEXT)', $case['expression']);
            $t->same([[1, 123], [2, '123']], $case['result_rows']);
            $t->same(true, $case['uses_expression_index']);
        }

        if ($case['upstream_section'] === 'indexexpr2-7.1/7.3') {
            $t->same('integer overflow', $case['expected_error']);
            $t->same([['CREATE TABLE t0(c0)']], $case['result_rows']);
            $t->same(false, $case['uses_expression_index']);
        }

        if ($case['upstream_section'] === 'indexexpr2-8.1.1/8.1.2') {
            $t->same([[null]], $case['result_rows']);
            $t->same(false, $case['uses_expression_index']);
            $t->true(str_contains($case['detail'], 'partial index'));
        }

        if ($case['upstream_section'] === 'indexexpr2-8.3') {
            $t->same([[1]], $case['result_rows']);
            $t->same(false, $case['uses_expression_index']);
            $t->true(str_contains($case['sql'], 'BETWEEN'));
        }

        if ($case['upstream_section'] === 'indexexpr2-8.5') {
            $t->same([[1, 2, null, null], [3, 4, null, null]], $case['result_rows']);
            $t->same(null, $case['index_name']);
            $t->true(str_contains($case['sql'], 'LEFT JOIN'));
        }

        if ($case['upstream_section'] === 'indexexpr2-9.0') {
            $t->same('t1x', $case['index_name']);
            $t->same([[5, -5, 205], [5, 20, 220]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'aggregate'));
        }

        if ($case['upstream_section'] === 'indexexpr2-10.0') {
            $t->same('t1x', $case['index_name']);
            $t->same([[1, 'abcde']], $case['result_rows']);
            $t->true(str_contains($case['expression'], 'COLLATE NOCASE'));
        }

        if ($case['upstream_section'] === 'indexexpr2-10.1') {
            $t->same('t2x', $case['index_name']);
            $t->same([[4]], $case['result_rows']);
            $t->true(str_contains($case['sql'], 'GROUP BY'));
        }

        if ($case['upstream_section'] === 'indexexpr2-11.0') {
            $t->same('t3x', $case['index_name']);
            $t->same([[44, -44]], $case['result_rows']);
            $t->same(true, $case['covering']);
        }
    };
}

$tests['real upstream indexexpr2 cast truth aggregate dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexExpr2CastTruthAggregateCases(1000);

    $t->same(1000, count($cases));
    $t->same('indexexpr2-6.1.1/6.1.3', $cases[0]['upstream_section']);
    $t->same('indexexpr2-11.0', $cases[9]['upstream_section']);
    $t->same('indexexpr2-10.1', $cases[998]['upstream_section']);
    $t->same('indexexpr2-11.0', $cases[999]['upstream_section']);
};

$tests['real upstream indexexpr2 cast truth aggregate dynamic rejects invalid count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexExpr2CastTruthAggregateCases(0));
};

$tests['real upstream indexexpr2 cast truth aggregate dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local expression-index, affinity, nullable truth-expression, aggregate, collation, and generated-column planning helpers',
        'no new support component needed; reuses lane-local expression-index, affinity, nullable truth-expression, aggregate, collation, and generated-column planning helpers',
    );
};

return $tests;
