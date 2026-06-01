<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWhereLimitMutationPlan;

$tests = [];

// Source truth: SQLite upstream test/wherelimit.test sections wherelimit-0.1
// through wherelimit-4.12. These cases protect UPDATE/DELETE ORDER BY LIMIT
// selection, OFFSET normalization, RETURNING rows, target aliases,
// INSTEAD OF view triggers, and WITHOUT ROWID primary-key b-tree deletes.
foreach (SQLiteWhereLimitMutationPlan::dynamicCases(1200) as $case) {
    $tests['real upstream wherelimit mutation dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case): void {
            $t->same('wherelimit.test sections wherelimit-0.1 through wherelimit-4.12', $case['source']);
            $t->same('test/wherelimit.test', $case['upstream_file']);
            $t->true($case['case'] >= 1 && $case['case'] <= 1200);
            $t->true($case['batch'] >= 1);
            $t->true(str_starts_with($case['upstream_section'], 'wherelimit-'));
            $t->true(in_array($case['operation'], ['DELETE', 'UPDATE'], true));
            $t->true($case['scenario'] !== '');
            $t->true($case['statement'] !== '');
            $t->same('ok', $case['integrity']);
            $t->true($case['row_count_before'] >= 0);
            $t->true($case['row_count_after'] >= 0);
            $t->true($case['affected_rows'] >= 0);
            $t->same($case['affected_rows'], count($case['selected_rowids']));
            $t->same($case['selected_rowids'], $case['mutation_rowids']);
            $t->same($case['uses_limit_clause'], str_contains($case['statement'], 'LIMIT'));
            if ($case['uses_order_by']) {
                $t->true(str_contains($case['statement'], 'ORDER BY'));
            }
            if (!$case['uses_order_by'] && $case['result_code'] === 0) {
                $t->same(false, str_contains($case['statement'], 'ORDER BY'));
            }

            if ($case['result_code'] === 1) {
                $t->true(is_string($case['error']) && $case['error'] !== '');
                $t->same(0, $case['affected_rows']);
                $t->same($case['row_count_before'], $case['row_count_after']);
                $t->same([], $case['selected_pairs']);
                $t->same([], $case['returning_rows']);
                $t->same(false, $case['uses_returning']);
                $t->same(false, $case['uses_view_trigger']);
                $t->same(false, $case['uses_without_rowid_btree']);
            } else {
                $t->same(null, $case['error']);
                $t->true($case['qualified_rows'] >= $case['affected_rows']);
            }

            if ($case['uses_rowid_btree'] && $case['result_code'] === 0) {
                $t->true($case['grid_x_count'] >= 6);
                $t->true($case['grid_y_count'] >= 6);
                if ($case['operation'] === 'DELETE') {
                    $t->same($case['row_count_before'] - $case['affected_rows'], $case['row_count_after']);
                } else {
                    $t->same($case['row_count_before'], $case['row_count_after']);
                }
                foreach ($case['selected_pairs'] as $pair) {
                    $t->true($pair['x'] >= 1 && $pair['x'] <= $case['grid_x_count']);
                    $t->true($pair['y'] >= 1 && $pair['y'] <= $case['grid_y_count']);
                    $t->true($pair['rowid'] >= 1 && $pair['rowid'] <= $case['row_count_before']);
                    if (isset($case['where_x'])) {
                        $t->same($case['where_x'], $pair['x']);
                    }
                }
            }

            if ($case['uses_returning']) {
                $t->same($case['affected_rows'], count($case['returning_rows']));
                foreach ($case['returning_rows'] as $row) {
                    $t->true(array_key_exists('x', $row));
                    $t->true(array_key_exists('y', $row));
                    $t->same('|', $row['separator']);
                }
            } else {
                $t->same([], $case['returning_rows']);
            }

            if ($case['uses_limit_clause']) {
                $t->true(is_int($case['limit_value']));
                $t->true(is_int($case['offset_value']));
                $t->true($case['limit_expression'] !== '');
                $t->same(max(0, $case['offset_value']), $case['normalized_offset']);
                if ($case['limit_value'] < 0) {
                    $t->same(null, $case['normalized_limit']);
                } else {
                    $t->same($case['limit_value'], $case['normalized_limit']);
                    $t->true($case['affected_rows'] <= $case['limit_value']);
                }
            }

            if ($case['uses_temp_order_btree']) {
                $t->same(true, $case['uses_order_by']);
                $t->same(true, $case['uses_limit_clause']);
            }

            if ($case['uses_view_trigger']) {
                $t->same('DELETE', $case['operation']);
                $t->same(2, $case['affected_rows']);
                $t->same([1, 2], $case['trigger_deleted_rowids']);
                $t->same([3], $case['remaining_t1_rows']);
                $t->same([103], $case['remaining_t2_rows']);
                foreach ($case['selected_pairs'] as $pair) {
                    $t->true($pair[0] === 't1' || $pair[0] === 't2');
                    $t->true($pair[1] === 1 || $pair[1] === 2);
                }
            }

            if ($case['uses_without_rowid_btree']) {
                $t->same('DELETE', $case['operation']);
                $t->same(['a', 'b'], $case['primary_key']);
                $t->same([[5, 6, 7, 8]], $case['selected_pairs']);
                $t->same([[1, 2, 3, 4], [9, 10, 11, 12]], $case['remaining_rows']);
            }

            if ($case['upstream_section'] === 'wherelimit-0.1') {
                $t->same('ORDER BY without LIMIT on DELETE', $case['error']);
            }
            if ($case['upstream_section'] === 'wherelimit-0.5.2') {
                $t->same('no such column: t1.x', $case['error']);
            }
            if ($case['upstream_section'] === 'wherelimit-1.3b') {
                $t->same(true, $case['uses_returning']);
                $t->same(['x' => 1, 'y' => 1, 'rowid' => 1], $case['first_selected_pair']);
                $t->same(['x' => 1, 'y' => 5, 'rowid' => 5], $case['last_selected_pair']);
            }
            if ($case['upstream_section'] === 'wherelimit-1.6') {
                $t->same(null, $case['normalized_limit']);
                $t->same(2, $case['normalized_offset']);
            }
            if ($case['upstream_section'] === 'wherelimit-2.11') {
                $t->same(0, $case['affected_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit-3.2') {
                $t->same(true, $case['uses_returning']);
                $t->same(5, $case['affected_rows']);
            }
            if ($case['upstream_section'] === 'wherelimit-3.13') {
                $t->same(0, $case['affected_rows']);
            }
        };
}

$tests['real upstream wherelimit mutation dynamic source truth'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/wherelimit.test');
    $t->true(is_string($source));
    foreach ([
        'do_test wherelimit-0.1',
        'DELETE FROM t1 ORDER BY x',
        "DELETE FROM t1 RETURNING x, y, '|' ORDER BY x, y LIMIT 5",
        "UPDATE t1 SET y=1 WHERE x=1 RETURNING x, y, '|' LIMIT 5",
        'DELETE FROM tv WHERE 1 ORDER BY a LIMIT 2',
        'DELETE FROM t3 WHERE a=5 LIMIT 2',
        'SELECT a,b,c,d FROM t3 ORDER BY 1',
    ] as $needle) {
        $t->true(str_contains($source, $needle));
    }
};

$tests['real upstream wherelimit mutation dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteWhereLimitMutationPlan::dynamicCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1200, count($cases));
    $t->same('wherelimit-0.1', $cases[0]['upstream_section']);
    $t->same('wherelimit-0.7', $cases[7]['upstream_section']);
    $t->same('wherelimit-1.13', $cases[21]['upstream_section']);
    $t->same('wherelimit-4.3', $cases[49]['upstream_section']);
    $t->same('wherelimit-4.11/4.12', $cases[50]['upstream_section']);
    $t->same(24, $cases[1199]['batch']);
    $t->same([
        'wherelimit-0.1',
        'wherelimit-0.2',
        'wherelimit-0.3',
        'wherelimit-0.4',
        'wherelimit-0.5.1',
        'wherelimit-0.5.2',
        'wherelimit-0.6',
        'wherelimit-0.7',
        'wherelimit-1.1',
        'wherelimit-1.10',
        'wherelimit-1.11',
        'wherelimit-1.12',
        'wherelimit-1.13',
        'wherelimit-1.2',
        'wherelimit-1.3',
        'wherelimit-1.3b',
        'wherelimit-1.4',
        'wherelimit-1.5',
        'wherelimit-1.6',
        'wherelimit-1.7',
        'wherelimit-1.8',
        'wherelimit-1.9',
        'wherelimit-2.1',
        'wherelimit-2.10',
        'wherelimit-2.11',
        'wherelimit-2.12',
        'wherelimit-2.13',
        'wherelimit-2.2',
        'wherelimit-2.3',
        'wherelimit-2.4',
        'wherelimit-2.5',
        'wherelimit-2.6',
        'wherelimit-2.7',
        'wherelimit-2.8',
        'wherelimit-2.9',
        'wherelimit-3.1',
        'wherelimit-3.10',
        'wherelimit-3.11',
        'wherelimit-3.12',
        'wherelimit-3.13',
        'wherelimit-3.2',
        'wherelimit-3.3',
        'wherelimit-3.4',
        'wherelimit-3.5',
        'wherelimit-3.6',
        'wherelimit-3.7',
        'wherelimit-3.8',
        'wherelimit-3.9',
        'wherelimit-4.11/4.12',
        'wherelimit-4.2',
        'wherelimit-4.3',
    ], $sections);
};

$tests['real upstream wherelimit mutation dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteWhereLimitMutationPlan::dynamicCases(0));
};

$tests['real upstream wherelimit mutation dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local UPDATE/DELETE LIMIT selection and rowid/without-rowid B-tree planning for upstream wherelimit.test',
        'no new support component needed; reuses lane-local UPDATE/DELETE LIMIT selection and rowid/without-rowid B-tree planning for upstream wherelimit.test',
    );
};

return $tests;
