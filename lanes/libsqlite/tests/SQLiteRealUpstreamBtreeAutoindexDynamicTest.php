<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/autoindex1.test sections autoindex1-100
// through autoindex-1211. These cases cover automatic-index gating, planner
// targets, status counters, mutation snapshot stability, subquery/view
// materialization, and WITHOUT ROWID automatic-index admission.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::autoindex1AutomaticIndexPlannerCases(1000) as $case) {
    $tests['real upstream autoindex1 automatic index planner dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('autoindex1.test autoindex1-100 through autoindex-1211', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], [
            'autoindex1-100/110',
            'autoindex1-110/113',
            'autoindex1-200/210',
            'autoindex1-300/310',
            'autoindex1-400/401',
            'autoindex1-500.1/501/502',
            'autoindex1-600a',
            'autoindex1-700a',
            'autoindex1-900/901',
            'autoindex1-920/1020',
            'autoindex1-1110/1120',
            'autoindex1-1210/1211',
        ], true));
        $t->true($case['scenario'] !== '');
        $t->true($case['join_shape'] !== '');
        $t->same('ok', $case['integrity']);
        $t->same($case['uses_automatic_index'], str_contains($case['detail'], 'AUTOMATIC COVERING INDEX'));
        $t->true($case['autoindex_inserts'] >= 0);
        $t->true($case['step_count'] === null || $case['step_count'] >= $case['autoindex_inserts']);
        $t->true(array_values($case['expected_rows']) === $case['expected_rows']);

        foreach ($case['expected_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['upstream_section'] === 'autoindex1-100/110') {
            $t->same(false, $case['automatic_index_enabled']);
            $t->same(63, $case['step_count']);
            $t->same(0, $case['autoindex_inserts']);
            $t->same('t2(c)', $case['autoindex_target']);
            $t->same([[11, 911], [22, 922], [33, 933], [44, 944], [55, 955], [66, 966], [77, 977], [88, 988]], $case['expected_rows']);
        }

        if ($case['upstream_section'] === 'autoindex1-110/113') {
            $t->same(true, $case['automatic_index_enabled']);
            $t->same(7, $case['step_count']);
            $t->same(7, $case['autoindex_inserts']);
            $t->true(str_contains($case['detail'], 't2'));
        }

        if ($case['upstream_section'] === 'autoindex1-200/210') {
            $t->true(str_contains($case['detail'], 'CORRELATED SCALAR SUBQUERY'));
            $t->same(7, $case['step_count']);
            $t->same(7, $case['autoindex_inserts']);
        }

        if ($case['upstream_section'] === 'autoindex1-300/310') {
            $t->same('UPDATE t2 SET d=d+1 during each output row', $case['mutation']);
            $t->same([[11, 911], [22, 922], [33, 933], [44, 944], [55, 955], [66, 966], [77, 977], [88, 988]], $case['expected_rows']);
        }

        if ($case['upstream_section'] === 'autoindex1-400/401') {
            $t->same([[4087]], $case['expected_rows']);
            $t->same(9, $case['autoindex_inserts']);
            $t->true(str_contains($case['join_shape'], 'x10'));
        }

        if ($case['upstream_section'] === 'autoindex1-500.1/501/502') {
            $t->same('t502(y)', $case['autoindex_target']);
            $t->true(str_contains($case['detail'], 'CORRELATED LIST SUBQUERY'));
            $t->same([], $case['expected_rows']);
        }

        if ($case['upstream_section'] === 'autoindex1-600a') {
            $t->same('y(sheep_no)', $case['autoindex_target']);
            $t->true(str_contains($case['detail'], 'MATERIALIZE y'));
            $t->true(str_contains($case['detail'], 'LEFT-JOIN'));
        }

        if ($case['upstream_section'] === 'autoindex1-700a') {
            $t->same(false, $case['uses_automatic_index']);
            $t->same('', $case['autoindex_target']);
            $t->true(str_contains($case['detail'], 'USE TEMP B-TREE FOR ORDER BY'));
        }

        if ($case['upstream_section'] === 'autoindex1-900/901') {
            $t->same('agglabels(message_id)', $case['autoindex_target']);
            $t->true(str_contains($case['detail'], 'agg2'));
        }

        if ($case['upstream_section'] === 'autoindex1-920/1020') {
            $t->same(false, $case['uses_automatic_index']);
            $t->same([[5, 0, 9], [5, 0, 9], [5, 0, 9]], $case['expected_rows']);
            $t->true(str_contains($case['detail'], 'NULL'));
        }

        if ($case['upstream_section'] === 'autoindex1-1110/1120') {
            $t->same([[1, 1, 1, 2, null, null]], $case['expected_rows']);
            $t->true(str_contains($case['detail'], 'LEFT JOIN'));
        }

        if ($case['upstream_section'] === 'autoindex1-1210/1211') {
            $t->same('t1(b)', $case['autoindex_target']);
            $t->same([[null, null, null, 5, 55], [1, 3, 91, 3, 33], [1, 4, 92, 4, 44]], $case['expected_rows']);
            $t->true(str_contains($case['join_shape'], 'WITHOUT ROWID'));
        }
    };
}

return $tests;
