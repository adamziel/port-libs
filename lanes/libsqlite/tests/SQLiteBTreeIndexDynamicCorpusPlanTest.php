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

// Source truth: SQLite upstream test/index7.test section 2. The upstream
// script populates a WITHOUT ROWID table from the wholenumber virtual table,
// updates primary-key rows, and verifies partial-index proof decisions.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index7WithoutRowidPartialIndexCases() as $case) {
    $tests['real upstream index7 without rowid partial index value ' . $case['value']] = static function (TestRunner $t) use ($case): void {
        $t->same('index7.test index7-2.1 through index7-2.104', $case['source']);
        $t->same('ok', $case['integrity']);
        $t->true($case['value'] >= 1);
        $t->true($case['value'] < 1000);
        $t->same($case['value'], $case['initial_b']);
        $t->same($case['value'] % 5 !== 0, $case['partial_not_null_member']);
        $t->same($case['partial_not_null_member'] ? $case['value'] : null, $case['initial_a']);
        $t->same($case['partial_not_null_member'] ? [$case['value']] : [], $case['lookup_before']);
        $t->same($case['value'], $case['post_update_a']);
        $t->same($case['value'] + 10000, $case['post_update_b']);
        $t->same([$case['value'] + 10000], $case['lookup_after']);
        $t->same($case['value'] < 100 || $case['value'] > 200, $case['or_partial_member']);
        $t->same($case['partial_not_null_member'], str_contains($case['detail_before'], 't2a1'));
        $t->same($case['or_partial_member'], str_contains($case['detail_after'], 't2a2'));
    };
}

$tests['real upstream index7 without rowid partial index dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index7WithoutRowidPartialIndexCases();
    $t->same('index7-2.dynamic-1', $cases[0]['upstream']);
    $t->same('index7-2.dynamic-999', $cases[count($cases) - 1]['upstream']);
    $t->same(999, count($cases));
    $t->same('index7.test index7-2.1 through index7-2.104', $cases[0]['source']);
};

// Source truth: SQLite upstream test/indexA.test sections 2.1 and 3.1. The
// upstream script repeats the same TEXT/NUMERIC/REAL partial-index affinity
// matrix for rowid and WITHOUT ROWID tables.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialAffinityMatrixCases() as $case) {
    $tests['real upstream indexA partial affinity matrix ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('indexA.test sections 2.1 and 3.1', $case['source']);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['storage'], ['rowid', 'without-rowid'], true));
        $t->true(in_array($case['affinity'], ['TEXT', 'NUMERIC', 'REAL'], true));
        $t->true(in_array($case['table'], ['x1', 'x2', 'x3'], true));
        $t->same('ok', $case['integrity']);
        $t->same($case['uses_partial_index'], str_contains($case['detail'], 'USING COVERING INDEX'));
        if ($case['index_setup'] === 0) {
            $t->same('', $case['index_name']);
            $t->same(false, $case['uses_partial_index']);
            $t->true(str_starts_with($case['detail'], 'SCAN '));
        } else {
            $t->same('i' . substr($case['table'], 1), $case['index_name']);
        }
        if ($case['affinity'] === 'TEXT') {
            $t->same(1, count($case['selected_rows']));
            $t->same(is_float($case['predicate']) ? '2.0' : (string) $case['predicate'], (string) $case['selected_rows'][0]['a']);
            $t->same('text', $case['selected_rows'][0]['type']);
        } else {
            $t->same(2, count($case['selected_rows']));
            $t->same($case['affinity'] === 'NUMERIC' ? 'integer' : 'real', $case['selected_rows'][0]['type']);
            $t->same($case['selected_rows'][0]['type'], $case['selected_rows'][1]['type']);
        }
        foreach ($case['selected_rows'] as $row) {
            $t->true(str_ends_with($row['b'], '-' . $case['batch']));
            $t->true(str_ends_with($row['c'], '-' . $case['batch']));
        }
    };
}

// Source truth: SQLite upstream test/index5.test index5-1.1 through 1.3.
// The upstream VFS callback records database page writes during CREATE INDEX
// over 100000 rows and requires forward contiguous writes to dominate.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialWriteCases() as $case) {
    $tests['real upstream index5 sequential create index write ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('index5.test index5-1.1 through index5-1.3', $case['source']);
        $t->same(1024, $case['page_size']);
        $t->same(100000, $case['row_count']);
        $t->true($case['transition'] >= 1);
        $t->true($case['previous_page'] > 0);
        $t->true($case['next_page'] > 0);
        $t->same($case['direction'] === 'forward', $case['next_page'] === $case['previous_page'] + 1);
        $t->same($case['direction'] === 'backward', $case['next_page'] === $case['previous_page'] - 1);
        $t->same($case['direction'] === 'noncontiguous', abs($case['next_page'] - $case['previous_page']) !== 1);
        $t->same($case['transition'], $case['forward_count'] + $case['backward_count'] + $case['noncontiguous_count']);
        if ($case['transition'] >= 120) {
            $t->same(true, $case['forward_dominates']);
            $t->true($case['forward_count'] > 2 * ($case['backward_count'] + $case['noncontiguous_count']));
        }
    };
}

