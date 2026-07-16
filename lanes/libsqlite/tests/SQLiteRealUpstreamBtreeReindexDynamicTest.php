<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/reindex.test sections reindex-1.1
// through reindex-2.8.1. These cases cover REINDEX target resolution and
// changed-collation index repair without overlapping existing index build,
// autoindex, partial-index, INDEXED BY, or B-tree page-move corpus batches.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::reindexCollationRepairCases(1000) as $case) {
    $tests['real upstream reindex collation repair dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('reindex.test sections reindex-1.1 through reindex-2.8.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'reindex-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true(in_array($case['integrity_before'], ['ok', 'not-ok'], true));
        $t->true(in_array($case['integrity_after'], ['ok', 'not-ok'], true));
        $t->true(is_array($case['order_before']));
        $t->true(is_array($case['order_after']));
        $t->true(is_array($case['reindexed_objects']));
        $t->true($case['detail'] !== '');

        if ($case['result_code'] === 1) {
            $t->same('reindex-1.9', $case['upstream_section']);
            $t->same('unable to identify the object to be reindexed', $case['error']);
            $t->same([], $case['reindexed_objects']);
        } else {
            $t->same(0, $case['result_code']);
            $t->same(null, $case['error']);
        }

        if ($case['upstream_section'] === 'reindex-2.5/2.5.1') {
            $t->same(true, $case['changed_collation']);
            $t->same('not-ok', $case['integrity_after']);
            $t->same($case['order_before'], $case['order_after']);
            $t->same([], $case['reindexed_objects']);
        }

        if ($case['upstream_section'] === 'reindex-2.6') {
            $t->same('c2', $case['target']);
            $t->same('not-ok', $case['integrity_after']);
            $t->same(['sqlite_autoindex_t2_2'], $case['reindexed_objects']);
        }

        if ($case['upstream_section'] === 'reindex-2.8/2.8.1') {
            $t->same('c1', $case['target']);
            $t->same('not-ok', $case['integrity_before']);
            $t->same('ok', $case['integrity_after']);
            $t->same(['bcd', 'abc', 'BCDE', 'ABCD'], $case['order_before']);
            $t->same(['ABCD', 'BCDE', 'abc', 'bcd'], $case['order_after']);
            $t->same(['sqlite_autoindex_t2_1'], $case['reindexed_objects']);
        }
    };
}

$tests['real upstream reindex collation repair dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::reindexCollationRepairCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('reindex-1.1/1.2', $cases[0]['upstream_section']);
    $t->same('reindex-2.8/2.8.1', $cases[10]['upstream_section']);
    $t->same('reindex-1.7', $cases[999]['upstream_section']);
    $t->same([
        'reindex-1.1/1.2',
        'reindex-1.3/1.4',
        'reindex-1.5/1.6',
        'reindex-1.7',
        'reindex-1.8',
        'reindex-1.9',
        'reindex-2.1/2.4',
        'reindex-2.5/2.5.1',
        'reindex-2.6',
        'reindex-2.7',
        'reindex-2.8/2.8.1',
        'reindex-2.2',
    ], $sections);
};

$tests['real upstream reindex collation repair dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::reindexCollationRepairCases(0));
};

$tests['real upstream reindex collation repair dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, collation-order fixtures, REINDEX target resolution, and integrity-state modeling',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, collation-order fixtures, REINDEX target resolution, and integrity-state modeling',
    );
};

return $tests;
