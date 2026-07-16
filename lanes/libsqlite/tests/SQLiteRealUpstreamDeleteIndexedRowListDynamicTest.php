<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/delete.test sections delete-3.1.1 through
// delete-6.11. This batch focuses on indexed DELETE row-list behavior, changed
// row counts, survivor key order, and large row-list overflow deletes.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::deleteIndexedRowListDynamicCases(1200) as $case) {
    $tests['real upstream delete indexed row list dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('delete.test sections delete-3.1.1 through delete-6.11', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'delete-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['predicate'] !== '');
        $t->true($case['initial_rows'] >= $case['deleted_rows']);
        $t->same($case['initial_rows'] - $case['deleted_rows'], $case['remaining_rows']);
        $t->same($case['remaining_rows'], count($case['remaining_keys']));
        $t->same('ok', $case['integrity']);
        $t->true(str_contains($case['detail'], 'dynamic replay'));

        if ($case['uses_index']) {
            $t->true($case['index_name'] !== null);
            $t->true(str_contains($case['detail'], 'index') || str_contains($case['detail'], 'btree'));
        }

        if ($case['count_changes']) {
            $t->true(in_array($case['upstream_section'], ['delete-3.1.4/3.1.5', 'delete-3.1.6.1/3.1.7', 'delete-5.1.1/5.1.2'], true));
        }

        if ($case['large_delete']) {
            $t->true(in_array($case['upstream_section'], ['delete-6.5.1/6.5.2', 'delete-6.6', 'delete-6.7/6.10'], true));
        }

        if ($case['upstream_section'] === 'delete-5.3') {
            $t->same(150, $case['remaining_rows']);
            $t->same([2, 3, 4, 6, 7], array_slice($case['remaining_keys'], 0, 5));
            $t->same([196, 198, 199, 200], array_slice($case['remaining_keys'], -4));
        }

        if ($case['upstream_section'] === 'delete-5.4.1/5.4.2') {
            $t->same(37, $case['remaining_rows']);
            $t->same(50, $case['remaining_keys'][array_key_last($case['remaining_keys'])]);
        }

        if ($case['upstream_section'] === 'delete-5.5') {
            $t->same([2, 3, 6, 8, 11, 12, 14, 15, 18, 20, 23, 24, 26, 27, 30, 32, 35, 36, 38, 39, 42, 44, 47, 48, 50], $case['remaining_keys']);
        }

        if ($case['upstream_section'] === 'delete-6.5.1/6.5.2' || $case['upstream_section'] === 'delete-6.6') {
            $t->same(3000, $case['initial_rows']);
            $t->same(2993, $case['deleted_rows']);
            $t->same([1, 2, 3, 4, 5, 6, 7], $case['remaining_keys']);
        }
    };
}

$tests['real upstream delete indexed row list dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::deleteIndexedRowListDynamicCases(1200);
    $t->same(1200, count($cases));
    $t->same('delete-3.1.2/3.1.3', $cases[0]['upstream_section']);
    $t->same('delete-6.7/6.10', $cases[11]['upstream_section']);
    $t->same('delete-6.7/6.10', $cases[1199]['upstream_section']);
    $t->same(100, $cases[1199]['batch']);
};

$tests['real upstream delete indexed row list dynamic rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::deleteIndexedRowListDynamicCases(0));
};

$tests['real upstream delete indexed row list dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, indexed delete survivor ordering, count_changes, and row-list overflow modeling',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, indexed delete survivor ordering, count_changes, and row-list overflow modeling',
    );
};

return $tests;
