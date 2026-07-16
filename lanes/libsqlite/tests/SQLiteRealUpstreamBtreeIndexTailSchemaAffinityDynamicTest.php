<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-14.1 through
// index-23.1. This tail cluster covers mixed-affinity indexed range scans,
// numeric-looking TEXT ordering, automatic index catalog rules, reserved
// sqlite_* names, conflict-policy validation, temp-index scope, and
// expression-index rows.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexTestTailSchemaAffinityCases(1000) as $case) {
    $tests['real upstream index.test tail schema affinity dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-14.1 through index-23.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'index-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['kind'] !== '');
        $t->true($case['sql'] !== '');
        $t->same($case['result_code'] === 0 ? 'ok' : 'expected-error', $case['integrity']);
        $t->same($case['result_code'] === 0, $case['error'] === null);
        $t->same(array_values($case['catalog_indexes']), $case['catalog_indexes']);
        foreach ($case['result_rows'] as $row) {
            $t->same(array_values($row), $row);
        }
        if ($case['uses_index']) {
            $t->true($case['index_name'] !== null);
            $t->true(in_array($case['index_name'], $case['catalog_indexes'], true));
        }
        if ($case['kind'] === 'affinity-order') {
            $t->same([3, 5, 2, 1, 4], $case['expected']);
            $t->same('t6i1', $case['index_name']);
        }
        if ($case['kind'] === 'affinity-range') {
            $t->true(in_array($case['upstream_section'], ['index-14.4', 'index-14.5', 'index-14.6', 'index-14.7', 'index-14.8', 'index-14.9', 'index-14.10', 'index-14.11'], true));
            $t->same('t6i1', $case['index_name']);
        }
        if ($case['kind'] === 'numeric-text-order') {
            $t->same([13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7], $case['expected']);
        }
        if ($case['kind'] === 'numeric-type-filter') {
            $t->same([1, 2, 3, 5, 6, 8, 10, 11, 12, 13, 14, 15], $case['expected']);
        }
        if ($case['kind'] === 'autoindex-count') {
            $t->true(in_array($case['expected'], [[1], [2]], true));
            $t->true(str_starts_with((string) $case['index_name'], 'sqlite_autoindex_t7_'));
        }
        if ($case['kind'] === 'autoindex-drop-error') {
            $t->same(1, $case['result_code']);
            $t->same('index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped', $case['error']);
        }
        if ($case['kind'] === 'reserved-name-error') {
            $t->same(1, $case['result_code']);
            $t->true(str_starts_with((string) $case['error'], 'object name reserved for internal use: sqlite_'));
            $t->same(false, $case['uses_index']);
        }
        if ($case['kind'] === 'conflict-policy') {
            $t->same(1, $case['result_code']);
            $t->true(str_contains((string) $case['error'], 'UNIQUE constraint failed') || str_contains((string) $case['error'], 'conflicting ON CONFLICT'));
        }
        if ($case['kind'] === 'temp-index-scope') {
            $t->true($case['result_code'] === 0 || str_contains((string) $case['error'], 'TEMP index'));
        }
        if ($case['kind'] === 'expression-index') {
            $t->same(0, $case['result_code']);
            $t->true($case['uses_index']);
            $t->true(in_array($case['index_name'], ['x2', 't1x1', 'index_0'], true));
        }
        $t->true(str_contains($case['detail'], 'dynamic replay'));
    };
}

$tests['real upstream index.test tail schema affinity dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexTestTailSchemaAffinityCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('index-14.1', $cases[0]['upstream_section']);
    $t->same('index-23.1', $cases[28]['upstream_section']);
    $t->true(in_array('index-18.4', $sections, true));
    $t->true(in_array('index-21.2', $sections, true));
    $t->true(in_array('index-23.0', $sections, true));
};

return $tests;
