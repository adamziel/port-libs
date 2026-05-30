<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionDynamicCases(1200) as $case) {
    $tests['real upstream index expression dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->true(in_array($case['source'], [
            'indexexpr1.test indexexpr1-110 and 120',
            'indexexpr1.test indexexpr1-130 and 230',
            'indexexpr1.test indexexpr1-141 and 241',
            'indexexpr1.test indexexpr1-150 and 250',
            'indexexpr1.test indexexpr1-160 and 260',
            'indexexpr1.test indexexpr1-170 and 171',
            'indexexpr2.test indexexpr2-3.4.5 and 3.4.6',
            'indexexpr2.test indexexpr2-4.110 through 4.130',
        ], true));
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true(str_starts_with($case['upstream_section'], 'indexexpr1-') || str_starts_with($case['upstream_section'], 'indexexpr2-'));
        $t->true($case['storage'] === 'rowid' || $case['storage'] === 'without-rowid');
        $t->true($case['index_name'] !== '');
        $t->true($case['expression'] !== '');
        $t->true($case['predicate'] !== '');
        $t->true($case['detail'] !== '');
        $t->true($case['collation'] === 'binary' || $case['collation'] === 'nocase');
        $t->true($case['order'] !== '');
        $t->true(array_values($case['result_rows']) === $case['result_rows']);
        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['source'] === 'indexexpr1.test indexexpr1-110 and 120') {
            $t->same('t1a1', $case['index_name']);
            $t->same('substr(a,1,12)', $case['expression']);
            $t->same([[1, 2, '|'], [1, 3, '|']], $case['result_rows']);
            $t->same(true, $case['uses_index']);
        }
        if ($case['source'] === 'indexexpr1.test indexexpr1-130 and 230') {
            $t->same('t1ba', $case['index_name']);
            $t->same([[2], [3]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'COVERING INDEX'));
        }
        if ($case['source'] === 'indexexpr1.test indexexpr1-141 and 241') {
            $t->same('t1abx', $case['index_name']);
            $t->same([[1], [2], [3]], $case['result_rows']);
            $t->true(str_contains($case['predicate'], '<='));
        }
        if ($case['source'] === 'indexexpr1.test indexexpr1-150 and 250') {
            $t->same('t1abx', $case['index_name']);
            $t->same([[2], [3], [5]], $case['result_rows']);
            $t->true(str_contains($case['predicate'], ' IN '));
        }
        if ($case['source'] === 'indexexpr1.test indexexpr1-160 and 260') {
            $t->same('t1a2', $case['index_name']);
            $t->same([[1, 1, 1]], $case['result_rows']);
            $t->true(str_contains($case['predicate'], 'd>=29'));
        }
        if ($case['source'] === 'indexexpr1.test indexexpr1-170 and 171') {
            $t->same('t1alen', $case['index_name']);
            $t->same('length(a)', $case['expression']);
            $t->same($case['order'] === 'length(a) DESC' ? [[52], [38], [29], [27], [25], [20]] : [[20], [25], [27], [29], [38], [52]], $case['result_rows']);
        }
        if ($case['source'] === 'indexexpr2.test indexexpr2-3.4.5 and 3.4.6') {
            $t->same('i4', $case['index_name']);
            $t->same('nocase', $case['collation']);
            $t->same([['.ABC1', 1], ['.abc2', 2], ['.ABC3', 3], ['.abc4', 4]], $case['result_rows']);
        }
        if ($case['source'] === 'indexexpr2.test indexexpr2-4.110 through 4.130') {
            $t->same('t1abc', $case['index_name']);
            $t->true($case['mutation_column'] === 'b' || $case['mutation_column'] === 'd');
            $t->same($case['mutation_column'] === 'b', $case['recomputes_index']);
            $t->same($case['mutation_column'] === 'b' ? 2 : 0, $case['expected_refcount']);
        }
    };
}

$tests['real upstream index expression dynamic corpus source files are explicit'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionDynamicCases(1200);

    $t->same(1200, count($cases));
    $t->same('indexexpr1.test indexexpr1-110 and 120', $cases[0]['source']);
    $t->same('indexexpr2.test indexexpr2-4.110 through 4.130', $cases[7]['source']);
    $t->same('indexexpr1-210', $cases[9]['upstream_section']);
    $t->same('without-rowid', $cases[9]['storage']);
    $t->same('indexexpr1.test', substr($cases[0]['source'], 0, 15));
    $t->same('indexexpr2.test', substr($cases[7]['source'], 0, 15));
};

$tests['real upstream index expression dynamic corpus rejects invalid count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionDynamicCases(0));
};

return $tests;
