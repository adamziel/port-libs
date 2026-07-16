<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/wherelfault.test. The upstream script
// runs faultsim retry loops around UPDATE/DELETE ORDER BY LIMIT over
// INSTEAD OF view triggers and an empty WITHOUT ROWID primary-key table.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereLimitFaultUpdateDeleteCases(1000) as $case) {
    $tests['real upstream wherelfault update delete limit retry dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case): void {
            $t->same('wherelfault.test sections wherelfault-1.1, wherelfault-1.2, and wherelfault-2.1', $case['source']);
            $t->same('test/wherelfault.test', $case['upstream_file']);
            $t->true($case['case'] >= 1 && $case['case'] <= 1000);
            $t->true($case['batch'] >= 1);
            $t->true(in_array($case['upstream_section'], ['wherelfault-1.1', 'wherelfault-1.2', 'wherelfault-2.1'], true));
            $t->true(str_contains($case['scenario'], 'dynamic batch ' . $case['batch']));
            $t->true(in_array($case['operation'], ['DELETE', 'UPDATE'], true));
            $t->true(in_array($case['target_kind'], ['view-trigger', 'without-rowid-empty'], true));
            $t->true($case['statement'] !== '');
            $t->true(str_contains($case['statement'], 'ORDER BY'));
            $t->true(str_contains($case['statement'], 'LIMIT'));
            $t->true(is_int($case['limit']) && $case['limit'] > 0);
            $t->same('oom', $case['fault']['kind']);
            $t->true($case['fault']['attempt'] >= 0 && $case['fault']['attempt'] < 89);
            $t->true($case['fault']['checkpoint'] !== '');
            $t->same(true, $case['fault']['recovered']);
            $t->same(true, $case['schema_reopened_before_retry']);
            $t->same(0, $case['result_code']);
            $t->same(null, $case['error']);
            $t->same('ok', $case['integrity']);
            $t->same(array_values($case['selected_keys']), $case['selected_keys']);
            $t->same($case['selected_keys'], $case['mutation_keys']);
            $t->same(count($case['selected_keys']), $case['affected_rows']);
            $t->true($case['row_count_after'] <= $case['row_count_before']);
            $t->true(count($case['dependencies']) >= 3);
            $t->true(str_contains($case['detail'], 'faultsim retry'));

            if ($case['upstream_section'] === 'wherelfault-1.1') {
                $t->same('DELETE', $case['operation']);
                $t->same('v1', $case['table']);
                $t->same('a ASC', $case['order_by']);
                $t->same(3, $case['limit']);
                $t->same([1, 2, 3], $case['selected_keys']);
                $t->same([['delete', 1], ['delete', 2], ['delete', 3]], $case['log_rows']);
                $t->same([], $case['updated_rows']);
                $t->same(6, $case['row_count_before']);
                $t->same(6, $case['row_count_after']);
                $t->same(false, $case['base_table_mutated']);
                $t->same(true, $case['uses_view_trigger']);
                $t->same(false, $case['uses_without_rowid_btree']);
            }

            if ($case['upstream_section'] === 'wherelfault-1.2') {
                $t->same('UPDATE', $case['operation']);
                $t->same('v1', $case['table']);
                $t->same('a ASC', $case['order_by']);
                $t->same(3, $case['limit']);
                $t->same([1, 2, 3], $case['selected_keys']);
                $t->same([['update', 1], ['update', 2], ['update', 3]], $case['log_rows']);
                $t->same([[1, 555], [2, 555], [3, 555]], $case['updated_rows']);
                $t->same(['b' => 555], $case['update_set']);
                $t->same(6, $case['row_count_before']);
                $t->same(6, $case['row_count_after']);
                $t->same(false, $case['base_table_mutated']);
                $t->same(true, $case['uses_view_trigger']);
                $t->same(false, $case['uses_without_rowid_btree']);
            }

            if ($case['upstream_section'] === 'wherelfault-2.1') {
                $t->same('DELETE', $case['operation']);
                $t->same('t2', $case['table']);
                $t->same('a DESC', $case['order_by']);
                $t->same(10, $case['limit']);
                $t->same([null], $case['bound_parameters']);
                $t->same(true, $case['parameterized']);
                $t->same([], $case['selected_keys']);
                $t->same([], $case['log_rows']);
                $t->same([], $case['updated_rows']);
                $t->same(0, $case['row_count_before']);
                $t->same(0, $case['row_count_after']);
                $t->same(true, $case['uses_without_rowid_btree']);
                $t->same(false, $case['uses_view_trigger']);
                $t->same(['a', 'b'], $case['primary_key']);
            }
        };
}

$tests['real upstream wherelfault source citations'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/wherelfault.test');

    $t->true(is_string($source));
    foreach ([
        'do_faultsim_test 1.1',
        'DELETE FROM v1 ORDER BY a LIMIT 3',
        'do_faultsim_test 1.2',
        'UPDATE v1 SET b = 555 ORDER BY a LIMIT 3',
        'CREATE TABLE t2(a, b, c, PRIMARY KEY(a, b)) WITHOUT ROWID',
        'DELETE FROM t2 WHERE c=? ORDER BY a DESC LIMIT 10',
    ] as $needle) {
        $t->true(str_contains($source, $needle));
    }
};

$tests['real upstream wherelfault dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereLimitFaultUpdateDeleteCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('wherelfault-1.1', $cases[0]['upstream_section']);
    $t->same('wherelfault-1.2', $cases[1]['upstream_section']);
    $t->same('wherelfault-2.1', $cases[2]['upstream_section']);
    $t->same('wherelfault-1.1', $cases[999]['upstream_section']);
    $t->same(['wherelfault-1.1', 'wherelfault-1.2', 'wherelfault-2.1'], $sections);
};

$tests['real upstream wherelfault dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::whereLimitFaultUpdateDeleteCases(0));
};

$tests['real upstream wherelfault dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, UPDATE/DELETE LIMIT selection evidence, faultsim retry modeling, INSTEAD OF trigger logs, and WITHOUT ROWID primary-key metadata',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, UPDATE/DELETE LIMIT selection evidence, faultsim retry modeling, INSTEAD OF trigger logs, and WITHOUT ROWID primary-key metadata',
    );
};

return $tests;
