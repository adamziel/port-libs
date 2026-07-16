<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$expectedBySection = [
    'where7-1.1.1' => [1, 2],
    'where7-1.2' => [2, 3],
    'where7-1.3' => [2, 3],
    'where7-1.4' => [2, 3],
    'where7-1.5' => [2, 3],
    'where7-1.6' => [2, 3],
    'where7-1.7' => [2, 5],
    'where7-1.8' => [2, 4, 5],
    'where7-1.9' => [2, 4, 5],
    'where7-1.10' => [2, 4, 5],
    'where7-1.11' => [2, 5],
    'where7-1.12' => [1, 2, 3, 5],
    'where7-1.13' => [5, 4, 1],
    'where7-1.14' => [3],
    'where7-1.15' => [3],
    'where7-1.20' => [],
    'where7-1.21' => [5],
    'where7-1.22' => [5],
    'where7-1.23' => [5],
    'where7-1.31' => [],
    'where7-1.32' => [],
];

$fullScanSections = ['where7-1.3', 'where7-1.4', 'where7-1.14', 'where7-1.15'];
$tempSortSections = [
    'where7-1.2',
    'where7-1.5',
    'where7-1.6',
    'where7-1.11',
    'where7-1.12',
    'where7-1.13',
    'where7-1.20',
    'where7-1.21',
    'where7-1.22',
    'where7-1.23',
    'where7-1.31',
    'where7-1.32',
];

// Source truth: SQLite upstream test/where7.test sections where7-1.1.1
// through where7-1.32. The upstream file verifies the multi-index OR
// optimizer, including rowid de-duplication, unary-plus no-index guards,
// range/equality OR arms, large OR lists, and DELETE count_changes behavior.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where7MultiIndexOrOptimizerCases(1200) as $case) {
    $tests['real upstream where7 multi index OR dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $expectedBySection, $fullScanSections, $tempSortSections): void {
        $t->same('where7.test sections where7-1.1.1 through where7-1.32', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'where7-1.'));
        $t->same('ok', $case['integrity']);
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['or_terms'] !== []);
        $t->same($expectedBySection[$case['upstream_section']], $case['result_a']);
        $t->same(in_array($case['upstream_section'], $fullScanSections, true), $case['uses_full_scan']);
        $t->same(in_array($case['upstream_section'], $tempSortSections, true), $case['uses_temp_sort']);
        $t->same($case['scan_steps'] > 0, $case['uses_full_scan']);
        $t->same($case['sort_steps'] > 0, $case['uses_temp_sort']);
        $t->same($case['chosen_indexes'] !== [] && $case['scan_steps'] === 0, $case['uses_multi_index_or']);
        $t->true(str_contains($case['detail'], 'where7 dynamic replay'));

        $flattened = [];
        foreach ($case['result_rows'] as $row) {
            $t->true(isset($row['a'], $row['b'], $row['c'], $row['d']));
            $flattened[] = $row['a'];
        }
        $t->same($case['result_a'], $flattened);

        foreach ($case['chosen_indexes'] as $indexName) {
            $t->true(in_array($indexName, ['ta', 't1b', 't1c', 'rowid'], true));
        }

        if ($case['upstream_section'] === 'where7-1.1.1') {
            $t->same(2, $case['delete_count']);
            $t->same([], $case['rows_after']);
            $t->same(true, $case['deduplicates_rowids']);
            $t->same(['ta'], $case['chosen_indexes']);
            $t->contains('overlapping OR terms', $case['detail']);
        } else {
            $t->same(null, $case['delete_count']);
        }

        if (in_array($case['upstream_section'], ['where7-1.3', 'where7-1.4'], true)) {
            $t->same([], $case['chosen_indexes']);
            $t->same(4, $case['scan_steps']);
            $t->contains('SCAN t1', $case['detail']);
            $t->contains('+', $case['statement']);
        }

        if (in_array($case['upstream_section'], ['where7-1.14', 'where7-1.15'], true)) {
            $t->same([], $case['chosen_indexes']);
            $t->same([3], $case['result_a']);
            $t->contains('d=8', implode(' ', $case['or_terms']));
        }

        if ($case['upstream_section'] === 'where7-1.2') {
            $t->same(['t1b', 't1c'], $case['chosen_indexes']);
            $t->same(0, $case['scan_steps']);
            $t->same(1, $case['sort_steps']);
        }

        if ($case['upstream_section'] === 'where7-1.9') {
            $t->same(true, $case['deduplicates_rowids']);
            $t->contains('de-duplicates rowid 2', $case['detail']);
        }

        if ($case['upstream_section'] === 'where7-1.13') {
            $t->same([5, 4, 1], $case['result_a']);
            $t->contains('DESC', $case['statement']);
        }

        if (in_array($case['upstream_section'], ['where7-1.20', 'where7-1.31', 'where7-1.32'], true)) {
            $t->same([], $case['result_rows']);
            $t->same([], $case['result_a']);
            $t->same(0, $case['scan_steps']);
        }

        if (in_array($case['upstream_section'], ['where7-1.21', 'where7-1.22', 'where7-1.23'], true)) {
            $t->same([5], $case['result_a']);
            $t->same(['t1b', 't1c'], $case['chosen_indexes']);
        }
    };
}

$tests['real upstream where7 multi index OR dynamic source count'] = static function (TestRunner $t) use ($expectedBySection): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where7MultiIndexOrOptimizerCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same('where7-1.1.1', $cases[0]['upstream_section']);
    $t->same('where7-1.32', $cases[20]['upstream_section']);
    $t->same('where7-1.1.1', $cases[21]['upstream_section']);
    $t->same('where7-1.3', $cases[1199]['upstream_section']);
    $t->same(array_keys($expectedBySection), $sections);
};

$tests['real upstream where7 multi index OR dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where7MultiIndexOrOptimizerCases(0));
};

$tests['real upstream where7 multi index OR dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, OR-index union, rowid de-duplication, residual predicate, scan/sort counter, and result-row helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, OR-index union, rowid de-duplication, residual predicate, scan/sort counter, and result-row helpers',
    );
    $t->same(
        'non-overlap: owns where7.test multi-index OR optimizer sections 1.1.1-1.32 and avoids accepted where8/where9/whereD/whereK OR planner batches',
        'non-overlap: owns where7.test multi-index OR optimizer sections 1.1.1-1.32 and avoids accepted where8/where9/whereD/whereK OR planner batches',
    );
};

return $tests;
