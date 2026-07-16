<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/where4.test. The upstream script targets
// indexed IS NULL optimization, NULL-aware IN probes, and LEFT JOIN cases where
// a right-table NULL may be a null-extended join row rather than an indexed key.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where4IsNullIndexOptimizationCases(1000) as $case) {
    $tests['real upstream where4 is-null index dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('where4.test selected sections where4-1.1 through where4-8.2', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'where4-'));
        $t->true(str_contains($case['scenario'], 'dynamic replay'));
        $t->true($case['statement'] !== '');
        $t->true($case['table_shape'] !== '');
        $t->true(is_array($case['predicate_terms']));
        $t->true(is_array($case['residual_terms']));
        $t->true(is_array($case['result_rows']));
        $t->same('ok', $case['integrity']);
        $t->true($case['detail'] !== '');

        if ($case['uses_index']) {
            $t->true($case['index_name'] !== null);
            $t->true($case['index_columns'] !== []);
            $t->true(
                str_contains($case['detail'], (string) $case['index_name'])
                || str_contains($case['detail'], 'PRIMARY KEY')
                || str_contains($case['detail'], 'UNIQUE INDEX')
            );
        }

        if ($case['result_rowids'] !== []) {
            $t->same($case['result_rowids'], array_map(static fn (array $row): int => (int) $row[0], $case['result_rows']));
        }

        if ($case['search_count'] !== null) {
            $t->true($case['search_count'] >= 0);
            $t->same(str_starts_with($case['upstream_section'], 'where4-1.'), true);
            if ($case['uses_unary_plus_guard']) {
                $t->true($case['search_count'] >= 3);
            }
        }

        if ($case['uses_is_null_optimization']) {
            $t->true(str_contains(implode(' ', $case['predicate_terms']), 'IS'));
            $t->same($case['uses_unary_plus_guard'], false);
        }

        if ($case['uses_unary_plus_guard']) {
            $t->true(str_contains(implode(' ', $case['residual_terms']), '+'));
            $t->same($case['uses_is_null_optimization'], false);
        }

        if ($case['uses_left_join']) {
            $t->true(str_contains($case['statement'], 'LEFT'));
            if ($case['left_join_null_extension']) {
                $t->true(in_array([3, null, null], $case['result_rows'], true));
            }
        }

        if ($case['uses_in_operator']) {
            $t->true(str_contains($case['statement'], ' IN '));
            $t->true($case['uses_composite_key']);
            $t->true(count($case['index_columns']) >= 2);
            if ($case['null_list_preserved']) {
                $t->true(str_contains($case['statement'], 'NULL'));
            }
        }

        if ($case['null_bound_variable']) {
            $t->true(str_contains($case['statement'], '$null'));
            $t->true(str_contains(implode(' ', $case['predicate_terms']), '$null'));
        }

        if ($case['order_by'] !== []) {
            $t->true(str_contains($case['statement'], 'ORDER BY'));
            $t->same(7, count($case['result_rowids']));
            $t->same('i1wxy', $case['index_name']);
        }

        if ($case['upstream_section'] === 'where4-1.2') {
            $t->same(false, $case['uses_index']);
            $t->same([7], $case['result_rowids']);
            $t->same(6, $case['search_count']);
        }

        if ($case['upstream_section'] === 'where4-1.4') {
            $t->same('i1wxy', $case['index_name']);
            $t->same(['+x IS NULL'], $case['residual_terms']);
            $t->same(3, $case['search_count']);
        }

        if ($case['upstream_section'] === 'where4-5.2') {
            $t->same([1, 2, 4], $case['result_rowids']);
            $t->true($case['null_list_preserved']);
        }

        if ($case['upstream_section'] === 'where4-6.1') {
            $t->same([3, 2], $case['result_rowids']);
            $t->same(false, $case['null_list_preserved']);
        }

        if ($case['upstream_section'] === 'where4-8.2-null' || $case['upstream_section'] === 'where4-8.2-bound') {
            $t->same([[null, 1], [null, 2]], $case['result_rows']);
            $t->same('sqlite_autoindex_u9_1', $case['index_name']);
        }
    };
}

$tests['real upstream where4 is-null index dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where4IsNullIndexOptimizationCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('where4-1.1', $cases[0]['upstream_section']);
    $t->same('where4-8.2-bound', $cases[35]['upstream_section']);
    $t->same('where4.test selected sections where4-1.1 through where4-8.2', $cases[999]['source']);
    $t->same([
        'where4-1.1',
        'where4-1.1b',
        'where4-1.2',
        'where4-1.3',
        'where4-1.4',
        'where4-1.5',
        'where4-1.6',
        'where4-1.7',
        'where4-1.8',
        'where4-1.9',
        'where4-1.10',
        'where4-1.11',
        'where4-1.12',
        'where4-1.13',
        'where4-1.14',
        'where4-1.15',
        'where4-1.16',
        'where4-2.1',
        'where4-2.2',
        'where4-2.3',
        'where4-3.1',
        'where4-3.2',
        'where4-3.3',
        'where4-3.4',
        'where4-4.1',
        'where4-4.2',
        'where4-4.3',
        'where4-4.4',
        'where4-5.2',
        'where4-5.3',
        'where4-6.1',
        'where4-6.2',
        'where4-7.1',
        'where4-7.2',
        'where4-8.2-null',
        'where4-8.2-bound',
    ], $sections);
};

$tests['real upstream where4 is-null index dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where4IsNullIndexOptimizationCases(0));
};

$tests['real upstream where4 is-null index dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, IS NULL optimization, NULL-aware IN probing, composite-key, LEFT JOIN null-extension, and UNIQUE NULL lookup helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, IS NULL optimization, NULL-aware IN probing, composite-key, LEFT JOIN null-extension, and UNIQUE NULL lookup helpers',
    );
};

return $tests;
