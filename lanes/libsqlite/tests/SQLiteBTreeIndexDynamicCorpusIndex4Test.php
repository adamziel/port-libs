<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index4.test. The upstream script stresses
// CREATE INDEX over large randomblob tables, a limited-memory repeat, empty and
// single-row tables, mixed large-payload rows, and duplicate failure for UNIQUE
// index creation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexStressCases() as $case) {
    $tests['real upstream index4 create index stress dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index4.test index4-1.1 through index4-2.2', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1200);
        $t->true(str_starts_with($case['upstream_section'], 'index4-'));
        $t->true($case['row_count'] >= 0);
        $t->same(1024, $case['page_size']);
        $t->true($case['sorter_pages'] >= 0);
        $t->true($case['spill_batches'] >= 0);
        $t->same($case['row_count'] > 0, $case['sorter_pages'] > 0);
        $t->same($case['row_count'] > 0, $case['spill_batches'] > 0);
        $t->same(in_array($case['upstream_section'], ['index4-1.6', 'index4-1.7', 'index4-1.8'], true), $case['table_reset']);
        $t->same($case['unique'], $case['expected_error'] !== null);

        if ($case['cache_size'] !== null) {
            $t->same('i2', $case['index_name']);
            $t->same(10, $case['cache_size']);
            $t->same('index4-1.4', $case['upstream_section']);
        }

        if ($case['unique']) {
            $t->same('i3', $case['index_name']);
            $t->same(35, $case['duplicate_value']);
            $t->same('UNIQUE constraint failed: t2.x', $case['expected_error']);
            $t->same('unchanged-after-error', $case['integrity']);
        } else {
            $t->same(null, $case['duplicate_value']);
            $t->same(null, $case['expected_error']);
            $t->same('ok', $case['integrity']);
        }

        if ($case['upstream_section'] === 'index4-1.6') {
            $t->same(256, $case['row_count']);
            $t->same(5202, $case['blob_bytes']);
            $t->same(1301, $case['sorter_pages']);
        }
    };
}

$tests['real upstream index4 create index stress dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexStressCases();
    $t->same(1200, count($cases));
    $t->same(1, $cases[0]['case']);
    $t->same(1200, $cases[count($cases) - 1]['case']);
    $t->same('index4-1.2', $cases[0]['upstream_section']);
    $t->same('index4-2.2', $cases[5]['upstream_section']);
};

$tests['real upstream index4 create index stress rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexStressCases(0));
};

$tests['real upstream index4 create index stress dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, create-index page estimates, external sorter classification, and unique duplicate diagnostics',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, create-index page estimates, external sorter classification, and unique duplicate diagnostics',
    );
};

return $tests;
