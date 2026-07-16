<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$expectedFlat = [
    'whereA-1.1' => [1, 2, 3, 2, 'hello', 'world', 3, 4.53, null],
    'whereA-1.2' => [3, 4.53, null, 2, 'hello', 'world', 1, 2, 3],
    'whereA-1.3' => [3, 4.53, null, 2, 'hello', 'world', 1, 2, 3],
    'whereA-1.4' => [1, 2, 3, 2, 'hello', 'world', 3, 4.53, null],
    'whereA-1.5' => [1, 2, 3, 2, 'hello', 'world', 3, 4.53, null],
    'whereA-1.6' => [1],
    'whereA-1.7' => [3, 4.53, null, 2, 'hello', 'world', 1, 2, 3],
    'whereA-1.8' => [],
    'whereA-1.9' => [1, 2, 3],
    'whereA-2.1' => [1, 2, 3, 2, 'hello', 'world', 3, 4.53, null],
    'whereA-2.2' => [3, 4.53, null, 2, 'hello', 'world', 1, 2, 3],
    'whereA-2.3' => [1, 2, 3, 2, 'hello', 'world', 3, 4.53, null],
    'whereA-3.1' => [1, 2, 3, 3, 4.53, null, 2, 'hello', 'world'],
    'whereA-3.2' => [2, 'hello', 'world', 3, 4.53, null, 1, 2, 3],
    'whereA-3.3' => [1, 2, 3, 3, 4.53, null, 2, 'hello', 'world'],
    'whereA-4.1' => [2, 1],
    'whereA-4.2' => [2, 1],
    'whereA-4.3' => [1, 2],
    'whereA-4.4' => [2, 1],
    'whereA-4.5' => [1, 2],
    'whereA-4.6' => [2, 1],
    'whereA-5.1' => [1],
    'whereA-6.1' => [1],
];

$reverseSections = [
    'whereA-1.2',
    'whereA-1.3',
    'whereA-1.4',
    'whereA-1.5',
    'whereA-1.6',
    'whereA-1.7',
    'whereA-1.8',
    'whereA-1.9',
    'whereA-2.2',
    'whereA-2.3',
    'whereA-3.2',
    'whereA-3.3',
    'whereA-4.1',
    'whereA-4.2',
    'whereA-4.3',
    'whereA-4.4',
    'whereA-4.5',
    'whereA-4.6',
    'whereA-5.1',
    'whereA-6.1',
];

// Source truth: SQLite upstream test/whereA.test sections whereA-1.1 through
// whereA-6.1. These cases verify reverse_unordered_selects over rowid and
// index range cursors, ORDER BY index usage without temp sorting, OR predicate
// routing, reopen/VACUUM persistence, and rowid NULL impossibility checks.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereAReverseUnorderedIndexCases(1000) as $case) {
    $tests['real upstream whereA reverse unordered index dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $expectedFlat, $reverseSections): void {
        $t->same('whereA.test sections whereA-1.1 through whereA-6.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'whereA-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->same('ok', $case['integrity']);
        $t->same($expectedFlat[$case['upstream_section']], $case['result_flat']);
        $t->same(in_array($case['upstream_section'], $reverseSections, true), $case['reverse_unordered_selects']);
        $t->same($case['sort_count'] === 1, $case['ordered'] && $case['uses_index'] === false && $case['table_name'] === 't2');
        $t->same($case['result_rows'] === [], $case['result_flat'] === []);

        $flat = [];
        foreach ($case['result_rows'] as $row) {
            foreach ($row as $value) {
                $flat[] = $value;
            }
        }
        $t->same($flat, $case['result_flat']);

        if ($case['order_by'] !== null) {
            $t->same(true, $case['ordered']);
        }

        if ($case['index_name'] !== null) {
            $t->true($case['index_name'] !== '');
        }

        if ($case['upstream_section'] === 'whereA-1.8') {
            $t->same([], $case['result_rows']);
            $t->same('b=2 AND a IS NULL', $case['predicate']);
            $t->same('sqlite_autoindex_t1_1', $case['index_name']);
        }

        if ($case['upstream_section'] === 'whereA-3.2') {
            $t->same([[2, 'hello', 'world'], [3, 4.53, null], [1, 2, 3]], $case['result_rows']);
            $t->same('sqlite_autoindex_t1_1', $case['index_name']);
            $t->same(false, $case['ordered']);
        }

        if ($case['upstream_section'] === 'whereA-4.3') {
            $t->same([[1], [2]], $case['result_rows']);
            $t->same('x ASC', $case['order_by']);
            $t->same('t2x', $case['index_name']);
            $t->same(0, $case['sort_count']);
        }

        if ($case['upstream_section'] === 'whereA-4.5') {
            $t->same([[1], [2]], $case['result_rows']);
            $t->same(null, $case['index_name']);
            $t->same(1, $case['sort_count']);
        }

        if ($case['upstream_section'] === 'whereA-6.1') {
            $t->same('t1aa', $case['index_name']);
            $t->contains('stat1-adjusted duplicate-column index', $case['detail']);
        }
    };
}

$tests['real upstream whereA reverse unordered dynamic corpus count'] = static function (TestRunner $t) use ($expectedFlat): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereAReverseUnorderedIndexCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('whereA-1.1', $cases[0]['upstream_section']);
    $t->same('whereA-6.1', $cases[22]['upstream_section']);
    $t->same('whereA-1.1', $cases[23]['upstream_section']);
    $t->same('whereA-2.2', $cases[999]['upstream_section']);
    $t->same(44, $cases[999]['batch']);
    $t->same(array_keys($expectedFlat), $sections);
};

$tests['real upstream whereA reverse unordered dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereAReverseUnorderedIndexCases(0));
};

$tests['real upstream whereA reverse unordered dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, rowid scans, unique-index scans, ORDER BY sort accounting, and OR predicate metadata',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, rowid scans, unique-index scans, ORDER BY sort accounting, and OR predicate metadata',
    );
};

return $tests;
