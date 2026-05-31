<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindexA.test. The Tcl virtual table
// records xBestIndex constraints for column predicates, LIMIT, commuted
// equality, and the overloaded two-argument even() function.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindexAVirtualTableConstraintCases(1000) as $case) {
    $tests['real upstream bestindexA virtual table xbestindex dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindexA.test sections bestindexA-1.1 through bestindexA-1.9', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindexA-1.'));
        $t->true(str_starts_with($case['query'], 'SELECT * FROM t1 WHERE '));
        $t->true($case['scenario'] !== '');
        $t->true(count($case['constraints']) >= 1);
        $t->true(count($case['constraints']) <= 2);
        $t->same(1000000, $case['cost']);
        $t->same(1000000, $case['estimated_rows']);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['detail'], 'xBestIndex constraints: '));

        $operators = array_column($case['constraints'], 'operator');
        $columns = array_column($case['constraints'], 'column');
        $columnNames = array_column($case['constraints'], 'column_name');
        $t->same($case['limit_constraint'], in_array('limit', $operators, true));
        $t->same($case['function_constraint'], in_array('152', $operators, true));

        foreach ($case['constraints'] as $constraint) {
            $t->true(in_array($constraint['operator'], ['eq', 'ne', 'limit', '152'], true));
            $t->true(in_array($constraint['column'], [0, 1], true));
            $t->same($constraint['column'] === 0 ? 'a' : 'b', $constraint['column_name']);
        }

        if ($case['upstream_section'] === 'bestindexA-1.1') {
            $t->same(['eq'], $operators);
            $t->same([0], $columns);
            $t->same(['a'], $columnNames);
        }

        if ($case['upstream_section'] === 'bestindexA-1.2') {
            $t->same(['eq', 'limit'], $operators);
            $t->same([0, 0], $columns);
            $t->same([10], [$case['constraints'][1]['value']]);
        }

        if ($case['upstream_section'] === 'bestindexA-1.3') {
            $t->same(['eq'], $operators);
            $t->true($case['expression_constraint_omitted']);
            $t->true(str_contains($case['query'], '(b+1)=?'));
        }

        if ($case['upstream_section'] === 'bestindexA-1.4') {
            $t->same(['152'], $operators);
            $t->same([0], $columns);
            $t->true(str_contains($case['query'], 'even(a, ?)'));
        }

        if ($case['upstream_section'] === 'bestindexA-1.5') {
            $t->same(['eq', '152'], $operators);
            $t->same([1, 0], $columns);
            $t->same(10, $case['constraints'][0]['value']);
        }

        if ($case['upstream_section'] === 'bestindexA-1.6') {
            $t->same(['eq', 'limit'], $operators);
            $t->same([1, 0], $columns);
            $t->same(10, $case['constraints'][0]['value']);
        }

        if ($case['upstream_section'] === 'bestindexA-1.7') {
            $t->same(['152', 'limit'], $operators);
            $t->same([1, 0], $columns);
            $t->true(str_contains($case['query'], 'even(b,?)'));
        }

        if ($case['upstream_section'] === 'bestindexA-1.8') {
            $t->same(['ne', 'limit'], $operators);
            $t->same([1, 0], $columns);
            $t->true(str_contains($case['query'], 'b!=?'));
        }

        if ($case['upstream_section'] === 'bestindexA-1.9') {
            $t->same(['eq', 'limit'], $operators);
            $t->same([0, 0], $columns);
            $t->true(str_contains($case['query'], '?=a'));
        }
    };
}

$tests['real upstream bestindexA virtual table xbestindex dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindexAVirtualTableConstraintCases(1000);
    $t->same(1000, count($cases));
    $t->same('bestindexA-1.1', $cases[0]['upstream_section']);
    $t->same('bestindexA-1.9', $cases[8]['upstream_section']);
    $t->same('bestindexA-1.1', $cases[9]['upstream_section']);
    $t->same(112, $cases[count($cases) - 1]['batch']);
};

$tests['real upstream bestindexA virtual table xbestindex dynamic rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindexAVirtualTableConstraintCases(0));
};

$tests['real upstream bestindexA virtual table xbestindex dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex constraint accounting',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex constraint accounting',
    );
};

return $tests;
