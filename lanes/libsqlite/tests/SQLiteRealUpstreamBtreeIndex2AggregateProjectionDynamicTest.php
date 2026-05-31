<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index2.test. The script creates a
// 1000-column table, verifies c123 projection, bulk-inserts 101 wide rows,
// sums c1000, creates a 1000-column index, and scans an ORDER BY prefix.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index2DynamicWideAggregateProjectionCases(1000) as $case) {
    $tests['real upstream index2 aggregate projection dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index2.test sections index2-1.3, index2-1.5, index2-2.1, and index2-2.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->same(1000, $case['table_columns']);
        $t->same(101, $case['row_count']);
        $t->same(1000, $case['indexed_columns']);
        $t->same('c1000', $case['sum_column']);
        $t->same(50601000.0, $case['rounded_sum']);
        $t->same('ok', $case['integrity']);

        $columnNumber = (int) substr($case['projection_column'], 1);
        $t->true($columnNumber >= 1 && $columnNumber <= 1000);
        $t->true($case['projection_rowid'] >= 1 && $case['projection_rowid'] <= 101);
        $expectedProjection = $case['projection_rowid'] === 1
            ? $columnNumber
            : (($case['projection_rowid'] - 1) * 10000) + $columnNumber;
        $t->same($expectedProjection, $case['projection_value']);
        $t->same($columnNumber, $case['min_value']);
        $t->same(1000000 + $columnNumber, $case['max_value']);

        $t->true($case['ordered_prefix_columns'] >= 1 && $case['ordered_prefix_columns'] <= 10);
        $t->true($case['limit'] >= 1 && $case['limit'] <= 5);
        $t->same($case['limit'], count($case['ordered_rowids']));
        $t->same($case['limit'], count($case['ordered_values']));
        $t->same(range(1, $case['limit']), $case['ordered_rowids']);
        $t->same($columnNumber, $case['ordered_values'][0]);
        foreach ($case['ordered_values'] as $offset => $value) {
            $rowid = $offset + 1;
            $expected = $rowid === 1 ? $columnNumber : (($rowid - 1) * 10000) + $columnNumber;
            $t->same($expected, $value);
        }
        $t->true(str_contains($case['detail'], 't1i1'));
    };
}

$tests['real upstream index2 aggregate projection dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index2DynamicWideAggregateProjectionCases(1000);
    $t->same(1000, count($cases));
    $t->same('index2-1.3/1.5/2.2.dynamic-aggregate-1', $cases[0]['upstream']);
    $t->same('index2-1.3/1.5/2.2.dynamic-aggregate-1000', $cases[999]['upstream']);
    $t->same('c1', $cases[0]['projection_column']);
    $t->same('c1000', $cases[999]['projection_column']);
};

$tests['real upstream index2 aggregate projection dynamic rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index2DynamicWideAggregateProjectionCases(0));
};

return $tests;
