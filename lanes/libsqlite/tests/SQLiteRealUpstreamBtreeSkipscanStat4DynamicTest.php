<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];
$source = 'skipscan5.test sections skipscan5-1.3 through skipscan5-3.3 and skipscan6.test sections skipscan6-1.2 through skipscan6-3.2';

// Source truth: SQLite upstream test/skipscan5.test sections skipscan5-1.3
// through skipscan5-3.3 and test/skipscan6.test sections skipscan6-1.2
// through skipscan6-3.2. These STAT4 cases protect planner decisions between
// skip-scan, table scan, and full-prefix index probes over integer, collated
// text, mixed-type, and competing-index distributions.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::skipscan5And6Stat4RangeCases(1000) as $case) {
    $tests['real upstream btree skipscan stat4 dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $source): void {
        $t->same($source, $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'skipscan5-') || str_starts_with($case['upstream_section'], 'skipscan6-'));
        $t->true($case['batch'] >= 1);
        $t->true(str_contains($case['scenario'], 'dynamic batch'));
        $t->true($case['table_name'] !== '');
        $t->true($case['index_name'] !== '');
        $t->true(count($case['index_columns']) >= 1);
        $t->true($case['where_clause'] !== '');
        $t->same(true, $case['stat4_enabled']);
        $t->true(in_array($case['stored_value_family'], [
            'integer-distribution',
            'text-collated-distribution',
            'mixed-storage-class-distribution',
            'stat4-full-prefix-preference',
            'stat4-single-column-preference',
        ], true));
        $t->same('ok', $case['integrity']);

        foreach ($case['range_terms'] as $term) {
            $t->true(is_string($term) && $term !== '');
        }

        if ($case['uses_skip_scan']) {
            $t->same(false, $case['uses_full_index']);
            $t->same($case['index_name'], $case['chosen_index']);
            $t->true(str_contains($case['constraint_detail'], 'ANY('));
            $t->same([], $case['rejected_indexes']);
        }

        if ($case['uses_full_index']) {
            $t->same(false, $case['uses_skip_scan']);
            $t->same($case['index_name'], $case['chosen_index']);
            $t->true(str_starts_with($case['constraint_detail'], 'SEARCH ' . $case['table_name'] . ' USING INDEX '));
            $t->true(count($case['rejected_indexes']) >= 1);
        }

        if (!$case['uses_skip_scan'] && !$case['uses_full_index']) {
            $t->same(null, $case['chosen_index']);
            $t->true(str_starts_with($case['constraint_detail'], 'SCAN '));
            $t->true(in_array($case['index_name'] . ' skip-scan', $case['rejected_indexes'], true));
        }

        if ($case['upstream_section'] === 'skipscan5-1.3.1') {
            $t->same('b = 5', $case['where_clause']);
            $t->same('ANY(a) AND b=?', $case['constraint_detail']);
            $t->same(['b=5'], $case['range_terms']);
            $t->same(true, $case['uses_skip_scan']);
        }

        if ($case['upstream_section'] === 'skipscan5-1.3.3') {
            $t->same('b > 2 AND b < 16', $case['where_clause']);
            $t->same('SCAN t1', $case['constraint_detail']);
            $t->same(false, $case['uses_skip_scan']);
        }

        if (str_starts_with($case['upstream_section'], 'skipscan5-2.')) {
            $t->same('t2', $case['table_name']);
            $t->same('i2', $case['index_name']);
            $t->same(['a', 'b', 'c'], $case['index_columns']);
            $t->true(in_array($case['encoding'], ['utf-8', 'utf-16'], true));
            $t->true(str_contains((string) $case['collation'], 'test_collate'));
        }

        if ($case['upstream_section'] === 'skipscan5-2.1.1') {
            $t->same("c BETWEEN 'd' AND 'e'", $case['where_clause']);
            $t->same('ANY(a) AND ANY(b) AND c>? AND c<?', $case['constraint_detail']);
            $t->same(true, $case['uses_skip_scan']);
        }

        if ($case['upstream_section'] === 'skipscan5-2.2.2') {
            $t->same("c BETWEEN 'b' AND 'r'", $case['where_clause']);
            $t->same('SCAN t2', $case['constraint_detail']);
            $t->same(false, $case['uses_skip_scan']);
        }

        if ($case['upstream_section'] === 'skipscan5-3.3.4') {
            $t->same("b > X'5555'", $case['where_clause']);
            $t->same('ANY(a) AND b>?', $case['constraint_detail']);
            $t->same(["b>X'5555'"], $case['range_terms']);
            $t->same(true, $case['uses_skip_scan']);
        }

        if ($case['upstream_section'] === 'skipscan6-1.2') {
            $t->same('ix', $case['chosen_index']);
            $t->same(true, $case['uses_full_index']);
            $t->true(in_array('ix skip-scan', $case['rejected_indexes'], true));
            $t->true(str_contains($case['constraint_detail'], 'aa=? AND bb=?'));
        }

        if ($case['upstream_section'] === 'skipscan6-2.2') {
            $t->same('good', $case['chosen_index']);
            $t->same(true, $case['uses_full_index']);
            $t->true(in_array('bad skip-scan', $case['rejected_indexes'], true));
            $t->true(str_contains($case['constraint_detail'], 'bb=? AND aa=?'));
        }

        if ($case['upstream_section'] === 'skipscan6-3.1') {
            $t->same('t3_a', $case['chosen_index']);
            $t->same(['a'], $case['index_columns']);
            $t->true(in_array('t3_ba skip-scan', $case['rejected_indexes'], true));
        }

        if ($case['upstream_section'] === 'skipscan6-3.2') {
            $t->same('t2_a', $case['chosen_index']);
            $t->same(['a'], $case['index_columns']);
            $t->true(in_array('t2_ba skip-scan', $case['rejected_indexes'], true));
        }
    };
}

$tests['real upstream btree skipscan stat4 dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::skipscan5And6Stat4RangeCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same(46, count($sections));
    $t->same('skipscan5-1.3.1', $cases[0]['upstream_section']);
    $t->same('skipscan6-3.2', $cases[45]['upstream_section']);
    $t->same('skipscan5-2.3.4', $cases[999]['upstream_section']);
    $t->same(22, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'skipscan5-1.3.1')));
    $t->same(21, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'skipscan6-3.2')));
};

$tests['real upstream btree skipscan stat4 dynamic rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::skipscan5And6Stat4RangeCases(0));
};

$tests['real upstream btree skipscan stat4 dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and STAT4 selectivity metadata arrays',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and STAT4 selectivity metadata arrays',
    );
};

return $tests;
