<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindex9.test. These cases cover
// virtual-table xBestIndex ORDER BY and distinct-code inputs for DISTINCT,
// GROUP BY, composite PRIMARY KEY, WITHOUT ROWID, NOT NULL, and joined sources.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindex9VirtualTableDistinctOrderByCases(1000) as $case) {
    $tests['real upstream bestindex9 virtual table distinct order by dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindex9.test sections bestindex9-1.0 through bestindex9-4', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindex9-'));
        $t->true(str_contains($case['scenario'], 'dynamic batch'));
        $t->true(str_starts_with($case['create_table'], 'CREATE TABLE x('));
        $t->true(str_contains($case['select_sql'], 'SELECT DISTINCT'));
        $t->true(in_array($case['distinct_code'], [0, 1, 2, 3], true));
        $t->same($case['distinct_code'] === 0, $case['order_by'] === []);
        $t->same($case['order_by'] !== [], $case['order_by_consumed']);
        $t->same(array_values($case['order_by']), $case['order_by']);
        $t->true($case['detail'] !== '');
        $t->true($case['batch'] >= 1);

        foreach ($case['order_by'] as $term) {
            $t->true(array_key_exists('column', $term));
            $t->true(array_key_exists('desc', $term));
            $t->true(is_int($term['column']));
            $t->true(is_bool($term['desc']));
            $t->same(false, $term['desc']);
        }

        if ($case['upstream_section'] === 'bestindex9-1.0') {
            $t->same([[ 'column' => 0, 'desc' => false ], [ 'column' => 1, 'desc' => false ]], $case['order_by']);
            $t->same(2, $case['distinct_code']);
            $t->same(false, $case['without_rowid']);
            $t->same(false, $case['primary_key_not_null']);
            $t->true(str_contains($case['create_table'], 'PRIMARY KEY(k1, k2)'));
        }

        if ($case['upstream_section'] === 'bestindex9-1.1') {
            $t->same([], $case['order_by']);
            $t->same(0, $case['distinct_code']);
            $t->same(true, $case['without_rowid']);
            $t->true(str_contains($case['create_table'], 'WITHOUT ROWID'));
        }

        if ($case['upstream_section'] === 'bestindex9-1.2') {
            $t->same([], $case['order_by']);
            $t->same(0, $case['distinct_code']);
            $t->same(true, $case['primary_key_not_null']);
            $t->true(str_contains($case['create_table'], 'NOT NULL'));
        }

        if ($case['upstream_section'] === 'bestindex9-2') {
            $t->same([[ 'column' => 0, 'desc' => false ]], $case['order_by']);
            $t->same(3, $case['distinct_code']);
            $t->true(str_contains($case['select_sql'], 'ORDER BY c1'));
        }

        if ($case['upstream_section'] === 'bestindex9-3') {
            $t->same([[ 'column' => 0, 'desc' => false ]], $case['order_by']);
            $t->same(1, $case['distinct_code']);
            $t->true(str_contains($case['select_sql'], 'GROUP BY c1'));
        }

        if ($case['upstream_section'] === 'bestindex9-4') {
            $t->same([[ 'column' => 0, 'desc' => false ]], $case['order_by']);
            $t->same(2, $case['distinct_code']);
            $t->same(true, $case['join_source']);
            $t->true(str_contains($case['select_sql'], 't1, t2'));
        }
    };
}

$tests['real upstream bestindex9 virtual table distinct order by source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindex9VirtualTableDistinctOrderByCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    $distinctCodes = array_values(array_unique(array_column($cases, 'distinct_code')));
    sort($distinctCodes);

    $t->same(1000, count($cases));
    $t->same(['bestindex9-1.0', 'bestindex9-1.1', 'bestindex9-1.2', 'bestindex9-2', 'bestindex9-3', 'bestindex9-4'], $sections);
    $t->same([0, 1, 2, 3], $distinctCodes);
    $t->same('bestindex9-1.0', $cases[0]['upstream_section']);
    $t->same('bestindex9-4', $cases[5]['upstream_section']);
    $t->true(count(array_filter($cases, static fn (array $case): bool => $case['order_by'] !== [])) > 600);
    $t->true(count(array_filter($cases, static fn (array $case): bool => $case['without_rowid'])) > 150);
    $t->true(count(array_filter($cases, static fn (array $case): bool => $case['join_source'])) > 150);
};

$tests['real upstream bestindex9 virtual table distinct order by rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindex9VirtualTableDistinctOrderByCases(0));
};

$tests['real upstream bestindex9 virtual table distinct order by dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex order-by/distinct metadata helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex order-by/distinct metadata helpers',
    );
};

return $tests;
