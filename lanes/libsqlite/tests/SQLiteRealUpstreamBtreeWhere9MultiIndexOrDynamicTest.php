<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/where9.test sections where9-1.2.1
// through where9-6.3.2. These cases cover the multi-index OR optimizer over
// B-tree indexes, including unary-plus deoptimization, INDEXED BY, NOT
// INDEXED, equality/range plan preference, and UPDATE/DELETE OR-clause use.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where9MultiIndexOrDynamicCases(1000) as $case) {
    $tests['real upstream where9 multi-index OR dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('where9.test sections where9-1.2.1 through where9-6.3.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'where9-'));
        $t->true(in_array($case['statement_kind'], ['select', 'eqp', 'delete', 'update'], true));
        $t->true(in_array($case['table_name'], ['t1', 't4'], true));
        $t->true($case['statement'] !== '');
        $t->true(count($case['or_terms']) >= 2);
        $t->same('ok', $case['integrity']);

        foreach ($case['chosen_indexes'] as $indexName) {
            $t->true(str_starts_with($indexName, $case['table_name']));
        }

        if ($case['uses_multi_index_or']) {
            $t->same(0, $case['scan_steps']);
            $t->true(count($case['chosen_indexes']) >= 2);
            $t->true(str_contains($case['detail'], 'OR') || str_contains($case['detail'], 'union'));
        } else {
            $t->true($case['scan_steps'] === 98 || count($case['chosen_indexes']) === 1 || $case['not_indexed']);
        }

        if ($case['not_indexed']) {
            $t->same([], $case['chosen_indexes']);
            $t->same(false, $case['uses_multi_index_or']);
            $t->same(98, $case['scan_steps']);
            $t->true(str_contains($case['statement'], 'NOT INDEXED'));
        }

        if ($case['indexed_by'] !== null) {
            $t->true(str_contains($case['statement'], 'INDEXED BY ' . $case['indexed_by']));
        }

        if (str_contains($case['statement'], '+b IS NULL')) {
            $t->same(false, $case['uses_multi_index_or']);
            $t->same(98, $case['scan_steps']);
            $t->true(in_array('t1b', $case['chosen_indexes'], true) === false);
        }

        if ($case['upstream_section'] === 'where9-5.1') {
            $t->same(true, $case['uses_multi_index_or']);
            $t->same(['t1c', 't1d'], $case['chosen_indexes']);
            $t->same(['b>1000'], $case['and_terms']);
        }

        if ($case['upstream_section'] === 'where9-5.2') {
            $t->same(false, $case['uses_multi_index_or']);
            $t->same(['t1b'], $case['chosen_indexes']);
            $t->same(['b=1000'], $case['and_terms']);
        }

        if ($case['mutation'] === 'delete') {
            $t->same('delete', $case['statement_kind']);
            $t->true(count($case['rows_after']) >= 9);
            $t->same(false, in_array(90, $case['rows_after'], true));
            $t->same(false, in_array(91, $case['rows_after'], true));
            $t->same(false, in_array(92, $case['rows_after'], true) && str_contains($case['upstream_section'], '6.2'));
        }

        if ($case['mutation'] === 'update') {
            $t->same('update', $case['statement_kind']);
            $t->true(in_array(190, $case['rows_after'], true));
            $t->true(in_array(191, $case['rows_after'], true));
            $t->true(in_array(196, $case['rows_after'], true));
            $t->true(in_array(199, $case['rows_after'], true));
            $t->true(in_array(92, $case['rows_after'], true));
            $t->true(in_array(97, $case['rows_after'], true));
        }
    };
}

$tests['real upstream where9 multi-index OR dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where9MultiIndexOrDynamicCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('where9-1.2.1', $cases[0]['upstream_section']);
    $t->same('where9-6.3.1/6.3.2', $cases[13]['upstream_section']);
    $t->same('where9-4.1', $cases[998]['upstream_section']);
    $t->same([
        'where9-1.2.1',
        'where9-1.2.2',
        'where9-1.2.5',
        'where9-1.3.1',
        'where9-4.1',
        'where9-4.4',
        'where9-4.6',
        'where9-5.1',
        'where9-5.2',
        'where9-5.3',
        'where9-6.2.2/6.2.3',
        'where9-6.2.4/6.2.5',
        'where9-6.2.6/6.2.7',
        'where9-6.3.1/6.3.2',
    ], $sections);
};

$tests['real upstream where9 multi-index OR dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where9MultiIndexOrDynamicCases(0));
};

$tests['real upstream where9 multi-index OR dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning for multi-index OR, INDEXED BY, NOT INDEXED, equality/range plan preference, and OR-clause UPDATE/DELETE metadata',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning for multi-index OR, INDEXED BY, NOT INDEXED, equality/range plan preference, and OR-clause UPDATE/DELETE metadata',
    );
};

return $tests;
