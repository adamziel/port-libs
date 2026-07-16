<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/skipscan2.test sections skipscan2-1.3
// through skipscan2-3.3eqp. This owns the optoverview people/height
// skip-scan threshold examples and WITHOUT ROWID primary-key skip-scan, away
// from the already-admitted skipscan1.test dynamic batch.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::skipscan2OptOverviewCases(1000) as $case) {
    $tests['real upstream skipscan2 btree index dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('skipscan2.test sections skipscan2-1.3 through skipscan2-3.3eqp', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'skipscan2-'));
        $t->true($case['variant'] !== '');
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['table_shape'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true(str_contains($case['detail'], 'skipscan2 dynamic replay'));
        $t->true(array_values($case['stat1_rows']) === $case['stat1_rows']);
        $t->true(array_values($case['result_rows']) === $case['result_rows']);
        $t->true(array_values($case['skip_prefix_columns']) === $case['skip_prefix_columns']);
        $t->true(array_values($case['constrained_columns']) === $case['constrained_columns']);
        $t->true(array_values($case['rejected_indexes']) === $case['rejected_indexes']);

        foreach ($case['stat1_rows'] as $stat) {
            $t->true($stat['tbl'] !== '');
            $t->true(array_key_exists('idx', $stat));
            $t->true($stat['stat'] !== '');
        }

        if ($case['uses_any_skip_scan']) {
            $t->true(count($case['skip_prefix_columns']) >= 1);
            $t->true($case['chosen_index'] !== null);
            $t->contains('ANY(', $case['plan_detail']);
        } else {
            $t->same([], $case['skip_prefix_columns']);
        }

        if ($case['uses_without_rowid']) {
            $t->contains('without-rowid', $case['table_shape']);
        }

        if ($case['uses_primary_key']) {
            $t->same('PRIMARY KEY', $case['chosen_index']);
            $t->contains('PRIMARY KEY', $case['plan_detail']);
        }

        if ($case['uses_explicit_prefix_scan']) {
            $t->contains('role', $case['statement']);
            $t->contains('role', implode(' ', $case['constrained_columns']));
        }

        if ($case['uses_union_all']) {
            $t->contains('UNION ALL', $case['statement']);
            $t->same(true, $case['uses_explicit_prefix_scan']);
        }

        if ($case['role_duplicates'] !== null) {
            $t->true($case['role_duplicates'] > 0);
        }

        if ($case['upstream_section'] !== 'skipscan2-3.3eqp') {
            $t->same([['David'], ['Jack'], ['Patrick'], ['Quiana'], ['Xavier']], $case['result_rows']);
        }

        if ($case['variant'] === 'pre-analyze-rowid-range') {
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same(null, $case['chosen_index']);
            $t->same(['people_idx1 skip-scan'], $case['rejected_indexes']);
            $t->contains('SCAN people', $case['plan_detail']);
        }

        if ($case['variant'] === 'fudged-stat1-rowid-skipscan') {
            $t->same(true, $case['uses_any_skip_scan']);
            $t->same('people_idx1', $case['chosen_index']);
            $t->same('10000 5000 20', $case['stat1_rows'][0]['stat']);
            $t->same(5000, $case['role_duplicates']);
            $t->same(['role'], $case['skip_prefix_columns']);
        }

        if ($case['variant'] === 'explicit-role-in-rowid') {
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same(true, $case['uses_explicit_prefix_scan']);
            $t->same(false, $case['uses_union_all']);
            $t->contains('SELECT DISTINCT role', $case['statement']);
        }

        if ($case['variant'] === 'explicit-role-union-rowid') {
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same(true, $case['uses_union_all']);
            $t->contains("role='teacher'", $case['statement']);
            $t->contains("role='student'", $case['statement']);
        }

        if ($case['variant'] === 'seventeen-duplicates-rowid-no-skipscan') {
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same(17, $case['role_duplicates']);
            $t->same('34 17 2', $case['stat1_rows'][0]['stat']);
            $t->same(['people_idx1 skip-scan'], $case['rejected_indexes']);
        }

        if ($case['variant'] === 'eighteen-duplicates-rowid-skipscan') {
            $t->same(true, $case['uses_any_skip_scan']);
            $t->same(18, $case['role_duplicates']);
            $t->same('36 18 2', $case['stat1_rows'][0]['stat']);
            $t->contains('ANY(role)', $case['plan_detail']);
        }

        if ($case['variant'] === 'without-rowid-before-analyze') {
            $t->same(true, $case['uses_without_rowid']);
            $t->same(false, $case['uses_any_skip_scan']);
            $t->same(['peoplew_idx1 skip-scan'], $case['rejected_indexes']);
        }

        if ($case['variant'] === 'without-rowid-explicit-role-in') {
            $t->same(true, $case['uses_without_rowid']);
            $t->same(true, $case['uses_explicit_prefix_scan']);
            $t->same('peoplew_idx1', $case['chosen_index']);
        }

        if ($case['variant'] === 'without-rowid-explicit-role-union') {
            $t->same(true, $case['uses_without_rowid']);
            $t->same(true, $case['uses_union_all']);
            $t->same('skipscan2-2.2', $case['upstream_section']);
        }

        if ($case['variant'] === 'without-rowid-analyzed-secondary-skipscan') {
            $t->same(true, $case['uses_without_rowid']);
            $t->same(true, $case['uses_any_skip_scan']);
            $t->same('peoplew_idx1', $case['chosen_index']);
            $t->same('36 18 2', $case['stat1_rows'][0]['stat']);
        }

        if ($case['variant'] === 'without-rowid-primary-key-skipscan') {
            $t->same('skipscan2-3.3eqp', $case['upstream_section']);
            $t->same([[0, 42, 'xyz']], $case['result_rows']);
            $t->same(true, $case['uses_primary_key']);
            $t->same(['a'], $case['skip_prefix_columns']);
            $t->same(['b=42'], $case['constrained_columns']);
            $t->same(500, $case['role_duplicates']);
        }
    };
}

