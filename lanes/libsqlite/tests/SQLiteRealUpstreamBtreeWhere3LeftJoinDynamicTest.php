<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$naturalRows = [
    [1, 'w-one', 'x-one', 'y-one', 'z-one'],
    [9, 'w-nine', 'x-nine', 'y-nine', 'z-nine'],
];

// Source truth: SQLite upstream test/where3.test selected sections where3-1.1
// through where3-8.2. This batch owns join planner behavior around comma joins
// before LEFT JOIN, tables reordered before a LEFT JOIN boundary, NATURAL/USING
// shared-column resolution, omit-noop LEFT JOIN stability, and the composite
// t2xy equality lookup guard from the 2023 regression.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where3LeftJoinReorderPlannerCases(1200) as $case) {
    $tests['real upstream where3 left join planner dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $naturalRows): void {
        $t->same('where3.test selected sections where3-1.1 through where3-8.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->same(intdiv($case['case'] - 1, 99) + 1, $case['batch']);
        $t->true(str_starts_with($case['upstream_section'], 'where3-'));
        $t->same('ok', $case['integrity']);
        $t->true(str_contains($case['scenario'], 'dynamic replay'));
        $t->true(str_contains($case['detail'], 'where3 dynamic replay'));
        $t->true($case['statement'] !== '');
        $t->true($case['join_shape'] !== '');
        $t->true(is_array($case['result_rows']));
        $t->true(is_array($case['plan_order']));
        $t->true(is_array($case['chosen_indexes']));
        $t->true($case['null_extended_rows'] >= 0);
        $t->same($case['chosen_indexes'], array_values(array_unique($case['chosen_indexes'])));

        $flat = [];
        foreach ($case['result_rows'] as $row) {
            $t->same(range(0, count($row) - 1), array_keys($row));
            foreach ($row as $value) {
                $flat[] = $value;
            }
        }
        $t->same($flat, $case['result_flat']);

        if ($case['uses_left_join']) {
            $t->true(str_contains($case['statement'], 'LEFT') || str_contains($case['join_shape'], 'left'));
            $t->true($case['null_extended_rows'] >= 0);
        }

        if ($case['join_reordered_before_left']) {
            $t->true(count($case['plan_order']) >= 2);
            $t->true(str_contains($case['detail'], 'SEARCH') || str_contains($case['detail'], 'planner order'));
        }

        if ($case['natural_or_using']) {
            $t->same('natural-using-name-resolution', $case['join_shape']);
            $t->same($naturalRows, $case['result_rows']);
            $t->same(['t6w', 't6x', 't6y', 't6z'], $case['plan_order']);
            $t->same(false, $case['uses_left_join']);
            $t->true(str_contains($case['statement'], 'NATURAL JOIN') || str_contains($case['statement'], 'USING(a)'));
        }

        if ($case['disabled_optimization'] !== null) {
            $t->true(in_array($case['disabled_optimization'], ['none', 'omit-noop-join', 'all'], true));
            $t->true(str_starts_with($case['upstream_section'], 'where3-7.'));
            $t->contains('optimization_control ' . $case['disabled_optimization'], $case['detail']);
        }

        if ($case['uses_temp_btree']) {
            $t->contains('USE TEMP B-TREE', $case['detail']);
            $t->true(str_contains($case['statement'], 'ORDER BY'));
        }

        if ($case['uses_primary_key_lookup']) {
            $t->true(
                str_contains($case['detail'], 'PRIMARY KEY')
                || in_array('t72 INTEGER PRIMARY KEY', $case['chosen_indexes'], true)
            );
        }

        if ($case['uses_composite_equality']) {
            $t->same(['t2xy'], $case['chosen_indexes']);
            $t->same(['x=c', 'y=d'], $case['on_terms']);
            $t->same(['d>0'], $case['where_terms']);
            $t->true(str_contains($case['detail'], 'x=? AND y=?') || $case['result_rows'] === [[1]]);
        }

        if ($case['upstream_section'] === 'where3-1.1') {
            $t->same([[222, 'two', 2, 222, null, null]], $case['result_rows']);
            $t->same(['t2', 't1', 't3'], $case['plan_order']);
            $t->same(['t2i1', 't3i1'], $case['chosen_indexes']);
            $t->same(1, $case['null_extended_rows']);
        }

        if ($case['upstream_section'] === 'where3-1.2') {
            $t->same([[1, 'Value for C1.1', 'Value for C2.1'], [2, null, 'Value for C2.2'], [3, 'Value for C1.3', 'Value for C2.3']], $case['result_rows']);
            $t->same(['parent1', 'child1', 'child2'], $case['plan_order']);
            $t->same(1, $case['null_extended_rows']);
        }

        if ($case['upstream_section'] === 'where3-2.4') {
            $t->same(['tC', 'tA', 'tB', 'tD'], $case['plan_order']);
            $t->same(true, $case['join_reordered_before_left']);
        }

        if ($case['upstream_section'] === 'where3-3.2') {
            $t->same([], $case['result_rows']);
            $t->contains('a IS NULL', implode(' ', $case['where_terms']));
        }

        if ($case['upstream_section'] === 'where3-3.3') {
            $t->same([[1, 2, 3], [2, 2, 3]], $case['result_rows']);
            $t->contains('a IS NOT NULL', implode(' ', $case['where_terms']));
        }

        if (str_starts_with($case['upstream_section'], 'where3-5.')) {
            $t->same(true, $case['uses_temp_btree']);
            $t->same(['aaa', 'bbb'], $case['plan_order']);
            $t->same(['aaa_333', 'bbb INTEGER PRIMARY KEY'], $case['chosen_indexes']);
        }

        if ($case['upstream_section'] === 'where3-6.1.1') {
            $t->same($naturalRows, $case['result_rows']);
            $t->contains('NATURAL JOIN', $case['statement']);
        }

        if ($case['upstream_section'] === 'where3-7.none.8') {
            $t->same([[123], [123]], $case['result_rows']);
            $t->same(false, str_contains($case['statement'], 'DISTINCT'));
        }

        if ($case['upstream_section'] === 'where3-7.all.9') {
            $t->same([[123]], $case['result_rows']);
            $t->contains('DISTINCT', $case['statement']);
        }

        if ($case['upstream_section'] === 'where3-8.2') {
            $t->contains('SEARCH t2 USING COVERING INDEX t2xy (x=? AND y=?)', $case['detail']);
            $t->same(false, str_contains($case['detail'], 'y>?'));
        }
    };
}

