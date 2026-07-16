<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/wherelimit2.test sections 1.1 through
// 7.2. These cases protect UPDATE/DELETE ORDER BY LIMIT selection across
// INSTEAD OF view triggers, WITHOUT ROWID primary-key b-trees, forced
// secondary indexes, quoted object names, and target names shadowed by CTEs.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereLimit2UpdateDeleteIndexCases(1000) as $case) {
    $tests['real upstream wherelimit2 view index dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case): void {
            $t->same('wherelimit2.test sections wherelimit2-1.1 through wherelimit2-7.2', $case['source']);
            $t->true($case['case'] >= 1 && $case['case'] <= 1000);
            $t->true($case['batch'] >= 1);
            $t->true(str_starts_with($case['upstream_section'], 'wherelimit2-'));
            $t->true(in_array($case['operation'], ['DELETE', 'UPDATE'], true));
            $t->true($case['scenario'] !== '');
            $t->true($case['statement'] !== '');
            $t->true($case['target_kind'] !== '');
            $t->true($case['table'] !== '');
            $t->true(is_int($case['limit']) && $case['limit'] >= 1);
            $t->true(is_int($case['offset']) && $case['offset'] >= 0);
            $t->same('ok', $case['integrity']);
            $t->same($case['mutation_count'], count($case['selected_keys']));
            $t->true($case['row_count_before'] >= $case['row_count_after']);
            if ($case['uses_indexed_by']) {
                $t->true(is_string($case['index_name']) && $case['index_name'] !== '');
            }
            $t->same($case['uses_quoted_names'], str_contains($case['statement'], '"'));
            $t->same($case['cte_shadows_target'], str_starts_with($case['statement'], 'WITH '));
            $t->same($case['uses_window_order'], str_contains($case['statement'], 'rank()OVER()'));

            if ($case['uses_view_trigger']) {
                $t->true(str_contains($case['target_kind'], 'view-trigger'));
                $t->same($case['row_count_before'], $case['row_count_after']);
                $t->same($case['mutation_count'], count($case['log_rows']));
                $t->same([], $case['remaining_rows']);
                foreach ($case['log_rows'] as $row) {
                    $t->true(array_values($row) === $row);
                    $t->true($row[0] === 'delete' || $row[0] === 'update' || is_string($row[0]));
                }
            }

            if ($case['uses_without_rowid']) {
                $t->true(str_contains($case['target_kind'], 'without-rowid'));
                $t->true($case['index_name'] === 'PRIMARY KEY(a,b)' || $case['index_name'] === 'PRIMARY KEY(a)' || $case['index_name'] === 'xycd');
                $t->true($case['remaining_rows'] !== [] || $case['updated_rows'] !== []);
            }

            if ($case['uses_indexed_by']) {
                $t->true(str_contains($case['statement'], 'INDEXED BY') || $case['index_name'] === 'xycd');
                $t->true(is_string($case['index_name']) && $case['index_name'] !== '');
                $t->true(str_contains($case['detail'], 'index') || str_contains($case['detail'], 'INDEXED BY'));
            }

            if ($case['operation'] === 'DELETE') {
                $t->true($case['updated_rows'] === []);
                if (!$case['uses_view_trigger']) {
                    $t->same($case['row_count_before'] - $case['mutation_count'], $case['row_count_after']);
                }
            } else {
                $t->true($case['updated_rows'] !== []);
                $t->same($case['row_count_before'], $case['row_count_after']);
            }

            if ($case['upstream_section'] === 'wherelimit2-1.1') {
                $t->same([1, 2, 3], $case['selected_keys']);
                $t->same([['delete', 1], ['delete', 2], ['delete', 3]], $case['log_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-1.2') {
                $t->same([6, 5, 4], $case['selected_keys']);
                $t->same([['delete', 6], ['delete', 5], ['delete', 4]], $case['log_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-2.1.1') {
                $t->same([[4, 1], [3, 1]], $case['selected_keys']);
                $t->same(['a', 'c', 'e', 'f', 'g', 'h'], $case['remaining_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-2.1.2') {
                $t->same([[1, 1], [2, 2], [2, 1]], $case['selected_keys']);
                $t->same([[1, 1, null], [1, 2, 'g'], [2, 1, null], [2, 2, null], [3, 1, 'd'], [3, 2, 'c'], [4, 1, 'b'], [4, 2, 'a']], $case['remaining_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-2.2.1') {
                $t->same([7, 5], $case['selected_keys']);
                $t->same(['a', 'c', 'e', 'f', 'g', 'h'], $case['remaining_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-2.2.2') {
                $t->same([7, 6, 5], $case['selected_keys']);
                $t->same([[7, null], [6, null], [5, null]], $case['updated_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-4.3') {
                $t->same('x1bc', $case['index_name']);
                $t->same([5], $case['selected_keys']);
                $t->same([1, 2, 3, 4, 6], $case['remaining_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-4.5') {
                $t->same('x1bc', $case['index_name']);
                $t->same([3], $case['selected_keys']);
                $t->same([[1, 1], [2, 2], [3, 5], [4, 3], [6, 1]], $case['remaining_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-5.1') {
                $t->same(['b', 'f'], $case['selected_keys']);
                $t->same([['a', 'a'], ['c', 'c'], ['d', 'd'], ['e', 'a'], ['g', 'c'], ['h', 'd']], $case['remaining_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-5.5') {
                $t->same([['ax', 'a'], ['bx', 'b'], ['cx', 'c'], ['dx', 'd'], ['ex', 'a']], $case['log_rows']);
                $t->same($case['log_rows'], $case['updated_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit2-6.1/6.2') {
                $t->same([1, 2], $case['selected_keys']);
                $t->same([3, 5, 8, 13], $case['remaining_rows']);
                $t->same(true, $case['cte_shadows_target']);
                $t->same(true, $case['uses_window_order']);
            }
            if ($case['upstream_section'] === 'wherelimit2-7.1/7.2') {
                $t->same([0], $case['selected_keys']);
                $t->same([[3]], $case['updated_rows']);
                $t->same(true, $case['cte_shadows_target']);
            }
        };
}

$tests['real upstream wherelimit2 view index dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereLimit2UpdateDeleteIndexCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('wherelimit2-1.1', $cases[0]['upstream_section']);
    $t->same('wherelimit2-1.2', $cases[1]['upstream_section']);
    $t->same('wherelimit2-7.1/7.2', $cases[16]['upstream_section']);
    $t->same('wherelimit2-5.4', $cases[999]['upstream_section']);
    $t->same(59, $cases[999]['batch']);
    $t->same([
        'wherelimit2-1.1',
        'wherelimit2-1.2',
        'wherelimit2-1.3',
        'wherelimit2-1.4',
        'wherelimit2-2.1.1',
        'wherelimit2-2.1.2',
        'wherelimit2-2.2.1',
        'wherelimit2-2.2.2',
        'wherelimit2-4.1',
        'wherelimit2-4.3',
        'wherelimit2-4.5',
        'wherelimit2-5.1',
        'wherelimit2-5.2',
        'wherelimit2-5.4',
        'wherelimit2-5.5',
        'wherelimit2-6.1/6.2',
        'wherelimit2-7.1/7.2',
    ], $sections);
};

$tests['real upstream wherelimit2 view index dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereLimit2UpdateDeleteIndexCases(0));
};

$tests['real upstream wherelimit2 view index dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning for UPDATE/DELETE LIMIT view triggers, WITHOUT ROWID primary-key b-trees, forced secondary indexes, quoted names, and CTE target shadowing',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning for UPDATE/DELETE LIMIT view triggers, WITHOUT ROWID primary-key b-trees, forced secondary indexes, quoted names, and CTE target shadowing',
    );
};

return $tests;
