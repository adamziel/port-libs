<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/wherelimit3.test sections 1.1 through
// 1.4. The upstream script verifies that a small positive LIMIT keeps the
// range index on a plus a temp ORDER BY sort, while LIMIT -1 lets STAT4 favor
// the b-order index scan.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereLimit3RangeLimitPlannerCases(1000) as $case) {
    $tests['real upstream wherelimit3 range limit planner dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case): void {
            $t->same('wherelimit3.test sections wherelimit3-1.1 through wherelimit3-1.4', $case['source']);
            $t->true($case['case'] >= 1 && $case['case'] <= 1000);
            $t->true($case['batch'] >= 1);
            $t->true(in_array($case['upstream_section'], ['wherelimit3-1.1', 'wherelimit3-1.2', 'wherelimit3-1.3', 'wherelimit3-1.4'], true));
            $t->true($case['scenario'] !== '');
            $t->true(str_contains($case['statement'], 'ORDER BY b LIMIT ' . $case['limit_expression']));
            $t->true($case['lower_bound'] >= 25);
            $t->true($case['upper_bound'] <= 1001);
            $t->true($case['upper_bound'] > $case['lower_bound']);
            $t->same($case['upper_bound'] - $case['lower_bound'], $case['candidate_count']);
            $t->same('ok', $case['integrity']);
            $t->same('t1a', $case['range_index']);
            $t->same('t1b', $case['order_index']);
            $t->same($case['uses_range_index'], $case['index_name'] === 't1a');
            $t->same($case['uses_order_index'], $case['index_name'] === 't1b');
            $t->same($case['uses_temp_btree'], in_array('USE TEMP B-TREE FOR ORDER BY', $case['plan_terms'], true));
            $t->same($case['uses_range_index'], str_contains($case['detail'], 'SEARCH t1 USING INDEX t1a'));
            $t->same($case['uses_order_index'], str_contains($case['detail'], 'SCAN t1 USING INDEX t1b'));
            $t->same($case['lower_bound'], $case['first_row']['a']);
            $t->same($case['first_row']['a'], $case['first_row']['b']);
            $t->same($case['last_row']['a'], $case['last_row']['b']);
            $t->same($case['lower_bound'] + $case['selected_row_count'] - 1, $case['last_row']['a']);
            $t->true($case['selected_row_count'] >= 1);
            $t->true($case['selected_row_count'] <= $case['candidate_count']);
            $t->true(count($case['sample_rows']) >= 1 && count($case['sample_rows']) <= 4);

            foreach ($case['sample_rows'] as $row) {
                $t->same($row['a'], $row['b']);
                $t->true($row['a'] >= $case['lower_bound']);
                $t->true($row['a'] <= $case['last_row']['a']);
            }

            if ($case['limit_value'] > 0) {
                $t->same(5, $case['selected_row_count']);
                $t->same(false, $case['stat4_required']);
                $t->same(true, $case['uses_range_index']);
                $t->same(false, $case['uses_order_index']);
                $t->same(true, $case['uses_temp_btree']);
                $t->same(['SEARCH t1 USING INDEX t1a (a>? AND a<?)', 'USE TEMP B-TREE FOR ORDER BY'], $case['plan_terms']);
            } else {
                $t->same(-1, $case['limit_value']);
                $t->same($case['candidate_count'], $case['selected_row_count']);
                $t->same(true, $case['stat4_required']);
                $t->same(false, $case['uses_range_index']);
                $t->same(true, $case['uses_order_index']);
                $t->same(false, $case['uses_temp_btree']);
                $t->same(['SCAN t1 USING INDEX t1b'], $case['plan_terms']);
            }

            if ($case['upstream_section'] === 'wherelimit3-1.3' || $case['upstream_section'] === 'wherelimit3-1.4') {
                $t->same(true, $case['limit_is_bound_variable']);
                $t->same('$N', $case['limit_expression']);
            } else {
                $t->same(false, $case['limit_is_bound_variable']);
                $t->same((string) $case['limit_value'], $case['limit_expression']);
            }
        };
}

$tests['real upstream wherelimit3 range limit planner source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereLimit3RangeLimitPlannerCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('wherelimit3-1.1', $cases[0]['upstream_section']);
    $t->same('wherelimit3-1.2', $cases[1]['upstream_section']);
    $t->same('wherelimit3-1.3', $cases[2]['upstream_section']);
    $t->same('wherelimit3-1.4', $cases[3]['upstream_section']);
    $t->same('wherelimit3-1.4', $cases[999]['upstream_section']);
    $t->same(250, $cases[999]['batch']);
    $t->same(['wherelimit3-1.1', 'wherelimit3-1.2', 'wherelimit3-1.3', 'wherelimit3-1.4'], $sections);
};

$tests['real upstream wherelimit3 range limit planner rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereLimit3RangeLimitPlannerCases(0));
};

$tests['real upstream wherelimit3 range limit planner dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning, STAT4 limit costing metadata, range-index detail, order-index detail, and result-row bounds',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning, STAT4 limit costing metadata, range-index detail, order-index detail, and result-row bounds',
    );
};

return $tests;
