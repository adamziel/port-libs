<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index9.test sections 1.1 through 4.5.
// The upstream script verifies that bound-variable values only prove a
// partial-index predicate when the prepared value is the same integer literal,
// while nearby real, string, NULL, and QPSG cases keep the table-only route.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index9DynamicBoundPartialIndexCases(1000) as $case) {
    $tests['real upstream index9 dynamic bound partial index case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index9.test sections 1.1 through 4.5', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream'], 'index9-'));
        $t->true(in_array($case['index_name'], ['t1x', 't1x2', 't1x3', 't1x4'], true));
        $t->true(in_array('t1', $case['objects'], true));
        $t->same($case['uses_partial_index'], in_array($case['index_name'], $case['objects'], true));
        $t->same($case['uses_partial_index'] ? 2 : 1, count($case['objects']));
        $t->same($case['uses_partial_index'], str_contains($case['detail'], $case['index_name']));
        $t->same($case['uses_partial_index'], $case['where_value_type'] === 'integer' && $case['where_value'] === $case['predicate_value'] && !$case['qpsg']);
        $t->true($case['order_by'] === null || $case['order_by'] === 'x');
        $t->true($case['operand_order'] === 'column-left' || $case['operand_order'] === 'literal-left');

        if ($case['qpsg']) {
            $t->same(false, $case['uses_partial_index']);
            $t->same(['t1'], $case['objects']);
        }

        if ($case['where_value_type'] === 'double' || $case['where_value_type'] === 'string' || $case['where_value_type'] === 'NULL') {
            $t->same(false, $case['uses_partial_index']);
        }

        if ($case['uses_partial_index']) {
            $t->same('integer', $case['where_value_type']);
            $t->same($case['predicate_value'], $case['where_value']);
        }
    };
}

$tests['real upstream index9 dynamic bound partial corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index9DynamicBoundPartialIndexCases(1000);
    $t->same(1000, count($cases));
    $t->same('index9-1.1/1.5.dynamic-1', $cases[0]['upstream']);
    $t->same('index9-4.1/4.5.dynamic-250', $cases[999]['upstream']);
};

$tests['real upstream index9 dynamic bound partial corpus rejects empty count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index9DynamicBoundPartialIndexCases(0));
};

return $tests;
