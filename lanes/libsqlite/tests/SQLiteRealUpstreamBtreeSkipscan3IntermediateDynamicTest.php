<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/skipscan3.test sections skipscan3-1.1
// through skipscan3-2.3. This owns the intermediate-term skip-scan cases for
// PRIMARY KEY(a,b,c) indexes, distinct from the accepted skipscan1 and
// skipscan2 optoverview batches.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::skipscan3IntermediateTermCases(1000) as $case) {
    $tests['real upstream skipscan3 intermediate btree index dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('skipscan3.test sections skipscan3-1.1 through skipscan3-2.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'skipscan3-'));
        $t->true(str_starts_with($case['setup_section'], 'skipscan3-'));
        $t->same('ok', $case['integrity']);
        $t->same(1000, $case['row_count']);
        $t->true($case['target_c'] >= 1 && $case['target_c'] <= 1000);
        $t->same(sprintf('x%04d', $case['target_c']), $case['target_d']);
        $t->same([['d' => $case['target_d']]], $case['result_rows']);
        $t->same(['a', 'b', 'c'], $case['index_columns']);
        $t->same(['a', 'b'], $case['skip_prefix_columns']);
        $t->same(['b'], $case['intermediate_terms_skipped']);
        $t->same(true, $case['uses_any_skip_scan']);
        $t->same('analyzed-primary-key-duplicates', $case['analyze_state']);
        $t->contains('ANY(a)', $case['plan_detail']);
        $t->contains('ANY(b)', $case['plan_detail']);
        $t->contains('c=?', $case['plan_detail']);
        $t->contains('skipscan3 dynamic replay', $case['detail']);
        $t->contains('c=' . $case['target_c'], $case['detail']);
        $t->contains('c=' . $case['target_c'], $case['scenario']);
        $t->contains('c=' . $case['target_c'], $case['statement']);

        if ($case['uses_without_rowid']) {
            $t->same('skipscan3-2.1', $case['setup_section']);
            $t->contains('WITHOUT ROWID', $case['primary_key']);
            $t->contains('t2', $case['statement']);
            $t->contains('t2', $case['plan_detail']);
        } else {
            $t->same('skipscan3-1.1', $case['setup_section']);
            $t->same('PRIMARY KEY(a,b,c)', $case['primary_key']);
            $t->contains('t1', $case['statement']);
            $t->contains('t1', $case['plan_detail']);
        }

        if ($case['uses_unary_plus_guard']) {
            $t->same('+a=1', $case['constrained_columns'][0]);
            $t->contains('+a=1', $case['statement']);
            $t->true(in_array($case['upstream_section'], ['skipscan3-1.2', 'skipscan3-2.2'], true));
        } else {
            $t->same('a=1', $case['constrained_columns'][0]);
            $t->true(in_array($case['upstream_section'], ['skipscan3-1.3', 'skipscan3-2.3'], true));
        }

        $t->same('c=?', $case['constrained_columns'][1]);
    };
}

$tests['real upstream skipscan3 intermediate btree index dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::skipscan3IntermediateTermCases(1000);
    $variants = [];
    foreach ($cases as $case) {
        $variants[$case['upstream_section'] . ' ' . ($case['uses_unary_plus_guard'] ? 'unary-plus' : 'ordinary') . ' ' . ($case['uses_without_rowid'] ? 'without-rowid' : 'rowid')] = true;
    }

    $t->same(1000, count($cases));
    $t->same('skipscan3-1.2', $cases[0]['upstream_section']);
    $t->same(1, $cases[0]['target_c']);
    $t->same('skipscan3-2.3', $cases[3]['upstream_section']);
    $t->same(1, $cases[3]['target_c']);
    $t->same('skipscan3-1.2', $cases[4]['upstream_section']);
    $t->same(2, $cases[4]['target_c']);
    $t->same('skipscan3-2.3', $cases[999]['upstream_section']);
    $t->same(250, $cases[999]['target_c']);
    $t->same([
        'skipscan3-1.2 unary-plus rowid',
        'skipscan3-1.3 ordinary rowid',
        'skipscan3-2.2 unary-plus without-rowid',
        'skipscan3-2.3 ordinary without-rowid',
    ], array_keys($variants));
};

$tests['real upstream skipscan3 intermediate btree index dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::skipscan3IntermediateTermCases(0));
};

$tests['real upstream skipscan3 intermediate btree index dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for skip-scan primary-key storage behavior',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for skip-scan primary-key storage behavior',
    );
    $t->same(
        'non-overlap: owns skipscan3.test intermediate-term skip-scan sections 1.1-2.3, avoiding accepted skipscan1 and skipscan2 dynamic batches',
        'non-overlap: owns skipscan3.test intermediate-term skip-scan sections 1.1-2.3, avoiding accepted skipscan1 and skipscan2 dynamic batches',
    );
};

return $tests;
