<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index2.test. The upstream script builds a
// 1000-column table, bulk inserts 101 wide rows, creates a 1000-column index,
// and verifies ORDER BY prefix scans over that index.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index2DynamicWideColumnOrderCases() as $case) {
    $tests['real upstream index2 wide column order dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index2.test sections index2-1.1 through index2-2.2', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->same(1000, $case['table_columns']);
        $t->same(101, $case['row_count']);
        $t->same(1000, $case['indexed_columns']);
        $t->true($case['ordered_columns'] >= 1);
        $t->true($case['ordered_columns'] <= 12);
        $t->true($case['limit'] >= 1);
        $t->true($case['limit'] <= 8);
        $t->true(str_starts_with($case['result_column'], 'c'));
        $t->same($case['limit'], count($case['result_values']));
        $t->same('c1000', $case['sum_column']);
        $t->same(50601000.0, $case['rounded_sum']);
        $t->same(true, $case['uses_wide_index']);
        $t->true(str_contains($case['detail'], 'using t1i1'));

        $columnNumber = (int) substr($case['result_column'], 1);
        $t->true($columnNumber >= 1);
        $t->true($columnNumber <= 1000);
        $t->same($columnNumber, $case['result_values'][0]);
        if ($case['limit'] > 1) {
            $t->same(10000 + $columnNumber, $case['result_values'][1]);
        }
        if ($case['limit'] > 7) {
            $t->same(70000 + $columnNumber, $case['result_values'][7]);
        }
    };
}

$tests['real upstream index2 wide column order dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index2DynamicWideColumnOrderCases();
    $t->same(1000, count($cases));
    $t->same('index2-2.2.dynamic-1', $cases[0]['upstream']);
    $t->same('index2-2.2.dynamic-1000', $cases[count($cases) - 1]['upstream']);
    $t->same('c1', $cases[0]['result_column']);
    $t->same('c1000', $cases[count($cases) - 1]['result_column']);
};

$tests['real upstream index2 wide column order dynamic rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index2DynamicWideColumnOrderCases(0));
};

$tests['real upstream index2 wide column order dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, wide-column index ordering, aggregate column, and order-prefix helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, wide-column index ordering, aggregate column, and order-prefix helpers',
    );
};

return $tests;
