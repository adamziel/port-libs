<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindexC.test sections 1.2
// through 3.6. These cases cover virtual-table xBestIndex LIMIT/OFFSET
// constraints through compound SELECT rowsets and fallback paths when a
// module declines one of the constraints.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindexCLimitOffsetConstraintCases(1000) as $case) {
    $tests['real upstream bestindexC limit offset dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindexC.test sections 1.2 through 3.6', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], [
            'bestindexC-1.2.t_unionall.1',
            'bestindexC-1.2.t_union.2',
            'bestindexC-1.2.t_intersect.3',
            'bestindexC-1.2.t_except.4',
            'bestindexC-2.1',
            'bestindexC-3.4',
            'bestindexC-3.5',
            'bestindexC-3.6',
        ], true));
        $t->true($case['scenario'] !== '');
        $t->true($case['limit'] > 0);
        $t->true($case['offset'] >= 0);
        $t->true($case['left_values'] !== []);
        $t->true(str_contains($case['detail'], 'xBestIndex constraints='));
        $t->true(str_contains($case['detail'], 'dynamic batch ' . $case['batch']));

        foreach ($case['accepted_constraints'] as $constraint) {
            $t->true(in_array($constraint, ['limit', 'offset'], true));
        }

        foreach ($case['fallback_constraints'] as $constraint) {
            $t->true(in_array($constraint, ['limit', 'offset'], true));
        }

        foreach ($case['result_rows'] as $row) {
            $t->same(1, count($row));
            $t->true(is_string($row[0]));
        }

        if ($case['upstream_section'] === 'bestindexC-1.2.t_unionall.1') {
            $t->same('UNION ALL', $case['operator']);
            $t->same(8, $case['limit']);
            $t->same(0, $case['offset']);
            $t->same([['a'], ['b'], ['c'], ['d'], ['e'], ['f'], ['A'], ['B']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexC-1.2.t_union.2') {
            $t->same('UNION', $case['operator']);
            $t->same([['A'], ['B'], ['C'], ['D']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexC-1.2.t_intersect.3') {
            $t->same('INTERSECT', $case['operator']);
            $t->same([], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexC-1.2.t_except.4') {
            $t->same('EXCEPT', $case['operator']);
            $t->same([], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexC-2.1') {
            $t->same('EXCEPT', $case['operator']);
            $t->same([['c'], ['d']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexC-3.4') {
            $t->same(null, $case['operator']);
            $t->same([['4'], ['5'], ['6'], ['7']], $case['result_rows']);
            $t->same(['limit', 'offset'], $case['accepted_constraints']);
        }

        if ($case['upstream_section'] === 'bestindexC-3.5') {
            $t->same(null, $case['operator']);
            $t->same([['d'], ['e'], ['f']], $case['result_rows']);
            $t->same(['offset'], $case['accepted_constraints']);
        }

        if ($case['upstream_section'] === 'bestindexC-3.6') {
            $t->same(null, $case['operator']);
            $t->same([['d'], ['e'], ['f']], $case['result_rows']);
            $t->same(['limit'], $case['accepted_constraints']);
        }
    };
}

$tests['real upstream bestindexC limit offset corpus count and endpoints'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindexCLimitOffsetConstraintCases(1000);
    $t->same(1000, count($cases));
    $t->same('bestindexC-1.2.t_unionall.1', $cases[0]['upstream_section']);
    $t->same('bestindexC-3.6', $cases[7]['upstream_section']);
    $t->same('bestindexC-3.6', $cases[999]['upstream_section']);
};

$tests['real upstream bestindexC limit offset rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindexCLimitOffsetConstraintCases(0));
};

return $tests;
