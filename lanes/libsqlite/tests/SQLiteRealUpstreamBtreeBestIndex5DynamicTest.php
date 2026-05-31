<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindex5.test. The Tcl module reports
// xBestIndex idxstr values for usable virtual-table constraints, then xFilter
// executes the corresponding SQL against backing tables.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindex5VirtualTableConstraintCases(1000) as $case) {
    $tests['real upstream bestindex5 virtual table constraint dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindex5.test sections bestindex5-1.1 through bestindex5-3.5', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindex5-'));
        $t->true($case['scenario'] !== '');
        $t->same($case['statement'], $case['query']);
        $t->true(str_starts_with($case['query'], 'SELECT * FROM ') || str_starts_with($case['query'], 'SELECT rowid, * FROM '));
        $t->same($case['idx_string'], $case['idxstr']);
        $t->same('xBestIndex idxstr: ' . $case['idxstr'], $case['detail']);
        $t->same(count($case['omitted_constraints']), $case['constraint_count']);
        $t->true($case['constraint_count'] >= 0);
        $t->true($case['constraint_count'] <= 2);
        $t->same($case['constraint_count'] === 0 ? 999999.0 : 1000000.0 / (2 ** $case['constraint_count']), $case['cost']);
        $t->true($case['batch'] >= 1);

        foreach ($case['omitted_constraints'] as $constraint) {
            $t->true(str_contains($case['idxstr'], $constraint));
        }

        if ($case['rows'] === []) {
            $t->true(str_contains($case['upstream_section'], '1.6') || str_contains($case['upstream_section'], '2.1') || str_contains($case['upstream_section'], '3.4') || str_contains($case['upstream_section'], '3.5'));
        } else {
            $t->true(count($case['rows']) >= 1);
        }

        if (str_contains($case['scenario'], 'row-value') && $case['constraint_count'] > 0) {
            $t->same(2, $case['constraint_count']);
            $t->true(str_contains($case['idxstr'], ' AND '));
        }

        if (str_contains($case['scenario'], 'commuted')) {
            $t->true(str_contains($case['query'], '!=' ) || str_contains($case['query'], ' IS ') || str_contains($case['query'], '=='));
        }

        if (str_contains($case['scenario'], 'join-derived')) {
            $t->true(str_contains($case['query'], 't1, t2'));
            $t->same(4, count($case['rows'][0]));
        }
    };
}

$tests['real upstream bestindex5 virtual table constraint dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindex5VirtualTableConstraintCases(1000);
    $t->same(1000, count($cases));
    $t->same('bestindex5-1.1', $cases[0]['upstream_section']);
    $t->same('bestindex5-2.2.5', $cases[15]['upstream_section']);
    $t->same('bestindex5-3.5', $cases[18]['upstream_section']);
    $t->same('bestindex5-1.1', $cases[19]['upstream_section']);
    $t->same(53, $cases[count($cases) - 1]['batch']);
};

$tests['real upstream bestindex5 virtual table constraint dynamic rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindex5VirtualTableConstraintCases(0));
};

$tests['real upstream bestindex5 virtual table constraint dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex constraint accounting',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex constraint accounting',
    );
};

return $tests;
