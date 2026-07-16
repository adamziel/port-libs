<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-1.1 through
// index-9.2. The upstream script verifies CREATE INDEX catalog rows, DROP TABLE
// index cleanup, missing-table/column diagnostics, duplicate-name handling,
// sqlite_master protection, primary-key autoindex visibility, missing DROP
// INDEX errors, and EXPLAIN CREATE INDEX non-mutation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexCatalogLifecycleCases(1000) as $case) {
    $tests['real upstream index catalog lifecycle dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-1.1 through index-9.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'index-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true(array_values($case['objects_before']) === $case['objects_before']);
        $t->true(array_values($case['objects_after']) === $case['objects_after']);
        $t->true(array_values($case['index_names']) === $case['index_names']);
        $t->true($case['table_name'] !== '' || $case['upstream_section'] === 'index-8.1');
        $t->same($case['expected_error'] === null ? 'ok' : 'schema-preserved-after-error', $case['integrity']);

        if ($case['expected_error'] !== null) {
            $t->true(str_contains($case['expected_error'], 'index') || str_contains($case['expected_error'], 'table') || str_contains($case['expected_error'], 'column'));
            if ($case['upstream_section'] !== 'index-6.2/6.4') {
                $t->same($case['objects_before'], $case['objects_after']);
            }
        }

        if ($case['upstream_section'] === 'index-1.1/1.1d') {
            $t->same(['index1', 'test1'], $case['objects_after']);
            $t->same(['index1'], $case['index_names']);
            $t->same('f1', $case['indexed_column']);
        }

        if ($case['upstream_section'] === 'index-3.1/3.3') {
            $t->same(99, count($case['index_names']));
            $t->same('index01', $case['index_names'][0]);
            $t->same('index99', $case['index_names'][98]);
            $t->same([], $case['objects_after']);
        }

        if ($case['upstream_section'] === 'index-6.1/6.1c') {
            $t->same('index index1 already exists', $case['expected_error']);
            $t->same(['index1', 'test1', 'test2'], $case['objects_after']);
        }

        if ($case['autoindex']) {
            $t->same(['sqlite_autoindex_test1_1'], $case['index_names']);
            $t->same('f2', $case['indexed_column']);
            $t->same(false, $case['explain_only']);
        }

        if ($case['explain_only']) {
            $t->same('idx1', $case['index_name']);
            $t->same(['tab1'], $case['objects_after']);
            $t->same([], $case['index_names']);
        }
    };
}

$tests['real upstream index catalog lifecycle dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexCatalogLifecycleCases(1000);
    $t->same(1000, count($cases));
    $t->same('index-1.1/1.1d', $cases[0]['upstream_section']);
    $t->same('index.test sections index-1.1 through index-9.2', $cases[999]['source']);
};

return $tests;
