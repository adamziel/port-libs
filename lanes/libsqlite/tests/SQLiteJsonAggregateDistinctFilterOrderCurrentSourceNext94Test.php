<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'priority' => 20, 'tie' => 'b', 'enabled' => 1, 'bucket' => 'core', 'payload' => new SQLiteJsonSubtypeValue('{"name":"theme"}')],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'siteurl', 'priority' => 30, 'tie' => 'a', 'enabled' => 1, 'bucket' => 'core', 'payload' => new SQLiteJsonSubtypeValue('{"name":"site"}')],
        ['option_id' => 3, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'priority' => 30, 'tie' => 'z', 'enabled' => 1, 'bucket' => 'core', 'payload' => new SQLiteJsonSubtypeValue('{"name":"theme"}')],
        ['option_id' => 4, 'autoload' => 'yes', 'option_name' => 'blogname', 'priority' => 40, 'tie' => 'c', 'enabled' => 0, 'bucket' => 'core', 'payload' => 'Port Fixture'],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'priority' => 50, 'tie' => 'b', 'enabled' => 1, 'bucket' => 'plugin', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'rules']))],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'priority' => 50, 'tie' => 'a', 'enabled' => 1, 'bucket' => 'plugin', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'queue']))],
        ['option_id' => 7, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'priority' => 45, 'tie' => 'z', 'enabled' => 1, 'bucket' => 'plugin', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'rules']))],
        ['option_id' => 8, 'autoload' => 'no', 'option_name' => 'empty_option', 'priority' => null, 'tie' => 'n', 'enabled' => 1, 'bucket' => 'empty', 'payload' => null],
        ['option_id' => 9, 'autoload' => 'no', 'option_name' => 'zero_option', 'priority' => 0, 'tie' => 'z', 'enabled' => 0, 'bucket' => 'empty', 'payload' => 0],
    ],
];

$tests['json aggregate distinct filter order current source next94 multi term order works for implicit aggregate'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) AS names FROM wp_options',
        $tables,
    );

    $t->same('["plugin_queue","plugin_rules","siteurl","theme_mods","empty_option"]', $rows[0]['names']);
};

$tests['json aggregate distinct filter order current source next94 secondary term decides duplicate admission'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY priority DESC, tie DESC) FILTER (WHERE enabled) AS names FROM wp_options',
        $tables,
    );

    $t->same('["plugin_rules","plugin_queue","theme_mods","siteurl","empty_option"]', $rows[0]['names']);
};

$tests['json aggregate distinct filter order current source next94 grouped multi term summaries'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) AS names FROM wp_options GROUP BY autoload ORDER BY autoload',
        $tables,
    );

    $t->same('["plugin_queue","plugin_rules","empty_option"]', $rows[0]['names']);
    $t->same('["siteurl","theme_mods"]', $rows[1]['names']);
};

$tests['json aggregate distinct filter order current source next94 having reads multi term hidden summary'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) LIKE '[\"plugin_queue\"%' ORDER BY autoload",
        $tables,
    );

    $t->same([['autoload' => 'no']], $rows);
};

$tests['json aggregate distinct filter order current source next94 final order reads multi term hidden summary'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) DESC',
        $tables,
    );

    $t->same(['yes', 'no'], array_column($rows, 'autoload'));
};

$tests['json aggregate distinct filter order current source next94 jsonb decodes multi term aggregate'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT jsonb_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) AS names FROM wp_options',
        $tables,
    );

    $t->true($rows[0]['names'] instanceof SQLiteBlobValue);
    $t->same(['plugin_queue', 'plugin_rules', 'siteurl', 'theme_mods', 'empty_option'], SQLiteJsonB::decode($rows[0]['names']->bytes));
};

$tests['json aggregate distinct filter order current source next94 json subtype payloads keep distinct classes after order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT payload ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) AS payloads FROM wp_options',
        $tables,
    );

    $t->same('[{"name":"queue"},{"name":"rules"},{"name":"site"},{"name":"theme"},null]', $rows[0]['payloads']);
};

