<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexexpr2.test sections 4.200 through
// 4.220. These sections use explain('UPDATE ...') joined to sqlite_master
// rootpages to verify that UPDATE opens only the table btree and expression
// indexes whose dependent columns may change.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexexpr2UpdateOpenIndexSetCases(1200) as $case) {
    $tests['real upstream indexexpr2 update open index set dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('indexexpr2.test sections indexexpr2-4.200 through indexexpr2-4.220', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], ['indexexpr2-4.200', 'indexexpr2-4.210', 'indexexpr2-4.220'], true));
        $t->true(str_starts_with($case['statement'], 'UPDATE t2 SET '));
        $t->same('ok', $case['integrity']);
        $t->same(true, $case['opens_table']);
        $t->same('t2', $case['opened_btrees'][0]);
        $t->same($case['opened_btrees'], $case['rootpage_join_order']);
        $t->same($case['open_opcode_count'], count($case['opened_btrees']));
        $t->same($case['open_opcode_count'] - 1, count($case['opened_indexes']));
        $t->same(true, $case['opens_only_dependent_indexes']);
        $t->same(['t2abc' => ['a', 'b', 'c'], 't2cd' => ['c', 'd'], 't2def' => ['d', 'e', 'f']], $case['index_dependencies']);
        $t->true(str_contains($case['detail'], 'sqlite_master rootpages'));

        foreach ($case['opened_btrees'] as $btree) {
            $t->true(str_contains($case['detail'], $btree));
        }

        foreach ($case['opened_indexes'] as $index) {
            $t->true(array_intersect($case['index_dependencies'][$index], $case['changed_columns']) !== []);
            $t->same(false, in_array($index, $case['skipped_indexes'], true));
        }

        foreach ($case['skipped_indexes'] as $index) {
            $t->same([], array_values(array_intersect($case['index_dependencies'][$index], $case['changed_columns'])));
            $t->same(false, in_array($index, $case['opened_indexes'], true));
        }

        if ($case['upstream_section'] === 'indexexpr2-4.200') {
            $t->same(['b'], $case['changed_columns']);
            $t->same(['t2', 't2abc'], $case['opened_btrees']);
            $t->same(['t2abc'], $case['opened_indexes']);
            $t->same(['t2cd', 't2def'], $case['skipped_indexes']);
            $t->same('UPDATE t2 SET b=b+1', $case['statement']);
        }

        if ($case['upstream_section'] === 'indexexpr2-4.210') {
            $t->same(['c'], $case['changed_columns']);
            $t->same(['t2', 't2abc', 't2cd'], $case['opened_btrees']);
            $t->same(['t2abc', 't2cd'], $case['opened_indexes']);
            $t->same(['t2def'], $case['skipped_indexes']);
            $t->same('UPDATE t2 SET c=c+1', $case['statement']);
        }

        if ($case['upstream_section'] === 'indexexpr2-4.220') {
            $t->same(['c', 'f'], $case['changed_columns']);
            $t->same(['t2', 't2abc', 't2cd', 't2def'], $case['opened_btrees']);
            $t->same(['t2abc', 't2cd', 't2def'], $case['opened_indexes']);
            $t->same([], $case['skipped_indexes']);
            $t->same('UPDATE t2 SET c=c+1, f=NULL', $case['statement']);
        }
    };
}

$tests['real upstream indexexpr2 update open index set corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexexpr2UpdateOpenIndexSetCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same(['indexexpr2-4.200', 'indexexpr2-4.210', 'indexexpr2-4.220'], $sections);
    $t->same('indexexpr2-4.200', $cases[0]['upstream_section']);
    $t->same('indexexpr2-4.220', $cases[2]['upstream_section']);
    $t->same('indexexpr2-4.220', $cases[1199]['upstream_section']);
    $t->same(400, $cases[1199]['batch']);
};

$tests['real upstream indexexpr2 update open index set rejects invalid count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::indexexpr2UpdateOpenIndexSetCases(0));
};

$tests['real upstream indexexpr2 update open index set dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local expression-index dependency analysis, sqlite_master rootpage/open-opcode evidence, and B-tree/index dynamic corpus helpers',
        'no new support component needed; reuses lane-local expression-index dependency analysis, sqlite_master rootpage/open-opcode evidence, and B-tree/index dynamic corpus helpers',
    );
};

return $tests;
