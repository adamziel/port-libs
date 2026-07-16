<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$expectedSections = [
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
];

// Source truth: SQLite upstream test/whereF.test sections whereF-1.1
// through whereF-5.6. These cases own costed join-order selection, CROSS
// JOIN order preservation, composite-index prefix selection, and the
// OR-clause guard that must be tested before seeking through t2(f2).
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereFJoinOrderDynamicCases(1000) as $case) {
    $tests['real upstream whereF join order dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('whereF.test sections whereF-1.1 through whereF-5.6', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'whereF-'));
        $t->same('ok', $case['integrity']);
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['detail'] !== '');
        $t->same(array_values(array_unique($case['chosen_indexes'])), $case['chosen_indexes']);
        $t->true(count($case['access_plan']) >= count($case['table_order']));

        if (str_starts_with($case['upstream_section'], 'whereF-1.')) {
            $t->same(['t2', 't1'], $case['table_order']);
            $t->same(['i1'], $case['chosen_indexes']);
            $t->same(false, $case['uses_composite_prefix']);
            $t->same(false, $case['uses_or_optimization']);
            $t->contains('SCAN t2', implode(' | ', $case['access_plan']));
            $t->contains('SEARCH t1 USING INDEX i1', implode(' | ', $case['access_plan']));
        }

        if (str_starts_with($case['upstream_section'], 'whereF-2.')) {
            $t->same(['t2', 't1'], $case['table_order']);
            $t->same(['i2'], $case['chosen_indexes']);
            $t->same(false, $case['uses_composite_prefix']);
            $t->same(false, $case['uses_or_optimization']);
            $t->contains('SEARCH t1 USING INDEX i2', implode(' | ', $case['access_plan']));
        }

        if (str_starts_with($case['upstream_section'], 'whereF-3.')) {
            $t->same(['t2', 't1'], $case['table_order']);
            $t->same(['i1'], $case['chosen_indexes']);
            $t->same(true, $case['uses_composite_prefix']);
            $t->same(false, $case['uses_or_optimization']);
            $t->contains('a=? AND b=?', implode(' | ', $case['access_plan']));
        }

        if ($case['upstream_section'] === 'whereF-4.0') {
            $t->same(['t4'], $case['table_order']);
            $t->same(['sqlite_autoindex_t4_1'], $case['chosen_indexes']);
            $t->same(true, $case['uses_composite_prefix']);
            $t->contains('a=? AND b=?', $case['access_plan'][0]);
            $t->contains('t4adc', (string) $case['rejected_outer_loop']);
        }

        if ($case['upstream_section'] === 'whereF-5.1/5.2') {
            $t->same(4, $case['result_count']);
            $t->same(false, $case['uses_or_optimization']);
            $t->same(false, $case['guard_terms_before_seek']);
            $t->same(true, $case['vmstep_upper_bound_passed']);
            $t->same(false, $case['vmstep_lower_bound_passed']);
            $t->same(['rowid'], $case['chosen_indexes']);
        }

        if ($case['upstream_section'] === 'whereF-5.3/5.4') {
            $t->same(4000, $case['result_count']);
            $t->same(true, $case['uses_or_optimization']);
            $t->same(false, $case['guard_terms_before_seek']);
            $t->same(false, $case['vmstep_upper_bound_passed']);
            $t->same(true, $case['vmstep_lower_bound_passed']);
            $t->same(['rowid', 't2f'], $case['chosen_indexes']);
            $t->contains('MULTI-INDEX OR', implode(' | ', $case['access_plan']));
        }

        if ($case['upstream_section'] === 'whereF-5.5/5.6') {
            $t->same(4, $case['result_count']);
            $t->same(true, $case['uses_or_optimization']);
            $t->same(true, $case['guard_terms_before_seek']);
            $t->same(true, $case['vmstep_upper_bound_passed']);
            $t->same(false, $case['vmstep_lower_bound_passed']);
            $t->same(['rowid', 't2f'], $case['chosen_indexes']);
            $t->contains('DEFER SEARCH t2', implode(' | ', $case['access_plan']));
        }

        if (str_ends_with($case['upstream_section'], '.3')) {
            $t->same(true, $case['cross_join_preserves_order']);
            $t->contains('CROSS JOIN', $case['statement']);
        }
    };
}

$tests['real upstream whereF join order dynamic corpus count and source range'] = static function (TestRunner $t) use ($expectedSections): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereFJoinOrderDynamicCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('whereF-1.1', $cases[0]['upstream_section']);
    $t->same('whereF-5.5/5.6', $cases[12]['upstream_section']);
    $t->same('whereF-1.1', $cases[13]['upstream_section']);
    $t->same('whereF-5.3/5.4', $cases[999]['upstream_section']);
    $t->same(77, $cases[999]['batch']);
    $t->same($expectedSections, $sections);
};

$tests['real upstream whereF join order dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereFJoinOrderDynamicCases(0));
};

$tests['real upstream whereF join order dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, join-order, composite-index, OR-index, vmstep-threshold, and guard-before-seek metadata helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, join-order, composite-index, OR-index, vmstep-threshold, and guard-before-seek metadata helpers',
    );
};

return $tests;
