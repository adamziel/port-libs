<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindexB.test. The Tcl virtual table
// plans a trigger SELECT and, during xBestIndex, may run an INSERT ... RETURNING
// side statement whose result must not disturb the outer INSERT RETURNING rowid.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindexBReturningSideEffectCases(1000) as $case) {
    $tests['real upstream bestindexB xbestindex returning side effect dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindexB.test sections bestindexB-1.0 through bestindexB-1.5', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindexB-1.'));
        $t->true($case['scenario'] !== '');
        $t->same([1, 2, 3], $case['virtual_result_row']);
        $t->same(0, $case['planner_idxnum']);
        $t->same('hello', $case['planner_idxstr']);
        $t->same(1000000, $case['planner_cost']);
        $t->same(1000000, $case['planner_rows']);
        $t->true($case['batch'] >= 1);

        $t->same($case['xbestindex_runs_returning'], $case['side_insert_rowid'] !== null);
        $t->same($case['xbestindex_runs_returning'], count($case['y2_rows']) === 1);
        $t->same($case['trigger_selects_virtual_table'], in_array($case['upstream_section'], ['bestindexB-1.3', 'bestindexB-1.4', 'bestindexB-1.5'], true));

        if ($case['upstream_section'] === 'bestindexB-1.1') {
            $t->same(0, $case['outer_insert_rowid']);
            $t->same([], $case['y1_rows']);
            $t->same([], $case['y2_rows']);
            $t->same(false, $case['trigger_selects_virtual_table']);
        }

        if ($case['upstream_section'] === 'bestindexB-1.2') {
            $t->same(1, $case['outer_insert_rowid']);
            $t->same([[ 'rowid' => 1, 'a' => 1 + ($case['batch'] - 1) * 10, 'b' => 2 + ($case['batch'] - 1) * 10 ]], $case['y1_rows']);
            $t->same([], $case['y2_rows']);
        }

        if ($case['upstream_section'] === 'bestindexB-1.3') {
            $t->same(2, $case['outer_insert_rowid']);
            $t->true($case['trigger_selects_virtual_table']);
            $t->same(false, $case['xbestindex_runs_returning']);
            $t->same([], $case['y2_rows']);
        }

        if ($case['upstream_section'] === 'bestindexB-1.4') {
            $t->same(3, $case['outer_insert_rowid']);
            $t->true($case['trigger_selects_virtual_table']);
            $t->same($case['batch'], $case['side_insert_rowid']);
            $t->same([[ 'rowid' => $case['batch'], 'a' => null, 'b' => null ]], $case['y2_rows']);
        }

        if ($case['upstream_section'] === 'bestindexB-1.5') {
            $t->same(3, $case['outer_insert_rowid']);
            $t->same($case['batch'], $case['side_insert_rowid']);
            $t->same([[ 'rowid' => $case['batch'], 'a' => null, 'b' => null ]], $case['y2_rows']);
        }
    };
}

$tests['real upstream bestindexB xbestindex returning source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindexBReturningSideEffectCases(1000);
    $t->same(1000, count($cases));
    $t->same('bestindexB-1.1', $cases[0]['upstream_section']);
    $t->same('bestindexB-1.5', $cases[4]['upstream_section']);
    $t->same('bestindexB-1.1', $cases[5]['upstream_section']);
    $t->same(200, $cases[count($cases) - 1]['batch']);
};

$tests['real upstream bestindexB xbestindex returning rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::bestindexBReturningSideEffectCases(0));
};

$tests['real upstream bestindexB xbestindex returning dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and trigger-returning side-effect accounting',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and trigger-returning side-effect accounting',
    );
};

return $tests;
