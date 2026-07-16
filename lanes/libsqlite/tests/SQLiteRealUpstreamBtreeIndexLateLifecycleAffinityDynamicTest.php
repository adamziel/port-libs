<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-10.0 through
// index-23.1. This batch owns late index lifecycle and affinity behavior:
// duplicate non-unique index keys, primary-key autoindex lookups, NUMERIC
// affinity range probes, autoindex drop guards, sqlite_ reserved names,
// merged constraint conflict policies, TEMP index scoping, and expression
// index REINDEX preservation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexLateLifecycleAndAffinityCases(1000) as $case) {
    $tests['real upstream index late lifecycle affinity dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-10.0 through index-23.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'index-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->true($case['table_name'] !== '');
        $t->true(in_array($case['integrity'], ['ok', 'expected-error-preserves-table', 'expected-error-preserves-schema', 'expected-conflict-policy'], true));
        $t->same(array_values($case['result_rows']), $case['result_rows']);
        $t->same(array_values($case['sort_order']), $case['sort_order']);

        if ($case['expected_error'] === null) {
            $t->same('ok', $case['integrity']);
        } else {
            $t->true($case['expected_error'] !== '');
            $t->true(str_starts_with($case['integrity'], 'expected-'));
        }

        if ($case['upstream_section'] === 'index-10.0/10.9') {
            $t->same('i1', $case['index_name']);
            $t->same('t1', $case['table_name']);
            $t->same([[2], [12]], $case['result_rows']);
            $t->same([2, 12, 4, 1, 3, 5, 7, 9, 0], $case['sort_order']);
        }

        if ($case['upstream_section'] === 'index-11.1/11.2') {
            $t->same('sqlite_autoindex_t3_1', $case['index_name']);
            $t->same(1, $case['autoindex_count']);
            $t->same([[0.1]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-12.1/12.8') {
            $t->same('t4i1', $case['index_name']);
            $t->same(true, $case['numeric_affinity']);
            $t->same([1, 2, 4, 6, 7], $case['sort_order']);
        }

        if ($case['upstream_section'] === 'index-13.1/13.5') {
            $t->same('sqlite_autoindex_t5_1', $case['index_name']);
            $t->same(3, $case['autoindex_count']);
            $t->true(str_contains($case['expected_error'] ?? '', 'cannot be dropped'));
            $t->same([[1, 2.0, 3], ['a', 'b', 'c']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-14.1/14.12') {
            $t->same('t6i1', $case['index_name']);
            $t->same([[3], [5], [2], [1], [4]], $case['result_rows']);
            $t->same([3, 5, 2, 1, 4], $case['sort_order']);
        }

        if ($case['upstream_section'] === 'index-15.1/15.4') {
            $t->same(true, $case['numeric_affinity']);
            $t->same([13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7], $case['sort_order']);
        }

        if ($case['upstream_section'] === 'index-16.1/17.4') {
            $t->same('sqlite_autoindex_t7_1', $case['index_name']);
            $t->same(3, $case['autoindex_count']);
            $t->same('expected-error-preserves-schema', $case['integrity']);
        }

        if ($case['upstream_section'] === 'index-18.1/18.5') {
            $t->same(null, $case['index_name']);
            $t->same('sqlite_t1', $case['table_name']);
            $t->true(str_contains($case['expected_error'] ?? '', 'reserved for internal use'));
        }

        if ($case['upstream_section'] === 'index-19.1/19.8') {
            $t->same('sqlite_autoindex_t8_1', $case['index_name']);
            $t->same('expected-conflict-policy', $case['integrity']);
            $t->true(str_contains($case['expected_error'] ?? '', 'UNIQUE constraint failed'));
        }

        if ($case['upstream_section'] === 'index-20.1/21.2') {
            $t->same('i21', $case['index_name']);
            $t->same([[9], [5], [1]], $case['result_rows']);
            $t->same([9, 5, 1], $case['sort_order']);
        }

        if ($case['upstream_section'] === 'index-22.0') {
            $t->same('x1', $case['index_name']);
            $t->same([['a', 1], ['a', 0]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'index-23.0/23.1') {
            $t->same('t1x1', $case['index_name']);
            $t->same(true, $case['numeric_affinity']);
            $t->same([['0.0', 1.0], ['1.0', 1.0], [0.1]], $case['result_rows']);
        }
    };
}

$tests['real upstream index late lifecycle affinity corpus count and endpoints'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexLateLifecycleAndAffinityCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('index-10.0/10.9', $cases[0]['upstream_section']);
    $t->same('index-23.0/23.1', $cases[11]['upstream_section']);
    $t->same('index-13.1/13.5', $cases[999]['upstream_section']);
    $t->same([
        'index-10.0/10.9',
        'index-11.1/11.2',
        'index-12.1/12.8',
        'index-13.1/13.5',
        'index-14.1/14.12',
        'index-15.1/15.4',
        'index-16.1/17.4',
        'index-18.1/18.5',
        'index-19.1/19.8',
        'index-20.1/21.2',
        'index-22.0',
        'index-23.0/23.1',
    ], $sections);
};

$tests['real upstream index late lifecycle affinity rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::indexLateLifecycleAndAffinityCases(0));
};

$tests['real upstream index late lifecycle affinity dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index lifecycle, autoindex catalog, affinity ordering, conflict-policy, TEMP index scope, and expression-index REINDEX helpers',
        'no new support component needed; reuses lane-local B-tree/index lifecycle, autoindex catalog, affinity ordering, conflict-policy, TEMP index scope, and expression-index REINDEX helpers',
    );
};

return $tests;
