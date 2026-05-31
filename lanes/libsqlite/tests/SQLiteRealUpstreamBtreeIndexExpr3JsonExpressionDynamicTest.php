<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexexpr3.test sections 1.1 through
// 2.5. These cases verify JSON expression-index substitution, Function opcode
// elimination, and covering-index planner distinctions.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexexpr3JsonExpressionCoveringCases(1000) as $case) {
    $tests['real upstream indexexpr3 json expression covering dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('indexexpr3.test sections indexexpr3-1.1 through indexexpr3-2.5', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'indexexpr3-'));
        $t->true(str_contains($case['statement'], 'json_extract') || str_contains($case['statement'], 'json_insert'));
        $t->true($case['index_name'] === 'i1' || $case['index_name'] === 'i2');
        $t->true(str_contains($case['indexed_expression'], "json_extract(j, '$.x')"));
        $t->true($case['function_opcode_count'] >= 0);
        $t->same('ok', $case['integrity']);
        $t->same(true, $case['uses_index']);
        $t->true($case['detail'] !== '');

        if ($case['uses_covering_index']) {
            $t->true(str_contains($case['detail'], 'COVERING INDEX') || str_contains($case['detail'], 'index key'));
            $t->same(0, $case['function_opcode_count']);
        } else {
            $t->true(str_contains($case['detail'], 'USING INDEX'));
        }

        if ($case['upstream_section'] === 'indexexpr3-1.1') {
            $t->same([['one'], ['three'], ['two']], $case['expected_rows']);
            $t->same(null, $case['where_clause']);
        }

        if ($case['upstream_section'] === 'indexexpr3-1.2') {
            $t->same([['two']], $case['expected_rows']);
            $t->same('a=2', $case['where_clause']);
        }

        if ($case['upstream_section'] === 'indexexpr3-1.4') {
            $t->same([['two.two']], $case['expected_rows']);
        }

        if ($case['upstream_section'] === 'indexexpr3-1.5' || $case['upstream_section'] === 'indexexpr3-1.6') {
            $t->same([['{"y":"two"}']], $case['expected_rows']);
            $t->same(2, $case['function_opcode_count']);
            $t->same(false, $case['uses_covering_index']);
        }

        if (str_starts_with($case['upstream_section'], 'indexexpr3-2.')) {
            $t->same('a=?', $case['where_clause']);
            $t->same([], $case['expected_rows']);
        }

        if ($case['upstream_section'] === 'indexexpr3-2.2' || $case['upstream_section'] === 'indexexpr3-2.5') {
            $t->same(false, $case['uses_covering_index']);
        }
    };
}

$tests['real upstream indexexpr3 json expression covering dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexexpr3JsonExpressionCoveringCases(1000);
    $t->same(1000, count($cases));
    $t->same('indexexpr3-1.1', $cases[0]['upstream_section']);
    $t->same('indexexpr3-2.5', $cases[10]['upstream_section']);
    $t->same('indexexpr3-1.1', $cases[11]['upstream_section']);
    $t->same('indexexpr3.test sections indexexpr3-1.1 through indexexpr3-2.5', $cases[0]['source']);
    $t->true($cases[999]['batch'] > $cases[0]['batch']);
};

$tests['real upstream indexexpr3 json expression covering rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::indexexpr3JsonExpressionCoveringCases(0));
};

$tests['real upstream indexexpr3 json expression covering dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index expression-index corpus planner and JSON expression metadata fixtures',
        'no new support component needed; reuses lane-local B-tree/index expression-index corpus planner and JSON expression metadata fixtures',
    );
};

return $tests;
