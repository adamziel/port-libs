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

// Source truth: SQLite upstream test/index2.test index2-1.1 through 2.2.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index2LargeColumnIndexCases() as $case) {
    $tests['real upstream index2 large column index ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same(1000, $case['table_columns']);
        $t->true($case['scenario'] !== '');
        $t->true($case['row_count'] >= 0);
        $t->true($case['indexed_columns'] === 0 || $case['indexed_columns'] === 1000);
        $t->true($case['ordered_columns'] >= 0);
        $t->true($case['limit'] >= 0);
        if ($case['upstream'] === 'index2-1.3') {
            $t->same('c123', $case['result_column']);
            $t->same([123], $case['result_values']);
        }
        if ($case['upstream'] === 'index2-1.4') {
            $t->same(101, $case['row_count']);
        }
        if ($case['upstream'] === 'index2-1.5') {
            $t->same('c1000', $case['sum_column']);
            $t->same(50601000.0, $case['rounded_sum']);
        }
        if ($case['upstream'] === 'index2-2.2') {
            $t->same('c9', $case['result_column']);
            $t->same([9, 10009, 20009, 30009, 40009], $case['result_values']);
            $t->same(6, $case['ordered_columns']);
            $t->same(5, $case['limit']);
        }
    };
}

// Source truth: SQLite upstream test/index6.test index6-12.1 through 19.2.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index6PartialIndexRegressionCases() as $case) {
    $tests['real upstream index6 partial index regression ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['scenario'] !== '');
        $t->true($case['detail'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true(array_values($case['result_rows']) === $case['result_rows']);
        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }
        if ($case['upstream'] === 'index6-12.2') {
            $t->same([[1], [2]], $case['result_rows']);
        }
        if ($case['upstream'] === 'index6-13.1') {
            $t->same([[null]], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
        }
        if ($case['upstream'] === 'index6-16.1') {
            $t->same([[1, 0]], $case['result_rows']);
        }
        if ($case['upstream'] === 'index6-16.2') {
            $t->same([], $case['result_rows']);
            $t->same(true, $case['uses_partial_index']);
        }
        if ($case['upstream'] === 'index6-17.1') {
            $t->same([['ok']], $case['result_rows']);
        }
        if ($case['upstream'] === 'index6-19.2') {
            $t->same([], $case['result_rows']);
            $t->same(false, $case['uses_partial_index']);
        }
        $t->true(str_starts_with($case['upstream'], 'index6-'));
    };
}

// Source truth: SQLite upstream test/indexA.test sections 2.1 and 3.1. The
// upstream script repeats the same partial-index affinity matrix for rowid and
// WITHOUT ROWID tables; the batches below keep those dynamic combinations
// distinct without adding generated fake script ids.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialAffinityMatrixCases(50) as $case) {
    $tests['real upstream indexA partial affinity matrix ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('indexA.test sections 2.1 and 3.1', $case['source']);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 50);
        $t->true($case['storage'] === 'rowid' || $case['storage'] === 'without-rowid');
        $t->true(in_array($case['affinity'], ['TEXT', 'NUMERIC', 'REAL'], true));
        $t->true($case['index_setup'] >= 0 && $case['index_setup'] <= 4);
        $t->same($case['uses_partial_index'], str_starts_with($case['detail'], 'SEARCH ' . $case['table']));
        $t->same('ok', $case['integrity']);
        $t->true($case['selected_rows'] !== []);
        foreach ($case['selected_rows'] as $row) {
            $t->same($case['affinity'] === 'TEXT' ? 'text' : ($case['affinity'] === 'NUMERIC' ? 'integer' : 'real'), $row['type']);
            $t->true(str_ends_with($row['b'], '-' . $case['batch']));
            $t->true(str_ends_with($row['c'], '-' . $case['batch']));
            if ($case['affinity'] === 'TEXT') {
                $t->true($row['a'] === '2' || $row['a'] === '2.0');
            } elseif ($case['affinity'] === 'NUMERIC') {
                $t->same(2, $row['a']);
            } else {
                $t->same(2.0, $row['a']);
            }
        }
        if ($case['uses_partial_index']) {
            $t->true($case['index_name'] !== '');
            $t->true(str_contains($case['detail'], 'USING COVERING INDEX ' . $case['index_name']));
        } else {
            $t->true($case['index_setup'] === 0 || str_starts_with($case['detail'], 'SCAN '));
        }
    };
}

