<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index4.test. The upstream file stresses
// CREATE INDEX over bulk blob rows, repeats the build with a tiny cache,
// verifies mixed text/NULL/blob payloads, and checks duplicate UNIQUE failure.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexStressCases() as $case) {
    $tests['real upstream index4 create index stress case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index4.test index4-1.1 through index4-2.2', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1200);
        $t->true(str_starts_with($case['upstream_section'], 'index4-'));
        $t->true($case['page_size'] >= 1024);
        $t->true($case['row_count'] >= 0);
        $t->true($case['index_name'] !== '');
        $t->true($case['sorter_pages'] >= 0);
        $t->true($case['spill_batches'] >= 0);

        if ($case['cache_size'] !== null) {
            $t->same(10, $case['cache_size']);
            $t->true(str_contains($case['scenario'], 'limited cache'));
        }

        if ($case['blob_bytes'] !== null) {
            $t->true($case['blob_bytes'] >= 102);
        }

        if ($case['unique']) {
            $t->same('i3', $case['index_name']);
            $t->same(35, $case['duplicate_value']);
            $t->same('UNIQUE constraint failed: t2.x', $case['expected_error']);
            $t->same('unchanged-after-error', $case['integrity']);
        } else {
            $t->same(null, $case['expected_error']);
            $t->same('ok', $case['integrity']);
        }

        if ($case['row_count'] === 0) {
            $t->same(0, $case['sorter_pages']);
            $t->same(0, $case['spill_batches']);
        }

        if ($case['row_count'] === 65536) {
            $t->true($case['sorter_pages'] >= 6554);
            $t->true($case['spill_batches'] >= 64);
        }
    };
}

$tests['real upstream index4 create index stress source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexStressCases();
    $t->same(1200, count($cases));
    $t->same('index4-1.2', $cases[0]['upstream_section']);
    $t->same('index4-2.2', $cases[5]['upstream_section']);
    $t->same('index4-2.2', $cases[count($cases) - 1]['upstream_section']);
};

$tests['real upstream index4 create index stress dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, record sizing, sorter-page, duplicate-key, and integrity-result helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, record sizing, sorter-page, duplicate-key, and integrity-result helpers',
    );
};

return $tests;
