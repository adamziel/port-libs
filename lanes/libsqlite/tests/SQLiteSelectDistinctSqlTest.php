<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectQuery;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_value' => 'Example Site', 'bytes' => 12],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'option_value' => 'cached', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'option_value' => 'cached', 'bytes' => 12],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'option_value' => null, 'bytes' => null],
    ['option_id' => 7, 'option_name' => 'binary_a', 'autoload' => 'no', 'option_value' => new SQLiteBlobValue('AB'), 'bytes' => 2],
    ['option_id' => 8, 'option_name' => 'binary_b', 'autoload' => 'no', 'option_value' => new SQLiteBlobValue('AB'), 'bytes' => 2],
];

$meta = [
    ['option_id' => 1, 'source' => 'core', 'priority' => 10],
    ['option_id' => 2, 'source' => 'core', 'priority' => 20],
    ['option_id' => 3, 'source' => 'theme', 'priority' => 30],
    ['option_id' => 4, 'source' => 'cache', 'priority' => 5],
    ['option_id' => 5, 'source' => 'cache', 'priority' => 5],
];

$settings = [
    ['option_id' => 10, 'option_name' => 'plugin_a', 'option_value' => '{"rules":[{"name":"seo","enabled":true},{"name":"seo","enabled":true},{"name":"cache","enabled":false}]}'],
    ['option_id' => 11, 'option_name' => 'plugin_b', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['rules' => [['name' => 'forms', 'enabled' => true], ['name' => 'forms', 'enabled' => true]]]))],
    ['option_id' => 12, 'option_name' => 'plugin_empty', 'option_value' => '{"rules":[]}'],
];

