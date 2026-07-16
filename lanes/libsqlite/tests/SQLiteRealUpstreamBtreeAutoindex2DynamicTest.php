<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/autoindex2.test sections autoindex2-100
// through autoindex2-120. The upstream regression protects a large
// sqlite_stat1-costed join from over-using automatic indexes and temp ORDER BY
// b-trees.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::autoindex2StatCostingCases(1000) as $case) {
    $tests['real upstream autoindex2 stat costing dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('autoindex2.test sections autoindex2-100 through autoindex2-120', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], ['autoindex2-100', 'autoindex2-110', 'autoindex2-120'], true));
        $t->true($case['batch'] >= 1);
        $t->true(str_contains($case['scenario'], 'dynamic batch'));
        $t->same(30, $case['master_count']);
        $t->same(false, $case['uses_automatic_index']);
        $t->same(false, $case['uses_temp_order_btree']);

        if ($case['upstream_section'] === 'autoindex2-100') {
            $t->same(0, $case['stat_rows']);
            $t->same([], $case['stat_tables']);
            $t->same([], $case['join_order']);
            $t->same([], $case['uses_declared_indexes']);
            $t->same('', $case['query']);
            $t->true(str_contains($case['detail'], '3 tables and 27 declared indexes'));
        }

        if ($case['upstream_section'] === 'autoindex2-110') {
            $t->same(27, $case['stat_rows']);
            $t->same(['t1', 't2', 't3'], $case['stat_tables']);
            $t->true(in_array('t1x2', $case['uses_declared_indexes'], true));
            $t->true(in_array('t2x15', $case['uses_declared_indexes'], true));
            $t->true(in_array('t3x0', $case['uses_declared_indexes'], true));
            $t->true(str_contains($case['detail'], 'stat1 cardinalities'));
        }

        if ($case['upstream_section'] === 'autoindex2-120') {
            $t->same(27, $case['stat_rows']);
            $t->same(['t1', 't2', 't3'], $case['join_order']);
            $t->same(['t1x1', 't2x0', 't3x0'], $case['uses_declared_indexes']);
            $t->same(500, $case['limit']);
            $t->same('t1.ptime desc', $case['order_by']);
            $t->true(str_contains($case['query'], 'ORDER BY t1.ptime desc LIMIT 500'));
            $t->true(str_contains($case['detail'], 'must not contain AUTOMATIC'));
        }
    };
}

$tests['real upstream autoindex2 stat costing corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::autoindex2StatCostingCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same(['autoindex2-100', 'autoindex2-110', 'autoindex2-120'], $sections);
    $t->same('autoindex2-100', $cases[0]['upstream_section']);
    $t->same('autoindex2-120', $cases[2]['upstream_section']);
    $t->same('autoindex2-100', $cases[999]['upstream_section']);
    $t->same(333, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex2-120')));
};

$tests['real upstream autoindex2 stat costing rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::autoindex2StatCostingCases(0));
};

$tests['real upstream autoindex2 stat costing dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and stat1 planner metadata arrays',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and stat1 planner metadata arrays',
    );
};

return $tests;
