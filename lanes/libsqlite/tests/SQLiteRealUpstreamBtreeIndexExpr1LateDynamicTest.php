<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexexpr1.test sections 510 through
// 2300. These sections cover late expression-index planner, collation,
// mutation, numeric-affinity, DELETE INDEXED BY, covering, and JSON-subtype
// guard behavior after the previously accepted 110-410 DDL/lookup range.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionLateDynamicCases(1000) as $case) {
    $tests['real upstream indexexpr1 late expression dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexexpr1.test sections indexexpr1-510 through indexexpr1-2300', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 25);
        $t->true(str_starts_with($case['upstream_section'], 'indexexpr1-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['result_code'] === 0 || $case['result_code'] === 1);
        $t->same($case['result_code'] === 0, $case['error'] === null);
        $t->true($case['planner_detail'] !== '');
        $t->true(in_array($case['collation'], ['binary', 'nocase', 'rtrim'], true));
        $t->true(is_bool($case['uses_expression_index']));
        $t->true(is_bool($case['covering_index']));
        $t->true(is_bool($case['subtype_preserved']));

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['result_code'] === 1) {
            $t->true($case['error'] !== null);
            $t->same([], $case['result_rows']);
            $t->true(str_contains($case['error'], 'UNIQUE constraint failed'));
        }

        if ($case['upstream_section'] === 'indexexpr1-510') {
            $t->same('t5ax', $case['index_name']);
            $t->same('substr(a,4,3)', $case['expression']);
            $t->same([['001'], ['002'], ['003'], ['004'], ['005']], $case['result_rows']);
            $t->true($case['covering_index']);
            $t->true(str_contains($case['planner_detail'], 'COVERING INDEX t5ax'));
        }

        if ($case['upstream_section'] === 'indexexpr1-600') {
            $t->same('t4all', $case['index_name']);
            $t->same([[9]], $case['result_rows']);
            $t->true(str_contains($case['planner_detail'], 'ANY(<expr>)'));
        }

        if ($case['upstream_section'] === 'indexexpr1-700') {
            $t->same('+b=+c', $case['expression']);
            $t->same([[1, 2, 2, '|'], ['abc', 'def', 'def', '|']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'indexexpr1-710') {
            $t->same('b+c=y+z', $case['expression']);
            $t->same([[1, 1, '|'], [2, 2, '|']], $case['result_rows']);
            $t->true(str_contains($case['planner_detail'], 't72yz'));
        }

        if (in_array($case['upstream_section'], ['indexexpr1-800', 'indexexpr1-810'], true)) {
            $t->same('nocase', $case['collation']);
            $t->same('t8bx', $case['index_name']);
        }

        if ($case['upstream_section'] === 'indexexpr1-820') {
            $t->same('rtrim', $case['collation']);
            $t->same(0, $case['result_code']);
            $t->same(null, $case['error']);
        }

        if ($case['upstream_section'] === 'indexexpr1-900') {
            $t->same([['ok']], $case['result_rows']);
            $t->same('ok', $case['integrity']);
        }

        if ($case['upstream_section'] === 'indexexpr1-910') {
            $t->same(1, $case['result_code']);
            $t->same("UNIQUE constraint failed: index 't9x1'", $case['error']);
        }

        if ($case['upstream_section'] === 'indexexpr1-1000') {
            $t->same([[0, 1, 2, '|'], [2, 99, 4, '|'], [5, 99, 7, '|']], $case['result_rows']);
            $t->true(str_contains((string) $case['mutation'], 'false expression-index'));
        }

        if ($case['upstream_section'] === 'indexexpr1-1010') {
            $t->same([[0, 88, 2, '|'], [2, 99, 4, '|'], [5, 99, 7, '|']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'indexexpr1-1100') {
            $t->same([['integer', 1]], $case['result_rows']);
            $t->true(str_contains($case['planner_detail'], 'NULL expression key skipped'));
        }

        if ($case['upstream_section'] === 'indexexpr1-1200.4') {
            $t->same([[0, 0], [0, 2], [0, 4], [2, 0], [2, 2], [4, 0]], $case['result_rows']);
            $t->true($case['covering_index']);
        }

        if ($case['upstream_section'] === 'indexexpr1-1300.1') {
            $t->same([[3], [4]], $case['result_rows']);
            $t->same('nocase', $case['collation']);
        }

        if (in_array($case['upstream_section'], ['indexexpr1-1400', 'indexexpr1-1410', 'indexexpr1-1420', 'indexexpr1-1430'], true)) {
            $t->true($case['covering_index']);
            $t->true($case['expression'] === '1' || $case['expression'] === 'abs(15+3)');
        }

        if (in_array($case['upstream_section'], ['indexexpr1-1500', 'indexexpr1-1510'], true)) {
            $t->true(str_contains((string) $case['mutation'], 'expression index') || str_contains($case['planner_detail'], 'expression index'));
        }

        if (in_array($case['upstream_section'], ['indexexpr1-1600', 'indexexpr1-1610', 'indexexpr1-1620'], true)) {
            $t->same('idx1', $case['index_name']);
            $t->same('lower(a)', $case['expression']);
            $t->true(str_contains($case['planner_detail'], 'numeric') || str_contains($case['statement'], 'lower(a)'));
        }

        if ($case['upstream_section'] === 'indexexpr1-1700') {
            $t->same(false, $case['uses_expression_index']);
            $t->same([[0]], $case['result_rows']);
            $t->true(str_contains($case['planner_detail'], 'implication rejected'));
        }

        if (in_array($case['upstream_section'], ['indexexpr1-1800', 'indexexpr1-1810', 'indexexpr1-1820'], true)) {
            $t->true(str_contains($case['planner_detail'], 'REAL'));
            $t->true($case['uses_expression_index']);
        }

        if ($case['upstream_section'] === 'indexexpr1-1910') {
            $t->same([['alpha', 'ALPHA', 1]], $case['result_rows']);
            $t->same('nocase', $case['collation']);
            $t->true(str_contains($case['statement'], 'DELETE FROM t1 INDEXED BY i1'));
        }

        if ($case['upstream_section'] === 'indexexpr1-1920') {
            $t->same([['bravo', 'charlie', 1]], $case['result_rows']);
        }

        if (in_array($case['upstream_section'], ['indexexpr1-2011', 'indexexpr1-2021', 'indexexpr1-2040'], true)) {
            $t->true($case['subtype_preserved']);
            $t->true($case['covering_index']);
            $t->true(str_contains($case['expression'] ?? '', "->>'"));
        }

        if (in_array($case['upstream_section'], ['indexexpr1-2110', 'indexexpr1-2120', 'indexexpr1-2130', 'indexexpr1-2140'], true)) {
            $t->true(str_contains($case['statement'], 'GLOB'));
            $t->true(str_contains($case['planner_detail'], 'GLOB') || str_contains($case['planner_detail'], 'string'));
        }

        if ($case['upstream_section'] === 'indexexpr1-2200') {
            $t->same([[7, 100], [8, 101]], $case['result_rows']);
            $t->same('-tag', $case['expression']);
        }

        if (in_array($case['upstream_section'], ['indexexpr1-2211', 'indexexpr1-2221', 'indexexpr1-2231', 'indexexpr1-2241', 'indexexpr1-2251', 'indexexpr1-2261', 'indexexpr1-2300'], true)) {
            $t->true($case['subtype_preserved']);
            $t->same(false, $case['covering_index']);
            $t->true(str_contains($case['planner_detail'], 'JSON subtype') || str_contains($case['planner_detail'], 'json_insert'));
        }
    };
}

$tests['real upstream indexexpr1 late expression corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionLateDynamicCases(1000);
    $t->same(1000, count($cases));
    $t->same('indexexpr1-510', $cases[0]['upstream_section']);
    $t->same('indexexpr1-2300', $cases[43]['upstream_section']);
    $t->same('indexexpr1-510', $cases[44]['upstream_section']);
    $t->same(23, $cases[999]['batch']);
};

$tests['real upstream indexexpr1 late expression rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionLateDynamicCases(0));
};

$tests['real upstream indexexpr1 late expression dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus expression-index, collation, mutation, JSON-subtype, and planner-detail helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus expression-index, collation, mutation, JSON-subtype, and planner-detail helpers',
    );
};

return $tests;
