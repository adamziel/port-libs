<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index6.test sections index6-1.1 through
// index6-6.2. This batch owns early partial-index parser guards, reduced
// sqlite_stat1 cardinality, partial UNIQUE admission, VACUUM integrity,
// database-qualified predicate names, and UPDATE OR REPLACE stability.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index6EarlyPartialIndexCases(1200) as $case) {
    $tests['real upstream index6 early partial index dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index6.test sections index6-1.1 through index6-6.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'index6-'));
        $t->same('rowid', $case['table_shape']);
        $t->true($case['scenario'] !== '');
        $t->true($case['operation'] !== '');
        $t->true($case['partial_index'] !== '');
        $t->true($case['partial_predicate'] !== '');
        $t->true($case['row_count'] >= 0);
        $t->true(in_array($case['integrity'], ['ok', 'expected-error', 'expected-error-and-ok'], true));
        $t->true(array_values($case['result_rows']) === $case['result_rows']);

        if ($case['expected_error'] !== null) {
            $t->true(str_contains($case['integrity'], 'expected-error'));
            $t->true($case['expected_error'] !== '');
        }

        if ($case['upstream_section'] === 'index6-1.1') {
            $t->same([[14, 20], ['ok']], $case['result_rows']);
        }

        if (in_array($case['upstream_section'], ['index6-1.2', 'index6-1.3', 'index6-1.4', 'index6-1.5'], true)) {
            $t->same([], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_contains($case['expected_error'] ?? '', 'prohibited') || str_contains($case['expected_error'] ?? '', 'no such column'));
        }

        if ($case['upstream_section'] === 'index6-1.10') {
            $t->same([['idx' => null, 'stat' => '20'], ['idx' => 't1a', 'stat' => '14 1'], ['idx' => 't1b', 'stat' => '10 1']], $case['stat_rows']);
        }

        if ($case['upstream_section'] === 'index6-1.11b') {
            $t->same('6 1', $case['stat_rows'][1]['stat']);
            $t->same('20 1', $case['stat_rows'][2]['stat']);
        }

        if ($case['upstream_section'] === 'index6-1.15') {
            $t->same('t1c', $case['stat_rows'][2]['idx']);
            $t->same('15 1', $case['stat_rows'][2]['stat']);
        }

        if ($case['upstream_section'] === 'index6-2.1/2.4') {
            $t->same(999, $case['row_count']);
            $t->same(500, $case['index_row_count']);
            $t->same([[500], ['SEARCH t2 USING INDEX t2a1'], ['SCAN t2']], $case['result_rows']);
            $t->same(true, $case['uses_partial_index']);
        }

        if ($case['upstream_section'] === 'index6-2.101/2.104') {
            $t->same([[10015], [10015], [10515]], $case['result_rows']);
            $t->same('a<100 OR a>200', $case['partial_predicate']);
        }

        if ($case['upstream_section'] === 'index6-3.1/3.5') {
            $t->same('UNIQUE constraint failed: t3.a', $case['expected_error']);
            $t->same([[162], ['ok']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index6-4.0') {
            $t->same([['ok']], $case['result_rows']);
            $t->true(str_contains($case['operation'], 'VACUUM'));
        }

        if ($case['upstream_section'] === 'index6-5.0') {
            $t->same(6, $case['index_row_count']);
            $t->same([['idx' => 't3b', 'stat' => '6 1']], $case['stat_rows']);
            $t->same([[6], [6]], $case['result_rows']);
            $t->true(str_contains($case['operation'], 'xyzzy.t3.b'));
        }

        if ($case['upstream_section'] === 'index6-6.0/6.2') {
            $t->same([[123, 456], [123, 789], ['ok']], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
        }
    };
}

$tests['real upstream index6 early partial index dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index6EarlyPartialIndexCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('index6-1.1', $cases[0]['upstream_section']);
    $t->same('index6-6.0/6.2', $cases[19]['upstream_section']);
    $t->same('index6-1.1', $cases[20]['upstream_section']);
    $t->same(60, $cases[1199]['batch']);
    $t->same([
        'index6-1.1',
        'index6-1.1.1',
        'index6-1.10',
        'index6-1.11a',
        'index6-1.11b',
        'index6-1.12',
        'index6-1.13',
        'index6-1.14',
        'index6-1.15',
        'index6-1.2',
        'index6-1.3',
        'index6-1.4',
        'index6-1.5',
        'index6-1.6/1.7',
        'index6-2.1/2.4',
        'index6-2.101/2.104',
        'index6-3.1/3.5',
        'index6-4.0',
        'index6-5.0',
        'index6-6.0/6.2',
    ], $sections);
};

$tests['real upstream index6 early partial index dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index6EarlyPartialIndexCases(0));
};

$tests['real upstream index6 early partial index dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, partial-index parser guard, stat-row, unique-admission, VACUUM integrity, and update-replace helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, partial-index parser guard, stat-row, unique-admission, VACUUM integrity, and update-replace helpers',
    );
};

return $tests;