return [
    'deduplicates select distinct single projected column' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT autoload FROM wp_options ORDER BY autoload', ['wp_options' => $options]);
        $t->same([null, 'no', 'yes'], array_column($rows, 'autoload'));
        $t->same(['autoload'], array_keys($rows[0]));
    },
    'keeps select all as explicit duplicate-preserving projection' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT ALL autoload FROM wp_options WHERE autoload = \'yes\' ORDER BY autoload', ['wp_options' => $options]);
        $t->same(['yes', 'yes', 'yes'], array_column($rows, 'autoload'));
        $t->same(3, count($rows));
    },
    'deduplicates select distinct aliases after projection' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT autoload AS load_state FROM wp_options ORDER BY load_state DESC', ['wp_options' => $options]);
        $t->same(['yes', 'no', null], array_column($rows, 'load_state'));
        $t->same(['load_state'], array_keys($rows[0]));
    },
    'deduplicates select distinct expression aliases' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT bytes + 1 AS byte_bucket FROM wp_options ORDER BY byte_bucket', ['wp_options' => $options]);
        $t->same([null, 3, 13, 21], array_column($rows, 'byte_bucket'));
        $t->same(4, count($rows));
    },
    'deduplicates select distinct concatenated labels' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT DISTINCT autoload || ':' || bytes AS label FROM wp_options WHERE autoload IS NOT NULL ORDER BY label", ['wp_options' => $options]);
        $t->same(['no:12', 'no:2', 'yes:12', 'yes:20'], array_column($rows, 'label'));
        $t->same(['label'], array_keys($rows[0]));
    },
    'deduplicates select distinct NULL result values' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT option_value AS value FROM wp_options WHERE option_value IS NULL OR option_name = \'orphaned\'', ['wp_options' => $options]);
        $t->same(1, count($rows));
        $t->same(null, $rows[0]['value']);
    },
    'deduplicates select distinct BLOB result values by byte content' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT DISTINCT option_value AS payload FROM wp_options WHERE option_name GLOB 'binary_*'", ['wp_options' => $options]);
        $t->same(1, count($rows));
        $t->same('AB', $rows[0]['payload']->bytes);
    },
    'deduplicates select distinct multi-column rows' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT autoload, bytes FROM wp_options ORDER BY autoload DESC, bytes DESC', ['wp_options' => $options]);
        $t->same([['yes', 20], ['yes', 12], ['no', 12], ['no', 2], [null, null]], array_map(static fn (array $row): array => [$row['autoload'], $row['bytes']], $rows));
        $t->same(5, count($rows));
    },
    'deduplicates select distinct wildcard rows only when every output column matches' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT * FROM copied ORDER BY option_id', ['copied' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
            ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ]]);
        $t->same(2, count($rows));
        $t->same([1, 2], array_column($rows, 'option_id'));
    },
    'deduplicates select distinct table-star rows after join projection' => static function (TestRunner $t) use ($options, $meta): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT m.* FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id WHERE m.source = \'cache\' ORDER BY priority', ['wp_options' => $options, 'option_meta' => $meta]);
        $t->same(2, count($rows));
        $t->same([4, 5], array_column($rows, 'option_id'));
    },
    'deduplicates select distinct joined source labels' => static function (TestRunner $t) use ($options, $meta): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT m.source AS source FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id ORDER BY source', ['wp_options' => $options, 'option_meta' => $meta]);
        $t->same(['cache', 'core', 'theme'], array_column($rows, 'source'));
        $t->same(['source'], array_keys($rows[0]));
    },
    'deduplicates select distinct left join NULL-extended rows' => static function (TestRunner $t) use ($options, $meta): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT m.source AS source FROM wp_options LEFT JOIN option_meta AS m ON wp_options.option_id = m.option_id ORDER BY source', ['wp_options' => $options, 'option_meta' => $meta]);
        $t->same([null, 'cache', 'core', 'theme'], array_column($rows, 'source'));
        $t->same(4, count($rows));
    },
    'deduplicates select distinct cross join constants' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT f.flag AS flag FROM wp_options CROSS JOIN flags AS f WHERE wp_options.option_id <= 2 ORDER BY flag', ['wp_options' => $options, 'flags' => [['flag' => 'public'], ['flag' => 'public'], ['flag' => 'private']]]);
        $t->same(['private', 'public'], array_column($rows, 'flag'));
        $t->same(2, count($rows));
    },
    'deduplicates select distinct CTE projection rows' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('WITH states AS (SELECT autoload, bytes FROM wp_options WHERE option_id <= 5) SELECT DISTINCT autoload FROM states ORDER BY autoload DESC', ['wp_options' => $options]);
        $t->same(['yes', 'no'], array_column($rows, 'autoload'));
        $t->same(2, count($rows));
    },
    'deduplicates select distinct CTE renamed columns' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('WITH states(load_state, byte_count) AS (SELECT autoload, bytes FROM wp_options WHERE option_id <= 5) SELECT DISTINCT load_state, byte_count FROM states ORDER BY load_state DESC, byte_count DESC', ['wp_options' => $options]);
        $t->same([['yes', 20], ['yes', 12], ['no', 12]], array_map(static fn (array $row): array => [$row['load_state'], $row['byte_count']], $rows));
        $t->same(3, count($rows));
    },
    'deduplicates select distinct parameterized expression rows' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT autoload || :suffix AS label FROM wp_options WHERE bytes >= ? ORDER BY label', ['wp_options' => $options], [0 => 12, ':suffix' => ':copy']);
        $t->same(['no:copy', 'yes:copy'], array_column($rows, 'label'));
        $t->same(2, count($rows));
    },
    'deduplicates select distinct scalar subquery values with local metadata' => static function (TestRunner $t) use ($options, $meta): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT (SELECT source FROM option_meta WHERE option_id = option_id) AS source FROM wp_options ORDER BY source', ['wp_options' => $options, 'option_meta' => $meta]);
        $t->same(['core'], array_column($rows, 'source'));
        $t->same(1, count($rows));
    },
    'deduplicates select distinct IN-filtered rows' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT autoload FROM wp_options WHERE option_id IN (1, 2, 3, 4, 5) ORDER BY autoload DESC', ['wp_options' => $options]);
        $t->same(['yes', 'no'], array_column($rows, 'autoload'));
        $t->same(2, count($rows));
    },
    'deduplicates select distinct grouped summary rows after projection' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT autoload, count(*) AS rows, sum(bytes) AS byte_sum FROM wp_options WHERE autoload IS NOT NULL GROUP BY autoload ORDER BY autoload', ['wp_options' => $options]);
        $t->same([['no', 4, 28], ['yes', 3, 52]], array_map(static fn (array $row): array => [$row['autoload'], $row['rows'], $row['byte_sum']], $rows));
        $t->same(2, count($rows));
    },
    'deduplicates select distinct json_each static rows' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute("SELECT DISTINCT atom FROM json_each('[\"seo\",\"seo\",\"cache\"]') ORDER BY atom", []);
        $t->same(['cache', 'seo'], array_column($rows, 'atom'));
        $t->same(2, count($rows));
    },
    'deduplicates select distinct json_tree visible rows' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute("SELECT DISTINCT key, type FROM json_tree('{\"plugin\":{\"name\":\"cache\",\"rules\":[1,1]}}') WHERE key IS NOT NULL ORDER BY key", []);
        $t->same([[0, 'integer'], [1, 'integer'], ['name', 'text'], ['plugin', 'object'], ['rules', 'array']], array_map(static fn (array $row): array => [$row['key'], $row['type']], $rows));
        $t->same(5, count($rows));
    },
    'deduplicates select distinct dynamic json table rows' => static function (TestRunner $t) use ($settings): void {
        $rows = SQLiteSelectSql::execute("SELECT DISTINCT j.atom AS rule_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'name' ORDER BY rule_name", ['wp_options' => $settings]);
        $t->same(['cache', 'forms', 'seo'], array_column($rows, 'rule_name'));
        $t->same(3, count($rows));
    },
    'deduplicates select distinct dynamic json table left join rows' => static function (TestRunner $t) use ($settings): void {
        $rows = SQLiteSelectSql::execute("SELECT DISTINCT j.atom AS rule_name FROM wp_options AS o LEFT JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'name' ORDER BY rule_name", ['wp_options' => $settings]);
        $t->same([null, 'cache', 'forms', 'seo'], array_column($rows, 'rule_name'));
        $t->same(4, count($rows));
    },
    'deduplicates select distinct JSONB dynamic json table rows' => static function (TestRunner $t) use ($settings): void {
        $rows = SQLiteSelectSql::execute("SELECT DISTINCT j.atom AS rule_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'name' WHERE o.option_id = 11 ORDER BY rule_name", ['wp_options' => $settings]);
        $t->same(['forms'], array_column($rows, 'rule_name'));
        $t->same(1, count($rows));
    },
    'deduplicates select distinct before final ORDER BY and LIMIT' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT autoload FROM wp_options ORDER BY autoload DESC LIMIT 1', ['wp_options' => $options]);
        $t->same(['yes'], array_column($rows, 'autoload'));
        $t->same(1, count($rows));
    },
    'returns an empty rowset for select distinct over no source rows' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT DISTINCT autoload FROM wp_options WHERE option_name = 'missing'", ['wp_options' => $options]);
        $t->same([], $rows);
    },
    'deduplicates select distinct before OFFSET' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT autoload FROM wp_options ORDER BY autoload DESC LIMIT 1 OFFSET 1', ['wp_options' => $options]);
        $t->same(['no'], array_column($rows, 'autoload'));
        $t->same(1, count($rows));
    },
    'deduplicates select distinct with comma LIMIT form' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT DISTINCT autoload FROM wp_options ORDER BY autoload DESC LIMIT 1, 2', ['wp_options' => $options]);
        $t->same(['no', null], array_column($rows, 'autoload'));
        $t->same(2, count($rows));
    },
    'plans select distinct as projected-row deduplication' => static function (TestRunner $t) use ($options): void {
        $plan = SQLiteSelectSql::plan('SELECT DISTINCT autoload AS load_state FROM wp_options WHERE bytes >= 12 ORDER BY load_state', ['wp_options' => $options]);
        $t->same(['from', 'select', 'distinct', 'where', 'orderBy'], array_keys($plan));
        $t->same(true, $plan['distinct']);
        $t->same('load_state', $plan['select'][0]['alias']);
        $t->same(['no', 'yes'], array_column(SQLiteSelectQuery::execute($plan), 'load_state'));
    },
    'rejects malformed select distinct without projection' => static function (TestRunner $t) use ($options): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT DISTINCT FROM wp_options', ['wp_options' => $options]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT ALL FROM wp_options', ['wp_options' => $options]));
    },
    'preserves explicit distinct-column query plans' => static function (TestRunner $t): void {
        $rows = SQLiteSelectQuery::execute([
            'from' => [['name' => 'siteurl', 'autoload' => 'yes'], ['name' => 'home', 'autoload' => 'yes'], ['name' => 'cache', 'autoload' => 'no']],
            'select' => [['type' => 'column', 'name' => 'autoload']],
            'distinct' => ['autoload'],
            'orderBy' => [['column' => 'autoload', 'direction' => 'DESC']],
        ]);
        $t->same(['yes', 'no'], array_column($rows, 'autoload'));
        $t->same(2, count($rows));
    },
];