$tests['json aggregate distinct filter order current source next94 grouped asc desc variants do not collide'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) AS desc_names, json_group_array(DISTINCT option_name ORDER BY priority ASC, tie DESC) FILTER (WHERE enabled) AS asc_names FROM wp_options GROUP BY autoload ORDER BY autoload',
        $tables,
    );

    $t->same('["plugin_queue","plugin_rules","empty_option"]', $rows[0]['desc_names']);
    $t->same('["empty_option","plugin_rules","plugin_queue"]', $rows[0]['asc_names']);
    $t->same('["siteurl","theme_mods"]', $rows[1]['desc_names']);
    $t->same('["theme_mods","siteurl"]', $rows[1]['asc_names']);
};

$orderCases = [
    'priority desc tie asc' => ['priority DESC, tie ASC', '["plugin_queue","plugin_rules","siteurl","theme_mods","empty_option"]'],
    'priority desc tie desc' => ['priority DESC, tie DESC', '["plugin_rules","plugin_queue","theme_mods","siteurl","empty_option"]'],
    'priority asc tie asc' => ['priority ASC, tie ASC', '["empty_option","theme_mods","siteurl","plugin_rules","plugin_queue"]'],
    'priority asc tie desc' => ['priority ASC, tie DESC', '["empty_option","theme_mods","siteurl","plugin_rules","plugin_queue"]'],
    'tie asc priority desc' => ['tie ASC, priority DESC', '["plugin_queue","siteurl","plugin_rules","theme_mods","empty_option"]'],
    'tie desc priority asc' => ['tie DESC, priority ASC', '["theme_mods","plugin_rules","empty_option","siteurl","plugin_queue"]'],
    'autoload asc priority desc tie asc' => ['autoload ASC, priority DESC, tie ASC', '["plugin_queue","plugin_rules","empty_option","siteurl","theme_mods"]'],
    'autoload desc priority desc tie asc' => ['autoload DESC, priority DESC, tie ASC', '["siteurl","theme_mods","plugin_queue","plugin_rules","empty_option"]'],
];

foreach ($orderCases as $name => [$orderSql, $expected]) {
    $tests['json aggregate distinct filter order current source next94 order case ' . $name] = static function (TestRunner $t) use ($tables, $orderSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_group_array(DISTINCT option_name ORDER BY {$orderSql}) FILTER (WHERE enabled) AS names FROM wp_options",
            $tables,
        );

        $t->same($expected, $rows[0]['names']);
    };
}

$filterCases = [
    "enabled" => '["plugin_queue","plugin_rules","siteurl","theme_mods","empty_option"]',
    "autoload = 'no'" => '["plugin_queue","plugin_rules","empty_option"]',
    "autoload = 'yes'" => '["siteurl","theme_mods"]',
    "bucket = 'plugin'" => '["plugin_queue","plugin_rules"]',
    "bucket <> 'empty'" => '["plugin_queue","plugin_rules","siteurl","theme_mods"]',
    "priority >= 30" => '["plugin_queue","plugin_rules","siteurl","theme_mods"]',
    "priority IS NULL" => '["empty_option"]',
    "option_name LIKE 'plugin_%'" => '["plugin_queue","plugin_rules"]',
    "option_name GLOB '*option'" => '["empty_option"]',
    "option_id BETWEEN 2 AND 8" => '["plugin_queue","plugin_rules","siteurl","theme_mods","empty_option"]',
    "option_id NOT BETWEEN 2 AND 8" => '["theme_mods"]',
];

foreach ($filterCases as $filterSql => $expected) {
    $tests['json aggregate distinct filter order current source next94 filter ' . $filterSql] = static function (TestRunner $t) use ($tables, $filterSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled AND {$filterSql}) AS names FROM wp_options",
            $tables,
        );

        $t->same($expected, $rows[0]['names']);
    };
}

