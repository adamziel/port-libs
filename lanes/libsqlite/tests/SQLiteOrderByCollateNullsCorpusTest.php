<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;
use PortLibs\LibSqlite\SQLiteSelectResult;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'option_value' => 'core', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'siteurl ', 'autoload' => 'yes', 'option_value' => 'spaced', 'bytes' => null],
    ['option_id' => 3, 'option_name' => 'home', 'autoload' => 'yes', 'option_value' => 'front', 'bytes' => 12],
    ['option_id' => 4, 'option_name' => 'HOME ', 'autoload' => 'no', 'option_value' => 'legacy', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => 'blogname', 'autoload' => 'no', 'option_value' => 'title', 'bytes' => null],
    ['option_id' => 6, 'option_name' => null, 'autoload' => null, 'option_value' => null, 'bytes' => 0],
    ['option_id' => 7, 'option_name' => '_Transient_Feed', 'autoload' => 'no', 'option_value' => 'cache', 'bytes' => 4],
    ['option_id' => 8, 'option_name' => '_transient_feed ', 'autoload' => 'no', 'option_value' => 'cache-spaced', 'bytes' => 4],
];

return [
    'orders nulls last ascending through select result' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectResult::orderBy($options, [['column' => 'bytes', 'nulls' => 'LAST'], ['column' => 'option_id']]);
        $t->same([0, 4, 4, 12, 12, 24, null, null], array_column($rows, 'bytes'));
    },
    'orders nulls first descending through select result' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectResult::orderBy($options, [['column' => 'bytes', 'direction' => 'DESC', 'nulls' => 'FIRST'], ['column' => 'option_id']]);
        $t->same([null, null, 24, 12, 12, 4, 4, 0], array_column($rows, 'bytes'));
    },
    'keeps default ascending null placement' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectResult::orderBy($options, [['column' => 'bytes'], ['column' => 'option_id']]);
        $t->same([null, null, 0, 4, 4, 12, 12, 24], array_column($rows, 'bytes'));
    },
    'keeps default descending null placement' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectResult::orderBy($options, [['column' => 'bytes', 'direction' => 'DESC'], ['column' => 'option_id']]);
        $t->same([24, 12, 12, 4, 4, 0, null, null], array_column($rows, 'bytes'));
    },
    'orders text with nocase collation through select result' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectResult::orderBy($options, [['column' => 'option_name', 'collation' => 'NOCASE', 'nulls' => 'LAST'], ['column' => 'option_id']]);
        $t->same(['_Transient_Feed', '_transient_feed ', 'blogname', 'home', 'HOME ', 'SiteURL', 'siteurl ', null], array_column($rows, 'option_name'));
    },
    'orders text with rtrim collation through select result' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectResult::orderBy($options, [['column' => 'option_name', 'collation' => 'RTRIM', 'nulls' => 'LAST'], ['column' => 'option_id']]);
        $t->same(['HOME ', 'SiteURL', '_Transient_Feed', '_transient_feed ', 'blogname', 'home', 'siteurl ', null], array_column($rows, 'option_name'));
    },
    'keeps binary collation case ordering distinct' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectResult::orderBy($options, [['column' => 'option_name', 'collation' => 'BINARY', 'nulls' => 'LAST']]);
        $t->same(['HOME ', 'SiteURL', '_Transient_Feed', '_transient_feed ', 'blogname', 'home', 'siteurl ', null], array_column($rows, 'option_name'));
    },
    'orders nocase descending with explicit nulls last' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectResult::orderBy($options, [['column' => 'option_name', 'collation' => 'NOCASE', 'direction' => 'DESC', 'nulls' => 'LAST'], ['column' => 'option_id']]);
        $t->same(['siteurl ', 'SiteURL', 'HOME ', 'home', 'blogname', '_transient_feed ', '_Transient_Feed', null], array_column($rows, 'option_name'));
    },
    'orders rtrim ties stably' => static function (TestRunner $t): void {
        $rows = SQLiteSelectResult::orderBy([
            ['name' => 'first', 'key' => 'plugin'],
            ['name' => 'second', 'key' => 'plugin '],
            ['name' => 'third', 'key' => 'plugin  '],
        ], [['column' => 'key', 'collation' => 'RTRIM']]);
        $t->same(['first', 'second', 'third'], array_column($rows, 'name'));
    },
    'orders mixed storage classes while applying text collation only to strings' => static function (TestRunner $t): void {
        $rows = SQLiteSelectResult::orderBy([
            ['name' => 'null', 'value' => null],
            ['name' => 'integer', 'value' => 9],
            ['name' => 'lower', 'value' => 'alpha'],
            ['name' => 'upper', 'value' => 'Alpha'],
        ], [['column' => 'value', 'collation' => 'NOCASE'], ['column' => 'name']]);
        $t->same(['null', 'integer', 'lower', 'upper'], array_column($rows, 'name'));
    },
    'rejects unsupported order by collation' => static function (TestRunner $t) use ($options): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectResult::orderBy($options, [['column' => 'option_name', 'collation' => 'WPNATURAL']]));
    },
    'rejects unsupported nulls placement' => static function (TestRunner $t) use ($options): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectResult::orderBy($options, [['column' => 'option_name', 'nulls' => 'MIDDLE']]));
    },
    'executes query plan with nulls last' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectQuery::execute(['from' => $options, 'orderBy' => [['column' => 'bytes', 'nulls' => 'LAST'], ['column' => 'option_id']]]);
        $t->same([6, 7, 8, 3, 4, 1, 2, 5], array_column($rows, 'option_id'));
    },
    'executes query plan with nocase collation' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectQuery::execute(['from' => $options, 'orderBy' => [['column' => 'option_name', 'collation' => 'NOCASE', 'nulls' => 'LAST'], ['column' => 'option_id']]]);
        $t->same([7, 8, 5, 3, 4, 1, 2, 6], array_column($rows, 'option_id'));
    },
    'executes query plan with rtrim collation and limit' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectQuery::execute(['from' => $options, 'orderBy' => [['column' => 'option_name', 'collation' => 'RTRIM', 'nulls' => 'LAST']], 'limit' => 3]);
        $t->same(['HOME ', 'SiteURL', '_Transient_Feed'], array_column($rows, 'option_name'));
    },
    'parses select sql nulls last ascending' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT option_id, bytes FROM wp_options ORDER BY bytes NULLS LAST, option_id', ['wp_options' => $options]);
        $t->same([6, 7, 8, 3, 4, 1, 2, 5], array_column($rows, 'option_id'));
    },
    'parses select sql nulls first descending' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT option_id, bytes FROM wp_options ORDER BY bytes DESC NULLS FIRST, option_id', ['wp_options' => $options]);
        $t->same([2, 5, 1, 3, 4, 7, 8, 6], array_column($rows, 'option_id'));
    },
    'parses select sql nocase order by collation' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT option_id, option_name FROM wp_options ORDER BY option_name COLLATE NOCASE NULLS LAST, option_id', ['wp_options' => $options]);
        $t->same([7, 8, 5, 3, 4, 1, 2, 6], array_column($rows, 'option_id'));
    },
    'parses select sql rtrim order by collation' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT option_id, option_name FROM wp_options ORDER BY option_name COLLATE RTRIM NULLS LAST', ['wp_options' => $options]);
        $t->same([4, 1, 7, 8, 5, 3, 2, 6], array_column($rows, 'option_id'));
    },
    'parses select sql collation direction and null placement together' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT option_id, option_name FROM wp_options ORDER BY option_name COLLATE NOCASE DESC NULLS LAST, option_id', ['wp_options' => $options]);
        $t->same([2, 1, 4, 3, 5, 8, 7, 6], array_column($rows, 'option_id'));
    },
    'parses select sql expression collation with hidden order column' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT option_id, option_name FROM wp_options ORDER BY option_name || '' COLLATE NOCASE NULLS LAST, option_id", ['wp_options' => $options]);
        $t->same([7, 8, 5, 3, 4, 1, 2, 6], array_column($rows, 'option_id'));
        $t->same(['option_id', 'option_name'], array_keys($rows[0]));
    },
    'parses select sql rtrim expression collation with hidden order column' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT option_id, option_name FROM wp_options ORDER BY option_name || '' COLLATE RTRIM NULLS LAST", ['wp_options' => $options]);
        $t->same([4, 1, 7, 8, 5, 3, 2, 6], array_column($rows, 'option_id'));
        $t->same(['option_id', 'option_name'], array_keys($rows[0]));
    },
    'parses select sql collated alias order' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT option_name AS name FROM wp_options ORDER BY name COLLATE NOCASE NULLS LAST LIMIT 3', ['wp_options' => $options]);
        $t->same(['_Transient_Feed', '_transient_feed ', 'blogname'], array_column($rows, 'name'));
    },
    'parses select sql collated alias descending nulls first' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT option_name AS name FROM wp_options ORDER BY name COLLATE NOCASE DESC NULLS FIRST LIMIT 3', ['wp_options' => $options]);
        $t->same([null, 'siteurl ', 'SiteURL'], array_column($rows, 'name'));
    },
    'parses select sql collated order after where predicate' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT option_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY option_name COLLATE NOCASE", ['wp_options' => $options]);
        $t->same([7, 8, 5, 4], array_column($rows, 'option_id'));
    },
    'parses select sql collated order after group by' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute('SELECT autoload, count(option_id) AS total FROM wp_options GROUP BY autoload ORDER BY autoload COLLATE NOCASE NULLS LAST', ['wp_options' => $options]);
        $t->same(['no', 'yes', null], array_column($rows, 'autoload'));
    },
    'plans select sql order by collation metadata' => static function (TestRunner $t) use ($options): void {
        $plan = SQLiteSelectSql::plan('SELECT option_id FROM wp_options ORDER BY option_name COLLATE NOCASE DESC NULLS LAST', ['wp_options' => $options]);
        $t->same([['column' => '__sqlite_order_column_0', 'direction' => 'DESC', 'collation' => 'NOCASE', 'nulls' => 'LAST']], $plan['orderBy']);
    },
    'plans select sql expression order by collation metadata' => static function (TestRunner $t) use ($options): void {
        $plan = SQLiteSelectSql::plan("SELECT option_id FROM wp_options ORDER BY option_name || '' COLLATE RTRIM NULLS FIRST", ['wp_options' => $options]);
        $t->same('RTRIM', $plan['orderBy'][0]['collation']);
        $t->same('FIRST', $plan['orderBy'][0]['nulls']);
    },
    'parses compound select order by collation metadata' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name AS name FROM wp_options WHERE option_id IN (1, 3) UNION ALL SELECT option_name AS name FROM wp_options WHERE option_id IN (4, 6) ORDER BY name COLLATE NOCASE NULLS LAST", ['wp_options' => $options]);
        $t->same(['home', 'HOME ', 'SiteURL', null], array_column($rows, 'name'));
    },
    'parses compound select order by nulls first descending' => static function (TestRunner $t) use ($options): void {
        $rows = SQLiteSelectSql::execute("SELECT bytes FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT bytes FROM wp_options WHERE option_id IN (5, 6) ORDER BY bytes DESC NULLS FIRST", ['wp_options' => $options]);
        $t->same([null, null, 24, 0], array_column($rows, 'bytes'));
    },
    'rejects select sql unsupported collation at execution' => static function (TestRunner $t) use ($options): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT option_name FROM wp_options ORDER BY option_name COLLATE WPNATURAL', ['wp_options' => $options]));
    },
];
