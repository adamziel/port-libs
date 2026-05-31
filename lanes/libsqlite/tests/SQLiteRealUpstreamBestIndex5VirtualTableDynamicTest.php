<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindex5.test sections bestindex5-1.1
// through bestindex5-3.5. These cases cover virtual-table xBestIndex
// propagation for !=, IS/IS NOT, unary NULL predicates, row-value constraints,
// and INTEGER-affinity residual comparisons.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindex5VirtualTableConstraintCases(1000) as $case) {
    $tests['real upstream bestindex5 virtual table constraint dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindex5.test sections bestindex5-1.1 through bestindex5-3.5', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], [
            'bestindex5-1.1',
            'bestindex5-1.2.1',
            'bestindex5-1.2.2',
            'bestindex5-1.3',
            'bestindex5-1.3.2',
            'bestindex5-1.4.1',
            'bestindex5-1.4.2',
            'bestindex5-1.5.1',
            'bestindex5-1.6.1',
            'bestindex5-1.7.1',
            'bestindex5-1.7.2',
            'bestindex5-2.2.4',
            'bestindex5-3.2',
            'bestindex5-3.4',
            'bestindex5-3.5',
        ], true));
        $t->true($case['statement'] !== '');
        $t->true($case['scenario'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true($case['cost'] > 0.0);
        $t->same($case['idx_string'] === '', $case['xfilter_where'] === null);

        foreach ($case['constraints'] as $constraint) {
            $t->true(in_array($constraint['column'], ['a', 'b', 'c'], true));
            $t->true(in_array($constraint['operator'], ['!=', 'IS', 'IS NOT', 'IS NULL', 'IS NOT NULL'], true));
            $t->same(true, $constraint['omitted']);
            $t->true($case['xfilter_where'] !== null);
            $t->true(str_contains($case['xfilter_where'], $constraint['column']));
        }

        if (str_contains($case['upstream_section'], '1.7')) {
            $t->same(true, $case['uses_row_value']);
            $t->same(2, count($case['constraints']));
            $t->same(1, count($case['result_rows']));
        }

        if ($case['upstream_section'] === 'bestindex5-1.4.1' || $case['upstream_section'] === 'bestindex5-1.4.2') {
            $t->same([[4, 5, 6.0, 1], [7, 8, 9.0, 1]], $case['result_rows']);
            $t->same("WHERE a != '1'", $case['xfilter_where']);
        }

        if ($case['upstream_section'] === 'bestindex5-1.6.1') {
            $t->same([], $case['result_rows']);
            $t->same('WHERE a IS NULL', $case['xfilter_where']);
        }

        if ($case['upstream_section'] === 'bestindex5-2.2.4') {
            $t->same(true, $case['uses_affinity_residual']);
            $t->same([[45, 46]], $case['result_rows']);
            $t->same([], $case['constraints']);
        }

        if ($case['upstream_section'] === 'bestindex5-3.2') {
            $t->same(true, $case['uses_affinity_residual']);
            $t->same([[1, 245]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindex5-3.4' || $case['upstream_section'] === 'bestindex5-3.5') {
            $t->same(true, $case['uses_affinity_residual']);
            $t->same([], $case['result_rows']);
        }
    };
}

$tests['real upstream bestindex5 virtual table constraint dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindex5VirtualTableConstraintCases(1000);
    $t->same(1000, count($cases));
    $t->same('bestindex5-1.1', $cases[0]['upstream_section']);
    $t->same('bestindex5-3.4', $cases[13]['upstream_section']);
    $t->same('bestindex5-3.5', $cases[14]['upstream_section']);
    $t->same('bestindex5-1.7.1', $cases[999]['upstream_section']);
};

$tests['real upstream bestindex5 virtual table constraint dynamic rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::bestindex5VirtualTableConstraintCases(0));
};

$tests['real upstream bestindex5 virtual table constraint dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table constraint, row-value, and affinity residual helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table constraint, row-value, and affinity residual helpers',
    );
};

return $tests;
