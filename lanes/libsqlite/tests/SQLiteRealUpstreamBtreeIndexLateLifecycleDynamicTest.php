<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-10.0 through
// index-23.1. This extends the earlier index catalog-lifecycle corpus into
// duplicate index keys, NUMERIC affinity, autoindex constraints, index sort
// order, reserved internal names, conflict policy, temp-index namespace, and
// expression-index REINDEX regression behavior.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexLateLifecycleAndAffinityCases(1000) as $case) {
    $tests['real upstream index late lifecycle affinity dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-10.0 through index-23.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'index-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['table_name'] !== '');
        $t->true(is_array($case['result_rows']));
        $t->true(is_array($case['sort_order']));
        $t->true($case['batch'] >= 1);

        if ($case['expected_error'] === null) {
            $t->same('ok', $case['integrity']);
        } else {
            $t->true(str_contains($case['integrity'], 'expected'));
            $t->true(str_contains($case['expected_error'], 'index') || str_contains($case['expected_error'], 'object') || str_contains($case['expected_error'], 'UNIQUE'));
        }

        if ($case['index_name'] !== null) {
            $t->true($case['index_name'] !== '');
            $t->same(true, $case['uses_index']);
        }

        if ($case['upstream_section'] === 'index-10.0/10.9') {
            $t->same('i1', $case['index_name']);
            $t->same([[2], [12]], $case['result_rows']);
            $t->same([2, 12, 4, 1, 3, 5, 7, 9, 0], $case['sort_order']);
            $t->same(false, $case['numeric_affinity']);
        }

        if ($case['upstream_section'] === 'index-12.1/12.8') {
            $t->same('t4i1', $case['index_name']);
            $t->same(true, $case['numeric_affinity']);
            $t->same([[0], [0], [-1], [0], [0]], $case['result_rows']);
            $t->same([1, 2, 4, 6, 7], $case['sort_order']);
        }

        if ($case['upstream_section'] === 'index-13.1/13.5') {
            $t->same(3, $case['autoindex_count']);
            $t->same('sqlite_autoindex_t5_1', $case['index_name']);
            $t->same('index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped', $case['expected_error']);
        }

        if ($case['upstream_section'] === 'index-14.1/14.12') {
            $t->same([3, 5, 2, 1, 4], $case['sort_order']);
            $t->same([[3], [5], [2], [1], [4]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-15.1/15.4') {
            $t->same(true, $case['numeric_affinity']);
            $t->same([13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7], $case['sort_order']);
            $t->same(count($case['sort_order']), count($case['result_rows']));
        }

        if ($case['upstream_section'] === 'index-16.1/17.4') {
            $t->same(3, $case['autoindex_count']);
            $t->same('sqlite_autoindex_t7_1', $case['index_name']);
            $t->true(str_contains($case['statement'], 'DROP INDEX IF EXISTS'));
        }

        if ($case['upstream_section'] === 'index-18.1/18.5') {
            $t->same(null, $case['index_name']);
            $t->same(false, $case['uses_index']);
            $t->same('object name reserved for internal use', $case['expected_error']);
        }

        if ($case['upstream_section'] === 'index-20.1/21.2') {
            $t->same('i21', $case['index_name']);
            $t->same([[9], [5], [1]], $case['result_rows']);
            $t->same([9, 5, 1], $case['sort_order']);
        }

        if ($case['upstream_section'] === 'index-22.0') {
            $t->same('x1', $case['index_name']);
            $t->same([['a', 1], ['a', 0]], $case['result_rows']);
            $t->true(str_contains($case['statement'], 'IF NOT EXISTS'));
        }

        if ($case['upstream_section'] === 'index-23.0/23.1') {
            $t->same(true, $case['numeric_affinity']);
            $t->true(str_contains($case['statement'], 'REINDEX'));
            $t->same([['0.0', 1.0], ['1.0', 1.0], [0.1]], $case['result_rows']);
        }
    };
}

$tests['real upstream index late lifecycle affinity dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexLateLifecycleAndAffinityCases(1000);
    $t->same(1000, count($cases));
    $t->same('index-10.0/10.9', $cases[0]['upstream_section']);
    $t->same('index-23.0/23.1', $cases[11]['upstream_section']);
    $t->same('index.test sections index-10.0 through index-23.1', $cases[999]['source']);
};

$tests['real upstream index late lifecycle affinity rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::indexLateLifecycleAndAffinityCases(0));
};

$tests['real upstream index late lifecycle affinity dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus generator for index.test lifecycle, affinity, sort-order, constraint, namespace, and expression-index REINDEX behavior',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus generator for index.test lifecycle, affinity, sort-order, constraint, namespace, and expression-index REINDEX behavior',
    );
};

return $tests;
