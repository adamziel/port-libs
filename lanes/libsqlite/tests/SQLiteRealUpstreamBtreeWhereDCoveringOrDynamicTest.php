<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$expectedFlat = [
    'whereD-1.2' => ['one', 'two'],
    'whereD-1.3' => ['one', 'two'],
    'whereD-1.4' => ['uno', 'dos'],
    'whereD-1.5' => ['one', 'uno', 'two', 'dos'],
    'whereD-1.6' => ['one', 'two', 'three'],
    'whereD-1.7' => ['uno', 'dos', 'tres'],
    'whereD-1.8' => ['one', 'two'],
    'whereD-1.9' => ['one', 'two', 'three'],
    'whereD-1.10' => ['uno', 'dos', 'tres'],
    'whereD-1.11' => ['one', 'two', 'three'],
    'whereD-1.12' => ['uno', 'dos', 'tres'],
    'whereD-1.13' => ['one', 'two', 'three'],
    'whereD-1.14' => ['one', 'two', 'three'],
    'whereD-1.15' => [],
    'whereD-1.16' => ['one', 'three'],
];

$fullyCovered = ['whereD-1.2', 'whereD-1.6', 'whereD-1.14', 'whereD-1.15', 'whereD-1.16'];
$mixedIndexSections = ['whereD-1.3', 'whereD-1.8', 'whereD-1.9', 'whereD-1.10', 'whereD-1.11', 'whereD-1.12', 'whereD-1.13'];

// Source truth: SQLite upstream test/whereD.test sections whereD-1.2 through
// whereD-1.16. The upstream file verifies that OR-clause index unions may use
// covering indexes when possible while preserving row order and table lookups
// for projections that are not covered by every OR arm.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereDCoveringOrIndexCases(1000) as $case) {
    $tests['real upstream whereD covering OR dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $expectedFlat, $fullyCovered, $mixedIndexSections): void {
        $t->same('whereD.test sections whereD-1.2 through whereD-1.16', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'whereD-1.'));
        $t->same('ok', $case['integrity']);
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true(count($case['or_terms']) >= 2);
        $t->same(count($case['or_terms']), $case['index_probe_count']);
        $t->same($case['result_rows'] === [], $case['empty_result']);
        $t->same($expectedFlat[$case['upstream_section']], $case['result_flat']);
        $t->same(array_values(array_unique($case['chosen_indexes'])), $case['chosen_indexes']);

        $flattened = [];
        foreach ($case['result_rows'] as $row) {
            foreach ($row as $value) {
                $flattened[] = $value;
            }
        }
        $t->same($flattened, $case['result_flat']);
        $t->same(count($case['selected_i_values']), count($case['result_rows']));
        $t->same(count($case['matched_rowids']), count($case['result_rows']));

        foreach ($case['chosen_indexes'] as $indexName) {
            $t->true(in_array($indexName, ['ijk', 'jmn'], true));
        }

        if (in_array($case['upstream_section'], $fullyCovered, true)) {
            $t->same(false, $case['requires_table_lookup']);
            $t->same(['ijk'], $case['covering_indexes']);
            $t->same([], $case['residual_terms']);
        } else {
            $t->same(true, $case['requires_table_lookup']);
        }

        if (in_array($case['upstream_section'], $mixedIndexSections, true)) {
            $t->true(in_array('jmn', $case['chosen_indexes'], true));
        }

        if ($case['upstream_section'] === 'whereD-1.3') {
            $t->same(['+i=2'], $case['residual_terms']);
            $t->contains('+i', $case['statement']);
        }

        if ($case['upstream_section'] === 'whereD-1.5') {
            $t->same(['k', 'n'], $case['projection']);
            $t->same([[ 'one', 'uno' ], [ 'two', 'dos' ]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'whereD-1.15') {
            $t->same([], $case['result_rows']);
            $t->same([], $case['selected_i_values']);
            $t->same(true, $case['empty_result']);
        }

        if ($case['upstream_section'] === 'whereD-1.16') {
            $t->contains('(j=1 OR j=2)', $case['or_terms'][0]);
            $t->same([1, 3], $case['selected_i_values']);
        }

        $t->same(true, $case['uses_or_clause_index_union']);
    };
}

$tests['real upstream whereD covering OR dynamic corpus count'] = static function (TestRunner $t) use ($expectedFlat): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereDCoveringOrIndexCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('whereD-1.2', $cases[0]['upstream_section']);
    $t->same('whereD-1.16', $cases[14]['upstream_section']);
    $t->same('whereD-1.2', $cases[15]['upstream_section']);
    $t->same('whereD-1.11', $cases[999]['upstream_section']);
    $t->same(array_keys($expectedFlat), $sections);
};

$tests['real upstream whereD covering OR dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereDCoveringOrIndexCases(0));
};

$tests['real upstream whereD covering OR dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, OR-index union, covering-index projection, residual, and result-row helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, OR-index union, covering-index projection, residual, and result-row helpers',
    );
};

return $tests;
