<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [
    ['id' => 1, 'name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'scope' => 'public'],
    ['id' => 2, 'name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'scope' => 'public'],
    ['id' => 3, 'name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9, 'scope' => null],
    ['id' => 4, 'name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'scope' => 'private'],
    ['id' => 5, 'name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110, 'scope' => 'plugin'],
    ['id' => 6, 'name' => 'orphaned', 'autoload' => null, 'bytes' => null, 'scope' => null],
];

$predicateCases = [
    'in empty list is false for non null lhs' => [['operator' => 'IN', 'left' => ['column' => 'name'], 'values' => []], false],
    'not in empty list is true for non null lhs' => [['operator' => 'NOT IN', 'left' => ['column' => 'name'], 'values' => []], true],
    'in empty list is false for null lhs' => [['operator' => 'IN', 'left' => ['column' => 'scope'], 'values' => []], false, 2],
    'not in empty list is true for null lhs' => [['operator' => 'NOT IN', 'left' => ['column' => 'scope'], 'values' => []], true, 2],
    'in exact match ignores later null' => [['operator' => 'IN', 'left' => ['column' => 'name'], 'values' => ['siteurl', null]], true],
    'not in exact match ignores later null then false' => [['operator' => 'NOT IN', 'left' => ['column' => 'name'], 'values' => ['siteurl', null]], false],
    'in miss with null rhs is null' => [['operator' => 'IN', 'left' => ['column' => 'name'], 'values' => ['missing', null]], null],
    'not in miss with null rhs is null' => [['operator' => 'NOT IN', 'left' => ['column' => 'name'], 'values' => ['missing', null]], null],
    'in null lhs with non null rhs is null' => [['operator' => 'IN', 'left' => ['column' => 'scope'], 'values' => ['public']], null, 2],
    'not in null lhs with non null rhs is null' => [['operator' => 'NOT IN', 'left' => ['column' => 'scope'], 'values' => ['public']], null, 2],
    'in null lhs with null rhs is null' => [['operator' => 'IN', 'left' => ['column' => 'scope'], 'values' => [null]], null, 2],
    'not in null lhs with null rhs is null' => [['operator' => 'NOT IN', 'left' => ['column' => 'scope'], 'values' => [null]], null, 2],
    'in non null match with duplicate nulls is true' => [['operator' => 'IN', 'left' => ['column' => 'autoload'], 'values' => [null, 'yes', null]], true],
    'not in non null match with duplicate nulls is false' => [['operator' => 'NOT IN', 'left' => ['column' => 'autoload'], 'values' => [null, 'yes', null]], false],
    'in numeric miss without null is false' => [['operator' => 'IN', 'left' => ['column' => 'bytes'], 'values' => [1, 2, 3]], false],
    'not in numeric miss without null is true' => [['operator' => 'NOT IN', 'left' => ['column' => 'bytes'], 'values' => [1, 2, 3]], true],
    'in numeric miss with null is null' => [['operator' => 'IN', 'left' => ['column' => 'bytes'], 'values' => [1, 2, null]], null],
    'not in numeric miss with null is null' => [['operator' => 'NOT IN', 'left' => ['column' => 'bytes'], 'values' => [1, 2, null]], null],
    'in numeric match before null is true' => [['operator' => 'IN', 'left' => ['column' => 'bytes'], 'values' => [24, null]], true],
    'not in numeric match before null is false' => [['operator' => 'NOT IN', 'left' => ['column' => 'bytes'], 'values' => [24, null]], false],
];

foreach ($predicateCases as $name => $case) {
    $tests['sqlite in null truth table ' . $name] = static function (TestRunner $t) use ($rows, $case): void {
        [$predicate, $expected, $rowIndex] = $case + [2 => 0];
        $t->same($expected, SQLiteSelectPredicate::evaluate($rows[$rowIndex], $predicate));
    };
}

$filterCases = [
    'in empty list filters no rows' => [['operator' => 'IN', 'left' => ['column' => 'name'], 'values' => []], []],
    'not in empty list keeps null and non null lhs rows' => [['operator' => 'NOT IN', 'left' => ['column' => 'scope'], 'values' => []], [1, 2, 3, 4, 5, 6]],
    'in null-bearing miss filters no unknown rows' => [['operator' => 'IN', 'left' => ['column' => 'name'], 'values' => ['missing', null]], []],
    'not in null-bearing miss filters no unknown rows' => [['operator' => 'NOT IN', 'left' => ['column' => 'name'], 'values' => ['missing', null]], []],
    'not in non null rhs drops null lhs rows' => [['operator' => 'NOT IN', 'left' => ['column' => 'scope'], 'values' => ['public']], [4, 5]],
    'in non null rhs drops null lhs rows' => [['operator' => 'IN', 'left' => ['column' => 'scope'], 'values' => ['public']], [1, 2]],
    'not in rhs without null keeps misses' => [['operator' => 'NOT IN', 'left' => ['column' => 'autoload'], 'values' => ['yes']], [4, 5]],
    'not in rhs with null keeps no misses' => [['operator' => 'NOT IN', 'left' => ['column' => 'autoload'], 'values' => ['yes', null]], []],
    'in rhs with null keeps only matches' => [['operator' => 'IN', 'left' => ['column' => 'autoload'], 'values' => ['yes', null]], [1, 2, 3]],
    'not of in unknown remains filtered' => [['operator' => 'NOT', 'term' => ['operator' => 'IN', 'left' => ['column' => 'name'], 'values' => ['missing', null]]], []],
];

foreach ($filterCases as $name => [$predicate, $expectedIds]) {
    $tests['sqlite in null filtering ' . $name] = static function (TestRunner $t) use ($rows, $predicate, $expectedIds): void {
        $t->same($expectedIds, array_column(SQLiteSelectPredicate::filter($rows, $predicate), 'id'));
    };
}

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => null],
];

$metadata = [
    ['meta_option_id' => 1, 'meta_key' => 'public', 'meta_value' => '1', 'rank' => 10],
    ['meta_option_id' => 2, 'meta_key' => 'public', 'meta_value' => null, 'rank' => 20],
    ['meta_option_id' => 4, 'meta_key' => 'expired', 'meta_value' => null, 'rank' => 30],
    ['meta_option_id' => 5, 'meta_key' => 'plugin', 'meta_value' => 'cache', 'rank' => 40],
    ['meta_option_id' => null, 'meta_key' => 'dangling', 'meta_value' => null, 'rank' => 50],
];

$sqlCases = [
    'exists counts null-only subquery rows as true' => ["SELECT option_name FROM wp_options WHERE EXISTS (SELECT meta_value FROM option_meta WHERE meta_option_id = option_id AND meta_value IS NULL) ORDER BY option_id", ['home', '_transient_feed']],
    'not exists ignores null projections and checks row absence' => ["SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT meta_value FROM option_meta WHERE meta_option_id = option_id AND meta_value IS NULL) ORDER BY option_id", ['siteurl', 'blogname', '_site_transient_update_plugins', 'orphaned']],
    'not in subquery with null value yields no rows' => ["SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT meta_option_id FROM option_meta) ORDER BY option_id", []],
    'not in subquery without null value keeps missing ids' => ["SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT meta_option_id FROM option_meta WHERE meta_option_id IS NOT NULL) ORDER BY option_id", ['blogname', 'orphaned']],
    'in subquery with null value keeps matches only' => ["SELECT option_name FROM wp_options WHERE option_id IN (SELECT meta_option_id FROM option_meta) ORDER BY option_id", ['siteurl', 'home', '_transient_feed', '_site_transient_update_plugins']],
    'in subquery without matches and with null filters unknown' => ["SELECT option_name FROM wp_options WHERE bytes IN (SELECT meta_option_id FROM option_meta WHERE meta_key = 'dangling') ORDER BY option_id", []],
    'not in subquery without matches and with null filters unknown' => ["SELECT option_name FROM wp_options WHERE bytes NOT IN (SELECT meta_option_id FROM option_meta WHERE meta_key = 'dangling') ORDER BY option_id", []],
    'not in subquery empty result keeps null lhs too' => ["SELECT option_name FROM wp_options WHERE bytes NOT IN (SELECT meta_option_id FROM option_meta WHERE meta_key = 'missing') ORDER BY option_id", ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins', 'orphaned']],
    'in subquery empty result filters null lhs too' => ["SELECT option_name FROM wp_options WHERE bytes IN (SELECT meta_option_id FROM option_meta WHERE meta_key = 'missing') ORDER BY option_id", []],
    'correlated in null projection keeps matching row' => ["SELECT option_name FROM wp_options WHERE option_id IN (SELECT meta_option_id FROM option_meta WHERE meta_option_id = option_id AND meta_value IS NULL) ORDER BY option_id", ['home', '_transient_feed']],
    'correlated not in null projection rejects matching row' => ["SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT meta_option_id FROM option_meta WHERE meta_option_id = option_id AND meta_value IS NULL) ORDER BY option_id", ['siteurl', 'blogname', '_site_transient_update_plugins', 'orphaned']],
    'correlated not in empty subquery keeps current row' => ["SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT meta_option_id FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'missing') ORDER BY option_id", ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins', 'orphaned']],
    'exists with null comparison predicate is empty' => ["SELECT option_name FROM wp_options WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = option_id AND meta_value = NULL) ORDER BY option_id", []],
    'not exists with null comparison predicate keeps all rows' => ["SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = option_id AND meta_value = NULL) ORDER BY option_id", ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins', 'orphaned']],
    'exists with is null predicate returns rows' => ["SELECT option_name FROM wp_options WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = option_id AND meta_value IS NULL) ORDER BY option_id", ['home', '_transient_feed']],
    'not in subquery under and preserves unknown filtering' => ["SELECT option_name FROM wp_options WHERE autoload = 'no' AND option_id NOT IN (SELECT meta_option_id FROM option_meta) ORDER BY option_id", []],
    'not in subquery under or admits true disjunct' => ["SELECT option_name FROM wp_options WHERE option_name = 'orphaned' OR option_id NOT IN (SELECT meta_option_id FROM option_meta) ORDER BY option_id", ['orphaned']],
    'in subquery under or admits exact true disjunct' => ["SELECT option_name FROM wp_options WHERE option_name = 'blogname' OR option_id IN (SELECT meta_option_id FROM option_meta WHERE meta_option_id IS NULL) ORDER BY option_id", ['blogname']],
    'not exists under or admits true disjunct' => ["SELECT option_name FROM wp_options WHERE option_name = 'siteurl' OR NOT EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = option_id) ORDER BY option_id", ['siteurl', 'blogname', 'orphaned']],
    'exists under and filters after null-aware subquery' => ["SELECT option_name FROM wp_options WHERE autoload = 'yes' AND EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = option_id AND meta_value IS NULL) ORDER BY option_id", ['home']],
    'not in scalar subquery null projection yields no rows' => ["SELECT option_name FROM wp_options WHERE autoload NOT IN (SELECT meta_value FROM option_meta WHERE meta_value IS NULL) ORDER BY option_id", []],
    'in scalar subquery null projection yields no rows' => ["SELECT option_name FROM wp_options WHERE autoload IN (SELECT meta_value FROM option_meta WHERE meta_value IS NULL) ORDER BY option_id", []],
    'not in scalar subquery non null projection keeps misses but not null lhs' => ["SELECT option_name FROM wp_options WHERE autoload NOT IN (SELECT meta_value FROM option_meta WHERE meta_value IS NOT NULL) ORDER BY option_id", ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins']],
    'in scalar subquery non null projection keeps matches' => ["SELECT option_name FROM wp_options WHERE autoload IN (SELECT meta_value FROM option_meta WHERE meta_value IS NOT NULL) ORDER BY option_id", []],
];

foreach ($sqlCases as $name => [$sql, $expectedNames]) {
    $tests['select sql exists in not-in null semantics ' . $name] = static function (TestRunner $t) use ($sql, $expectedNames, $options, $metadata): void {
        $rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options, 'option_meta' => $metadata]);
        $t->same($expectedNames, array_column($rows, 'option_name'));
    };
}

$rowValueCases = [
    'row-value not in subquery with null component is unknown for misses' => ["SELECT option_name FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT meta_option_id, meta_value FROM option_meta) ORDER BY option_id", []],
    'row-value in subquery matches exact pair only' => ["SELECT option_name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta) ORDER BY option_id", []],
    'row-value not in subquery with null rows removed keeps nonmatching rows' => ["SELECT option_name FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_option_id IS NOT NULL AND meta_value IS NOT NULL) ORDER BY option_id", ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins', 'orphaned']],
    'row-value in subquery with null rows removed matches exact non null pair' => ["SELECT option_name FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_key FROM option_meta WHERE meta_option_id IS NOT NULL) ORDER BY option_id", []],
    'row-value in subquery with empty result filters all rows' => ["SELECT option_name FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_key FROM option_meta WHERE meta_key = 'missing') ORDER BY option_id", []],
    'row-value not in subquery with empty result keeps all rows' => ["SELECT option_name FROM wp_options WHERE (option_id, option_name) NOT IN (SELECT meta_option_id, meta_key FROM option_meta WHERE meta_key = 'missing') ORDER BY option_id", ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins', 'orphaned']],
];

foreach ($rowValueCases as $name => [$sql, $expectedNames]) {
    $tests['select sql row-value in null semantics ' . $name] = static function (TestRunner $t) use ($sql, $expectedNames, $options, $metadata): void {
        $rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options, 'option_meta' => $metadata]);
        $t->same($expectedNames, array_column($rows, 'option_name'));
    };
}

return $tests;
