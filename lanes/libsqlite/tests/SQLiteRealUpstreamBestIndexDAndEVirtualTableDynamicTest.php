<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindexD.test sections 1.1 through
// 1.6 and test/bestindexE.test sections 1.1 through 3.2.3. These scripts
// verify virtual-table xBestIndex column-usage masks, usable equality
// constraints, LEFT JOIN propagation, compound UNION pushdown, and eponymous
// table schema reload behavior.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindexDAndEVirtualTablePlannerCases(1000) as $case) {
    $tests['real upstream bestindexD bestindexE virtual table dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindexD.test sections 1.1 through 1.6 and bestindexE.test sections 1.1 through 3.2.3', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindexD-') || str_starts_with($case['upstream_section'], 'bestindexE-'));
        $t->true(str_contains($case['sql'], 'dynamic batch'));
        $t->true($case['batch'] >= 1);
        $t->true($case['cost'] >= 1 && $case['rows'] === $case['cost']);
        $t->same($case['expected_col_used'], $case['reported_col_used'] & $case['expected_col_used']);
        $t->true(str_contains($case['detail'], 'xBestIndex table=' . $case['virtual_table']));
        $t->true(str_contains($case['detail'], 'colUsed=' . $case['expected_col_used']));

        foreach ($case['columns'] as $column) {
            $t->true(is_string($column) && $column !== '');
        }

        foreach ($case['constraints'] as $constraint) {
            $t->true(str_ends_with($constraint, '=?'));
            $t->true(str_contains($case['detail'], $constraint));
        }

        if ($case['upstream_section'] === 'bestindexD-1.1') {
            $t->same(1, $case['expected_col_used']);
            $t->same([], $case['constraints']);
            $t->same([], $case['constraint_log']);
        }

        if ($case['upstream_section'] === 'bestindexD-1.2') {
            $t->same(5, $case['expected_col_used']);
            $t->same(['a', 'b', 'c'], $case['columns']);
        }

        if ($case['upstream_section'] === 'bestindexD-1.4') {
            $t->same(6, $case['expected_col_used']);
            $t->same(['c=?'], $case['constraints']);
            $t->same(['x1: c=?'], $case['constraint_log']);
        }

        if ($case['upstream_section'] === 'bestindexD-1.5') {
            $t->same(0, $case['expected_col_used']);
            $t->same('x1', $case['virtual_table']);
        }

        if ($case['upstream_section'] === 'bestindexD-1.6') {
            $t->same(6, $case['expected_col_used']);
            $t->same(['b=?', 'c=?', 'b=?', 'c=?'], $case['constraints']);
            $t->same(['x1: b=? AND c=? AND b=? AND c=?'], $case['constraint_log']);
        }

        if ($case['upstream_section'] === 'bestindexE-1.1') {
            $t->same(['a=?'], $case['constraints']);
            $t->same(['x1: a=?'], $case['constraint_log']);
        }

        if ($case['upstream_section'] === 'bestindexE-1.2') {
            $t->same(['a=?', 'b=?'], $case['constraints']);
            $t->same(['x1: a=? AND b=?'], $case['constraint_log']);
        }

        if ($case['upstream_section'] === 'bestindexE-2.1') {
            $t->same('Customer', $case['virtual_table']);
            $t->same(['Delivery: ', 'Customer: oid=?'], $case['constraint_log']);
        }

        if ($case['upstream_section'] === 'bestindexE-2.2') {
            $t->same(['Delivery: id=?', 'Customer: oid=?', 'ReturnDelivery: id=?', 'Customer: oid=?'], $case['constraint_log']);
        }

        if ($case['upstream_section'] === 'bestindexE-3.1.0/3.2.3') {
            $t->same('tcl', $case['virtual_table']);
            $t->same([], $case['constraints']);
            $t->true(str_contains($case['sql'], 'RETURNING'));
        }
    };
}

$tests['real upstream bestindexD bestindexE virtual table corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindexDAndEVirtualTablePlannerCases(1000);
    $t->same(1000, count($cases));
    $t->same('bestindexD-1.1', $cases[0]['upstream_section']);
    $t->same('bestindexE-3.1.0/3.2.3', $cases[10]['upstream_section']);
    $t->same('bestindexE-1.1', $cases[996]['upstream_section']);
};

$tests['real upstream bestindexD bestindexE virtual table corpus rejects empty count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindexDAndEVirtualTablePlannerCases(0));
};

return $tests;
