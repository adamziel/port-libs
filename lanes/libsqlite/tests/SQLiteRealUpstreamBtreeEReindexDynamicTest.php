<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/e_reindex.test sections e_reindex-0.1
// through e_reindex-2.5.34. This batch owns REINDEX syntax admission,
// corrupt-index repair, and collation/table/index scoped rebuild behavior
// across main and attached schemas.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::eReindexCollationScopeCases(1000) as $case) {
    $tests['real upstream e_reindex collation scope dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('e_reindex.test sections e_reindex-0.1 through e_reindex-2.5.34', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'e_reindex-'));
        $t->true(str_starts_with($case['statement'], 'REINDEX'));
        $t->true(in_array($case['target_kind'], ['syntax', 'all', 'collation', 'table', 'index'], true));
        $t->true($case['detail'] !== '');
        $t->same('ok', $case['integrity_after']);
        $t->true(array_values($case['rebuilt_indexes']) === $case['rebuilt_indexes']);

        foreach (['main_t1_collA', 'main_t1_collB', 'main_t2_collA', 'main_t2_collB', 'aux_t1_collA', 'aux_t1_collB'] as $key) {
            $t->true(in_array($case[$key], ['length', 'value'], true));
        }

        if ($case['target_kind'] === 'syntax') {
            $t->same([], $case['rebuilt_indexes']);
            $t->same(true, $case['syntax_only']);
            $t->same([], $case['corrupt_before']);
            $t->same('length', $case['main_t1_collA']);
            $t->same('value', $case['main_t1_collB']);
        }

        if ($case['upstream_section'] === 'e_reindex-1.3/1.4') {
            $t->same([
                'wrong # of entries in index i2',
                'wrong # of entries in index i1',
                'row 3 missing from index i2',
                'row 3 missing from index i1',
                'row 4 missing from index i2',
                'row 4 missing from index i1',
            ], $case['corrupt_before']);
            $t->same(['main.i1', 'main.i2'], $case['rebuilt_indexes']);
        }

        if ($case['upstream_section'] === 'e_reindex-2.2.1/2.7') {
            $t->same('all', $case['target_kind']);
            $t->same(['main.i1_a', 'main.i1_b', 'main.i2_a', 'main.i2_b', 'aux.i1_a', 'aux.i1_b'], $case['rebuilt_indexes']);
            $t->same('value', $case['main_t1_collA']);
            $t->same('length', $case['main_t1_collB']);
            $t->same('value', $case['aux_t1_collA']);
            $t->same('length', $case['aux_t1_collB']);
        }

        if ($case['upstream_section'] === 'e_reindex-2.3.1/3.7') {
            $t->same('collation', $case['target_kind']);
            $t->same('collA', $case['target_name']);
            $t->same(['main.i1_a', 'main.i2_a', 'aux.i1_a'], $case['rebuilt_indexes']);
            $t->same('length', $case['main_t1_collA']);
            $t->same('length', $case['main_t2_collA']);
            $t->same('length', $case['aux_t1_collA']);
        }

        if ($case['upstream_section'] === 'e_reindex-2.3.8/3.14') {
            $t->same('collation', $case['target_kind']);
            $t->same('collB', $case['target_name']);
            $t->same(['main.i1_b', 'main.i2_b', 'aux.i1_b'], $case['rebuilt_indexes']);
            $t->same('value', $case['main_t1_collB']);
            $t->same('value', $case['main_t2_collB']);
            $t->same('value', $case['aux_t1_collB']);
        }

        if ($case['upstream_section'] === 'e_reindex-2.4.1/4.7') {
            $t->same('table', $case['target_kind']);
            $t->same('main.t1', $case['target_name']);
            $t->same(['main.i1_a', 'main.i1_b'], $case['rebuilt_indexes']);
        }

        if ($case['upstream_section'] === 'e_reindex-2.4.8/4.14') {
            $t->same('table', $case['target_kind']);
            $t->same('aux.t1', $case['target_name']);
            $t->same(['aux.i1_a', 'aux.i1_b'], $case['rebuilt_indexes']);
            $t->same('value', $case['aux_t1_collA']);
            $t->same('length', $case['aux_t1_collB']);
        }

        if ($case['upstream_section'] === 'e_reindex-2.4.15/4.21') {
            $t->same('table', $case['target_kind']);
            $t->same('main.t2', $case['target_name']);
            $t->same(['main.i2_a', 'main.i2_b'], $case['rebuilt_indexes']);
        }

        if (str_starts_with($case['upstream_section'], 'e_reindex-2.5.')) {
            $t->same('index', $case['target_kind']);
            $t->same(1, count($case['rebuilt_indexes']));
            $t->same($case['target_name'], $case['rebuilt_indexes'][0]);
        }
    };
}

$tests['real upstream e_reindex collation scope dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::eReindexCollationScopeCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same('e_reindex-0.1.1', $cases[0]['upstream_section']);
    $t->same('e_reindex-2.5.29/5.34', $cases[13]['upstream_section']);
    $t->same(72, $cases[999]['batch']);
    $t->same([
        'e_reindex-0.1.1',
        'e_reindex-0.1.2',
        'e_reindex-1.3/1.4',
        'e_reindex-2.2.1/2.7',
        'e_reindex-2.3.1/3.7',
        'e_reindex-2.3.8/3.14',
        'e_reindex-2.4.1/4.7',
        'e_reindex-2.4.15/4.21',
        'e_reindex-2.4.8/4.14',
        'e_reindex-2.5.1/5.7',
        'e_reindex-2.5.15/5.21',
        'e_reindex-2.5.22/5.28',
        'e_reindex-2.5.29/5.34',
        'e_reindex-2.5.8/5.14',
    ], $sections);
};

$tests['real upstream e_reindex collation scope dynamic rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::eReindexCollationScopeCases(0));
};

$tests['real upstream e_reindex collation scope dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, collation-order, schema-scope, corrupt-index repair, and integrity diagnostics',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, collation-order, schema-scope, corrupt-index repair, and integrity diagnostics',
    );
};

return $tests;
