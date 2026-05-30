<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index8.test sections 1.0, 1.0eqp,
// 1.1, and 1.1eqp. The upstream regression verifies that an index on
// (a,b,c) is preferred for WHERE c=? ORDER BY a,b LIMIT n because it can
// filter c without a table lookup, while an index on (a,b,d) correctly falls
// back to a table scan. These dynamic cases preserve that planner distinction
// across all c residues and bounded LIMIT values from the same 0..100 corpus.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index8DynamicOrderByLimitCases(1000) as $case) {
    $tests['real upstream index8 dynamic order by limit planner case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index8.test sections 1.0, 1.0eqp, 1.1, and 1.1eqp', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->same('c', $case['where_column']);
        $t->true($case['where_value'] >= 0 && $case['where_value'] <= 18);
        $t->true($case['limit'] >= 1 && $case['limit'] <= 8);
        $t->same(['a', 'b'], $case['order_by']);
        $t->same($case['uses_index'], $case['index_name'] === 't1abc');
        $t->same($case['uses_index'], $case['index_columns'] === ['a', 'b', 'c']);
        $t->same(!$case['uses_index'], $case['requires_table_lookup']);
        $t->same($case['uses_index'], str_contains($case['detail'], 'USING INDEX t1abc'));
        $t->same(!$case['uses_index'], $case['detail'] === 'SCAN t1');
        $t->true($case['matching_count'] >= count($case['result_rows']));
        $t->true(count($case['result_rows']) <= $case['limit']);
        $t->same(count($case['result_rows']) === 0, $case['first_d'] === null);
        $t->same(count($case['result_rows']) === 0, $case['last_d'] === null);

        $previous = null;
        foreach ($case['result_rows'] as $row) {
            $t->same($case['where_value'], $row['c']);
            $t->same(intdiv($row['d'], 10), $row['a']);
            $t->same($row['d'] % 10, $row['b']);
            $t->same($row['d'] % 19, $row['c']);
            if ($previous !== null) {
                $t->true([$previous['a'], $previous['b']] <= [$row['a'], $row['b']]);
            }
            $previous = $row;
        }

        if ($case['result_rows'] !== []) {
            $t->same($case['result_rows'][0]['d'], $case['first_d']);
            $t->same($case['result_rows'][count($case['result_rows']) - 1]['d'], $case['last_d']);
        }
    };
}

$tests['real upstream index8 dynamic order by limit corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index8DynamicOrderByLimitCases(1000);
    $t->same(1000, count($cases));
    $t->same('index8-1.dynamic-1', $cases[0]['upstream']);
    $t->same('index8-1.dynamic-1000', $cases[999]['upstream']);
};

return $tests;
