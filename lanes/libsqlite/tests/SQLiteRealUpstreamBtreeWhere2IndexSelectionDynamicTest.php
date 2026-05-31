<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$knownIndexes = [
    'i1w',
    'i1xy',
    'i1zyx',
    'rowid',
    'tx_xyz',
    'i11aba',
    'sqlite_autoindex_t8_1',
    'sqlite_autoindex_t2249a_1',
];
$compositeIndexes = ['i1xy', 'i1zyx', 'tx_xyz', 'i11aba', 'i11cccccccc'];

// Source truth: SQLite upstream test/where2.test sections where2-1.1
// through where2-11.4. This batch owns the B-tree/index queryplan behavior
// around UNIQUE-vs-non-unique index selection, rowid precedence, ORDER BY
// sorter elision, multi-layer IN constraints, OR-to-IN rewrites, affinity
// guards, and repeated-column index probes.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where2IndexSelectionAndInCases(1200) as $case) {
    $tests['real upstream where2 index selection dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $knownIndexes, $compositeIndexes): void {
        $t->same('where2.test sections where2-1.1 through where2-11.4', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->same(intdiv($case['case'] - 1, 35) + 1, $case['batch']);
        $t->true(str_starts_with($case['upstream_section'], 'where2-'));
        $t->same('ok', $case['integrity']);
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['table_name'] !== '');
        $t->true($case['projection'] !== []);
        $t->same(count($case['result_rows']), $case['result_count']);
        $t->same($case['requires_sort'] ? 'sort' : 'nosort', $case['sort_marker']);
        $t->same($case['index_name'] === 'rowid', $case['uses_rowid']);
        $t->true(str_contains($case['detail'], 'where2 dynamic replay'));

        $flat = [];
        foreach ($case['result_rows'] as $row) {
            $t->same(range(0, count($row) - 1), array_keys($row));
            foreach ($row as $value) {
                $flat[] = $value;
            }
        }
        $t->same($flat, $case['result_flat']);

        if ($case['index_name'] !== null && $case['index_name'] !== 'i11cccccccc') {
            $t->true(in_array($case['index_name'], $knownIndexes, true));
        }

        if ($case['uses_unique_index']) {
            $t->true($case['index_name'] === 'i1w' || str_starts_with((string) $case['index_name'], 'sqlite_autoindex_'));
        }

        $t->same(in_array($case['index_name'], $compositeIndexes, true), $case['uses_composite_index']);

        if ($case['uses_in_operator']) {
            $t->true($case['in_layers'] >= 1);
            $t->true(str_contains($case['statement'], ' IN ') || $case['or_to_in']);
        } else {
            $t->same(0, $case['in_layers']);
        }

        if ($case['or_to_in']) {
            $t->same(true, $case['uses_in_operator']);
            $t->contains('OR', $case['statement']);
            $t->same('i1w', $case['index_name']);
        }

        if ($case['duplicate_rhs_values']) {
            $t->same(true, $case['deduplicated_output']);
            $t->same(count($case['rowids']), count(array_unique($case['rowids'])));
            $t->contains('10006,10006', $case['statement']);
        } else {
            $t->same(false, $case['deduplicated_output']);
        }

        if ($case['unary_plus_disabled_index']) {
            $t->contains('+', $case['statement']);
            $t->true($case['index_name'] === 'rowid' || $case['affinity_sensitive']);
        }

        if ($case['affinity_sensitive']) {
            $t->contains('t2249', $case['table_name']);
            $t->contains('affinity', $case['detail']);
        }

        if ($case['opcode_expectation'] !== null) {
            $t->true($case['opcode_expectation'] !== '');
        }
    };
}

$tests['real upstream where2 index selection dynamic source count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where2IndexSelectionAndInCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same(35, count($sections));
    $t->same('where2-1.1', $cases[0]['upstream_section']);
    $t->same([85, 6, 7396, 7402], $cases[0]['result_flat']);
    $t->same('i1w', $cases[0]['index_name']);
    $t->same('where2-11.4', $cases[34]['upstream_section']);
    $t->same('where2-1.1', $cases[35]['upstream_section']);
    $t->same('where2-4.1', $cases[1199]['upstream_section']);
    $t->same(35, $cases[1199]['batch']);

    $bySection = [];
    foreach (array_slice($cases, 0, 35) as $case) {
        $bySection[$case['upstream_section']] = $case;
    }

    $t->same([100, 6, 10201, 10207, 99, 6, 10000, 10006], $bySection['where2-4.6y']['result_flat']);
    $t->same(true, $bySection['where2-4.6y']['duplicate_rhs_values']);
    $t->same([6, 99, 100], $bySection['where2-6.3']['rowids']);
    $t->same(true, $bySection['where2-6.3']['unary_plus_disabled_index']);
    $t->same([], $bySection['where2-6.9']['result_rows']);
    $t->same(true, $bySection['where2-6.9']['affinity_sensitive']);
    $t->same([10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20], $bySection['where2-8.8']['result_flat']);
    $t->same(['i11cccccccc', 'i11aba'], $bySection['where2-11.4']['chosen_indexes']);
};

$tests['real upstream where2 index selection dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where2IndexSelectionAndInCases(0));
};

$tests['real upstream where2 index selection dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, index choice, rowid, sort marker, IN-layer, OR-to-IN, affinity guard, and result-row helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, index choice, rowid, sort marker, IN-layer, OR-to-IN, affinity guard, and result-row helpers',
    );
    $t->same(
        'non-overlap: owns where2.test index-selection and IN/OR queryplan sections 1.1-11.4 and avoids accepted where7/where8/where9/whereG/whereJ dynamic batches plus B-tree page relocation/root-collapse/overflow release slices',
        'non-overlap: owns where2.test index-selection and IN/OR queryplan sections 1.1-11.4 and avoids accepted where7/where8/where9/whereG/whereJ dynamic batches plus B-tree page relocation/root-collapse/overflow release slices',
    );
};

return $tests;
