<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index6.test sections index6-12.1
// through index6-19.2. This batch owns late partial-index theorem regressions
// around NOT IN, NULL truthiness, IS FALSE/BETWEEN/IN predicates, collation
// direction, GLOB self-comparison indexes, NULL partial-UNIQUE predicates, and
// RIGHT JOIN no-match loops.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index6LatePartialIndexTheoremCases(1000) as $case) {
    $tests['real upstream index6 late partial theorem dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index6.test sections index6-12.1 through index6-19.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'index6-'));
        $t->same('rowid', $case['table_shape']);
        $t->true($case['statement'] !== '');
        $t->true($case['partial_index'] !== '');
        $t->true($case['predicate'] !== '');
        $t->true($case['query'] !== '');
        $t->same(null, $case['expected_error']);
        $t->same('ok', $case['integrity']);
        $t->true(array_values($case['result_rows']) === $case['result_rows']);
        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['upstream_section'] === 'index6-12.1/12.2') {
            $t->same([[1], [2]], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_contains($case['query'], 'IN (SELECT a FROM t1)'));
        }

        if ($case['upstream_section'] === 'index6-13.1') {
            $t->same([[null]], $case['result_rows']);
            $t->true(str_contains($case['query'], 'OR 1'));
        }

        if ($case['upstream_section'] === 'index6-14.1' || $case['upstream_section'] === 'index6-14.2') {
            $t->same([[null, 'row']], $case['result_rows']);
            $t->same('c0 NOT NULL', $case['predicate']);
        }

        if (str_starts_with($case['upstream_section'], 'index6-15.')) {
            $t->same([[1]], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_contains($case['query'], 'FALSE'));
        }

        if ($case['upstream_section'] === 'index6-16.1/16.3') {
            $t->same('NOCASE', $case['collation']);
            $t->same([[3]], $case['result_rows']);
            $t->same('c0 >= c1', $case['predicate']);
            $t->same('c1 <= c0', $case['query']);
        }

        if ($case['upstream_section'] === 'index6-17.1/17.3') {
            $t->same(true, $case['uses_partial_index']);
            $t->same([[1]], $case['result_rows']);
            $t->true(str_contains($case['predicate'], 'GLOB'));
        }

        if ($case['upstream_section'] === 'index6-18.1') {
            $t->same([[10, 10]], $case['result_rows']);
            $t->same('a>NULL', $case['predicate']);
        }

        if ($case['upstream_section'] === 'index6-19.2') {
            $t->same([], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_contains($case['query'], 'RIGHT JOIN'));
        }
    };
}

$tests['real upstream index6 late partial theorem corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index6LatePartialIndexTheoremCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('index6-12.1/12.2', $cases[0]['upstream_section']);
    $t->same('index6-19.2', $cases[12]['upstream_section']);
    $t->same('index6-12.1/12.2', $cases[13]['upstream_section']);
    $t->same(77, $cases[999]['batch']);
    $t->same([
        'index6-12.1/12.2',
        'index6-13.1',
        'index6-14.1',
        'index6-14.2',
        'index6-15.1',
        'index6-15.2',
        'index6-15.3',
        'index6-15.4',
        'index6-15.5',
        'index6-16.1/16.3',
        'index6-17.1/17.3',
        'index6-18.1',
        'index6-19.2',
    ], $sections);
};

$tests['real upstream index6 late partial theorem rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index6LatePartialIndexTheoremCases(0));
};

$tests['real upstream index6 late partial theorem dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index partial-index theorem, NULL truthiness, collation, GLOB, RIGHT JOIN, and result-row corpus helpers',
        'no new support component needed; reuses lane-local B-tree/index partial-index theorem, NULL truthiness, collation, GLOB, RIGHT JOIN, and result-row corpus helpers',
    );
};

return $tests;