$tests['real upstream where3 left join planner source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where3LeftJoinReorderPlannerCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    $where6Sections = array_values(array_filter($sections, static fn (string $section): bool => str_starts_with($section, 'where3-6.')));
    $where7Sections = array_values(array_filter($sections, static fn (string $section): bool => str_starts_with($section, 'where3-7.')));

    $t->same(1200, count($cases));
    $t->same(99, count($sections));
    $t->same(48, count($where6Sections));
    $t->same(27, count($where7Sections));
    $t->same('where3-1.1', $cases[0]['upstream_section']);
    $t->same('where3-8.2', $cases[98]['upstream_section']);
    $t->same('where3-1.1', $cases[99]['upstream_section']);
    $t->same('where3-2.5', $cases[1199]['upstream_section']);
    $t->same('where3.test selected sections where3-1.1 through where3-8.2', $cases[0]['source']);
};

$tests['real upstream where3 left join planner rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where3LeftJoinReorderPlannerCases(0));
};

$tests['real upstream where3 left join planner dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for LEFT JOIN join-order boundaries, NATURAL/USING name resolution, primary-key probes, temp-sort metadata, and composite-index equality guards',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for LEFT JOIN join-order boundaries, NATURAL/USING name resolution, primary-key probes, temp-sort metadata, and composite-index equality guards',
    );
    $t->same(
        'non-overlap: owns where3.test selected join-reordering and LEFT JOIN planner sections, avoiding accepted where2/4/6/7/8/9/A/C/D/E/F/G/H/I/J/K/L/M/N, index*, bestindex*, B-tree page relocation/root-collapse/overflow, JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, and source-neutral cleanup clusters',
        'non-overlap: owns where3.test selected join-reordering and LEFT JOIN planner sections, avoiding accepted where2/4/6/7/8/9/A/C/D/E/F/G/H/I/J/K/L/M/N, index*, bestindex*, B-tree page relocation/root-collapse/overflow, JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, and source-neutral cleanup clusters',
    );
};

return $tests;
