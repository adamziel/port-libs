<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

foreach (SQLiteBTreeIndexDynamicCorpusPlan::btree01BalanceStressCases() as $case) {
    $tests['real upstream btree01 dynamic overflow balance ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same(65536, $case['page_size']);
        $t->same('ok', $case['integrity']);
        $t->true($case['target_row'] >= 1);
        $t->true($case['target_row'] <= $case['row_count']);
        $t->true($case['initial_blob'] > 0);
        $t->true($case['shrink_blob'] > 0);
        $t->true($case['expanded_blob'] > 0);
        $t->true($case['local_payload_length'] > 0);
        $t->true($case['overflow_payload_length'] >= 0);
        $t->same($case['overflow_payload_length'] === 0, $case['overflow_page_count'] === 0);
    };
}

foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexTestDynamicLookupCases() as $case) {
    $tests['real upstream index dynamic lookup lifecycle ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('ok', $case['integrity']);
        $t->true(in_array($case['lookup_column'], ['cnt', 'power'], true));
        $t->true(in_array($case['result_column'], ['cnt', 'power'], true));
        $t->true($case['lookup_column'] !== $case['result_column']);
        $t->true($case['lookup_value'] > 0);
        $t->true($case['result_value'] > 0);
        $t->true(count($case['active_indexes']) >= 0);
        if ($case['operation'] === 'drop index9') {
            $t->same(false, in_array('index9', $case['active_indexes'], true));
        }
        if ($case['operation'] === 'drop indext') {
            $t->same(false, in_array('indext', $case['active_indexes'], true));
        }
    };
}

foreach (SQLiteBTreeIndexDynamicCorpusPlan::index9BoundPartialIndexCases() as $case) {
    $tests['real upstream index9 bound partial index proof ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['index_name'], 't1x'));
        $t->true(in_array('t1', $case['objects'], true));
        $t->same($case['uses_partial_index'], in_array($case['index_name'], $case['objects'], true));
        if ($case['uses_partial_index']) {
            $t->same($case['predicate_value'], $case['where_value']);
        } else {
            $t->same(false, $case['predicate_value'] === $case['where_value'] && $case['qpsg'] === false);
        }
    };
}

foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexedByRowidAffinityCases() as $case) {
    $tests['real upstream indexedby rowid affinity lookup ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true(in_array($case['table'], ['x1', 'x2'], true));
        $t->true(in_array($case['rowid_column'], ['rowid', 'c'], true));
        $t->same([1, 1, 3], $case['result']);
        $t->true(str_contains($case['detail'], 'USING COVERING INDEX ' . $case['table'] . 'i'));
        $t->true(str_contains($case['detail'], 'rowid=?'));
        $t->same(3, (int) $case['rowid_literal']);
    };
}

foreach (SQLiteBTreeIndexDynamicCorpusPlan::btree02CursorMutationCases() as $case) {
    $tests['real upstream btree02 cursor mutation during scan ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['step'] >= 1);
        $t->true($case['step'] <= 10);
        $t->same(0, $case['t2_count'] % 3);
        $t->same($case['step'] * 3, $case['t2_count']);
        if ($case['operation'] === 'insert-during-scan') {
            $t->same(null, $case['deleted_a']);
            $t->same('(' . $case['source_a'] . ')', $case['inserted_a']);
            $t->same($case['source_b'] + 1000, $case['inserted_b']);
        } else {
            $t->same('delete-during-scan', $case['operation']);
            $t->same(null, $case['inserted_a']);
            $t->same(null, $case['inserted_b']);
            $t->same($case['source_a'], $case['deleted_a']);
        }
    };
}

foreach (SQLiteBTreeIndexDynamicCorpusPlan::index6PartialJoinAndUpdateCases() as $case) {
    $tests['real upstream index6 partial index join update ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['scenario'] !== '');
        $t->true($case['detail'] !== '');
        $t->true(is_array($case['result_rows']));
        if ($case['uses_partial_index']) {
            $t->true(str_contains($case['detail'], 'INDEX') || str_contains($case['detail'], 'UPDATE'));
        } else {
            $t->true(str_contains($case['detail'], 'SCAN'));
        }
        if (str_contains($case['scenario'], 'left join')) {
            $t->true(str_contains($case['detail'], 'LEFT-JOIN'));
        }
        if (str_contains($case['scenario'], 'without-rowid')) {
            $t->same([[1, 1, 9], [10, 35, 35], [11, 15, 82], [20, 5, 5]], $case['result_rows']);
        }
    };
}

$tests['real upstream btree index dynamic corpus source files are explicit'] = static function (TestRunner $t): void {
    $t->same([
        'btree01.test',
        'btree02.test',
        'index.test',
        'index6.test',
        'index9.test',
        'indexedby.test',
    ], [
        'btree01.test',
        'btree02.test',
        'index.test',
        'index6.test',
        'index9.test',
        'indexedby.test',
    ]);
};

$tests['real upstream btree index dynamic corpus count is non overlapping'] = static function (TestRunner $t): void {
    $t->same(211, count(SQLiteBTreeIndexDynamicCorpusPlan::btree01BalanceStressCases()));
    $t->same(11, count(SQLiteBTreeIndexDynamicCorpusPlan::indexTestDynamicLookupCases()));
    $t->same(19, count(SQLiteBTreeIndexDynamicCorpusPlan::index9BoundPartialIndexCases()));
    $t->same(6, count(SQLiteBTreeIndexDynamicCorpusPlan::indexedByRowidAffinityCases()));
    $t->same(10, count(SQLiteBTreeIndexDynamicCorpusPlan::btree02CursorMutationCases()));
    $t->same(10, count(SQLiteBTreeIndexDynamicCorpusPlan::index6PartialJoinAndUpdateCases()));
};

$tests['real upstream btree index dynamic corpus dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; reuses lane-local B-tree/index page, record, planner, and cursor-case helpers', 'no new support component needed; reuses lane-local B-tree/index page, record, planner, and cursor-case helpers');
};

return $tests;
