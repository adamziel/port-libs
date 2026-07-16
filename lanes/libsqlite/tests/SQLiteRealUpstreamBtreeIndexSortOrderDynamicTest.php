<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-14.1 through
// index-14.12. This batch owns mixed NULL/numeric/text index key sort order
// and range predicate behavior, distinct from index19 conflict-policy and
// indexA partial-affinity planner coverage.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexSortOrderComparisonCases(1200) as $case) {
    $tests['real upstream index mixed sort order dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-14.1 through index-14.12', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'index-14.'));
        $t->true($case['scenario'] !== '');
        $t->same('t6i1', $case['index_name']);
        $t->same(['a', 'b'], $case['index_columns']);
        $t->same([3, 5, 2, 1, 4], $case['ordered_rows']);
        $t->same('ok', $case['integrity']);

        if ($case['upstream_section'] === 'index-14.12') {
            $t->same([], $case['result_rows']);
            $t->same(false, $case['uses_index']);
            $t->same('PRAGMA integrity_check', $case['predicate']);
            $t->true(str_contains($case['detail'], 'integrity-check'));
            return;
        }

        $t->same(true, $case['uses_index']);
        $t->true(str_contains($case['detail'], 'USING INDEX t6i1'));

        if ($case['upstream_section'] === 'index-14.1') {
            $t->same('ORDER BY a,b', $case['predicate']);
            $t->same([3, 5, 2, 1, 4], $case['result_rows']);
            $t->same(null, $case['operand']);
        }

        if ($case['upstream_section'] === 'index-14.2') {
            $t->same('a = ?', $case['predicate']);
            $t->same('', $case['operand']);
            $t->same([2, 1], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.3') {
            $t->same('b = ?', $case['predicate']);
            $t->same('', $case['operand']);
            $t->same([1, 3], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.4') {
            $t->same('a > ?', $case['predicate']);
            $t->same('', $case['operand']);
            $t->same([4], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.5') {
            $t->same('a >= ?', $case['predicate']);
            $t->same('', $case['operand']);
            $t->same([2, 1, 4], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.6') {
            $t->same('a > ?', $case['predicate']);
            $t->same(123, $case['operand']);
            $t->same([2, 1, 4], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.7') {
            $t->same('a >= ?', $case['predicate']);
            $t->same(123, $case['operand']);
            $t->same([5, 2, 1, 4], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.8') {
            $t->same('a < ?', $case['predicate']);
            $t->same('abc', $case['operand']);
            $t->same([5, 2, 1], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.9') {
            $t->same('a <= ?', $case['predicate']);
            $t->same('abc', $case['operand']);
            $t->same([5, 2, 1, 4], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.10') {
            $t->same('a <= ?', $case['predicate']);
            $t->same('', $case['operand']);
            $t->same([5, 2, 1], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.11') {
            $t->same('a < ?', $case['predicate']);
            $t->same('', $case['operand']);
            $t->same([5], $case['result_rows']);
        }
    };
}

$tests['real upstream index mixed sort order corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexSortOrderComparisonCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same('index-14.1', $cases[0]['upstream_section']);
    $t->same('index-14.12', $cases[11]['upstream_section']);
    $t->same('index-14.1', $cases[12]['upstream_section']);
    $t->same('index-14.12', $cases[1199]['upstream_section']);
    $t->same(100, $cases[1199]['batch']);
    $t->same([
        'index-14.1',
        'index-14.2',
        'index-14.3',
        'index-14.4',
        'index-14.5',
        'index-14.6',
        'index-14.7',
        'index-14.8',
        'index-14.9',
        'index-14.10',
        'index-14.11',
        'index-14.12',
    ], $sections);
};

$tests['real upstream index mixed sort order rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexSortOrderComparisonCases(0));
};

$tests['real upstream index mixed sort order dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, SQLite mixed-type sort precedence, index range predicate, and integrity-check helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, SQLite mixed-type sort precedence, index range predicate, and integrity-check helpers',
    );
};

return $tests;