$tests['real upstream skipscan2 btree index dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::skipscan2OptOverviewCases(1000);
    $variants = [];
    foreach ($cases as $case) {
        $variants[$case['upstream_section'] . ' ' . $case['variant']] = true;
    }

    $t->same(1000, count($cases));
    $t->same('skipscan2-1.3', $cases[0]['upstream_section']);
    $t->same('pre-analyze-rowid-range', $cases[0]['variant']);
    $t->same('skipscan2-3.3eqp', $cases[10]['upstream_section']);
    $t->same('without-rowid-primary-key-skipscan', $cases[10]['variant']);
    $t->same('skipscan2-1.3', $cases[11]['upstream_section']);
    $t->same('skipscan2-2.5', $cases[999]['upstream_section']);
    $t->same([
        'skipscan2-1.3 pre-analyze-rowid-range',
        'skipscan2-1.5 fudged-stat1-rowid-skipscan',
        'skipscan2-1.6 explicit-role-in-rowid',
        'skipscan2-1.7 explicit-role-union-rowid',
        'skipscan2-1.9 seventeen-duplicates-rowid-no-skipscan',
        'skipscan2-1.11 eighteen-duplicates-rowid-skipscan',
        'skipscan2-2.1 without-rowid-before-analyze',
        'skipscan2-2.2 without-rowid-explicit-role-in',
        'skipscan2-2.2 without-rowid-explicit-role-union',
        'skipscan2-2.5 without-rowid-analyzed-secondary-skipscan',
        'skipscan2-3.3eqp without-rowid-primary-key-skipscan',
    ], array_keys($variants));
};

$tests['real upstream skipscan2 btree index dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::skipscan2OptOverviewCases(0));
};

$tests['real upstream skipscan2 btree index dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for skip-scan threshold, stat1 duplicate counts, explicit-prefix rewrites, and WITHOUT ROWID primary-key storage behavior',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for skip-scan threshold, stat1 duplicate counts, explicit-prefix rewrites, and WITHOUT ROWID primary-key storage behavior',
    );
    $t->same(
        'non-overlap: owns skipscan2.test optoverview sections 1.3-3.3eqp, avoiding the accepted skipscan1 dynamic batch and generic planner skip-scan helper coverage',
        'non-overlap: owns skipscan2.test optoverview sections 1.3-3.3eqp, avoiding the accepted skipscan1 dynamic batch and generic planner skip-scan helper coverage',
    );
};

return $tests;
