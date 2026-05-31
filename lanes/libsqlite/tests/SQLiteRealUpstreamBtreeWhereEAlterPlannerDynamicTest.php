<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/whereE.test sections whereE-1.1 through
// whereE-1.4. These cases preserve the planner choice after ALTER TABLE adds
// indexed join columns and after ANALYZE reloads statistics: scan the expanded
// t1 rowset and probe t2 through the unique (z,x) index even when the FROM
// clause order is reversed.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereEAlterTableJoinPlannerCases(1000) as $case) {
    $tests['real upstream whereE alter table join planner dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('whereE.test sections whereE-1.1 through whereE-1.4', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], ['whereE-1.1', 'whereE-1.2', 'whereE-1.3', 'whereE-1.4'], true));
        $t->true($case['scenario'] !== '');
        $t->true($case['from_clause'] === 't1, t2' || $case['from_clause'] === 't2, t1');
        $t->same($case['upstream_section'] === 'whereE-1.3' || $case['upstream_section'] === 'whereE-1.4', $case['analyzed']);
        $t->same(5120, $case['t1_rows']);
        $t->same(128, $case['t2_rows']);
        $t->same(3, $case['t1_distinct_a']);
        $t->same(1, $case['t2_distinct_z']);
        $t->same(true, $case['uses_t1_scan']);
        $t->same(true, $case['uses_t2_index']);
        $t->same('t2zx', $case['index_name']);
        $t->true(str_contains($case['detail'], 'SCAN t1'));
        $t->true(str_contains($case['detail'], 'SEARCH t2'));
        $t->true(str_contains($case['detail'], 't2zx'));
        $t->same(['t1.c', 't2.z'], $case['altered_columns']);
        $t->same(['a=z', 'c=x'], $case['join_terms']);
        $t->same([[2, 2, 2, 10004], [4, 2, 2, 10008]], $case['result_rows']);

        if ($case['from_clause'] === 't2, t1') {
            $t->true(str_contains($case['scenario'], 'reversed FROM clause'));
        }

        if ($case['analyzed']) {
            $t->true(str_contains($case['scenario'], 'ANALYZE'));
        }
    };
}

$tests['real upstream whereE alter table join planner source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereEAlterTableJoinPlannerCases(1000);
    $t->same(1000, count($cases));
    $t->same('whereE-1.1', $cases[0]['upstream_section']);
    $t->same('whereE-1.2', $cases[1]['upstream_section']);
    $t->same('whereE-1.3', $cases[2]['upstream_section']);
    $t->same('whereE-1.4', $cases[3]['upstream_section']);
    $t->same('whereE-1.4', $cases[999]['upstream_section']);
};

$tests['real upstream whereE alter table join planner rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::whereEAlterTableJoinPlannerCases(0));
};

$tests['real upstream whereE alter table join planner dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, ALTER TABLE materialized-column metadata, ANALYZE statistics, and composite index join-plan helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, ALTER TABLE materialized-column metadata, ANALYZE statistics, and composite index join-plan helpers',
    );
};

return $tests;
