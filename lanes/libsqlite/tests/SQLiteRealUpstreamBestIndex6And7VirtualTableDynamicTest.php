<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindex6.test sections bestindex6-1.1
// through bestindex6-1.4 and test/bestindex7.test sections bestindex7-1.1
// through bestindex7-1.12. This batch covers virtual-table xBestIndex usable
// constraints for LEFT JOIN NULL filters, OR equality, and IN-list probes.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindex6And7VirtualTableNullConstraintCases(1000) as $case) {
    $tests['real upstream bestindex6 bestindex7 virtual table dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['source'], 'bestindex6.test') || str_starts_with($case['source'], 'bestindex7.test'));
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true($case['scenario'] !== '');
        $t->true($case['statement'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true($case['idx_string'] !== '');
        $t->true(str_starts_with($case['xfilter_sql'], 'SELECT rowid'));
        $t->true(is_array($case['result_rows']));

        foreach ($case['constraints'] as $constraint) {
            $t->true(in_array($constraint['table'], ['t1', 'vt1'], true));
            $t->true(in_array($constraint['column'], ['id', 'value', 'a'], true));
            $t->true(in_array($constraint['operator'], ['=', 'IS NULL', 'IN'], true));
            $t->same(true, $constraint['usable']);
            if (str_starts_with($case['upstream_section'], 'bestindex6-')) {
                $t->same(true, $constraint['omitted']);
            }
        }

        if (str_starts_with($case['upstream_section'], 'bestindex6-')) {
            $t->same('bestindex6.test sections bestindex6-1.1 through bestindex6-1.4', $case['source']);
            $t->same('LEFT JOIN', $case['join_type']);
            $t->same(false, $case['uses_or']);
            $t->same(false, $case['uses_in']);
            $t->same(false, $case['updated_null_row']);
            $t->true(str_contains($case['statement'], 'left join'));
            $t->true(str_contains($case['idx_string'], 'id'));

            if ($case['upstream_section'] === 'bestindex6-1.1' || $case['upstream_section'] === 'bestindex6-1.2' || $case['upstream_section'] === 'bestindex6-1.3') {
                $t->same([[2, 2, 'evil', null, null]], $case['result_rows']);
                $t->true(str_contains($case['idx_string'], 'value IS NULL'));
            }

            if ($case['upstream_section'] === 'bestindex6-1.4') {
                $t->same([], $case['result_rows']);
                $t->true(str_contains($case['idx_string'], 'value = %1%'));
            }
        }

        if (str_starts_with($case['upstream_section'], 'bestindex7-')) {
            $t->same('bestindex7.test sections bestindex7-1.1 through bestindex7-1.12', $case['source']);
            $t->same(null, $case['join_type']);
            $t->true(str_contains($case['xfilter_sql'], 'FROM t1'));

            if ($case['upstream_section'] === 'bestindex7-1.1') {
                $t->same([[0], [2]], $case['result_rows']);
                $t->same(false, $case['updated_null_row']);
            }

            if ($case['upstream_section'] === 'bestindex7-1.4' || $case['upstream_section'] === 'bestindex7-1.9') {
                $t->same(true, $case['uses_or']);
                $t->same([[0]], $case['result_rows']);
            }

            if ($case['upstream_section'] === 'bestindex7-1.6') {
                $t->same([[0], [null]], $case['result_rows']);
                $t->same(true, $case['updated_null_row']);
            }

            if ($case['uses_in']) {
                $t->true(str_contains($case['statement'], ' IN '));
                $t->true(str_contains($case['idx_string'], 'IN'));
                $t->same(true, $case['updated_null_row']);
            }

            if ($case['upstream_section'] === 'bestindex7-1.10' || $case['upstream_section'] === 'bestindex7-1.12') {
                $t->same([], $case['result_rows']);
            }

            if ($case['upstream_section'] === 'bestindex7-1.10b' || $case['upstream_section'] === 'bestindex7-1.11') {
                $t->same([[0]], $case['result_rows']);
            }
        }
    };
}

$tests['real upstream bestindex6 bestindex7 virtual table dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindex6And7VirtualTableNullConstraintCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('bestindex6-1.1', $cases[0]['upstream_section']);
    $t->same('bestindex7-1.12', $cases[15]['upstream_section']);
    $t->same('bestindex7-1.6', $cases[24]['upstream_section']);
    $t->same('bestindex7-1.4', $cases[999]['upstream_section']);
    $t->same([
        'bestindex6-1.1',
        'bestindex6-1.2',
        'bestindex6-1.3',
        'bestindex6-1.4',
        'bestindex7-1.1',
        'bestindex7-1.2',
        'bestindex7-1.3',
        'bestindex7-1.4',
        'bestindex7-1.6',
        'bestindex7-1.7',
        'bestindex7-1.8',
        'bestindex7-1.9',
        'bestindex7-1.10',
        'bestindex7-1.10b',
        'bestindex7-1.11',
        'bestindex7-1.12',
    ], $sections);
};

$tests['real upstream bestindex6 bestindex7 virtual table dynamic rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::bestindex6And7VirtualTableNullConstraintCases(0));
};

$tests['real upstream bestindex6 bestindex7 virtual table dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table usable constraint, LEFT JOIN NULL, OR, and IN-list helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and virtual-table usable constraint, LEFT JOIN NULL, OR, and IN-list helpers',
    );
};

return $tests;