foreach ([
    'plugin_queue' => ['no'],
    'plugin_rules' => ['no'],
    'empty_option' => ['no'],
    'siteurl' => ['yes'],
    'theme_mods' => ['yes'],
    'blogname' => [],
    'queue before rules' => ['no'],
    'site before theme' => ['yes'],
] as $needle => $expectedAutoloads) {
    $tests['json aggregate distinct filter order current source next94 grouped having membership ' . $needle] = static function (TestRunner $t) use ($tables, $needle, $expectedAutoloads): void {
        $pattern = match ($needle) {
            'queue before rules' => '%plugin_queue%plugin_rules%',
            'site before theme' => '%siteurl%theme_mods%',
            default => '%' . $needle . '%',
        };
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) LIKE '{$pattern}' ORDER BY autoload",
            $tables,
        );

        $t->same($expectedAutoloads, array_column($rows, 'autoload'));
    };
}

foreach ([0, 1, 20, 30, 45, 50, 60] as $minimum) {
    $tests['json aggregate distinct filter order current source next94 grouped priority minimum ' . $minimum] = static function (TestRunner $t) use ($tables, $minimum): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled AND priority >= {$minimum}) AS names FROM wp_options GROUP BY autoload ORDER BY autoload",
            $tables,
        );

        $expectedNo = match ($minimum) {
            0, 1, 20, 30, 45, 50 => '["plugin_queue","plugin_rules"]',
            default => '[]',
        };
        $expectedYes = match ($minimum) {
            0, 1, 20 => '["siteurl","theme_mods"]',
            30 => '["siteurl","theme_mods"]',
            default => '[]',
        };
        $t->same($expectedNo, $rows[0]['names']);
        $t->same($expectedYes, $rows[1]['names']);
    };
}

$mixedTables = [
    'wp_options' => [
        ['option_id' => 1, 'value' => null, 'rank' => null, 'tie' => 'n', 'enabled' => 1],
        ['option_id' => 2, 'value' => 7, 'rank' => 2, 'tie' => 'b', 'enabled' => 1],
        ['option_id' => 3, 'value' => '7', 'rank' => 2, 'tie' => 'a', 'enabled' => 1],
        ['option_id' => 4, 'value' => 7, 'rank' => 3, 'tie' => 'z', 'enabled' => 1],
        ['option_id' => 5, 'value' => new SQLiteJsonSubtypeValue('{"n":7}'), 'rank' => 2, 'tie' => 'c', 'enabled' => 1],
        ['option_id' => 6, 'value' => false, 'rank' => 1, 'tie' => 'f', 'enabled' => 1],
        ['option_id' => 7, 'value' => 'skip', 'rank' => 4, 'tie' => 's', 'enabled' => 0],
    ],
];

$tests['json aggregate distinct filter order current source next94 mixed sqlite distinct classes'] = static function (TestRunner $t) use ($mixedTables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT value ORDER BY rank DESC, tie ASC) FILTER (WHERE enabled) AS values_json FROM wp_options',
        $mixedTables,
    );

    $t->same('[7,"7",{"n":7},0,null]', $rows[0]['values_json']);
};

$tests['json aggregate distinct filter order current source next94 mixed jsonb distinct classes'] = static function (TestRunner $t) use ($mixedTables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT jsonb_group_array(DISTINCT value ORDER BY rank DESC, tie ASC) FILTER (WHERE enabled) AS values_json FROM wp_options',
        $mixedTables,
    );

    $t->same([7, '7', ['n' => 7], 0, null], SQLiteJsonB::decode($rows[0]['values_json']->bytes));
};

$tests['json aggregate distinct filter order current source next94 rejects malformed second order direction'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY priority DESC, tie SIDEWAYS) FILTER (WHERE enabled) AS names FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate distinct filter order current source next94 accepts expression second order term'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY priority DESC, option_id + 1 ASC) FILTER (WHERE enabled) AS names FROM wp_options',
        $tables,
    );

    $t->same('["plugin_rules","plugin_queue","siteurl","theme_mods","empty_option"]', $rows[0]['names']);
};

return $tests;
