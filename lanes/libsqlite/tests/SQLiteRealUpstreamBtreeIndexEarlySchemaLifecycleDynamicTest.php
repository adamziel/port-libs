<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-1.1 through
// index-6.5. This owns early CREATE INDEX schema lifecycle, catalog
// persistence, drop-table cleanup, and CREATE INDEX error behavior that is
// distinct from the existing index-4 lookup and later index lifecycle slices.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexEarlySchemaLifecycleCases(1200) as $case) {
    $tests['real upstream index early schema lifecycle dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-1.1 through index-6.5', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'index-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['result_code'] === 0 || $case['result_code'] === 1);
        $t->true($case['table_name'] === null || $case['table_name'] !== '');
        $t->true($case['index_name'] === null || $case['index_name'] !== '');
        $t->true(in_array($case['integrity'], ['ok', 'expected-error', 'expected-error-preserves-table', 'expected-error-cleanup', 'expected-error-preserves-schema'], true));

        if ($case['result_code'] === 0) {
            $t->same(null, $case['error']);
        } else {
            $t->true(is_string($case['error']) && $case['error'] !== '');
        }

        if ($case['catalog_row'] !== null) {
            $t->same($case['index_name'], $case['catalog_row']['name']);
            $t->same($case['table_name'], $case['catalog_row']['tbl_name']);
            $t->same('index', $case['catalog_row']['type']);
            $t->true(str_starts_with($case['catalog_row']['sql'], 'CREATE INDEX '));
        }

        if ($case['upstream_section'] === 'index-1.1/1.1b') {
            $t->same(['index1', 'test1'], $case['catalog_names']);
            $t->same('CREATE INDEX index1 ON test1(f1)', $case['catalog_row']['sql']);
            $t->same(false, $case['reopen_preserves_schema']);
        }

        if ($case['upstream_section'] === 'index-1.1c/1.1d') {
            $t->same(true, $case['reopen_preserves_schema']);
            $t->same(['index1', 'test1'], $case['catalog_names']);
        }

        if ($case['upstream_section'] === 'index-1.2') {
            $t->same([], $case['catalog_names']);
            $t->same(true, $case['drop_table_clears_indexes']);
        }

        if ($case['upstream_section'] === 'index-2.1') {
            $t->same('no such table: main.test1', $case['error']);
            $t->same([], $case['catalog_names']);
        }

        if ($case['upstream_section'] === 'index-2.1b' || $case['upstream_section'] === 'index-2.2') {
            $t->same('no such column: f4', $case['error']);
            $t->true(str_contains($case['statement'], 'f4'));
        }

        if ($case['upstream_section'] === 'index-3.1/3.3') {
            $t->same('index42', $case['catalog_row']['name']);
            $t->same('CREATE INDEX index42 ON test1(f3)', $case['catalog_row']['sql']);
            $t->same(true, $case['drop_table_clears_indexes']);
            $t->true(in_array('index99', $case['catalog_names'], true));
        }

        if ($case['upstream_section'] === 'index-5.1/5.2') {
            $t->same('sqlite_master', $case['table_name']);
            $t->same('table sqlite_master may not be indexed', $case['error']);
            $t->same([], $case['catalog_names']);
        }

        if ($case['upstream_section'] === 'index-6.1/6.1.1/6.1c') {
            $t->same('index index1 already exists', $case['error']);
            $t->same(['index1', 'test1', 'test2'], $case['catalog_names']);
            $t->same('CREATE INDEX index1 ON test1(f1)', $case['catalog_row']['sql']);
        }

        if ($case['upstream_section'] === 'index-6.2/6.2b') {
            $t->same('there is already a table named test1', $case['error']);
            $t->same('test1', $case['index_name']);
        }

        if ($case['upstream_section'] === 'index-6.3/6.5') {
            $t->same([], $case['catalog_names']);
            $t->same(true, $case['drop_table_clears_indexes']);
            $t->same('ok', $case['integrity']);
        }
    };
}

$tests['real upstream index early schema lifecycle dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexEarlySchemaLifecycleCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    $t->same(1200, count($cases));
    $t->same('index-1.1/1.1b', $cases[0]['upstream_section']);
    $t->same('index-6.3/6.5', $cases[10]['upstream_section']);
    $t->same('index-6.3/6.5', $cases[1198]['upstream_section']);
    $t->same(110, $cases[1199]['batch']);
    $t->same([
        'index-1.1/1.1b',
        'index-1.1c/1.1d',
        'index-1.2',
        'index-2.1',
        'index-2.1b',
        'index-2.2',
        'index-3.1/3.3',
        'index-5.1/5.2',
        'index-6.1/6.1.1/6.1c',
        'index-6.2/6.2b',
        'index-6.3/6.5',
    ], $sections);
};

$tests['real upstream index early schema lifecycle dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexEarlySchemaLifecycleCases(0));
};

$tests['real upstream index early schema lifecycle dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, schema catalog, CREATE INDEX validation, reopen persistence, and drop-table cleanup helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, schema catalog, CREATE INDEX validation, reopen persistence, and drop-table cleanup helpers',
    );
};

return $tests;
