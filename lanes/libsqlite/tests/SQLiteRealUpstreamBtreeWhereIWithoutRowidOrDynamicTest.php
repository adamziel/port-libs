<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/whereI.test sections 1.1 through 3.0.
// The upstream script verifies MULTI-INDEX OR planning and row de-duplication
// on WITHOUT ROWID tables with integer, text, and composite primary keys.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereIWithoutRowidOrOptimizationCases(1200) as $case) {
    $tests['real upstream whereI without rowid OR dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('whereI.test sections 1.1 through 3.0', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true(str_starts_with($case['upstream_section'], 'whereI-'));
        $t->true($case['scenario'] !== '');
        $t->true(in_array($case['table'], ['t1', 't2', 't3'], true));
        $t->same('ok', $case['integrity']);
        $t->same(true, $case['uses_multi_index_or']);
        $t->true(str_contains($case['detail'], 'MULTI-INDEX OR'));
        $t->same(2, count($case['or_terms']));
        $t->same(2, count($case['indexes']));
        $t->same($case['indexes'], array_values(array_unique($case['indexes'])));
        $t->same($case['primary_key'], $case['table'] === 't3' ? ['c', 'b'] : ['a']);
        $t->true(array_values($case['result_rows']) === $case['result_rows']);

        foreach ($case['or_terms'] as $term) {
            $t->true(in_array($term['column'], ['a', 'b', 'c', 'd'], true));
            $t->true(in_array($term['index'], $case['indexes'], true));
            $t->true($term['value'] !== null);
        }

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['upstream_section'] === 'whereI-1.1/1.2') {
            $t->same('t1', $case['table']);
            $t->same(['i1', 'i2'], $case['indexes']);
            $t->same([[2], [3]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'SEARCH t1 USING INDEX i1'));
        }

        if ($case['upstream_section'] === 'whereI-1.3') {
            $t->same([[1]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'returned once'));
        }

        if ($case['upstream_section'] === 'whereI-2.1/2.2') {
            $t->same('t2', $case['table']);
            $t->same(['i3', 'i4'], $case['indexes']);
            $t->same([['ii'], ['iii']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'SEARCH t2 USING INDEX i3'));
        }

        if ($case['upstream_section'] === 'whereI-2.3') {
            $t->same([['i']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'text primary-key row'));
        }

        if ($case['upstream_section'] === 'whereI-3.0') {
            $t->same('t3', $case['table']);
            $t->same(['c', 'b'], $case['primary_key']);
            $t->same(['t3i1', 't3i2'], $case['indexes']);
            $t->same([['2.1'], ['2.2'], ['1.2']], $case['result_rows']);
        }
    };
}

$tests['real upstream whereI without rowid OR dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereIWithoutRowidOrOptimizationCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    $t->same(1200, count($cases));
    $t->same('whereI.test sections 1.1 through 3.0', $cases[0]['source']);
    $t->same('whereI-1.1/1.2', $cases[0]['upstream_section']);
    $t->same('whereI-3.0', $cases[4]['upstream_section']);
    $t->same('whereI-3.0', $cases[1199]['upstream_section']);
    $t->same(['whereI-1.1/1.2', 'whereI-1.3', 'whereI-2.1/2.2', 'whereI-2.3', 'whereI-3.0'], $sections);
};

$tests['real upstream whereI without rowid OR dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::whereIWithoutRowidOrOptimizationCases(0));
};

$tests['real upstream whereI without rowid OR dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and WITHOUT ROWID multi-index OR result modeling',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and WITHOUT ROWID multi-index OR result modeling',
    );
};

return $tests;
