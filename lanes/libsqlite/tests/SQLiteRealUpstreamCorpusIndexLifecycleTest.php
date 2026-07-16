<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexLifecyclePlan;

$tests = [];

$basic = static fn (): array => SQLiteIndexLifecyclePlan::basicCatalogLifecycle();
$tests['real upstream corpus index.test index-1.1 records created table and index names'] = static fn (TestRunner $t) => $t->same(['index1', 'test1'], $basic()['created_names']);
$tests['real upstream corpus index.test index-1.1b records index catalog name'] = static fn (TestRunner $t) => $t->same('index1', $basic()['index_record']['name']);
$tests['real upstream corpus index.test index-1.1b records index SQL'] = static fn (TestRunner $t) => $t->same('CREATE INDEX index1 ON test1(f1)', $basic()['index_record']['sql']);
$tests['real upstream corpus index.test index-1.1b records index table'] = static fn (TestRunner $t) => $t->same('test1', $basic()['index_record']['tbl_name']);
$tests['real upstream corpus index.test index-1.1b records index type'] = static fn (TestRunner $t) => $t->same('index', $basic()['index_record']['type']);
$tests['real upstream corpus index.test index-1.2 drops index with owning table'] = static fn (TestRunner $t) => $t->same([], $basic()['after_drop_names']);

$errors = static fn (): array => SQLiteIndexLifecyclePlan::creationErrors();
$tests['real upstream corpus index.test index-2.1 rejects index on missing table'] = static fn (TestRunner $t) => $t->same([1, 'no such table: main.test1'], $errors()['missing_table']);
$tests['real upstream corpus index.test index-2.1b rejects missing indexed column'] = static fn (TestRunner $t) => $t->same([1, 'no such column: f4'], $errors()['missing_column']);
$tests['real upstream corpus index.test index-2.2 rejects mixed missing indexed column'] = static fn (TestRunner $t) => $t->same([1, 'no such column: f4'], $errors()['mixed_missing_column']);
$tests['real upstream corpus index.test index-18.2 rejects reserved sqlite index name'] = static fn (TestRunner $t) => $t->same([1, 'object name reserved for internal use: sqlite_i1'], $errors()['reserved_index_name']);

$many = static fn (): array => SQLiteIndexLifecyclePlan::manyIndexes();
$tests['real upstream corpus index.test index-3.1 creates ninety-nine indexes'] = static fn (TestRunner $t) => $t->same(99, $many()['count']);
$tests['real upstream corpus index.test index-3.1 first generated index name'] = static fn (TestRunner $t) => $t->same('index01', $many()['first']);
$tests['real upstream corpus index.test index-3.1 last generated index name'] = static fn (TestRunner $t) => $t->same('index99', $many()['last']);
$tests['real upstream corpus index.test index-3.3 drops all generated indexes with table'] = static fn (TestRunner $t) => $t->same([], $many()['after_drop_names']);
foreach (range(1, 99) as $ordinal) {
    $tests["real upstream corpus index.test index-3.1 generated index {$ordinal} exists"] = static function (TestRunner $t) use ($many, $ordinal): void {
        $t->same(sprintf('index%02d', $ordinal), $many()['index_names'][$ordinal - 1]);
    };
}

$duplicates = static fn (): array => SQLiteIndexLifecyclePlan::duplicateNameRules();
$tests['real upstream corpus index.test index-6.1 rejects duplicate index name'] = static fn (TestRunner $t) => $t->same([1, 'index index1 already exists'], $duplicates()['duplicate_index']);
$tests['real upstream corpus index.test index-6.1c if not exists duplicate succeeds'] = static fn (TestRunner $t) => $t->same([0, []], $duplicates()['if_not_exists_duplicate']);
$tests['real upstream corpus index.test index-6.2 rejects table name collision'] = static fn (TestRunner $t) => $t->same([1, 'there is already a table named test1'], $duplicates()['table_name_collision']);
$tests['real upstream corpus index.test index-6.2b keeps catalog after duplicate errors'] = static fn (TestRunner $t) => $t->same(['index1', 'test1', 'test2'], $duplicates()['names_after_errors']);

