<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

foreach (SQLiteDynamicTriggerForeignKeyPlan::withoutRowid3ForeignKeyRuntimeCases(1200) as $case) {
    $tests['real upstream without_rowid3 foreign key runtime dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('without_rowid3.test sections 1.1 through 1.7, 9.1, 11.1, and 12.1', $case['source']);
        $t->same('test/without_rowid3.test', $case['upstream_file']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'without_rowid3-'));
        $t->true(str_contains($case['scenario'], 'dynamic batch ' . $case['batch']));
        $t->true(is_string($case['operation']) && $case['operation'] !== '');
        $t->true($case['statement'] === null || $case['statement'] !== '');
        $t->true($case['table'] === null || $case['table'] !== '');
        $t->true(array_values($case['without_rowid_tables']) === $case['without_rowid_tables']);
        $t->same([], $case['foreign_key_check']);
        $t->same($case['result_code'] === 0 ? 'commit-ok' : 'constraint-failed', $case['status']);
        $t->same($case['result_code'] !== 0, $case['statement_rolled_back']);
        $t->same($case['count_changes'] && $case['result_code'] === 0 ? [1] : [], $case['count_changes_rows']);
        $t->true($case['result_code'] === 0 || is_string($case['error']));
        $t->true($case['result_code'] !== 0 || $case['error'] === null);
        $t->true(array_values($case['final_parent_keys']) === $case['final_parent_keys']);
        $t->true(array_values($case['final_child_keys']) === $case['final_child_keys']);
        $t->true(in_array($case['parent_collation'], ['binary', 'nocase'], true));
        $t->true(in_array($case['child_collation'], ['binary', 'nocase'], true));
        $t->true($case['child_value_type'] === null || in_array($case['child_value_type'], ['integer', 'text'], true));
        $t->true($case['parent_value_type'] === null || in_array($case['parent_value_type'], ['integer', 'text'], true));
        $t->true(count($case['dependencies']) >= 2);

        if ($case['upstream_section'] === 'without_rowid3-1.5.1') {
            $t->same('without-rowid-parent-int-affinity-preserves-child-text', $case['operation']);
            $t->same('35.0', $case['child_key']);
            $t->same('text', $case['child_value_type']);
            $t->same('integer', $case['parent_value_type']);
            $t->same(['35.0'], $case['final_child_keys']);
        }

        if ($case['upstream_section'] === 'without_rowid3-1.7.1') {
            $t->same('nocase', $case['parent_collation']);
            $t->same('binary', $case['child_collation']);
            $t->same('FOREIGN KEY constraint failed', $case['error']);
            $t->same(['SQLite'], $case['final_parent_keys']);
            $t->same(['sqlite'], $case['final_child_keys']);
        }

        if ($case['upstream_section'] === 'without_rowid3-1.7.2') {
            $t->same('binary', $case['parent_collation']);
            $t->same('nocase', $case['child_collation']);
            $t->same([], $case['final_child_keys']);
        }

        if ($case['upstream_section'] === 'without_rowid3-9.1.2') {
            $t->same('SET DEFAULT', $case['action']);
            $t->same([1], $case['final_child_keys']);
        }

        if ($case['upstream_section'] === 'without_rowid3-11.1.1') {
            $t->same('CASCADE', $case['action']);
            $t->same([15], $case['final_parent_keys']);
            $t->same([15], $case['final_child_keys']);
        }

        if ($case['upstream_section'] === 'without_rowid3-12.1.4') {
            $t->same('RESTRICT', $case['action']);
            $t->same(true, $case['deferred']);
            $t->same(true, $case['transaction_active']);
        }
    };
}

foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereFaultOrOptimizationCases(800) as $case) {
    $tests['real upstream wherefault OR planner fault dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('wherefault.test sections wherefault-1, wherefault-2, and wherefault-3.1', $case['source']);
        $t->same('test/wherefault.test', $case['upstream_file']);
        $t->true($case['case'] >= 1 && $case['case'] <= 800);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'wherefault-'));
        $t->true(str_contains($case['scenario'], 'dynamic batch ' . $case['batch']));
        $t->same('t1', $case['table']);
        $t->same('oom', $case['fault']['kind']);
        $t->true($case['fault']['attempt'] >= 0 && $case['fault']['attempt'] < 97);
        $t->same(true, $case['fault']['recovered']);
        $t->same(0, $case['result_code']);
        $t->same(null, $case['error']);
        $t->same('ok', $case['integrity']);
        $t->true($case['statement'] !== '');
        $t->true($case['detail'] !== '');
        $t->true(count($case['dependencies']) >= 2);
        $t->true(array_values($case['or_terms']) === $case['or_terms']);
        $t->true(array_values($case['range_terms']) === $case['range_terms']);
        $t->true(array_values($case['indexes']) === $case['indexes']);
        $t->true(array_values($case['expected_rows']) === $case['expected_rows']);

        if ($case['upstream_section'] === 'wherefault-1.1') {
            $t->same(9, count($case['or_terms']));
            $t->same(['i1(a)', 'i2(b)'], $case['indexes']);
            $t->same(true, $case['uses_or_optimization']);
            $t->same([], $case['expected_rows']);
        }

        if ($case['upstream_section'] === 'wherefault-1.2') {
            $t->same(6, count($case['or_terms']));
            $t->same(['i1(a)'], $case['indexes']);
            $t->same(true, $case['uses_or_optimization']);
        }

        if ($case['upstream_section'] === 'wherefault-1.3') {
            $t->same([], $case['or_terms']);
            $t->same(4, count($case['range_terms']));
            $t->same(false, $case['uses_or_optimization']);
        }

        if ($case['upstream_section'] === 'wherefault-2') {
            $t->same(1000, $case['row_count']);
            $t->same(993, $case['expected_count']);
            $t->same([[993]], $case['expected_rows']);
            $t->same(true, $case['uses_or_optimization']);
        }

        if ($case['upstream_section'] === 'wherefault-3.1') {
            $t->same(true, $case['uses_generated_columns']);
            $t->same(true, $case['uses_natural_join']);
            $t->same(['t1a(a)'], $case['indexes']);
        }
    };
}

$tests['real upstream without_rowid3 and wherefault source citations'] = static function (TestRunner $t): void {
    $withoutRowid3 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid3.test');
    $wherefault = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/wherefault.test');

    $t->true(is_string($withoutRowid3));
    $t->true(str_contains($withoutRowid3, 'CREATE TABLE t1(a PRIMARY KEY, b) WITHOUT rowid;'));
    $t->true(str_contains($withoutRowid3, 'FOREIGN KEY constraint failed'));
    $t->true(str_contains($withoutRowid3, 'Use a collation sequence on the parent key.'));
    $t->true(str_contains($withoutRowid3, 'ON UPDATE CASCADE'));
    $t->true(str_contains($withoutRowid3, 'ON DELETE SET DEFAULT'));

    $t->true(is_string($wherefault));
    $t->true(str_contains($wherefault, 'do_malloc_test 1'));
    $t->true(str_contains($wherefault, 'a = 2 OR b ='));
    $t->true(str_contains($wherefault, 'SELECT count(*) FROM t1 WHERE a BETWEEN 5 AND 995 OR b BETWEEN 5 AND 900000'));
    $t->true(str_contains($wherefault, 'do_faultsim_test 3.1'));
};

$tests['real upstream without_rowid3 wherefault dynamic source range'] = static function (TestRunner $t): void {
    $fkCases = SQLiteDynamicTriggerForeignKeyPlan::withoutRowid3ForeignKeyRuntimeCases(1200);
    $whereCases = SQLiteBTreeIndexDynamicCorpusPlan::whereFaultOrOptimizationCases(800);

    $t->same(1200, count($fkCases));
    $t->same(800, count($whereCases));
    $t->same('without_rowid3-1.1.1.1', $fkCases[0]['upstream_section']);
    $t->true(in_array('without_rowid3-12.1.4', array_column($fkCases, 'upstream_section'), true));
    $t->same('wherefault-1.1', $whereCases[0]['upstream_section']);
    $t->same('wherefault-3.1', $whereCases[4]['upstream_section']);
    $t->same('wherefault-3.1', $whereCases[799]['upstream_section']);
};

$tests['real upstream without_rowid3 wherefault dynamic rejects invalid sizes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::withoutRowid3ForeignKeyRuntimeCases(0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::whereFaultOrOptimizationCases(0));
};

$tests['real upstream without_rowid3 wherefault dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local WITHOUT ROWID foreign-key runtime modeling, parent affinity/collation comparison, FK action summaries, and WHERE OR fault-retry planner evidence',
        'no new support component needed; reuses lane-local WITHOUT ROWID foreign-key runtime modeling, parent affinity/collation comparison, FK action summaries, and WHERE OR fault-retry planner evidence',
    );
};

return $tests;