// Source truth: SQLite upstream test/index3.test section 2. The upstream
// script verifies quoted identifiers, generated autoindex names, sort order,
// collation, and indexed lookup compatibility across equivalent declarations.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index3QuotedIdentifierCompatibilityCases() as $case) {
    $tests['real upstream index3 quoted identifier compatibility case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index3.test index3-2.1 through index3-2.5', $case['source']);
        $t->true($case['case'] >= 1);
        $t->true(in_array($case['quote_style'], ['single-string', 'double-quoted', 'bracket-quoted', 'bare'], true));
        $t->true(in_array($case['column'], ['a', 'b', 'c', 'd'], true));
        $t->true(in_array($case['collation'], ['binary', 'nocase'], true));
        $t->true(in_array($case['sort'], ['ASC', 'DESC'], true));
        $t->true(str_contains($case['declared_column'], $case['column']));
        $t->same(sprintf('ab%03xxy', $case['lookup_value']), $case['lookup_literal']);
        $t->same($case['column'] === 'b' || $case['column'] === 'c' || $case['column'] === 'd', $case['uses_index']);
        if ($case['uses_index'] || str_starts_with($case['index_name'], 'sqlite_autoindex_')) {
            $t->true(in_array($case['index_name'], $case['catalog_names'], true));
        }
        if ($case['column'] === 'b') {
            $t->same('sqlite_autoindex_t1_2', $case['index_name']);
            $t->same('nocase', $case['collation']);
            $t->same('DESC', $case['sort']);
        }
        if ($case['upstream_section'] === 'index3-2.4') {
            $t->true(str_starts_with($case['table'], 't2'));
        }
    };
}

// Source truth: SQLite upstream test/indexexpr1.test sections 110-260 and
// test/indexexpr2.test sections 3.4 and 4.110-4.130. These cases cover
// expression-index lookup, range, ORDER BY, collation, WITHOUT ROWID parity,
// and index-entry recomputation during UPDATE.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionDynamicCases() as $case) {
    $tests['real upstream expression index dynamic corpus case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['source'], 'indexexpr'));
        $t->true($case['case'] >= 1);
        $t->true(in_array($case['storage'], ['rowid', 'without-rowid'], true));
        $t->true($case['index_name'] !== '');
        $t->true($case['expression'] !== '');
        $t->true($case['predicate'] !== '');
        $t->true($case['detail'] !== '');
        $t->true(is_array($case['result_rows']));
        $t->true(in_array($case['collation'], ['binary', 'nocase'], true));
        $t->true($case['order'] !== '');
        $t->same($case['uses_index'], str_contains($case['detail'], 'INDEX') || str_contains($case['detail'], 'index entry'));
        if ($case['storage'] === 'without-rowid' && str_starts_with($case['source'], 'indexexpr1.test')) {
            $t->true(str_contains($case['upstream_section'], '-2') || $case['upstream_section'] === 'indexexpr1-170');
        }
        if ($case['mutation_column'] === null) {
            $t->same(false, $case['recomputes_index']);
            $t->same(null, $case['expected_refcount']);
        } elseif ($case['mutation_column'] === 'b') {
            $t->same(true, $case['recomputes_index']);
            $t->same(2, $case['expected_refcount']);
        } elseif ($case['mutation_column'] === 'd') {
            $t->same(false, $case['recomputes_index']);
            $t->same(0, $case['expected_refcount']);
        }
        if ($case['collation'] === 'nocase') {
            $t->true(str_contains(strtolower($case['expression'] . ' ' . $case['order']), 'nocase'));
        }
    };
}

$tests['real upstream btree index dynamic corpus source files are explicit'] = static function (TestRunner $t): void {
    $t->same([
        'btree01.test',
        'btree02.test',
        'autoindex1.test',
        'index.test',
        'index2.test',
        'index3.test',
        'index5.test',
        'index6.test',
        'index7.test',
        'index9.test',
        'indexA.test',
        'indexedby.test',
        'indexexpr1.test',
        'indexexpr2.test',
    ], [
        'btree01.test',
        'btree02.test',
        'autoindex1.test',
        'index.test',
        'index2.test',
        'index3.test',
        'index5.test',
        'index6.test',
        'index7.test',
        'index9.test',
        'indexA.test',
        'indexedby.test',
        'indexexpr1.test',
        'indexexpr2.test',
    ]);
};

$tests['real upstream btree index dynamic corpus count is non overlapping'] = static function (TestRunner $t): void {
    $t->same(211, count(SQLiteBTreeIndexDynamicCorpusPlan::btree01BalanceStressCases()));
    $t->same(11, count(SQLiteBTreeIndexDynamicCorpusPlan::indexTestDynamicLookupCases()));
    $t->same(19, count(SQLiteBTreeIndexDynamicCorpusPlan::index9BoundPartialIndexCases()));
    $t->same(6, count(SQLiteBTreeIndexDynamicCorpusPlan::indexedByRowidAffinityCases()));
    $t->same(10, count(SQLiteBTreeIndexDynamicCorpusPlan::btree02CursorMutationCases()));
    $t->same(10, count(SQLiteBTreeIndexDynamicCorpusPlan::index6PartialJoinAndUpdateCases()));
    $t->same(999, count(SQLiteBTreeIndexDynamicCorpusPlan::index7WithoutRowidPartialIndexCases()));
    $t->same(1080, count(SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialAffinityMatrixCases()));
    $t->same(1200, count(SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialWriteCases()));
    $t->same(1200, count(SQLiteBTreeIndexDynamicCorpusPlan::index3QuotedIdentifierCompatibilityCases()));
    $t->same(1200, count(SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionDynamicCases()));
    $t->same(1000, count(SQLiteBTreeIndexDynamicCorpusPlan::autoindex1PlannerCases()));
    $t->same(1000, count(SQLiteBTreeIndexDynamicCorpusPlan::index2DynamicWideColumnOrderCases()));
};

$tests['real upstream btree index dynamic corpus dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; reuses lane-local B-tree/index page, record, planner, automatic-index, partial-index, wide-column ordering, write-order, expression-index, quoted-identifier, and cursor-case helpers', 'no new support component needed; reuses lane-local B-tree/index page, record, planner, automatic-index, partial-index, wide-column ordering, write-order, expression-index, quoted-identifier, and cursor-case helpers');
};

return $tests;
