<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/autoindex5.test. These cases cover
// automatic-index planning around coroutine subqueries, rowid binding inside
// nested coroutine SELECTs, DISTINCT subqueries feeding IN/OR terms, and the
// result rows preserved by those plans.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::autoindex5CoroutineSubqueryCases(1000) as $case) {
    $tests['real upstream autoindex5 coroutine subquery dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['source'], 'autoindex5.test autoindex5-'));
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'autoindex5-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['table'] !== '');
        $t->true($case['autoindex_target'] !== '');
        $t->true($case['probe_column'] !== '');
        $t->true($case['detail'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true($case['uses_coroutine']);
        $t->true(array_values($case['result_rows']) === $case['result_rows']);
        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['upstream_section'] === 'autoindex5-1.1') {
            $t->same('debian_cve', $case['table']);
            $t->same('bug_name', $case['autoindex_target']);
            $t->same('bug_name', $case['probe_column']);
            $t->true(str_starts_with((string) $case['probe_value'], 'CVE-'));
            $t->same(true, $case['uses_automatic_index']);
            $t->same(true, $case['order_by_preserved']);
            $t->same(false, $case['subquery_resolves_rowid']);
            $t->same([[$case['probe_value'], $case['case'], 'sid']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'AUTOMATIC COVERING INDEX'));
        } elseif ($case['upstream_section'] === 'autoindex5-2.1') {
            $t->same('vvv', $case['table']);
            $t->same('x', $case['autoindex_target']);
            $t->same('aaa', $case['probe_value']);
            $t->same(false, $case['uses_automatic_index']);
            $t->same([[8.0]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'sum(z)'));
        } elseif ($case['upstream_section'] === 'autoindex5-2.2') {
            $t->same('t1', $case['table']);
            $t->same('rowid', $case['autoindex_target']);
            $t->same('rowid', $case['probe_column']);
            $t->same(1, $case['probe_value']);
            $t->same(true, $case['subquery_resolves_rowid']);
            $t->same([[9]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'rowid'));
        } elseif ($case['upstream_section'] === 'autoindex5-3.1') {
            $t->same('t1/t2/t3/t4', $case['table']);
            $t->same('t3.c', $case['autoindex_target']);
            $t->same('c', $case['probe_column']);
            $t->same(104, $case['probe_value']);
            $t->same([[104, 104]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'DISTINCT'));
        } elseif ($case['upstream_section'] === 'autoindex5-3.2') {
            $t->same('t5/t6', $case['table']);
            $t->same('t6.e', $case['autoindex_target']);
            $t->same('e', $case['probe_column']);
            $t->same(1, $case['probe_value']);
            $t->same([[1, 1, 1, 1]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'DISTINCT'));
        } else {
            $t->same('autoindex5-3.3', $case['upstream_section']);
            $t->same('t1/t2', $case['table']);
            $t->same('t2.d', $case['autoindex_target']);
            $t->same('d', $case['probe_column']);
            $t->same(3, $case['probe_value']);
            $t->same([[3, 1, 1, 'x'], [3, 2, 2, 'x']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'OR'));
        }
    };
}

$tests['real upstream autoindex5 coroutine subquery source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::autoindex5CoroutineSubqueryCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same(['autoindex5-1.1', 'autoindex5-2.1', 'autoindex5-2.2', 'autoindex5-3.1', 'autoindex5-3.2', 'autoindex5-3.3'], $sections);
    $t->same(200, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex5-1.1')));
    $t->same(200, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex5-2.1')));
    $t->same(200, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex5-2.2')));
    $t->same(100, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex5-3.1')));
    $t->same(100, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex5-3.2')));
    $t->same(200, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'autoindex5-3.3')));
};

return $tests;
