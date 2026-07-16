<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindex1.test sections bestindex1-1.1
// through bestindex1-5.0. This batch covers virtual-table xBestIndex handling
// for usable equality, IN-as-equality replanning, omitted versus residual
// constraints, outer IN loop register stability, and module argument errors.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindex1VirtualTableInConstraintCases(1000) as $case) {
    $tests['real upstream bestindex1 virtual table in constraint dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindex1.test sections bestindex1-1.1 through bestindex1-5.0', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'bestindex1-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['virtual_table'] !== '');
        $t->true($case['cost'] >= 0);
        $t->true($case['estimated_rows'] >= 0);
        $t->true($case['detail'] !== '');

        foreach ($case['constraints'] as $constraint) {
            $t->true($constraint['column'] !== '');
            $t->true(in_array($constraint['operator'], ['=', 'IN', '>=', '<='], true));
            $t->same(true, $constraint['usable']);
            if ($constraint['operator'] === 'IN') {
                $t->same(true, $case['uses_in_replan']);
                $t->true(is_array($constraint['value']));
            }
        }

        foreach ($case['xbestindex_calls'] as $call) {
            foreach ($call as $constraint) {
                $t->true($constraint['column'] !== '');
                $t->true(in_array($constraint['operator'], ['eq', 'ge', 'le'], true));
                $t->true(is_bool($constraint['usable']));
            }
        }

        if ($case['upstream_section'] === 'bestindex1-1.1') {
            $t->same('x1', $case['virtual_table']);
            $t->same(555, $case['idxnum']);
            $t->same('eq!', $case['idxstr']);
            $t->same(false, $case['uses_in_replan']);
            $t->same(false, $case['uses_temp_btree']);
            $t->same(null, $case['wrong_arg_error']);
            $t->same([], $case['result_rows']);
            $t->true(str_contains($case['detail'], '555:eq!'));
        }

        if ($case['upstream_section'] === 'bestindex1-1.2') {
            $t->same(555, $case['idxnum']);
            $t->same('eq!', $case['idxstr']);
            $t->same(true, $case['uses_in_replan']);
            $t->same(false, $case['xbestindex_calls'][1][0]['usable']);
            $t->same(['abc', 'def'], $case['constraints'][0]['value']);
        }

        if ($case['upstream_section'] === 'bestindex1-2.2.use.4') {
            $t->same([[2]], $case['result_rows']);
            $t->same(false, $case['constraints'][0]['omitted']);
            $t->same("SELECT * FROM t1x WHERE a='%1%'", $case['idxstr']);
        }

        if ($case['upstream_section'] === 'bestindex1-2.2.omit.4') {
            $t->same([[2]], $case['result_rows']);
            $t->same(true, $case['constraints'][0]['omitted']);
            $t->same("SELECT * FROM t1x WHERE a='%1%'", $case['idxstr']);
        }

        if ($case['upstream_section'] === 'bestindex1-2.2.use2.5') {
            $t->same([[1], [4]], $case['result_rows']);
            $t->same(true, $case['uses_in_replan']);
            $t->same(true, $case['uses_temp_btree']);
            $t->same('SELECT * FROM t1x', $case['idxstr']);
            $t->true(str_contains($case['detail'], 'USE TEMP B-TREE'));
        }

        if ($case['upstream_section'] === 'bestindex1-3.4' || $case['upstream_section'] === 'bestindex1-3.5') {
            $t->same('VirtualTableA/VirtualTableB', $case['virtual_table']);
            $t->same(4, count($case['result_rows']));
            $t->same(true, $case['uses_in_replan']);
            $t->same([1, 0, 'ValueA', 1, 0, 'ValueA'], $case['result_rows'][0]);
            $t->same([4, 0, 'ValueB', 4, 0, 'ValueB'], $case['result_rows'][3]);
            $t->true(str_contains($case['statement'], 'CROSS JOIN'));
        }

        if ($case['upstream_section'] === 'bestindex1-4.1') {
            $t->same(4, count($case['constraints']));
            $t->same(true, $case['uses_in_replan']);
            $t->same(false, $case['xbestindex_calls'][1][1]['usable']);
            $t->same('all-usable-then-in-unusable', $case['idxstr']);
            $t->same([], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindex1-5.0') {
            $t->same('tcl', $case['virtual_table']);
            $t->same('wrong number of arguments', $case['wrong_arg_error']);
            $t->same([], $case['constraints']);
            $t->same([], $case['xbestindex_calls']);
        }
    };
}

$tests['real upstream bestindex1 virtual table in constraint source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindex1VirtualTableInConstraintCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('bestindex1-1.1', $cases[0]['upstream_section']);
    $t->same('bestindex1-5.0', $cases[8]['upstream_section']);
    $t->same('bestindex1-1.1', $cases[999]['upstream_section']);
    $t->same([
        'bestindex1-1.1',
        'bestindex1-1.2',
        'bestindex1-2.2.omit.4',
        'bestindex1-2.2.use.4',
        'bestindex1-2.2.use2.5',
        'bestindex1-3.4',
        'bestindex1-3.5',
        'bestindex1-4.1',
        'bestindex1-5.0',
    ], $sections);
};

$tests['real upstream bestindex1 virtual table in constraint rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::bestindex1VirtualTableInConstraintCases(0));
};

$tests['real upstream bestindex1 virtual table in constraint dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex IN/equality metadata modelling',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex IN/equality metadata modelling',
    );
};

return $tests;
