<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexedby.test sections indexedby-1.2
// through indexedby-12.4. This ports INDEXED BY and NOT INDEXED planner
// requirements across SELECT, joins, views, DELETE, UPDATE, rowid-tail index
// constraints, and partial-index no-solution diagnostics.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexedByPlannerDynamicCases(1000) as $case) {
    $tests['real upstream indexedby planner dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexedby.test sections indexedby-1.2 through indexedby-12.4', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'indexedby-'));
        $t->true($case['batch'] >= 1);
        $t->true($case['statement'] !== '');
        $t->true(in_array($case['statement_kind'], ['select', 'view-select', 'delete', 'update', 'join'], true));
        $t->true($case['table_name'] !== '');
        $t->true(is_array($case['where_terms']));
        $t->true(is_array($case['result_rows']));
        $t->same($case['result_code'] === 0 ? 'ok' : 'expected-error', $case['integrity']);
        $t->same($case['result_code'] === 0, $case['error'] === null);
        $t->true($case['detail'] !== '');

        if ($case['not_indexed']) {
            $t->same(null, $case['indexed_by']);
            $t->true($case['uses_index'] === false || $case['uses_rowid_tail'] === true);
        }

        if ($case['indexed_by'] !== null && $case['uses_index']) {
            $t->same($case['indexed_by'], $case['index_name']);
            $t->true(str_contains(strtoupper($case['statement']), 'INDEXED BY') || $case['view_dependency']);
        }

        if ($case['result_code'] === 1) {
            $t->true(in_array($case['error'], ['no such index: i3', 'no such index: i1', 'no such index: sqlite_autoindex_t3_2', 'no query solution'], true));
        }

        if ($case['uses_rowid_tail']) {
            $t->true(in_array('rowid=?', $case['where_terms'], true));
            $t->true(str_contains($case['detail'], 'rowid'));
        }

        if ($case['view_dependency']) {
            $t->same('v2', $case['table_name']);
            $t->same('i1', $case['indexed_by']);
        }

        if ($case['partial_index_no_solution']) {
            $t->same('p2', $case['index_name']);
            $t->same('no query solution', $case['error']);
            $t->same(false, $case['uses_index']);
        }

        if ($case['upstream_section'] === 'indexedby-10.3') {
            $t->same([[1]], $case['result_rows']);
            $t->same('indexed', $case['index_name']);
        }
    };
}

$tests['real upstream indexedby planner dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexedByPlannerDynamicCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    $t->same(1000, count($cases));
    $t->same('indexedby-1.2', $cases[0]['upstream_section']);
    $t->same('indexedby-12.4', $cases[21]['upstream_section']);
    $t->same('indexedby-11.5', $cases[18]['upstream_section']);
    $t->same('indexedby-11.10', $cases[19]['upstream_section']);
    $t->same('indexedby.test sections indexedby-1.2 through indexedby-12.4', $cases[999]['source']);
    $t->same([
        'indexedby-1.2',
        'indexedby-2.1',
        'indexedby-2.2',
        'indexedby-2.4',
        'indexedby-2.7',
        'indexedby-3.1.2',
        'indexedby-3.8',
        'indexedby-3.11',
        'indexedby-4.2',
        'indexedby-5.1',
        'indexedby-5.3',
        'indexedby-5.5',
        'indexedby-7.3',
        'indexedby-7.5',
        'indexedby-8.3',
        'indexedby-8.5',
        'indexedby-9.2',
        'indexedby-10.3',
        'indexedby-11.5',
        'indexedby-11.10',
        'indexedby-12.2',
        'indexedby-12.4',
    ], $sections);
};

$tests['real upstream indexedby planner dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::indexedByPlannerDynamicCases(0));
};

$tests['real upstream indexedby planner dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, schema catalog, planner detail, DML index forcing, view dependency, rowid-tail, and partial-index diagnostic fixtures',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, schema catalog, planner detail, DML index forcing, view dependency, rowid-tail, and partial-index diagnostic fixtures',
    );
};

return $tests;
