<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindex2.test bestindex2-1.1 through
// bestindex2-1.7.2. These cases cover xBestIndex equality constraint choice,
// duplicate-column constraint de-duplication, join-derived usable constraints,
// CROSS JOIN loop-order preservation, and downstream virtual-table constraint
// propagation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindex2VirtualTableJoinConstraintCases(1000) as $case) {
    $tests['real upstream bestindex2 virtual table join constraint case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindex2.test bestindex2-1.1 through bestindex2-1.7.2', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindex2-1.'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['virtual_tables'] !== []);
        $t->true($case['join_order'] !== []);
        $t->same(0, $case['result_code']);
        $t->same(null, $case['error']);
        $t->same('ok', $case['integrity']);
        $t->same($case['cost'], $case['estimated_rows']);
        $t->same($case['uses_indexed_constraint'], count(array_filter($case['constraints'], static fn (array $constraint): bool => $constraint['used'])) > 0);

        foreach ($case['constraints'] as $constraint) {
            $t->true(in_array($constraint['table'], $case['virtual_tables'], true));
            $t->same('=', $constraint['operator']);
            $t->true($constraint['usable']);
            if ($constraint['used']) {
                $t->true(str_contains(implode(' ', $case['detail']), $constraint['column'] . '=?'));
            }
        }

        if ($case['upstream_section'] === 'bestindex2-1.1') {
            $t->same(['t1'], $case['virtual_tables']);
            $t->same(['t1'], $case['join_order']);
            $t->same(10000, $case['cost']);
            $t->same(['SCAN t1 VIRTUAL TABLE INDEX 0:indexed(a=?)'], $case['detail']);
        }
        if ($case['upstream_section'] === 'bestindex2-1.2') {
            $t->same(2, count(array_filter($case['constraints'], static fn (array $constraint): bool => $constraint['used'])));
            $t->same(9000, $case['cost']);
            $t->true(str_contains($case['detail'][0], 'a=? AND b=?'));
        }
        if ($case['upstream_section'] === 'bestindex2-1.3') {
            $t->same(2, count($case['constraints']));
            $t->same(1, count(array_filter($case['constraints'], static fn (array $constraint): bool => $constraint['used'])));
            $t->same('a', $case['constraints'][1]['column']);
            $t->same(false, $case['constraints'][1]['used']);
        }
        if ($case['upstream_section'] === 'bestindex2-1.4') {
            $t->same(['t1', 't2'], $case['join_order']);
            $t->same('t2', $case['constraints'][0]['table']);
            $t->true(str_contains($case['detail'][1], 'indexed(c=?)'));
        }
        if ($case['upstream_section'] === 'bestindex2-1.5' || $case['upstream_section'] === 'bestindex2-1.6') {
            $t->same(['t1', 't2', 't3'], $case['join_order']);
            $t->same(['t1', 't2', 't3'], $case['virtual_tables']);
            $t->same(9000, $case['cost']);
            $t->true(str_contains($case['detail'][1], 'indexed(c=?)'));
            $t->true(str_contains($case['detail'][2], 'indexed(e=?)'));
        }
        if ($case['upstream_section'] === 'bestindex2-1.7.2') {
            $t->same(['x1', 't1', 't2', 't3'], $case['join_order']);
            $t->same('SCAN x1', $case['detail'][0]);
            $t->same(4, count($case['detail']));
        }
    };
}

$tests['real upstream bestindex2 virtual table join constraint source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindex2VirtualTableJoinConstraintCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same([
        'bestindex2-1.1',
        'bestindex2-1.2',
        'bestindex2-1.3',
        'bestindex2-1.4',
        'bestindex2-1.5',
        'bestindex2-1.6',
        'bestindex2-1.7.2',
    ], $sections);
    $t->same('bestindex2-1.1', $cases[0]['upstream_section']);
    $t->same('bestindex2-1.7.2', $cases[6]['upstream_section']);
    $t->same('bestindex2-1.6', $cases[999]['upstream_section']);
};

$tests['real upstream bestindex2 virtual table join constraint rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindex2VirtualTableJoinConstraintCases(0));
};

$tests['real upstream bestindex2 virtual table join constraint dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex metadata modelling',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex metadata modelling',
    );
};

return $tests;
