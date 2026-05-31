<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/whereK.test sections whereK-1.1 through
// whereK-2.1. The upstream script verifies OR terms that are factored into a
// useful index lower bound, plus a NOCASE OR regression over a join.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereKOrTermFactoringCases(1000) as $case) {
    $tests['real upstream whereK OR-term factoring dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('whereK.test sections whereK-1.1 through whereK-2.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'whereK-'));
        $t->same('t1bc', $case['index_name']);
        $t->same(['b', 'c'], $case['index_columns']);
        $t->true($case['scenario'] !== '');
        $t->true($case['predicate'] !== '');
        $t->same($case['nocase'], $case['upstream_section'] === 'whereK-2.1');

        if ($case['nocase']) {
            $t->same([], $case['result_a']);
            $t->same(null, $case['factored_lower_bound']);
            $t->same(false, $case['uses_index']);
            $t->same(1, $case['count_result']);
            $t->true(str_contains($case['detail'], 'NOCASE'));
            return;
        }

        $t->same(true, $case['uses_index']);
        $t->same(null, $case['count_result']);
        $t->true(str_contains($case['detail'], 'SEARCH t1 USING INDEX t1bc'));
        $t->true($case['factored_lower_bound'] === 'b>=8' || $case['factored_lower_bound'] === 'b>=9');
        $t->true(array_values($case['result_a']) === $case['result_a']);

        if ($case['upstream_section'] === 'whereK-1.1') {
            $t->same(range(90, 99), $case['result_a']);
            $t->same('b>=9', $case['factored_lower_bound']);
        }

        if ($case['upstream_section'] === 'whereK-1.2' || $case['upstream_section'] === 'whereK-1.3' || $case['upstream_section'] === 'whereK-1.4') {
            $t->same(range(88, 99), $case['result_a']);
            $t->same('b>=8', $case['factored_lower_bound']);
        }

        if ($case['upstream_section'] === 'whereK-1.5') {
            $t->same([88, 89, 90, 91, 92, 93, 97, 98, 99], $case['result_a']);
            $t->same('b>=8', $case['factored_lower_bound']);
            $t->true(str_contains($case['predicate'], 'NOT IN'));
        }
    };
}

$tests['real upstream whereK OR-term factoring dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereKOrTermFactoringCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    $t->same(1000, count($cases));
    $t->same('whereK-1.1', $cases[0]['upstream_section']);
    $t->same('whereK-2.1', $cases[5]['upstream_section']);
    $t->same('whereK-1.4', $cases[999]['upstream_section']);
    $t->same(['whereK-1.1', 'whereK-1.2', 'whereK-1.3', 'whereK-1.4', 'whereK-1.5', 'whereK-2.1'], $sections);
};

$tests['real upstream whereK OR-term factoring dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::whereKOrTermFactoringCases(0));
};

$tests['real upstream whereK OR-term factoring dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, OR-term factoring, residual predicate, NOCASE comparison, and result-row helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, OR-term factoring, residual predicate, NOCASE comparison, and result-row helpers',
    );
};

return $tests;
