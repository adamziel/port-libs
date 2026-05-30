<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

foreach (SQLiteBTreeIndexDynamicCorpusPlan::btree01BalanceStressCases() as $case) {
    $tests['real upstream btree01 balance stress ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same(65536, $case['page_size']);
        $t->true($case['target_row'] >= 1);
        $t->true($case['target_row'] <= $case['row_count']);
        $t->true($case['expanded_blob'] > $case['shrink_blob']);
        $t->true($case['initial_blob'] >= $case['shrink_blob']);
        $t->true($case['local_payload_length'] > 0);
        $t->true($case['local_payload_length'] <= $case['expanded_blob'] + 8);
        $t->true($case['overflow_payload_length'] >= 0);
        $t->same($case['overflow_payload_length'] === 0 ? 0 : 1, min(1, $case['overflow_page_count']));
        $t->same('ok', $case['integrity']);
    };
}

foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexTestDynamicLookupCases() as $case) {
    $tests['real upstream index dynamic lookup ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['active_indexes'] === array_values($case['active_indexes']));
        $t->true($case['lookup_column'] === 'cnt' || $case['lookup_column'] === 'power');
        $t->true($case['result_column'] === 'cnt' || $case['result_column'] === 'power');
        $t->true($case['lookup_value'] > 0);
        $t->true($case['result_value'] > 0);
        $t->same('ok', $case['integrity']);
    };
}

// Source truth: SQLite upstream test/index9.test index9-1.1 through 4.5.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index9BoundPartialIndexCases() as $case) {
    $tests['real upstream index9 bound partial index ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['index_name'], 't1x'));
        $t->true(in_array('t1', $case['objects'], true));
        $t->same($case['uses_partial_index'], in_array($case['index_name'], $case['objects'], true));
        $t->same($case['uses_partial_index'] ? 2 : 1, count($case['objects']));
        $t->true(is_int($case['predicate_value']));
        $t->true($case['where_value'] === null || is_int($case['where_value']) || is_float($case['where_value']) || is_string($case['where_value']));
        $t->same($case['qpsg'], str_ends_with($case['upstream'], '.4') || str_ends_with($case['upstream'], '.5') ? $case['qpsg'] : false);
        $t->true($case['uses_partial_index'] || $case['objects'] === ['t1']);
    };
}

// Source truth: SQLite upstream test/indexedby.test indexedby-11.2 through
// 11.10. These cases pin rowid/INTEGER PRIMARY KEY affinity when an indexed
// lookup is forced through a covering index.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexedByRowidAffinityCases() as $case) {
    $tests['real upstream indexedby rowid affinity ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['table'] === 'x1' || $case['table'] === 'x2');
        $t->true($case['rowid_column'] === 'rowid' || $case['rowid_column'] === 'c');
        $t->same([1, 1, 3], $case['result']);
        $t->same(3, (int) $case['rowid_literal']);
        $t->true(str_contains($case['detail'], 'USING COVERING INDEX'));
        $t->true(str_contains($case['detail'], $case['table'] . 'i'));
        $t->true(str_contains($case['detail'], 'rowid=?'));
        $t->same($case['table'] === 'x1' ? 'rowid' : 'c', $case['rowid_column']);
    };
}

// Source truth: SQLite upstream test/btree02.test btree02-110. The upstream
// script mutates a WITHOUT ROWID table while a cursor scan is in progress.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::btree02CursorMutationCases() as $case) {
    $tests['real upstream btree02 cursor mutation ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['step'] >= 1);
        $t->true($case['step'] <= 10);
        $t->true($case['operation'] === 'insert-during-scan' || $case['operation'] === 'delete-during-scan');
        $t->same($case['step'] % 2 === 1 ? 'insert-during-scan' : 'delete-during-scan', $case['operation']);
        $t->same($case['step'] * 3, $case['t2_count']);
        $t->same($case['step'] % 2 === 1 ? 11 : 10, $case['t1_count']);
        $t->true($case['source_b'] >= 1 && $case['source_b'] <= 10);
        if ($case['operation'] === 'insert-during-scan') {
            $t->same('(' . $case['source_a'] . ')', $case['inserted_a']);
            $t->same($case['source_b'] + 1000, $case['inserted_b']);
            $t->same(null, $case['deleted_a']);
        } else {
            $t->same(null, $case['inserted_a']);
            $t->same(null, $case['inserted_b']);
            $t->same($case['source_a'], $case['deleted_a']);
        }
    };
}

