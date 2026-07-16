<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/indexA.test sections 1.1 through 1.7
// and 4.1 through 8.1. These cases avoid the already-covered indexA 2.x/3.x
// affinity matrix and focus on partial-index join routing, aggregate covering
// reads, collation admission, bloom-filter plans, INDEXED BY, and expression
// index coexistence.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexAJoinPlannerGuardCases(720) as $case) {
    $tests['real upstream indexA join planner guard dynamic ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('indexA.test sections 1.1 through 1.7 and 4.1 through 8.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 720);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 72);
        $t->true(str_starts_with($case['upstream'], 'indexA-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['index_name'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true($case['error'] === null || str_contains($case['error'], 'collation sequence'));
        $t->true($case['collation'] === null || in_array($case['collation'], ['g', 'xyz'], true));

        if ($case['error'] !== null) {
            $t->same([], $case['result_rows']);
            $t->same(false, $case['uses_index']);
            $t->same('g', $case['collation']);
            $t->same('no such collation sequence: g', $case['error']);
            return;
        }

        $t->same($case['uses_index'], str_contains($case['detail'], $case['index_name'])
            || str_contains($case['detail'], 'BLOOM FILTER')
            || str_starts_with($case['detail'], 'CREATE INDEX'));

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if (str_contains($case['upstream'], 'indexA-1.1/1.2')) {
            $t->same([['abc', 1 + (($case['batch'] - 1) * 1000), 2 + (($case['batch'] - 1) * 1000)]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'COVERING INDEX i1'));
        }

        if (str_contains($case['upstream'], 'indexA-1.3/1.7')) {
            $t->same(2, count($case['result_rows']));
            $t->same('i2', $case['index_name']);
            $t->true(str_contains($case['detail'], 'RIGHT-JOIN'));
        }

        if (str_contains($case['upstream'], 'indexA-4.1')) {
            $t->same([[6 + (($case['batch'] - 1) * 1000), 'two']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'COVERING INDEX t2a_two'));
        }

        if (str_contains($case['upstream'], 'indexA-5.2/5.3')) {
            $t->same([], $case['result_rows']);
            $t->same('xyz', $case['collation']);
            $t->same(true, $case['uses_index']);
        }

        if (str_contains($case['upstream'], 'indexA-6.')) {
            $t->same(2, count($case['result_rows']));
            $t->true(str_contains($case['detail'], 'BLOOM FILTER') || $case['index_name'] === 't2yz');
            $t->same(1 + (($case['batch'] - 1) * 1000), $case['result_rows'][0][0]);
            $t->same(2 + (($case['batch'] - 1) * 1000), $case['result_rows'][1][0]);
        }

        if (str_contains($case['upstream'], 'indexA-7.0')) {
            $t->same([[5 + (($case['batch'] - 1) * 1000), 'abc', 'xyz']], $case['result_rows']);
            $t->same('i1', $case['index_name']);
        }

        if (str_contains($case['upstream'], 'indexA-8.1')) {
            $t->same(2, count($case['result_rows']));
            $t->same('ex1', $case['index_name']);
            $t->same(4 + (($case['batch'] - 1) * 1000), $case['result_rows'][0][1]);
        }
    };
}

return $tests;
