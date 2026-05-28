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
        ['option_id' => 3, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'option_size' => 20],
        ['option_id' => 4, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'option_size' => 30],
        ['option_id' => 5, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'option_size' => 30],
        ['option_id' => 6, 'option_name' => 'plugin_queue', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2, 'ok' => true])), 'autoload' => 'no', 'option_size' => 25],
        ['option_id' => 7, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'option_size' => 0],
        ['option_id' => 8, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'option_size' => 0],
    ],
];

$tests['json aggregate distinct current next76 groups distinct names by autoload'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY option_name) AS names FROM wp_options GROUP BY autoload ORDER BY autoload',
        $tables,
    );

    $t->same('no', $rows[0]['autoload']);
    $t->same('["empty_option","plugin_queue","plugin_rules"]', $rows[0]['names']);
    $t->same('yes', $rows[1]['autoload']);
    $t->same('["blogname","siteurl"]', $rows[1]['names']);
};

$tests['json aggregate distinct current next76 implicit aggregate preserves ordered distinct names'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY option_id) AS names FROM wp_options',
        $tables,
    );

    $t->same('["siteurl","blogname","plugin_rules","plugin_queue","empty_option"]', $rows[0]['names']);
};

$tests['json aggregate distinct current next76 filter runs before distinct'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(DISTINCT option_name ORDER BY option_id) FILTER (WHERE autoload = 'no') AS names FROM wp_options",
        $tables,
    );

    $t->same('["plugin_rules","plugin_queue","empty_option"]', $rows[0]['names']);
};

$tests['json aggregate distinct current next76 no order keeps first source occurrence'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name) AS names FROM wp_options',
        $tables,
    );

    $t->same('["siteurl","blogname","plugin_rules","plugin_queue","empty_option"]', $rows[0]['names']);
};

$tests['json aggregate distinct current next76 order chooses first row after aggregate sort'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY option_size) AS names FROM wp_options',
        ['wp_options' => [
            ['option_name' => 'dup', 'option_size' => 50],
            ['option_name' => 'tail', 'option_size' => 60],
            ['option_name' => 'dup', 'option_size' => 10],
            ['option_name' => 'head', 'option_size' => 20],
        ]],
    );

    $t->same('["dup","head","tail"]', $rows[0]['names']);
};

$tests['json aggregate distinct current next76 stable order preserves first equal order key'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY autoload) AS names FROM wp_options',
        ['wp_options' => [
            ['option_name' => 'first', 'autoload' => 'same'],
            ['option_name' => 'second', 'autoload' => 'same'],
            ['option_name' => 'first', 'autoload' => 'same'],
        ]],
    );

    $t->same('["first","second"]', $rows[0]['names']);
};

$tests['json aggregate distinct current next76 empty filtered aggregate remains array'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY option_id) FILTER (WHERE option_size < 0) AS names FROM wp_options',
        $tables,
    );

    $t->same('[]', $rows[0]['names']);
};

$tests['json aggregate distinct current next76 distinct null collapses to one null'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(DISTINCT option_value ORDER BY option_id) FILTER (WHERE option_name = 'empty_option') AS payloads FROM wp_options",
        $tables,
    );

    $t->same('[null]', $rows[0]['payloads']);
};

$tests['json aggregate distinct current next76 distinct json subtype collapses by json text'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(DISTINCT option_value ORDER BY option_id) FILTER (WHERE option_name = 'plugin_rules') AS payloads FROM wp_options",
        $tables,
    );

    $t->same('[[{"name":"seo"},{"name":"cache"}]]', $rows[0]['payloads']);
};

$tests['json aggregate distinct current next76 jsonb aggregate decodes distinct ordered names'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT jsonb_group_array(DISTINCT option_name ORDER BY option_name) FILTER (WHERE autoload = 'no') AS names FROM wp_options",
        $tables,
    );

    $t->true($rows[0]['names'] instanceof SQLiteBlobValue);
    $t->same(['empty_option', 'plugin_queue', 'plugin_rules'], SQLiteJsonB::decode($rows[0]['names']->bytes));
};

