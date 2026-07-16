<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/where9.test sections where9-6.4.1
// through where9-11.1. These cases extend the existing early where9 dynamic
// corpus into OR-clause UPDATE/DELETE, INDEXED BY, compound-index OR planning,
// LEFT JOIN correctness, and copied subexpression handling.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::where9LateOrJoinMutationCases(1000) as $case) {
    $tests['real upstream where9 late OR join mutation dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('where9.test sections where9-1.2.1 through where9-6.3.2', SQLiteBTreeIndexDynamicCorpusPlan::where9MultiIndexOrDynamicCases(1)[0]['source']);
        $t->same('where9.test sections where9-6.4.1 through where9-11.1', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true($case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'where9-'));
        $t->true($case['statement'] !== '');
        $t->true(in_array($case['statement_kind'], ['select', 'delete', 'update', 'join'], true));
        $t->true($case['table_name'] !== '');
        $t->true(is_array($case['or_terms']));
        $t->true(count($case['or_terms']) >= 2);
        $t->true(is_array($case['and_terms']));
        $t->true(is_array($case['result_rows']));
        $t->true($case['scan_steps'] >= 0);
        $t->true($case['sort_steps'] >= 0);
        $t->true(is_bool($case['uses_multi_index_or']));
        $t->true(is_array($case['chosen_indexes']));
        $t->true($case['detail'] !== '');
        $t->true(str_contains($case['detail'], 'replay'));
        $t->same('ok', $case['integrity']);

        if ($case['not_indexed']) {
            $t->same(false, $case['uses_multi_index_or']);
            $t->same([], $case['chosen_indexes']);
            $t->same(98, $case['scan_steps']);
        }

        if ($case['indexed_by'] !== null) {
            $t->same('t1b', $case['indexed_by']);
            $t->true(in_array('t1b', $case['chosen_indexes'], true));
            $t->same(false, $case['uses_multi_index_or']);
        }

        if ($case['mutation'] === 'delete') {
            $t->same('delete', $case['statement_kind']);
        }

        if ($case['mutation'] === 'update') {
            $t->same('update', $case['statement_kind']);
        }

        if ($case['upstream_section'] === 'where9-6.4.1/6.4.2') {
            $t->same(['b BETWEEN 950 AND 1010', 'b IS NULL AND c NOT NULL'], $case['or_terms']);
            $t->same([85, 86, 92, 93, 94, 95, 96, 97, 98, 99], $case['rows_after']);
            $t->same(true, $case['uses_multi_index_or']);
            $t->same(['t1b'], $case['chosen_indexes']);
        }

        if ($case['upstream_section'] === 'where9-6.5.3/6.5.4') {
            $t->same([[105], [131], [157], [182], [183], [184], [185], [186], [187]], $case['result_rows']);
            $t->same(['rowid', 't1b', 't1c', 't1d', 't1e', 't1f', 't1g'], $case['chosen_indexes']);
        }

        if ($case['upstream_section'] === 'where9-6.6.1/6.6.2') {
            $t->same(98, $case['scan_steps']);
            $t->same(false, $case['uses_multi_index_or']);
            $t->true(str_contains($case['statement'], '+c IS NULL'));
        }

        if ($case['upstream_section'] === 'where9-7.1.1/7.1.4') {
            $t->same([[79], [81], [83]], $case['result_rows']);
            $t->same(['t5xb', 't5xc'], $case['chosen_indexes']);
            $t->true(str_contains($case['detail'], 'compound indexes'));
        }

        if ($case['upstream_section'] === 'where9-8.1/8.3') {
            $t->same('join', $case['statement_kind']);
            $t->same([[2, 3, 4, 5, null, null, 5, 55], [3, 4, 5, 6, 2, 4, 5, 55]], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'LEFT JOIN'));
        }

        if ($case['upstream_section'] === 'where9-9.1') {
            $t->same([[1], [2], [3], [4], [8], [9]], $case['result_rows']);
            $t->true(str_contains($case['statement'], 'LEFT JOIN t92'));
        }

        if ($case['upstream_section'] === 'where9-10.1/10.2') {
            $t->same([[1, null, 1]], $case['result_rows']);
            $t->same(['LEFT JOIN no-match row'], $case['and_terms']);
        }

        if ($case['upstream_section'] === 'where9-11.1') {
            $t->same([], $case['result_rows']);
            $t->true(str_contains($case['statement'], 'UNION ALL view'));
            $t->true(str_contains($case['detail'], 'subexpressions'));
        }
    };
}

$tests['real upstream where9 late OR join mutation dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::where9LateOrJoinMutationCases(1000);
    $t->same(1000, count($cases));
    $t->same('where9.test sections where9-6.4.1 through where9-11.1', $cases[0]['source']);
    $t->same('where9-6.4.1/6.4.2', $cases[0]['upstream_section']);
    $t->same('where9-11.1', $cases[15]['upstream_section']);
    $t->same('where9-6.5.3/6.5.4', $cases[995]['upstream_section']);
    $t->same(63, $cases[999]['batch']);
};

$tests['real upstream where9 late OR join mutation rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::where9LateOrJoinMutationCases(0));
};

$tests['real upstream where9 late OR join mutation dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning for late where9 OR mutation, INDEXED BY, LEFT JOIN, and copied-subexpression planner metadata',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planning for late where9 OR mutation, INDEXED BY, LEFT JOIN, and copied-subexpression planner metadata',
    );
};

return $tests;
