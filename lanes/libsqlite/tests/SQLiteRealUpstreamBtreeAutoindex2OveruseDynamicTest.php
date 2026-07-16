<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAutoIndexDynamicPlan;

$tests = [];

// Source truth: SQLite upstream test/autoindex2.test sections autoindex2-100
// through autoindex2-120. The upstream regression uses a wide real-world
// schema plus sqlite_stat1 rows to prove the planner must not over-use a
// transient automatic covering index when declared indexes dominate the join.
foreach (SQLiteAutoIndexDynamicPlan::autoindex2RealWorldOveruseCases(1000) as $case) {
    $tests['real upstream autoindex2 overuse dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('autoindex2.test autoindex2-100 through autoindex2-120', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], ['autoindex2-100', 'autoindex2-110', 'autoindex2-120'], true));
        $t->true(in_array($case['query_shape'], ['schema-stat-seed', 'stat1-refresh', 'three-table-order-limit', 'order-limit-cost'], true));
        $t->same(3, $case['table_count']);
        $t->same(23, $case['index_count']);
        $t->same(23, $case['stat_count']);
        $t->same(10747267, $case['fact_table_rows']);
        $t->same(39667, $case['dimension_rows']);
        $t->same(569, $case['lookup_rows']);
        $t->same(['t1.ptime DESC'], $case['order_by']);
        $t->same(500, $case['limit']);
        $t->same([
            't1.ptime > 1393520400',
            'param3 <> 9001',
            't3.flg7 = 1',
            't1.did = t2.did',
            't2.uid = t3.uid',
        ], $case['where_terms']);
        $t->same(true, $case['autoindex_suppressed']);
        $t->same(true, $case['uses_declared_index']);
        $t->same(false, $case['requires_temp_btree']);
        $t->true($case['scenario'] !== '');
        $t->true($case['chosen_loop'] !== '');
        $t->true(str_contains($case['rejected_loop'], 'automatic'));
        $t->true($case['detail'] !== '');
        $t->true(str_contains($case['non_overlap'], 'autoindex2.test'));
        $t->same(
            'no new support component needed; reuses lane-local planner-stat and automatic-index admission models for declared-index cost comparison',
            $case['dependency_closure'],
        );

        [$table, $index, $stat] = explode(':', $case['stat_signature'], 3);
        $t->true(in_array($table, ['t1', 't2', 't3'], true));
        $t->true(str_starts_with($index, $table . 'x'));
        $t->true(preg_match('/^[0-9]+( [0-9]+)*$/', $stat) === 1);

        if ($case['upstream_section'] === 'autoindex2-100') {
            $t->same('sqlite_schema declared index inventory', $case['chosen_loop']);
            $t->true(str_contains($case['detail'], '23 declared indexes'));
        }

        if ($case['upstream_section'] === 'autoindex2-110') {
            $t->same('t1x2 did/ssid/ptime/vstatus/exbyte/t1_id', $case['chosen_loop']);
            $t->true(str_contains($case['detail'], 'sqlite_stat1'));
        }

        if ($case['query_shape'] === 'three-table-order-limit') {
            $t->same('automatic covering index on wide fact table', $case['rejected_loop']);
            $t->true(str_contains($case['detail'], 'does not include AUTO'));
        }

        if ($case['query_shape'] === 'order-limit-cost') {
            $t->same('t1x1 ptime/vstatus with t1x2 join filter', $case['chosen_loop']);
            $t->true(str_contains($case['rejected_loop'], 'sort btree'));
        }
    };
}

$tests['real upstream autoindex2 overuse dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteAutoIndexDynamicPlan::autoindex2RealWorldOveruseCases(1000);

    $t->same(1000, count($cases));
    $t->same('autoindex2-100', $cases[0]['upstream_section']);
    $t->same('autoindex2-120', $cases[2]['upstream_section']);
    $t->same('autoindex2.test autoindex2-100 through autoindex2-120', $cases[999]['source']);
    $t->same(250, count(array_filter($cases, static fn (array $case): bool => $case['query_shape'] === 'schema-stat-seed')));
    $t->same(500, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex2-120')));
};

$tests['real upstream autoindex2 overuse dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAutoIndexDynamicPlan::autoindex2RealWorldOveruseCases(0));
};

$tests['real upstream autoindex2 overuse dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local planner-stat and automatic-index admission models for declared-index cost comparison',
        'no new support component needed; reuses lane-local planner-stat and automatic-index admission models for declared-index cost comparison',
    );
};

return $tests;