$tests['json aggregate distinct current next76 jsonb aggregate decodes distinct json subtype payload'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT jsonb_group_array(DISTINCT option_value ORDER BY option_id) FILTER (WHERE option_name = 'plugin_rules') AS payloads FROM wp_options",
        $tables,
    );

    $t->same([[['name' => 'seo'], ['name' => 'cache']]], SQLiteJsonB::decode($rows[0]['payloads']->bytes));
};

$tests['json aggregate distinct current next76 grouped jsonb rows remain independently distinct'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, jsonb_group_array(DISTINCT option_name ORDER BY option_name) AS names FROM wp_options GROUP BY autoload ORDER BY autoload',
        $tables,
    );

    $t->same(['empty_option', 'plugin_queue', 'plugin_rules'], SQLiteJsonB::decode($rows[0]['names']->bytes));
    $t->same(['blogname', 'siteurl'], SQLiteJsonB::decode($rows[1]['names']->bytes));
};

$tests['json aggregate distinct current next76 having can read distinct json summary'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload, json_group_array(DISTINCT option_name ORDER BY option_name) AS names FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY option_name) LIKE '%plugin%'",
        $tables,
    );

    $t->same(1, count($rows));
    $t->same('no', $rows[0]['autoload']);
};

$tests['json aggregate distinct current next76 final order can sort by distinct json summary'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY option_name) AS names FROM wp_options GROUP BY autoload ORDER BY names DESC',
        $tables,
    );

    $t->same('no', $rows[0]['autoload']);
    $t->same('yes', $rows[1]['autoload']);
};

$tests['json aggregate distinct current next76 count distinct can coexist with json distinct'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, count(DISTINCT option_name) AS unique_names, json_group_array(DISTINCT option_name ORDER BY option_name) AS names FROM wp_options GROUP BY autoload ORDER BY autoload',
        $tables,
    );

    $t->same(3, $rows[0]['unique_names']);
    $t->same(2, $rows[1]['unique_names']);
    $t->same('["blogname","siteurl"]', $rows[1]['names']);
};

$filterCases = [
    'between keeps duplicated plugin and empty rows once' => ['option_id BETWEEN 4 AND 8', '["plugin_rules","plugin_queue","empty_option"]'],
    'not between keeps first site rows once' => ['option_id NOT BETWEEN 4 AND 8', '["siteurl","blogname"]'],
    'is null keeps one empty option' => ['option_value IS NULL', '["empty_option"]'],
    'is not null skips duplicate payload nulls' => ['option_value IS NOT NULL', '["siteurl","blogname","plugin_rules","plugin_queue"]'],
    'like plugin names collapse duplicates' => ["option_name LIKE 'plugin_%'", '["plugin_rules","plugin_queue"]'],
    'glob option suffix collapses duplicates' => ["option_name GLOB '*option'", '["empty_option"]'],
    'truthy size skips zero duplicates' => ['option_size', '["siteurl","blogname","plugin_rules","plugin_queue"]'],
    'not size keeps zero duplicate once' => ['NOT option_size', '["empty_option"]'],
    'compound and keeps no-autoload nonzero names' => ["autoload = 'no' AND option_size > 0", '["plugin_rules","plugin_queue"]'],
    'compound or keeps selected duplicate names' => ["option_name = 'siteurl' OR option_name = 'empty_option'", '["siteurl","empty_option"]'],
];

foreach ($filterCases as $name => [$filterSql, $expected]) {
    $tests['json aggregate distinct current next76 filter ' . $name] = static function (TestRunner $t) use ($tables, $filterSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_group_array(DISTINCT option_name ORDER BY option_id) FILTER (WHERE {$filterSql}) AS names FROM wp_options",
            $tables,
        );

        $t->same($expected, $rows[0]['names']);
    };
}

$tests['json aggregate distinct current next76 rejects distinct star'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT json_group_array(DISTINCT *) AS names FROM wp_options', $tables));
};

$tests['json aggregate distinct current next76 accepts expression order term'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute('SELECT json_group_array(DISTINCT option_name ORDER BY option_id + 1) AS names FROM wp_options', $tables);

    $t->same('["siteurl","blogname","plugin_rules","plugin_queue","empty_option"]', $rows[0]['names']);
};

$tests['json aggregate distinct current next76 rejects missing distinct argument'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT json_group_array(DISTINCT) AS names FROM wp_options', $tables));
};

return $tests;
