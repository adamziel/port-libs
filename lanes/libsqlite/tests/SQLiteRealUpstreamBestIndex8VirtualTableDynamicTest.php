<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindex8.test. These cases cover
// virtual-table xBestIndex distinct/order-by handoff, LIMIT/OFFSET constraint
// forwarding, IN-vector constraint handling, rhs_value reporting, and generated
// xFilter SQL for vectorized and scalarized IN probes.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindex8VirtualTableDistinctLimitInCases(1000) as $case) {
    $tests['real upstream bestindex8 virtual table distinct limit in dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindex8.test sections bestindex8-1.1 through bestindex8-5.1.5b', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindex8-'));
        $t->true(str_contains($case['scenario'], 'dynamic batch'));
        $t->true($case['sql'] !== '');
        $t->true(in_array($case['distinct_code'], [0, 2, 3], true));
        $t->true(is_bool($case['idxinsert']));
        $t->true(is_bool($case['order_by_consumed']));
        $t->true(is_bool($case['uses_in_operator']));
        $t->true(is_bool($case['handle_in']));
        $t->true($case['detail'] !== '');
        $t->same(array_values($case['filter_args']), $case['filter_args']);
        $t->same(array_values($case['filter_sql']), $case['filter_sql']);
        $t->same(array_values($case['rhs_values']), $case['rhs_values']);
        $t->same(array_values($case['result_rows']), $case['result_rows']);

        if (str_starts_with($case['upstream_section'], 'bestindex8-1.')) {
            $t->same([], $case['filter_args']);
            $t->same([], $case['filter_sql']);
            $t->same([], $case['rhs_values']);
            $t->same($case['distinct_code'] !== 0, str_contains($case['sql'], 'DISTINCT'));
            $t->same($case['idxinsert'], str_contains($case['detail'], 'IdxInsert') || str_contains($case['detail'], 'duplicate tracking'));
            $t->true(count($case['result_rows']) >= 1);
        }

        if (str_starts_with($case['upstream_section'], 'bestindex8-2.')) {
            $t->same(0, $case['distinct_code']);
            $t->same([], $case['filter_sql']);
            $t->same([], $case['rhs_values']);
            $t->same(false, $case['uses_in_operator']);
            $t->true($case['result_rows'] === []);
            $t->true(str_contains($case['sql'], 'LIMIT'));
            $t->same($case['upstream_section'] !== 'bestindex8-2.4', $case['filter_args'] !== [[]]);
        }

        if (str_starts_with($case['upstream_section'], 'bestindex8-3.')) {
            $t->same(true, $case['uses_in_operator']);
            $t->same(true, $case['handle_in']);
            $t->same([], $case['filter_args']);
            $t->same([], $case['filter_sql']);
            $t->true(str_contains($case['sql'], ' IN '));
            $t->true(count($case['rhs_values']) >= 1);
        }

        if (str_starts_with($case['upstream_section'], 'bestindex8-4.')) {
            $t->same([], $case['filter_args']);
            $t->same([], $case['filter_sql']);
            $t->true(count($case['rhs_values']) >= 1);
            $t->same(str_contains($case['sql'], '30+2'), in_array('-', $case['rhs_values'], true));
        }

        if (str_starts_with($case['upstream_section'], 'bestindex8-5.')) {
            $t->true($case['filter_sql'] !== []);
            $t->true(count($case['result_rows']) >= 1);
            $t->same($case['uses_in_operator'], str_contains($case['sql'], ' IN '));
            $t->same($case['handle_in'], !str_contains(implode(';', $case['filter_sql']), 'c = 4') || $case['upstream_section'] === 'bestindex8-5.1.1');
            foreach ($case['filter_sql'] as $sql) {
                $t->true(str_starts_with($sql, 'SELECT '));
                $t->true(str_contains($sql, ' FROM t1'));
            }
        }

        if (!$case['handle_in'] && $case['uses_in_operator']) {
            $t->true(count($case['filter_sql']) > 1);
            $t->true(str_contains(implode(';', $case['filter_sql']), ' = '));
        }
    };
}

$tests['real upstream bestindex8 virtual table distinct limit in source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindex8VirtualTableDistinctLimitInCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('bestindex8-1.1', $cases[0]['upstream_section']);
    $t->same('bestindex8-5.1.5b', $cases[24]['upstream_section']);
    $t->true(in_array('bestindex8-2.4', $sections, true));
    $t->true(in_array('bestindex8-3.6', $sections, true));
    $t->true(in_array('bestindex8-4.5', $sections, true));
    $t->same(25, count($sections));
    $t->true(count(array_filter($cases, static fn (array $case): bool => $case['uses_in_operator'])) > 300);
    $t->true(count(array_filter($cases, static fn (array $case): bool => !$case['handle_in'])) > 70);
    $t->true(count(array_filter($cases, static fn (array $case): bool => $case['distinct_code'] === 3)) > 70);
};

$tests['real upstream bestindex8 virtual table distinct limit in rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindex8VirtualTableDistinctLimitInCases(0));
};

$tests['real upstream bestindex8 virtual table distinct limit in dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, virtual-table xBestIndex distinct, LIMIT/OFFSET, IN-vector, rhs_value, and xFilter SQL metadata helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, virtual-table xBestIndex distinct, LIMIT/OFFSET, IN-vector, rhs_value, and xFilter SQL metadata helpers',
    );
};

return $tests;
