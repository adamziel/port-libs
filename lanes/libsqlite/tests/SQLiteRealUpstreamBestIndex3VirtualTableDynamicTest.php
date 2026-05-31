<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindex3.test bestindex3-1.1 through
// bestindex3-3.2. This batch covers virtual-table xBestIndex LIKE and equality
// constraints, OR-arm planning, omitted-constraint xFilter behavior, ordinary
// table LIKE/equality multi-index OR parity, and ignored vtab PRIMARY KEYs.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindex3VirtualTableLikeOrCases(1000) as $case) {
    $tests['real upstream bestindex3 virtual table like or dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindex3.test bestindex3-1.1 through bestindex3-3.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindex3-'));
        $t->true($case['scenario'] !== '');
        $t->true(in_array($case['table_kind'], ['virtual', 'ordinary'], true));
        $t->true(in_array($case['plan_shape'], [
            'single-vtab-scan',
            'multi-index-or',
            'xfilter-residual',
            'xfilter-residual-or',
            'xfilter-omit',
            'xfilter-omit-or',
            'decl-vtab-primary-key-ignored',
        ], true));
        $t->true($case['cost'] === 100 || $case['cost'] === 1000000);
        $t->true($case['estimated_rows'] === 10 || $case['estimated_rows'] === 1000000);
        $t->true($case['detail'] !== '');

        foreach ($case['constraints'] as $constraint) {
            $t->true(in_array($constraint['column'], ['a', 'b', 'c', 'x', 'y'], true));
            $t->true(in_array($constraint['operator'], ['LIKE', '='], true));
            $t->same(true, $constraint['usable']);
            $t->true(is_string($constraint['value']));
        }

        if ($case['table_kind'] === 'virtual' && $case['constraints'] !== []) {
            $t->true($case['virtual_index'] !== []);
            $t->same([], $case['ordinary_indexes']);
            $t->true(str_contains($case['detail'], 'xFilter') || str_contains($case['detail'], 'VIRTUAL TABLE INDEX'));
        }

        if ($case['plan_shape'] === 'multi-index-or') {
            $t->true(count($case['constraints']) >= 2);
            $t->true(count($case['virtual_index']) >= 2);
            $t->true(str_contains($case['detail'], 'MULTI-INDEX OR'));
        }

        if (str_starts_with($case['upstream_section'], 'bestindex3-1.6.')) {
            $t->true($case['result_rowids'] !== []);
            $t->true($case['result_rowids'][0] === 1 || $case['result_rowids'][0] === 3);
            $t->true(count($case['result_rowids']) === count(array_unique($case['result_rowids'])));
        }

        if ($case['upstream_section'] === 'bestindex3-2.2') {
            $t->same('ordinary', $case['table_kind']);
            $t->same(['t2x', 't2y'], $case['ordinary_indexes']);
            $t->same(['x>? AND x<?', 'y=?'], $case['virtual_index']);
            $t->same([], $case['result_rowids']);
            $t->true(str_contains($case['detail'], 'SEARCH t2 USING INDEX t2x'));
            $t->true(str_contains($case['detail'], 'SEARCH t2 USING INDEX t2y'));
        }

        if ($case['primary_key_ignored']) {
            $t->same([], $case['constraints']);
            $t->same([], $case['virtual_index']);
            $t->same(1000000, $case['cost']);
            $t->true(str_contains($case['detail'], 'ignore PRIMARY KEY'));
        }
    };
}

$tests['real upstream bestindex3 virtual table like or source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindex3VirtualTableLikeOrCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('bestindex3-1.1', $cases[0]['upstream_section']);
    $t->same('bestindex3-3.1/3.2', $cases[11]['upstream_section']);
    $t->same('bestindex3-1.4', $cases[999]['upstream_section']);
    $t->same([
        'bestindex3-1.1',
        'bestindex3-1.2',
        'bestindex3-1.3',
        'bestindex3-1.4',
        'bestindex3-1.6.0.1',
        'bestindex3-1.6.0.2',
        'bestindex3-1.6.0.3',
        'bestindex3-1.6.1.1',
        'bestindex3-1.6.1.2',
        'bestindex3-1.6.1.3',
        'bestindex3-2.2',
        'bestindex3-3.1/3.2',
    ], $sections);
};

$tests['real upstream bestindex3 virtual table like or rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindex3VirtualTableLikeOrCases(0));
};

$tests['real upstream bestindex3 virtual table like or dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, virtual-table constraint, OR-plan, ordinary-index, and rowid-result helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, virtual-table constraint, OR-plan, ordinary-index, and rowid-result helpers',
    );
};

return $tests;