$dups = static fn (): array => SQLiteIndexLifecyclePlan::duplicateKeyLookup();
$duplicateCases = [
    'index-10.0 duplicate key returns both b values' => ['a1_initial_b', [2, 12]],
    'index-10.1 unique key returns one b value' => ['a2_initial_b', [4]],
    'index-10.2 delete one duplicate preserves other duplicate' => ['a1_after_delete_12', [2]],
    'index-10.3 delete last duplicate empties lookup' => ['a1_after_delete_2', []],
    'index-10.4 dense duplicate key orders nine values' => ['a1_dense', [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'index-10.5 in-list delete leaves odd values' => ['a1_after_in_delete', [1, 3, 5, 7, 9]],
    'index-10.6 range delete leaves first value' => ['a1_after_greater_delete', [1]],
    'index-10.7 final delete empties duplicate key' => ['a1_after_final_delete', []],
    'index-10.8 unrelated key remains after duplicate deletes' => ['all_after_final_delete', [0]],
];
foreach ($duplicateCases as $label => [$key, $expected]) {
    $tests["real upstream corpus index.test {$label}"] = static fn (TestRunner $t) => $t->same($expected, $dups()[$key]);
}

$numeric = static fn (): array => SQLiteIndexLifecyclePlan::numericAffinityIndexRows();
$numericCases = [
    'index-12.1 stores numeric affinity values in b order' => ['stored_a_order_by_b', [0, 0, 'abc', -1, 1, 0, 0]],
    'index-12.2 compares equality before explicit index' => ['where_eq_zero', [0, 0, 0, 0]],
    'index-12.3 compares less-than before explicit index' => ['where_lt_half', [0, 0, -1, 0, 0]],
    'index-12.4 compares greater-than before explicit index' => ['where_gt_negative_half', [0, 0, 'abc', 1, 0, 0]],
    'index-12.5 compares equality through index' => ['indexed_where_eq_zero', [0, 0, 0, 0]],
    'index-12.6 compares less-than through index' => ['indexed_where_lt_half', [0, 0, -1, 0, 0]],
    'index-12.7 compares greater-than through index' => ['indexed_where_gt_negative_half', [0, 0, 'abc', 1, 0, 0]],
];
foreach ($numericCases as $label => [$key, $expected]) {
    $tests["real upstream corpus index.test {$label}"] = static fn (TestRunner $t) => $t->same($expected, $numeric()[$key]);
}

$scientific = static fn (): array => SQLiteIndexLifecyclePlan::scientificNumericOrdering();
$tests['real upstream corpus index.test index-15.2 orders scientific numeric strings before text'] = static fn (TestRunner $t) => $t->same([13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7], $scientific()['ordered_b']);
$tests['real upstream corpus index.test index-15.3 detects numeric storage rows'] = static fn (TestRunner $t) => $t->same([13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11], $scientific()['numeric_storage_b']);

$composite = static fn (): array => SQLiteIndexLifecyclePlan::compositeIndexOrdering();
$compositeCases = [
    'index-14.1 orders by composite index keys' => ['order_by_a_b_c', [3, 5, 2, 1, 4]],
    'index-14.2 searches first indexed column equality' => ['where_a_empty', [1, 2]],
    'index-14.3 searches second indexed column equality' => ['where_b_empty', [1, 3]],
    'index-14.4 searches first indexed column greater-than empty string' => ['where_a_gt_empty', [4]],
    'index-14.5 searches first indexed column greater-equal empty string' => ['where_a_ge_empty', [1, 2, 4]],
    'index-14.6 searches first indexed column greater-than numeric' => ['where_a_gt_123', [1, 2, 4]],
    'index-14.7 searches first indexed column greater-equal numeric' => ['where_a_ge_123', [1, 2, 4, 5]],
    'index-14.8 searches first indexed column less-than abc' => ['where_a_lt_abc', [1, 2, 3, 5]],
    'index-14.9 searches first indexed column less-equal abc' => ['where_a_le_abc', [1, 2, 3, 4, 5]],
    'index-14.10 searches first indexed column less-equal empty string' => ['where_a_le_empty', [1, 2, 3, 5]],
    'index-14.11 searches first indexed column less-than empty string' => ['where_a_lt_empty', [3, 5]],
];
foreach ($compositeCases as $label => [$key, $expected]) {
    $tests["real upstream corpus index.test {$label}"] = static fn (TestRunner $t) => $t->same($expected, $composite()[$key]);
}

$constraints = static fn (): array => SQLiteIndexLifecyclePlan::constraintIndexSummary();
$constraintCases = [
    'index-16.1 unique primary key shares single auto index' => ['unique_primary_key_single_column_index_count', 1],
    'index-16.3 primary key then unique same column shares single auto index' => ['primary_key_then_unique_same_column_index_count', 1],
    'index-16.4 unique composite and primary key same columns share single auto index' => ['unique_composite_then_primary_key_same_columns_index_count', 1],
    'index-16.5 unique single plus composite primary key creates two indexes' => ['unique_single_plus_composite_primary_key_index_count', 2],
    'index-17.1 names automatic indexes in ordinal order' => ['autoindex_names', ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3']],
    'index-17.2 rejects dropping automatic index' => ['drop_autoindex', [1, 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped']],
    'index-17.3 rejects dropping automatic index with if exists' => ['drop_autoindex_if_exists', [1, 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped']],
    'index-17.4 allows drop missing index if exists' => ['drop_missing_if_exists', [0, []]],
    'index-19.6 rejects conflicting constraint conflict clauses' => ['conflicting_on_conflict_clauses', [1, 'conflicting ON CONFLICT clauses specified']],
];
foreach ($constraintCases as $label => [$key, $expected]) {
    $tests["real upstream corpus index.test {$label}"] = static fn (TestRunner $t) => $t->same($expected, $constraints()[$key]);
}

$wide = static fn (): array => SQLiteIndexLifecyclePlan::wideIndexOrdering();
$wideCases = [
    'index2-1.1 creates one thousand columns' => ['column_count', 1000],
    'index2-1.3 reads c123 from first row' => ['c123_first_row', 123],
    'index2-1.4 inserts one hundred additional wide rows' => ['row_count', 101],
    'index2-1.5 sums c1000 over wide rows' => ['sum_c1000', 50601000],
    'index2-2.1 creates one thousand column index' => ['index_column_count', 1000],
    'index2-2.2 orders by wide index prefix' => ['order_by_c1_to_c6_limit_5_c9', [9, 10009, 20009, 30009, 40009]],
    'index2-2.2 cites upstream source' => ['upstream', ['index2.test index2-1.1..2.2']],
];
foreach ($wideCases as $label => [$key, $expected]) {
    $tests["real upstream corpus index2.test {$label}"] = static fn (TestRunner $t) => $t->same($expected, $wide()[$key]);
}

foreach (range(0, 100) as $rowOrdinal) {
    $expected = $rowOrdinal === 0 ? 9 : ($rowOrdinal * 10000) + 9;
    $tests["real upstream corpus index2.test index2-2.2 ordered wide index row {$rowOrdinal} c9"] = static function (TestRunner $t) use ($wide, $rowOrdinal, $expected): void {
        $t->same($expected, $wide()['order_by_c1_to_c6_c9'][$rowOrdinal]);
    };
}

return $tests;
