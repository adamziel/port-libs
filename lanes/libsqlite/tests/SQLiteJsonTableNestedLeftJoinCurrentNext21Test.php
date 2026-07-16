<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_nested_alpha',
        'option_value' => '{"groups":[{"name":"core","rules":["seo","cache"]},{"name":"empty","rules":[]}]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_nested_beta',
        'option_value' => '{"groups":[{"name":"forms","rules":["forms"]},{"name":"missing"}]}',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_nested_empty',
        'option_value' => '{"groups":[]}',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_nested_null',
        'option_value' => null,
    ],
    [
        'option_id' => 5,
        'option_name' => 'plugin_nested_jsonb',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'groups' => [
                ['name' => 'media', 'rules' => ['cdn', 'images']],
            ],
        ])),
    ],
];

$nestedRows = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_id AS option_id, o.option_name AS option_name, g.key AS group_index, json_extract(g.value, '$.name') AS group_name, r.rowid AS rule_rowid, r._rowid_ AS rule__rowid_, r.oid AS rule_oid, r.key AS rule_index, r.atom AS rule_name, r.fullkey AS rule_fullkey
       FROM wp_options AS o
       LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object'
       LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL
      ORDER BY option_id, group_index, rule_name",
    ['wp_options' => $options],
);

return [
    'nested left join row count preserves empty groups and empty options' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(9, count($nestedRows()));
    },
    'nested left join preserves host option order' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same([1, 1, 1, 2, 2, 3, 4, 5, 5], array_column($nestedRows(), 'option_id'));
    },
    'nested left join expands first group rules' => static function (TestRunner $t) use ($nestedRows): void {
        $rows = $nestedRows();
        $t->same(['cache', 'seo'], [$rows[0]['rule_name'], $rows[1]['rule_name']]);
    },
    'nested left join preserves first group index' => static function (TestRunner $t) use ($nestedRows): void {
        $rows = $nestedRows();
        $t->same([0, 0], [$rows[0]['group_index'], $rows[1]['group_index']]);
    },
    'nested left join reports first group name for each matched rule' => static function (TestRunner $t) use ($nestedRows): void {
        $rows = $nestedRows();
        $t->same(['core', 'core'], [$rows[0]['group_name'], $rows[1]['group_name']]);
    },
    'nested left join maps rowid alias for first matched rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(2, $nestedRows()[0]['rule_rowid']);
    },
    'nested left join maps underscore rowid alias for first matched rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(2, $nestedRows()[0]['rule__rowid_']);
    },
    'nested left join maps oid alias for first matched rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(2, $nestedRows()[0]['rule_oid']);
    },
    'nested left join increments rowid alias for second matched rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(1, $nestedRows()[1]['rule_rowid']);
    },
    'nested left join preserves rule key for second matched rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(0, $nestedRows()[1]['rule_index']);
    },
    'nested left join preserves nested fullkey for matched rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same('$.rules[0]', $nestedRows()[1]['rule_fullkey']);
    },
    'nested left join null-extends empty inner array rule value' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[2]['rule_name']);
    },
    'nested left join null-extends empty inner array rowid' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[2]['rule_rowid']);
    },
    'nested left join null-extends empty inner array underscore rowid' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[2]['rule__rowid_']);
    },
    'nested left join null-extends empty inner array oid' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[2]['rule_oid']);
    },
    'nested left join keeps empty inner group name' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same('empty', $nestedRows()[2]['group_name']);
    },
    'nested left join expands beta matched rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same('forms', $nestedRows()[3]['rule_name']);
    },
    'nested left join null-extends missing inner path value' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[4]['rule_name']);
    },
    'nested left join null-extends missing inner path rowid alias' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[4]['rule_rowid']);
    },
    'nested left join preserves missing inner path group name' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same('missing', $nestedRows()[4]['group_name']);
    },
    'nested left join null-extends empty outer array group' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[5]['group_index']);
    },
    'nested left join null-extends empty outer array nested rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[5]['rule_rowid']);
    },
    'nested left join null-extends SQL NULL JSON source group' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[6]['group_index']);
    },
    'nested left join null-extends SQL NULL JSON source nested rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same(null, $nestedRows()[6]['rule_oid']);
    },
    'nested left join expands JSONB dynamic outer source' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same('media', $nestedRows()[7]['group_name']);
    },
    'nested left join expands first JSONB nested rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same('cdn', $nestedRows()[7]['rule_name']);
    },
    'nested left join expands second JSONB nested rule' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same('images', $nestedRows()[8]['rule_name']);
    },
    'nested left join assigns JSONB nested rowid aliases' => static function (TestRunner $t) use ($nestedRows): void {
        $t->same([1, 2], [$nestedRows()[7]['rule_rowid'], $nestedRows()[8]['rule_rowid']]);
    },
    'nested left join plan records dynamic JSON joins' => static function (TestRunner $t) use ($options): void {
        $plan = SQLiteSelectSql::plan("SELECT o.option_name, g.value, r.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL", ['wp_options' => $options]);
        $t->same(2, count($plan['joins']));
    },
    'nested left join plan records right columns with rowid aliases' => static function (TestRunner $t) use ($options): void {
        $plan = SQLiteSelectSql::plan("SELECT o.option_name, g.value, r.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL", ['wp_options' => $options]);
        $t->same(true, in_array('r.rowid', $plan['joins'][1]['rightColumns'], true));
    },
    'nested left join plan records underscore rowid alias as nullable right column' => static function (TestRunner $t) use ($options): void {
        $plan = SQLiteSelectSql::plan("SELECT o.option_name, g.value, r.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL", ['wp_options' => $options]);
        $t->same(true, in_array('r._rowid_', $plan['joins'][1]['rightColumns'], true));
    },
    'nested left join plan records oid alias as nullable right column' => static function (TestRunner $t) use ($options): void {
        $plan = SQLiteSelectSql::plan("SELECT o.option_name, g.value, r.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL", ['wp_options' => $options]);
        $t->same(true, in_array('r.oid', $plan['joins'][1]['rightColumns'], true));
    },
    'nested left join where can filter null-extended rowid aliases' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT o.option_name AS option_name, json_extract(g.value, '$.name') AS group_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL WHERE r.rowid IS NULL ORDER BY option_name, group_name", ['wp_options' => $options]);
        $t->same(['plugin_nested_alpha', 'plugin_nested_beta', 'plugin_nested_empty', 'plugin_nested_null'], array_column($rows, 'option_name'));
    },
    'nested left join where can filter underscore rowid alias NULLs' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL WHERE r._rowid_ IS NULL ORDER BY option_name", ['wp_options' => $options]);
        $t->same(['plugin_nested_alpha', 'plugin_nested_beta', 'plugin_nested_empty', 'plugin_nested_null'], array_column($rows, 'option_name'));
    },
    'nested left join where can filter oid alias NULLs' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL WHERE r.oid IS NULL ORDER BY option_name", ['wp_options' => $options]);
        $t->same(['plugin_nested_alpha', 'plugin_nested_beta', 'plugin_nested_empty', 'plugin_nested_null'], array_column($rows, 'option_name'));
    },
    'nested left join can filter matched rowid alias' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT r.atom AS rule_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL WHERE r.rowid = 2 ORDER BY rule_name", ['wp_options' => $options]);
        $t->same(['cache', 'images'], array_column($rows, 'rule_name'));
    },
    'nested left join treats scalar nested JSON value as one json_each row' => static function (TestRunner $t): void {
        $bad = [['option_id' => 1, 'option_name' => 'bad_nested', 'option_value' => '{"groups":[{"rules":"not-array"}]}']];
        $t->same([['rule_name' => 'not-array']], SQLiteSelectSql::execute("SELECT r.atom AS rule_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL", ['wp_options' => $bad]));
    },
];
