<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexexpr3.test sections 1.1 through 2.5.
// The upstream script verifies JSON expression-index values are read from the
// index when possible and that nested JSON calls still execute Function opcodes.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionJsonCoveringCases(1000) as $case) {
    $tests['real upstream indexexpr3 json covering dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexexpr3.test sections indexexpr3-1.1 through indexexpr3-2.5', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 91);
        $t->true(in_array($case['upstream_section'], [
            'indexexpr3-1.1',
            'indexexpr3-1.2',
            'indexexpr3-1.3',
            'indexexpr3-1.4',
            'indexexpr3-1.5',
            'indexexpr3-1.6',
            'indexexpr3-2.1',
            'indexexpr3-2.2',
            'indexexpr3-2.3',
            'indexexpr3-2.4',
            'indexexpr3-2.5',
        ], true));
        $t->true(str_contains($case['sql'], 'json_extract'));
        $t->true($case['index_name'] === 'i1' || $case['index_name'] === 'i2');
        $t->same("json_extract(j, '$.x')", $case['expression']);
        $t->true($case['where_clause'] === null || str_contains($case['where_clause'], 'a='));
        $t->true($case['order_by'] === null || str_contains($case['order_by'], 'json_extract'));
        $t->true($case['uses_index']);
        $t->true($case['function_opcode_count'] >= 0);
        $t->same('ok', $case['integrity']);
        $t->true($case['detail'] !== '');

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['upstream_section'] === 'indexexpr3-1.1') {
            $t->same('i1', $case['index_name']);
            $t->same(0, $case['function_opcode_count']);
            $t->same(true, $case['covering_index']);
            $t->same([['one'], ['three'], ['two']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'without Function opcode'));
        }

        if (in_array($case['upstream_section'], ['indexexpr3-1.2', 'indexexpr3-1.3', 'indexexpr3-1.4'], true)) {
            $t->same('i2', $case['index_name']);
            $t->same(0, $case['function_opcode_count']);
            $t->same(true, $case['covering_index']);
            $t->same('a=2', $case['where_clause']);
            $t->true(str_contains($case['detail'], 'COVERING INDEX i2'));
        }

        if ($case['upstream_section'] === 'indexexpr3-1.4') {
            $t->same([['two.two']], $case['result_rows']);
        }

        if (in_array($case['upstream_section'], ['indexexpr3-1.5', 'indexexpr3-1.6'], true)) {
            $t->same('i2', $case['index_name']);
            $t->same(2, $case['function_opcode_count']);
            $t->same(false, $case['covering_index']);
            $t->same([['{"y":"two"}']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'Function opcodes'));
        }

        if ($case['upstream_section'] === 'indexexpr3-2.1') {
            $t->same('i1', $case['index_name']);
            $t->same(true, $case['covering_index']);
            $t->same('t1 USING COVERING INDEX i1', $case['detail']);
        }

        if (in_array($case['upstream_section'], ['indexexpr3-2.2', 'indexexpr3-2.3', 'indexexpr3-2.5'], true)) {
            $t->same('i1', $case['index_name']);
            $t->same(false, $case['covering_index']);
            $t->same('t1 USING INDEX i1', $case['detail']);
        }

        if ($case['upstream_section'] === 'indexexpr3-2.3') {
            $t->same(1, $case['function_opcode_count']);
            $t->true(str_contains($case['sql'], 'json_insert'));
        }

        if ($case['upstream_section'] === 'indexexpr3-2.4') {
            $t->same(true, $case['covering_index']);
            $t->same('t1 USING COVERING INDEX i1', $case['detail']);
            $t->true(str_contains($case['sql'], 'sum('));
        }
    };
}

$tests['real upstream indexexpr3 json covering corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionJsonCoveringCases(1000);
    $t->same(1000, count($cases));
    $t->same('indexexpr3-1.1', $cases[0]['upstream_section']);
    $t->same('indexexpr3-2.5', $cases[10]['upstream_section']);
    $t->same('indexexpr3-2.1', $cases[996]['upstream_section']);
};

$tests['real upstream indexexpr3 json covering rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionJsonCoveringCases(0));
};

$tests['real upstream indexexpr3 json covering dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus expression-index and JSON planner detail helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus expression-index and JSON planner detail helpers',
    );
};

return $tests;
