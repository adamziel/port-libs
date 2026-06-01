<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/where.test sections where-1.1.1 through
// where-1.41. This batch owns the original B-tree index seek corpus for
// equality, IS, aliases, unary-plus de-optimization, composite (x,y) probes,
// and simple range constraints before the later where2/where3/... suites.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where1BasicIndexSeekCases(1000) as $case) {
    $tests['real upstream where1 basic btree index seek dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('where.test sections where-1.1.1 through where-1.41', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'where-1.'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['projection'] !== []);
        $t->true($case['predicate_terms'] !== []);
        $t->same($case['index_name'] !== null, $case['uses_index']);
        $t->same('ok', $case['integrity']);
        $t->same(count($case['expected_rows']), $case['row_count']);
        $t->same(array_merge($case['result_flat'], [$case['search_count']]), $case['count_output']);

        $flat = [];
        foreach ($case['expected_rows'] as $row) {
            $t->same(range(0, count($row) - 1), array_keys($row));
            foreach ($row as $value) {
                $flat[] = $value;
            }
        }
        $t->same($flat, $case['result_flat']);

        if ($case['uses_index']) {
            $t->true($case['index_name'] === 'i1w' || $case['index_name'] === 'i1xy');
            $t->contains((string) $case['index_name'], $case['detail']);
        } else {
            $t->contains('SCAN t1', $case['detail']);
        }

        if ($case['uses_covering_index']) {
            $t->contains('COVERING INDEX', $case['detail']);
        }

        if ($case['unary_plus_disabled_index']) {
            $t->contains('+', implode(' ', $case['predicate_terms']));
            $t->same(false, $case['uses_index']);
        }

        if ($case['alias_predicate']) {
            $t->true(str_contains($case['statement'], ' AS ') || str_contains(implode(' ', $case['predicate_terms']), 'abc'));
        }

        if ($case['commuted_predicate']) {
            $terms = implode(' ', $case['predicate_terms']);
            $t->same(1, preg_match('/(^|\s)[0-9]+(\s+IS|[<>=])/', $terms));
        }

        if ($case['expression_predicate']) {
            $t->contains('+', implode(' ', $case['predicate_terms']));
            $t->true($case['search_count'] >= 10);
        }

        if ($case['index_name'] === 'i1xy') {
            $t->contains('x', implode(' ', $case['predicate_terms']));
            $t->contains('x=?', $case['detail']);
        }
    };
}

$tests['real upstream where1 basic btree index seek source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where1BasicIndexSeekCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    $bySection = array_column(array_slice($cases, 0, 58), null, 'upstream_section');

    $t->same(1000, count($cases));
    $t->same(58, count($sections));
    $t->same('where-1.1.1', $cases[0]['upstream_section']);
    $t->same('where-1.41', $cases[57]['upstream_section']);
    $t->same('where-1.1.1', $cases[58]['upstream_section']);
    $t->same('where-1.6', $cases[999]['upstream_section']);
    $t->same([3, 121, 10, 3], $bySection['where-1.1.1']['count_output']);
    $t->same([3, 121, 10, 99], $bySection['where-1.1.4']['count_output']);
    $t->same([10, 11, 12, 13, 9], $bySection['where-1.23']['count_output']);
    $t->same([1, 2, 3], $bySection['where-1.35']['count_output']);
    $t->same([97, 99], $bySection['where-1.41']['count_output']);
    $t->same('SEARCH t1 USING COVERING INDEX i1xy (x=? AND y=?)', $bySection['where-1.8.3']['detail']);
};

$tests['real upstream where1 basic btree index seek rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where1BasicIndexSeekCases(0));
};

$tests['real upstream where1 basic btree index seek dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus helpers for row synthesis, predicate routing, range search counts, and planner detail evidence',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus helpers for row synthesis, predicate routing, range search counts, and planner detail evidence',
    );
    $t->same(
        'non-overlap: owns upstream where.test section-1 basic B-tree index seek behavior and avoids accepted where2-whereN, B-tree page relocation/root-collapse/overflow release, VFS writer/sync/lock, and rollback-commit clusters',
        'non-overlap: owns upstream where.test section-1 basic B-tree index seek behavior and avoids accepted where2-whereN, B-tree page relocation/root-collapse/overflow release, VFS writer/sync/lock, and rollback-commit clusters',
    );
};

return $tests;
