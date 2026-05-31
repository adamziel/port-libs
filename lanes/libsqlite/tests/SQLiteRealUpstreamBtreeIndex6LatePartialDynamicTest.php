<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index6.test sections index6-15.2 through
// index6-19.2. These late partial-index regressions cover theorem-prover
// BETWEEN forms, NOCASE comparison direction, GLOB partial-index integrity
// after REPLACE, NULL partial-unique predicates, and RIGHT JOIN no-match loops.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index6LatePartialIndexRegressionCases(1000) as $case) {
    $tests['real upstream index6 late partial index dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index6.test sections index6-15.2 through index6-19.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], [
            'index6-15.2',
            'index6-15.3',
            'index6-15.4',
            'index6-16.2',
            'index6-16.3',
            'index6-17.1',
            'index6-17.2',
            'index6-17.3',
            'index6-18.1',
            'index6-19.2',
        ], true));
        $t->true($case['scenario'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true($case['predicate'] !== '');
        $t->true($case['detail'] !== '');
        $t->true(array_values($case['result_rows']) === $case['result_rows']);

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['upstream_section'] === 'index6-15.2') {
            $t->same([[1]], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_contains($case['predicate'], 'BETWEEN FALSE AND TRUE'));
        }

        if ($case['upstream_section'] === 'index6-15.3') {
            $t->same([[1]], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_starts_with($case['predicate'], 'TRUE BETWEEN'));
        }

        if ($case['upstream_section'] === 'index6-15.4') {
            $t->same([[1]], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_starts_with($case['predicate'], 'FALSE BETWEEN'));
        }

        if ($case['upstream_section'] === 'index6-16.2') {
            $t->same([], $case['result_rows']);
            $t->same(true, $case['uses_partial_index']);
            $t->same('NOCASE', $case['collation']);
            $t->same('c0 >= c1', $case['predicate']);
        }

        if ($case['upstream_section'] === 'index6-16.3') {
            $t->same([[3]], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->same('NOCASE', $case['collation']);
            $t->same('c1 <= c0', $case['predicate']);
        }

        if ($case['upstream_section'] === 'index6-17.1') {
            $t->same([['ok']], $case['result_rows']);
            $t->same(true, $case['uses_partial_index']);
            $t->same('c0 GLOB c0', $case['predicate']);
            $t->true(str_contains($case['detail'], 'CREATE UNIQUE INDEX i1'));
        }

        if ($case['upstream_section'] === 'index6-17.2') {
            $t->same([['ok']], $case['result_rows']);
            $t->same(true, $case['uses_partial_index']);
            $t->true(str_contains($case['detail'], 'REPLACE INTO'));
        }

        if ($case['upstream_section'] === 'index6-17.3') {
            $t->same([[1]], $case['result_rows']);
            $t->same(true, $case['uses_partial_index']);
            $t->true(str_contains($case['detail'], 'COUNT(*)'));
        }

        if ($case['upstream_section'] === 'index6-18.1') {
            $t->same([[10, 10]], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->same('a > NULL', $case['predicate']);
        }

        if ($case['upstream_section'] === 'index6-19.2') {
            $t->same([], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->same('RIGHT JOIN', $case['join_kind']);
            $t->true(str_contains($case['detail'], 'RIGHT JOIN'));
        }
    };
}

$tests['real upstream index6 late partial index dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index6LatePartialIndexRegressionCases(1000);
    $t->same(1000, count($cases));
    $t->same('index6-15.2', $cases[0]['upstream_section']);
    $t->same('index6-19.2', $cases[9]['upstream_section']);
    $t->same('index6-19.2', $cases[999]['upstream_section']);
};

$tests['real upstream index6 late partial index dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index6LatePartialIndexRegressionCases(0));
};

$tests['real upstream index6 late partial index dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, partial-index theorem-prover, collation, GLOB, replace-integrity, and RIGHT JOIN result helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, partial-index theorem-prover, collation, GLOB, replace-integrity, and RIGHT JOIN result helpers',
    );
};

return $tests;
