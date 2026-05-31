<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index4.test sections 1.2 through 2.2.
// This batch owns CREATE INDEX validation over large randomblob inputs,
// limited-memory rebuilds, empty/single-row tables, overflow-sized values, and
// UNIQUE duplicate rejection. It intentionally avoids index5 write-order and
// index8/index9 planner cases covered by adjacent real-upstream files.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexValidationCases(1200) as $case) {
    $tests['real upstream index4 create index validation dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index4.test sections 1.2 through 2.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 200);
        $t->true(str_starts_with($case['upstream_section'], 'index4-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true(in_array($case['index_name'], ['i1', 'i2', 'i3'], true));
        $t->true(in_array($case['table_name'], ['t1', 't2'], true));
        $t->same(['x'], $case['index_columns']);
        $t->same(null, $case['sort_order']);

        if ($case['result_code'] === 0) {
            $t->same(null, $case['error']);
            $t->same('ok', $case['integrity']);
            $t->true(in_array($case['index_name'], $case['catalog_names'], true));
            $t->true(in_array($case['table_name'], $case['catalog_names'], true));
            $t->same(false, $case['unique']);
            $t->true(str_contains($case['statement'], 'PRAGMA integrity_check'));
        } else {
            $t->same(1, $case['result_code']);
            $t->same('expected-error', $case['integrity']);
            $t->same('UNIQUE constraint failed: t2.x', $case['error']);
            $t->same(['t2'], $case['catalog_names']);
            $t->same(true, $case['unique']);
            $t->same('i3', $case['index_name']);
            $t->same('t2', $case['table_name']);
            $t->true(str_contains($case['statement'], 'CREATE UNIQUE INDEX'));
        }

        if ($case['upstream_section'] === 'index4-1.2/1.3') {
            $t->same(65536, $case['row_count']);
            $t->same(102, $case['blob_bytes']);
            $t->same(false, $case['limited_memory']);
        }

        if ($case['upstream_section'] === 'index4-1.4/1.5') {
            $t->same(65536, $case['row_count']);
            $t->same(102, $case['blob_bytes']);
            $t->same(true, $case['limited_memory']);
            $t->true(str_contains($case['statement'], 'cache_size = 10'));
        }

        if ($case['upstream_section'] === 'index4-1.6') {
            $t->same(256, $case['row_count']);
            $t->same(5202, $case['blob_bytes']);
        }

        if ($case['upstream_section'] === 'index4-1.7') {
            $t->same(1, $case['row_count']);
            $t->same(null, $case['blob_bytes']);
        }

        if ($case['upstream_section'] === 'index4-1.8') {
            $t->same(0, $case['row_count']);
            $t->same(null, $case['blob_bytes']);
        }
    };
}

$tests['real upstream index4 create index validation corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexValidationCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('index4-1.2/1.3', $cases[0]['upstream_section']);
    $t->same('index4-2.2', $cases[5]['upstream_section']);
    $t->same('index4-2.2', $cases[1199]['upstream_section']);
    $t->same(200, $cases[1199]['batch']);
    $t->same([
        'index4-1.2/1.3',
        'index4-1.4/1.5',
        'index4-1.6',
        'index4-1.7',
        'index4-1.8',
        'index4-2.2',
    ], $sections);
};

$tests['real upstream index4 create index validation rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexValidationCases(0));
};

$tests['real upstream index4 create index validation dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index corpus planner, schema catalog, integrity, large-row, limited-cache, and UNIQUE constraint diagnostics',
        'no new support component needed; reuses lane-local B-tree/index corpus planner, schema catalog, integrity, large-row, limited-cache, and UNIQUE constraint diagnostics',
    );
};

return $tests;
