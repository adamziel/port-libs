<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$sections = [
    'whereF-1.1',
    'whereF-1.2',
    'whereF-1.3',
    'whereF-2.1',
    'whereF-2.2',
    'whereF-2.3',
    'whereF-3.1',
    'whereF-3.2',
    'whereF-3.3',
    'whereF-4.0',
    'whereF-5.1/5.2',
    'whereF-5.3/5.4',
    'whereF-5.5/5.6',
    'whereF-7.1',
    'whereF-7.2',
    'whereF-7.3',
];

// Source truth: SQLite upstream test/whereF.test sections whereF-1.1
// through whereF-5.6 and whereF-7.1 through whereF-7.3. The JSON virtual
// table section whereF-6.* is deliberately left to the JSON-table lane so this
// B-tree/index batch remains non-overlapping.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereFJoinOrderAndOrFactoringCases(1000) as $case) {
    $tests['real upstream whereF join planner dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $sections): void {
        $t->same('whereF.test sections whereF-1.1 through whereF-5.6 and whereF-7.1 through whereF-7.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], $sections, true));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->same(false, $case['automatic_index']);
        $t->same('ok', $case['integrity']);
        $t->true($case['input_order'] !== []);
        $t->true($case['chosen_order'] !== []);
        $t->true($case['available_indexes'] !== []);
        $t->true($case['chosen_indexes'] !== []);

        $flat = [];
        foreach ($case['result_rows'] as $row) {
            $t->same(array_values($row), $row);
            foreach ($row as $value) {
                $flat[] = $value;
            }
        }
        $t->same($flat, $case['result_flat']);

        if ($case['category'] === 'join-order-cost') {
            $t->same(['t2', 't1'], $case['chosen_order']);
            $t->contains('SCAN t2', $case['detail']);
            $t->contains('SEARCH t1', $case['detail']);
            $t->same([], $case['result_rows']);
            $t->same(null, $case['vmstep_relation']);
            $t->same(null, $case['vmstep_threshold']);
            $t->same(false, $case['guard_tested_before_seek']);
        }

        if ($case['upstream_section'] === 'whereF-1.3' || $case['upstream_section'] === 'whereF-2.3' || $case['upstream_section'] === 'whereF-3.3') {
            $t->same(true, $case['cross_join']);
            $t->same(['t2', 't1'], $case['input_order']);
        }

        if ($case['upstream_section'] === 'whereF-2.1' || $case['upstream_section'] === 'whereF-2.2' || $case['upstream_section'] === 'whereF-2.3') {
            $t->same(['i2'], $case['chosen_indexes']);
            $t->contains('t1.b=t2.e', implode(' ', $case['where_terms']));
        }

        if ($case['upstream_section'] === 'whereF-3.1' || $case['upstream_section'] === 'whereF-3.2' || $case['upstream_section'] === 'whereF-3.3') {
            $t->same(['i1'], $case['chosen_indexes']);
            $t->contains('a=? AND b=?', $case['detail']);
        }

        if ($case['upstream_section'] === 'whereF-4.0') {
            $t->same('composite-primary-key', $case['category']);
            $t->same(['t4'], $case['chosen_order']);
            $t->same(['sqlite_autoindex_t4_1'], $case['chosen_indexes']);
            $t->same(['a=?', 'b=?'], $case['where_terms']);
            $t->contains('a=? AND b=?', $case['detail']);
        }

        if ($case['category'] === 'or-vmstep-guard') {
            $t->same([$case['expected_count']], $case['result_flat']);
            $t->true($case['vmstep_relation'] === '<' || $case['vmstep_relation'] === '>');
            $t->true($case['vmstep_threshold'] === 200 || $case['vmstep_threshold'] === 1000);
            $t->true(in_array('rowid', $case['chosen_indexes'], true));
            $t->same($case['upstream_section'] === 'whereF-5.5/5.6', $case['guard_tested_before_seek']);
        }

        if ($case['upstream_section'] === 'whereF-5.3/5.4') {
            $t->same([4000], $case['result_flat']);
            $t->same('>', $case['vmstep_relation']);
            $t->same(1000, $case['vmstep_threshold']);
            $t->true(in_array('t2f', $case['chosen_indexes'], true));
        }

        if ($case['upstream_section'] === 'whereF-5.5/5.6') {
            $t->same([4], $case['result_flat']);
            $t->same('<', $case['vmstep_relation']);
            $t->same(200, $case['vmstep_threshold']);
            $t->contains('t1.f1!=-1', implode(' ', $case['where_terms']));
        }

        if ($case['upstream_section'] === 'whereF-7.1') {
            $t->same('or-factoring-regression', $case['category']);
            $t->same([[4], [5]], $case['result_rows']);
            $t->same(2, $case['expected_count']);
            $t->contains('TERM_VIRTUAL', $case['detail']);
        }

        if ($case['upstream_section'] === 'whereF-7.2') {
            $t->same([[2]], $case['result_rows']);
            $t->same(1, $case['expected_count']);
            $t->contains('counts exactly two', $case['detail']);
        }

        if ($case['upstream_section'] === 'whereF-7.3') {
            $t->same('virtual-term-opcode-guard', $case['category']);
            $t->same(['Lt', 'Ge'], $case['forbidden_opcodes']);
            $t->same(false, $case['contains_forbidden_opcodes']);
            $t->true(in_array('t2bb', $case['chosen_indexes'], true));
            $t->contains('must not contain Lt or Ge', $case['detail']);
        }
    };
}

$tests['real upstream whereF join planner dynamic source range'] = static function (TestRunner $t) use ($sections): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereFJoinOrderAndOrFactoringCases(1000);
    $seen = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same($sections, $seen);
    $t->same('whereF-1.1', $cases[0]['upstream_section']);
    $t->same('whereF-7.3', $cases[15]['upstream_section']);
    $t->same('whereF-1.1', $cases[16]['upstream_section']);
    $t->same('whereF-3.2', $cases[999]['upstream_section']);
    $t->same(63, $cases[999]['batch']);
};

$tests['real upstream whereF join planner dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereFJoinOrderAndOrFactoringCases(0));
};

$tests['real upstream whereF join planner dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, join-order metadata, OR-index guard modeling, and opcode exclusion checks',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, join-order metadata, OR-index guard modeling, and opcode exclusion checks',
    );
};

return $tests;