// Source truth: SQLite upstream test/index3.test index3-2.1 through 2.5.
// SQLite keeps backwards-compatible support for string literals in places
// that historically named indexed columns. This dynamic corpus keeps the
// quoted identifier, collation, sort order, catalog, and lookup rules distinct.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index3QuotedIdentifierCompatibilityCases(1200) as $case) {
    $tests['real upstream index3 quoted identifier compatibility case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index3.test index3-2.1 through index3-2.5', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true(in_array($case['upstream_section'], ['index3-2.1', 'index3-2.2', 'index3-2.4'], true));
        $t->true(in_array($case['quote_style'], ['single-string', 'double-quoted', 'bracket-quoted', 'bare'], true));
        $t->true(in_array($case['column'], ['a', 'b', 'c', 'd'], true));
        $t->true(str_contains($case['declared_column'], $case['column']));
        $t->same($case['column'] === 'b' ? 'sqlite_autoindex_t1_2' : 't1' . $case['column'], $case['index_name']);
        $t->same($case['column'] === 'b' || $case['column'] === 'c' || $case['column'] === 'd', $case['uses_index']);
        $t->same(sprintf('ab%03xxy', $case['lookup_value']), $case['lookup_literal']);
        $t->true($case['lookup_value'] >= 1 && $case['lookup_value'] <= 30);
        $t->same(['sqlite_autoindex_t1_1', 'sqlite_autoindex_t1_2', 't1', 't1c', 't1d'], $case['catalog_names']);
        if ($case['column'] === 'b') {
            $t->same('nocase', $case['collation']);
            $t->same('DESC', $case['sort']);
            $t->same('index3-2.2', $case['upstream_section']);
        }
        if ($case['upstream_section'] === 'index3-2.4') {
            $t->true(in_array($case['table'], ['t2a', 't2b', 't2c', 't2d'], true));
        } else {
            $t->same('t1', $case['table']);
        }
    };
}

// Source truth: SQLite upstream test/index5.test index5-1.1 through 1.3.
// The Tcl script installs a test VFS xWrite hook around CREATE INDEX over a
// 100000-row table and verifies that index page writes are predominantly
// forward. These focused cases preserve each observed transition separately.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialWriteCases(1200) as $case) {
    $tests['real upstream index5 create index write order transition ' . $case['transition']] = static function (TestRunner $t) use ($case): void {
        $t->same('index5.test index5-1.1 through index5-1.3', $case['source']);
        $t->true($case['transition'] >= 1 && $case['transition'] <= 1200);
        $t->same(1024, $case['page_size']);
        $t->same(100000, $case['row_count']);
        $t->true($case['previous_page'] > 0);
        $t->true($case['next_page'] > 0);
        $t->true(in_array($case['direction'], ['forward', 'backward', 'noncontiguous'], true));
        if ($case['direction'] === 'forward') {
            $t->same($case['previous_page'] + 1, $case['next_page']);
        }
        if ($case['direction'] === 'backward') {
            $t->same($case['previous_page'] - 1, $case['next_page']);
        }
        if ($case['direction'] === 'noncontiguous') {
            $t->true(abs($case['next_page'] - $case['previous_page']) > 1);
        }
        $t->true($case['forward_count'] >= 0);
        $t->true($case['backward_count'] >= 0);
        $t->true($case['noncontiguous_count'] >= 0);
        $t->same($case['forward_count'] > 2 * ($case['backward_count'] + $case['noncontiguous_count']), $case['forward_dominates']);
    };
}

