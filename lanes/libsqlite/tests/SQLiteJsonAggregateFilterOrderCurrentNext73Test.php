<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'option_size' => 20],
        ['option_id' => 2, 'option_name' => 'blogname', 'option_value' => 'Port Fixture', 'autoload' => 'yes', 'option_size' => 12],
        ['option_id' => 3, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'option_size' => 30],
        ['option_id' => 4, 'option_name' => 'plugin_queue', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2, 'ok' => true])), 'autoload' => 'no', 'option_size' => 25],
        ['option_id' => 5, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'option_size' => 0],
    ],
];

$tests['json aggregate filter order current next73 groups arrays by autoload with local order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload, json_group_array(option_name ORDER BY option_id) FILTER (WHERE option_size > 0) AS names FROM wp_options GROUP BY autoload ORDER BY autoload",
        $tables,
    );

    $t->same(2, count($rows));
    $t->same('no', $rows[0]['autoload']);
    $t->same('["plugin_rules","plugin_queue"]', $rows[0]['names']);
    $t->same('yes', $rows[1]['autoload']);
    $t->same('["siteurl","blogname"]', $rows[1]['names']);
};

$tests['json aggregate filter order current next73 orders aggregate input independently of final order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload, json_group_array(option_name ORDER BY option_name) FILTER (WHERE option_id >= 2) AS names FROM wp_options GROUP BY autoload ORDER BY names DESC",
        $tables,
    );

    $t->same('no', $rows[0]['autoload']);
    $t->same('["empty_option","plugin_queue","plugin_rules"]', $rows[0]['names']);
    $t->same('yes', $rows[1]['autoload']);
    $t->same('["blogname"]', $rows[1]['names']);
};

$tests['json aggregate filter order current next73 implicit aggregate preserves empty filtered array'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(option_name ORDER BY option_id) FILTER (WHERE option_size < 0) AS names FROM wp_options",
        $tables,
    );

    $t->same(1, count($rows));
    $t->same('[]', $rows[0]['names']);
};

$tests['json aggregate filter order current next73 implicit aggregate keeps json subtype and jsonb values'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(option_value ORDER BY option_id) FILTER (WHERE autoload = 'no') AS payloads FROM wp_options",
        $tables,
    );

    $t->same('[[{"name":"seo"},{"name":"cache"}],{"pending":2,"ok":true},null]', $rows[0]['payloads']);
};

$tests['json aggregate filter order current next73 dispatches jsonb aggregate through select sql'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT jsonb_group_array(option_name ORDER BY option_name) FILTER (WHERE autoload = 'no') AS names FROM wp_options",
        $tables,
    );

    $t->true($rows[0]['names'] instanceof SQLiteBlobValue);
    $t->same(['empty_option', 'plugin_queue', 'plugin_rules'], SQLiteJsonB::decode($rows[0]['names']->bytes));
};

$tests['json aggregate filter order current next73 supports having over grouped json rows'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload, json_group_array(option_name ORDER BY option_name) FILTER (WHERE option_size >= 0) AS names FROM wp_options GROUP BY autoload HAVING count(*) > 2",
        $tables,
    );

    $t->same(1, count($rows));
    $t->same('no', $rows[0]['autoload']);
    $t->same('["empty_option","plugin_queue","plugin_rules"]', $rows[0]['names']);
};

$tests['json aggregate filter order current next73 rejects malformed filter'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT json_group_array(option_name) FILTER (autoload = 'yes') AS names FROM wp_options", $tables));
};

$tests['json aggregate filter order current next73 accepts expression order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute("SELECT json_group_array(option_name ORDER BY option_id + 1) AS names FROM wp_options", $tables);

    $t->same('["siteurl","blogname","plugin_rules","plugin_queue","empty_option"]', $rows[0]['names']);
};

