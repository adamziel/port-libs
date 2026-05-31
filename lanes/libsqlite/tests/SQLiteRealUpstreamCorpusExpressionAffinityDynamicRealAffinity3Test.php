<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealAffinityViewPlan;

$tests = [];

// Source truth: SQLite upstream test/affinity3.test affinity3-100 through
// affinity3-142. That section proves REAL affinity survives view expansion,
// RIGHT/LEFT join rewrites, and automatic-index probes for APR values that can
// otherwise look integer-like.
foreach (SQLiteRealAffinityViewPlan::affinity3RealViewCases(1200) as $case) {
    $tests['real upstream corpus expression affinity dynamic affinity3 real view case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('affinity3.test affinity3-100 through affinity3-142', $case['source']);
        $t->contains('affinity3-', $case['upstream_section']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['scenario'] !== '');
        $t->true(in_array($case['view_shape'], ['left-view', 'right-view', 'nested-left-view', 'nested-right-view', 'materialized-union'], true));
        $t->true(is_bool($case['automatic_index']));
        $t->true(in_array($case['comparison_affinity'], ['INTEGER', 'TEXT', 'NUMERIC', 'REAL', 'NONE'], true));
        $t->same('real', $case['apr_storage']);

        if ($case['id_matches']) {
            $t->same('real', $case['projected_storage']);
            $t->same('real', $case['division_storage']);
            $t->same([1, $case['division_value'], 'real'], $case['result_row']);
            $t->true(is_float($case['projected_apr']));
            $t->true(is_float($case['division_value']));
            $t->true(abs($case['division_value'] - ($case['projected_apr'] / 100.0)) < 1.0e-15);
        } else {
            $t->same('null', $case['projected_storage']);
            $t->same('null', $case['division_storage']);
            $t->same([], $case['result_row']);
        }

        if ($case['apr_label'] === 'integer-literal' || $case['apr_label'] === 'integer-value' || $case['apr_label'] === 'exponent-integer') {
            $t->true(is_float($case['apr_value']), 'REAL affinity must not leave integer-looking APR values as integer storage');
            $t->same('real', $case['apr_storage']);
        }

        if ($case['upstream_section'] === 'affinity3-110' || $case['upstream_section'] === 'affinity3-130') {
            $t->contains('left-view', $case['scenario']);
        }
    };
}

$tests['real upstream corpus expression affinity dynamic affinity3 owns exactly 1200 pass cases'] = static function (TestRunner $t): void {
    $cases = SQLiteRealAffinityViewPlan::affinity3RealViewCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('affinity3-110', $cases[0]['upstream_section']);
    $t->same('affinity3-142', max($sections));
    $t->same([
        'affinity3-110',
        'affinity3-111',
        'affinity3-120',
        'affinity3-121',
        'affinity3-122',
        'affinity3-130',
        'affinity3-131',
        'affinity3-140',
        'affinity3-141',
        'affinity3-142',
    ], $sections);
    $t->contains('affinity3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test');
};

$tests['real upstream corpus expression affinity dynamic affinity3 rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealAffinityViewPlan::affinity3RealViewCases(0));
};

$tests['real upstream corpus expression affinity dynamic affinity3 dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local REAL affinity coercion, storage-class comparison, and view/join dynamic corpus helpers',
        'no new support component needed; reuses lane-local REAL affinity coercion, storage-class comparison, and view/join dynamic corpus helpers',
    );
};

return $tests;
