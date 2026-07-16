<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/bestindexF.test sections 1.1.1
// through 2.11. The upstream script verifies virtual-table xBestIndex
// DISTINCT and ORDER BY contracts, including no-sorter VFilter idxStr
// handoff and the cases where DISTINCT still needs IdxInsert.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::bestindexFDistinctOrderByCases(1000) as $case) {
    $tests['real upstream bestindexF distinct order dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('bestindexF.test sections 1.1.1 through 2.11', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'bestindexF-'));
        $t->true(str_contains($case['sql'], 'dynamic batch'));
        $t->true($case['distinct_mode'] >= 0 && $case['distinct_mode'] <= 3);
        $t->same(false, $case['uses_sorter']);
        $t->same($case['idx_insert'], str_contains($case['detail'], 'idxInsert=yes'));
        $t->true(str_contains($case['detail'], 'xBestIndex distinct=' . $case['distinct_mode']));
        $t->true(str_contains($case['detail'], 'sorter=no'));
        $t->true($case['idx_str'] === '' || str_contains($case['detail'], $case['idx_str']));
        $t->true(count($case['result_rows']) > 0);

        foreach ($case['orderby'] as $term) {
            $t->true($term['column'] >= 0);
            $t->true(is_bool($term['desc']));
        }

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['upstream_section'] === 'bestindexF-1.1.1/1.1.2') {
            $t->same(2, $case['distinct_mode']);
            $t->same([['column' => 0, 'desc' => false], ['column' => 1, 'desc' => false]], $case['orderby']);
            $t->same(false, $case['idx_insert']);
            $t->same([[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexF-1.4.1/1.4.2') {
            $t->same(3, $case['distinct_mode']);
            $t->same([['column' => 0, 'desc' => false]], $case['orderby']);
            $t->same(true, $case['idx_insert']);
            $t->same([[0], [1]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexF-2.3') {
            $t->same('DISTINCT {ORDER BY ((a+2)%5)}', $case['idx_str']);
            $t->same([[3], [4], [1], [2]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexF-2.4') {
            $t->same('DISTINCT {ORDER BY a}', $case['idx_str']);
            $t->same([[1], [2], [3], [4]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexF-2.5') {
            $t->same('DISTINCT {ORDER BY a DESC}', $case['idx_str']);
            $t->same([[4], [3], [2], [1]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexF-2.10') {
            $t->same('{} {ORDER BY ((a+2)%5)}', $case['idx_str']);
            $t->same([[3, 3], [4, 3], [1, 3], [2, 3]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'bestindexF-2.11') {
            $t->same(true, $case['idx_insert']);
            $t->same('{} {ORDER BY ((a+2)%5)}', $case['idx_str']);
            $t->same([[3, 3], [4, 3], [1, 3], [2, 3]], $case['result_rows']);
        }
    };
}

$tests['real upstream bestindexF distinct order corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::bestindexFDistinctOrderByCases(1000);
    $t->same(1000, count($cases));
    $t->same('bestindexF-1.1.1/1.1.2', $cases[0]['upstream_section']);
    $t->same('bestindexF-2.11', $cases[11]['upstream_section']);
    $t->same('bestindexF-2.3', $cases[999]['upstream_section']);
};

$tests['real upstream bestindexF distinct order corpus rejects empty count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDynamicCorpusPlan::bestindexFDistinctOrderByCases(0));
};

return $tests;
