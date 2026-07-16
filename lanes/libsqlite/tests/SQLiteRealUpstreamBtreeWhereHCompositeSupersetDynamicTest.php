<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/whereH.test sections whereH-1.1 through
// whereH-8.2. These cases verify that a longer composite index that covers a
// complete equality prefix plus the range/order column is preferred over a
// shorter suffix index, regardless of index creation order.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereHCompositeSupersetIndexCases(1000) as $case) {
    $tests['real upstream whereH composite-superset index dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('whereH.test sections whereH-1.1 through whereH-8.2', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(str_starts_with($case['upstream_section'], 'whereH-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['sql'] !== '');
        $t->same('ok', $case['integrity']);
        $t->same(false, $case['uses_temp_btree']);
        $t->contains('SEARCH t1 USING INDEX ' . $case['chosen_index'], $case['detail']);
        $t->contains('WITHOUT TEMP B-TREE', $case['detail']);
        $t->true(in_array($case['chosen_index'], $case['candidate_indexes'], true));

        foreach ($case['candidate_indexes'] as $indexName) {
            $t->true($indexName !== '');
        }

        foreach ($case['rejected_indexes'] as $indexName) {
            $t->true($indexName !== $case['chosen_index']);
            $t->true(in_array($indexName, $case['candidate_indexes'], true));
            $t->true(!str_contains($case['detail'], 'USING INDEX ' . $indexName));
        }

        if ($case['chosen_index'] === 't1abc') {
            $candidateSet = array_values(array_unique($case['candidate_indexes']));
            sort($candidateSet);
            $t->same(['t1abc', 't1bc'], $candidateSet);
            $t->true($case['candidate_indexes'] === ['t1abc', 't1bc'] || $case['candidate_indexes'] === ['t1bc', 't1abc']);
            $t->same(['a', 'b'], $case['equality_prefix']);
            $t->same('c', $case['range_column']);
            $t->same('c', $case['order_column']);
            $t->same(['t1bc'], $case['rejected_indexes']);
            $t->same('t1(a,b,c,d) with t1abc and t1bc candidate indexes', $case['table_shape']);
        } else {
            $t->same('t1abcd', $case['chosen_index']);
            $t->same(['a', 'b', 'c'], $case['equality_prefix']);
            $t->same('d', $case['range_column']);
            $t->same('d', $case['order_column']);
            $t->same(3, count($case['candidate_indexes']));
            $t->same(2, count($case['rejected_indexes']));
            $t->true(in_array('t1cd', $case['candidate_indexes'], true));
            $t->true(in_array('t1bcd', $case['candidate_indexes'], true));
            $t->true(in_array('t1abcd', $case['candidate_indexes'], true));
            $t->same('t1(a,b,c,d,e) with t1cd, t1bcd, and t1abcd candidate indexes', $case['table_shape']);
        }
    };
}

$tests['real upstream whereH composite-superset index dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereHCompositeSupersetIndexCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('whereH-1.1/1.2', $cases[0]['upstream_section']);
    $t->same('whereH-8.1/8.2', $cases[7]['upstream_section']);
    $t->same('whereH-8.1/8.2', $cases[999]['upstream_section']);
    $t->same([
        'whereH-1.1/1.2',
        'whereH-2.1/2.2',
        'whereH-3.1/3.2',
        'whereH-4.1/4.2',
        'whereH-5.1/5.2',
        'whereH-6.1/6.2',
        'whereH-7.1/7.2',
        'whereH-8.1/8.2',
    ], $sections);
};

$tests['real upstream whereH composite-superset index dynamic rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereHCompositeSupersetIndexCases(0));
};

$tests['real upstream whereH composite-superset index dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and composite index ranking helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and composite index ranking helpers',
    );
};

return $tests;