$tests['json aggregate filter order current next73 now admits distinct order follow-up'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute("SELECT json_group_array(DISTINCT option_name ORDER BY option_id) AS names FROM wp_options", $tables);

    $t->same('["siteurl","blogname","plugin_rules","plugin_queue","empty_option"]', $rows[0]['names']);
};

$filterCases = [
    'equality keeps autoload yes' => ["autoload = 'yes'", '["siteurl","blogname"]'],
    'not equality keeps plugin rows' => ["autoload != 'yes'", '["plugin_rules","plugin_queue","empty_option"]'],
    'between keeps middle option ids' => ['option_id BETWEEN 2 AND 4', '["blogname","plugin_rules","plugin_queue"]'],
    'not between keeps edge option ids' => ['option_id NOT BETWEEN 2 AND 4', '["siteurl","empty_option"]'],
    'is null keeps empty option payload' => ['option_value IS NULL', '["empty_option"]'],
    'is not null skips empty option payload' => ['option_value IS NOT NULL', '["siteurl","blogname","plugin_rules","plugin_queue"]'],
    'like keeps plugin names' => ["option_name LIKE 'plugin_%'", '["plugin_rules","plugin_queue"]'],
    'glob keeps option suffix' => ["option_name GLOB '*option'", '["empty_option"]'],
    'truthy integer expression keeps nonzero sizes' => ['option_size', '["siteurl","blogname","plugin_rules","plugin_queue"]'],
    'not expression keeps zero size' => ['NOT option_size', '["empty_option"]'],
    'compound and filter keeps large plugin rows' => ["autoload = 'no' AND option_size > 20", '["plugin_rules","plugin_queue"]'],
    'compound or filter keeps selected names' => ["option_name = 'siteurl' OR option_name = 'empty_option'", '["siteurl","empty_option"]'],
];

foreach ($filterCases as $name => [$filterSql, $expected]) {
    $tests['json aggregate filter order current next73 filter predicate ' . $name] = static function (TestRunner $t) use ($tables, $filterSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_group_array(option_name ORDER BY option_id) FILTER (WHERE {$filterSql}) AS values_json FROM wp_options",
            $tables,
        );

        $t->same($expected, $rows[0]['values_json']);
    };
}

$groupCases = [
    'yes ordered by name' => ['yes', '["blogname","siteurl"]'],
    'no ordered by name' => ['no', '["empty_option","plugin_queue","plugin_rules"]'],
];

foreach ($groupCases as $name => [$autoload, $expected]) {
    $tests['json aggregate filter order current next73 grouped order case ' . $name] = static function (TestRunner $t) use ($tables, $autoload, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload, json_group_array(option_name ORDER BY option_name) FILTER (WHERE option_id >= 1) AS names FROM wp_options GROUP BY autoload ORDER BY autoload",
            $tables,
        );
        $byAutoload = array_column($rows, null, 'autoload');

        $t->same($expected, $byAutoload[$autoload]['names']);
    };
}

$tests['json aggregate filter order current next73 stable order preserves source order for equal keys'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(option_name ORDER BY autoload) FILTER (WHERE option_size > 0) AS names FROM wp_options",
        ['wp_options' => [
            ['option_name' => 'first', 'autoload' => 'same', 'option_size' => 1],
            ['option_name' => 'second', 'autoload' => 'same', 'option_size' => 1],
            ['option_name' => 'third', 'autoload' => 'same', 'option_size' => 1],
        ]],
    );

    $t->same('["first","second","third"]', $rows[0]['names']);
};

$tests['json aggregate filter order current next73 jsonb grouped aggregate decodes selected row'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload, jsonb_group_array(option_name ORDER BY option_name) FILTER (WHERE option_id > 0) AS names FROM wp_options GROUP BY autoload ORDER BY autoload",
        $tables,
    );

    $t->same(['empty_option', 'plugin_queue', 'plugin_rules'], SQLiteJsonB::decode($rows[0]['names']->bytes));
    $t->same(['blogname', 'siteurl'], SQLiteJsonB::decode($rows[1]['names']->bytes));
};

return $tests;