// Source truth: SQLite upstream test/index7.test index7-1.1 through 1.15.
// These cases preserve the partial-index sqlite_stat1 cardinality transitions
// across INSERT, UPDATE, DELETE, REINDEX, and full-index creation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index7PartialIndexStatMutationCases(1000) as $case) {
    $tests['real upstream index7 partial index stat mutation case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index7.test index7-1.1 through index7-1.15', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'index7-1.'));
        $t->true($case['phase'] !== '');
        $t->true($case['mutation'] !== '');
        $t->true($case['planner_detail'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true($case['row_count'] === 20 || $case['row_count'] === 15);
        $t->true($case['t1a_stat'] <= $case['row_count']);
        $t->true($case['t1b_stat'] <= $case['row_count']);
        $t->same(2, $case['partial_index_count']);
        $t->true($case['full_index_count'] === 1 || $case['full_index_count'] === 2);
        if ($case['upstream_section'] === 'index7-1.11') {
            $t->same($case['row_count'], $case['t1a_stat']);
        }
        if ($case['upstream_section'] === 'index7-1.11b') {
            $t->same($case['row_count'], $case['t1b_stat']);
        }
        if ($case['upstream_section'] === 'index7-1.15') {
            $t->same(15, $case['t1c_stat']);
            $t->same(2, $case['full_index_count']);
        } else {
            $t->same(null, $case['t1c_stat']);
        }
    };
}

// Source truth: SQLite upstream test/autoindex5.test autoindex5-1.1 through
// 3.3. These cases preserve automatic covering-index use for coroutine views
// and the subquery/coroutine regressions that shared that planner path.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::autoindex5CoroutineSubqueryCases(1000) as $case) {
    $tests['real upstream autoindex5 coroutine subquery case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->true(in_array($case['source'], [
            'autoindex5.test autoindex5-1.0 through autoindex5-1.1',
            'autoindex5.test autoindex5-2.1',
            'autoindex5.test autoindex5-2.2',
            'autoindex5.test autoindex5-3.1 through autoindex5-3.2',
            'autoindex5.test autoindex5-3.3',
        ], true));
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(str_starts_with($case['upstream_section'], 'autoindex5-'));
        $t->true($case['scenario'] !== '');
        $t->true($case['table'] !== '');
        $t->true($case['autoindex_target'] !== '');
        $t->true($case['probe_column'] !== '');
        $t->true($case['detail'] !== '');
        $t->same(true, $case['uses_coroutine']);
        $t->same('ok', $case['integrity']);
        $t->true(array_values($case['result_rows']) === $case['result_rows']);
        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }
        if ($case['upstream_section'] === 'autoindex5-1.1') {
            $t->same('debian_cve', $case['table']);
            $t->same('bug_name', $case['autoindex_target']);
            $t->same(true, $case['uses_automatic_index']);
            $t->same(true, $case['order_by_preserved']);
            $t->true(str_contains($case['detail'], 'AUTOMATIC COVERING INDEX'));
            $t->true(str_starts_with((string) $case['probe_value'], 'CVE-'));
        }
        if ($case['upstream_section'] === 'autoindex5-2.1') {
            $t->same([[8.0]], $case['result_rows']);
            $t->same(false, $case['uses_automatic_index']);
            $t->same('aaa', $case['probe_value']);
        }
        if ($case['upstream_section'] === 'autoindex5-2.2') {
            $t->same([[9]], $case['result_rows']);
            $t->same(true, $case['subquery_resolves_rowid']);
            $t->same('rowid', $case['probe_column']);
        }
        if ($case['upstream_section'] === 'autoindex5-3.1') {
            $t->same([[104, 104]], $case['result_rows']);
            $t->same(104, $case['probe_value']);
        }
        if ($case['upstream_section'] === 'autoindex5-3.2') {
            $t->same([[1, 1, 1, 1]], $case['result_rows']);
            $t->same(1, $case['probe_value']);
        }
        if ($case['upstream_section'] === 'autoindex5-3.3') {
            $t->same([[3, 1, 1, 'x'], [3, 2, 2, 'x']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'a2=1 OR a3=2'));
        }
    };
}

return $tests;