// Source truth: SQLite upstream test/index6.test index6-7.0 through 11.2.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index6PartialJoinAndUpdateCases() as $case) {
    $tests['real upstream index6 partial join update ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['scenario'] !== '');
        $t->true($case['detail'] !== '');
        $t->same($case['uses_partial_index'], str_contains($case['detail'], 'USING INDEX') || str_contains($case['detail'], 'USING COVERING INDEX') || str_contains($case['detail'], 'UPDATE t9'));
        $t->true(array_values($case['result_rows']) === $case['result_rows']);
        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }
        if ($case['upstream'] === 'index6-8.2') {
            $t->same([2, 'two', null, null], $case['result_rows'][1]);
        }
        if ($case['upstream'] === 'index6-10.3') {
            $t->same(false, $case['uses_partial_index']);
            $t->same([[9], [5]], $case['result_rows']);
        }
        $t->true(str_starts_with($case['upstream'], 'index6-'));
    };
}

// Source truth: SQLite upstream test/index4.test index4-1.1 through 2.2.
// These cases cover large CREATE INDEX builds, cache-limited builds, empty and
// single-row tables, and duplicate-key rollback during UNIQUE index creation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index4LargeBuildCases() as $case) {
    $tests['real upstream index4 large index build ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['upstream'], 'index4-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['row_count'] >= 0);
        $t->true($case['blob_bytes'] >= 0);
        $t->true(str_starts_with($case['index_name'], 'i'));
        $t->same('ok', $case['expected_integrity']);
        if ($case['unique_error'] === null) {
            if ($case['row_count'] > 512) {
                $t->true($case['requires_external_sort'] || $case['cache_size'] === 10);
            }
            if ($case['row_count'] <= 1) {
                $t->same(false, $case['requires_external_sort']);
            }
        }
        if ($case['cache_size'] !== null) {
            $t->same(10, $case['cache_size']);
        }
        if ($case['unique_error'] !== null) {
            $t->same('UNIQUE constraint failed: t2.x', $case['unique_error']);
            $t->same(35, $case['duplicate_value']);
            $t->same(5, $case['row_count']);
        } else {
            $t->same(null, $case['duplicate_value']);
        }
    };
}

// Source truth: SQLite upstream test/index8.test index8-1.0 and 1.1.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index8OrderByLimitPlannerCases() as $case) {
    $tests['real upstream index8 order by limit planner ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('c', $case['where_column']);
        $t->same(4, $case['where_value']);
        $t->same(['a', 'b'], $case['order_by']);
        $t->same(2, $case['limit']);
        $t->same([[0, 4, 4, 4], [2, 3, 4, 23]], $case['result_rows']);
        $t->same($case['uses_index'], str_contains($case['detail'], 'USING INDEX'));
        $t->same($case['uses_index'], $case['index_name'] === 't1abc');
        $t->true(str_starts_with($case['upstream'], 'index8-1.'));
    };
}

// Source truth: SQLite upstream test/btree01.test btree01-2.1 and 2.2.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::btree01OverflowJoinCases() as $case) {
    $tests['real upstream btree01 overflow join ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same(1024, $case['page_size']);
        $t->same('WITHOUT ROWID PRIMARY KEY(a)', $case['primary_key']);
        $t->same(198, $case['overflow_key']);
        $t->same(1000, $case['overflow_blob']);
        $t->same([198, 187, 100], $case['probe_values']);
        $t->same([
            ['y' => 198, 'c' => 99],
            ['y' => 187, 'c' => null],
            ['y' => 100, 'c' => 50],
        ], $case['result_rows']);
        $t->true($case['join'] === 'LEFT JOIN' || $case['join'] === 'RIGHT JOIN');
        $t->same($case['join'] === 'LEFT JOIN' ? 'btree01-2.1' : 'btree01-2.2', $case['upstream']);
    };
}

return $tests;
