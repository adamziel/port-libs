<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bucket' => 'core', 'priority' => 30],
        ['option_id' => 2, 'option_name' => 'blogname', 'option_value' => 'Port Fixture', 'autoload' => 'yes', 'bucket' => 'core', 'priority' => 20],
        ['option_id' => 3, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bucket' => 'core', 'priority' => 10],
        ['option_id' => 4, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 80],
        ['option_id' => 5, 'option_name' => 'plugin_queue', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2])), 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 70],
        ['option_id' => 6, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 60],
        ['option_id' => 7, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'bucket' => 'empty', 'priority' => 0],
        ['option_id' => 8, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'bucket' => 'empty', 'priority' => -1],
    ],
];

$tests['json aggregate order distinct current source next86 desc aggregate chooses highest duplicate first'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY option_id DESC) AS names FROM wp_options',
        $tables,
    );

    $t->same('["empty_option","plugin_rules","plugin_queue","siteurl","blogname"]', $rows[0]['names']);
};

$tests['json aggregate order distinct current source next86 asc keyword matches existing ascending behavior'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY option_id ASC) AS names FROM wp_options',
        $tables,
    );

    $t->same('["siteurl","blogname","plugin_rules","plugin_queue","empty_option"]', $rows[0]['names']);
};

$tests['json aggregate order distinct current source next86 grouped desc summaries stay independent'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY option_id DESC) AS names FROM wp_options GROUP BY autoload ORDER BY autoload',
        $tables,
    );

    $t->same('["empty_option","plugin_rules","plugin_queue"]', $rows[0]['names']);
    $t->same('["siteurl","blogname"]', $rows[1]['names']);
};

$tests['json aggregate order distinct current source next86 filter precedes desc distinct admission'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(DISTINCT option_name ORDER BY option_id DESC) FILTER (WHERE autoload = 'no') AS names FROM wp_options",
        $tables,
    );

    $t->same('["empty_option","plugin_rules","plugin_queue"]', $rows[0]['names']);
};

$tests['json aggregate order distinct current source next86 having can read desc hidden summary'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY option_id DESC) LIKE '[\"empty_option\"%'",
        $tables,
    );

    $t->same([['autoload' => 'no']], $rows);
};

$tests['json aggregate order distinct current source next86 final order distinguishes asc and desc summaries'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(DISTINCT option_name ORDER BY option_id DESC)',
        $tables,
    );

    $t->same(['no', 'yes'], array_map(static fn (array $row): mixed => $row['autoload'], $rows));
};

$tests['json aggregate order distinct current source next86 jsonb desc decodes in sorted distinct order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT jsonb_group_array(DISTINCT option_name ORDER BY option_id DESC) AS names FROM wp_options',
        $tables,
    );

    $t->true($rows[0]['names'] instanceof SQLiteBlobValue);
    $t->same(['empty_option', 'plugin_rules', 'plugin_queue', 'siteurl', 'blogname'], SQLiteJsonB::decode($rows[0]['names']->bytes));
};

$tests['json aggregate order distinct current source next86 desc json subtype distinct collapses payloads'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(DISTINCT option_value ORDER BY option_id DESC) FILTER (WHERE option_name = 'plugin_rules') AS payloads FROM wp_options",
        $tables,
    );

    $t->same('[[{"name":"seo"},{"name":"cache"}]]', $rows[0]['payloads']);
};

$tests['json aggregate order distinct current source next86 desc null distinct collapses payloads'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(DISTINCT option_value ORDER BY option_id DESC) FILTER (WHERE option_name = 'empty_option') AS payloads FROM wp_options",
        $tables,
    );

    $t->same('[null]', $rows[0]['payloads']);
};

$tests['json aggregate order distinct current source next86 asc and desc selected together do not collide'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY option_id ASC) AS asc_names, json_group_array(DISTINCT option_name ORDER BY option_id DESC) AS desc_names FROM wp_options',
        $tables,
    );

    $t->same('["siteurl","blogname","plugin_rules","plugin_queue","empty_option"]', $rows[0]['asc_names']);
    $t->same('["empty_option","plugin_rules","plugin_queue","siteurl","blogname"]', $rows[0]['desc_names']);
};

$tests['json aggregate order distinct current source next86 grouped asc and desc selected together do not collide'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY option_id ASC) AS asc_names, json_group_array(DISTINCT option_name ORDER BY option_id DESC) AS desc_names FROM wp_options GROUP BY autoload ORDER BY autoload',
        $tables,
    );

    $t->same('["plugin_rules","plugin_queue","empty_option"]', $rows[0]['asc_names']);
    $t->same('["empty_option","plugin_rules","plugin_queue"]', $rows[0]['desc_names']);
    $t->same('["siteurl","blogname"]', $rows[1]['asc_names']);
    $t->same('["siteurl","blogname"]', $rows[1]['desc_names']);
};

$directionCases = [
    'priority descending keeps plugin before core' => ['priority DESC', '["plugin_rules","plugin_queue","siteurl","blogname","empty_option"]'],
    'priority ascending keeps empty before core' => ['priority ASC', '["empty_option","siteurl","blogname","plugin_rules","plugin_queue"]'],
    'name descending sorts text keys' => ['option_name DESC', '["siteurl","plugin_rules","plugin_queue","empty_option","blogname"]'],
    'name ascending sorts text keys' => ['option_name ASC', '["blogname","empty_option","plugin_queue","plugin_rules","siteurl"]'],
    'autoload descending keeps yes rows first' => ['autoload DESC', '["siteurl","blogname","plugin_rules","plugin_queue","empty_option"]'],
    'autoload ascending keeps no rows first' => ['autoload ASC', '["plugin_rules","plugin_queue","empty_option","siteurl","blogname"]'],
];

foreach ($directionCases as $name => [$orderSql, $expected]) {
    $tests['json aggregate order distinct current source next86 ' . $name] = static function (TestRunner $t) use ($tables, $orderSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_group_array(DISTINCT option_name ORDER BY {$orderSql}) AS names FROM wp_options",
            $tables,
        );

        $t->same($expected, $rows[0]['names']);
    };
}

$filterCases = [
    "autoload = 'no'" => '["empty_option","plugin_rules","plugin_queue"]',
    "autoload = 'yes'" => '["siteurl","blogname"]',
    "bucket = 'plugin'" => '["plugin_rules","plugin_queue"]',
    "option_value IS NULL" => '["empty_option"]',
    "priority >= 20" => '["plugin_rules","plugin_queue","blogname","siteurl"]',
    "priority < 20" => '["empty_option","siteurl"]',
    "option_name LIKE 'plugin_%'" => '["plugin_rules","plugin_queue"]',
    "option_name GLOB '*option'" => '["empty_option"]',
    "option_id BETWEEN 2 AND 6" => '["plugin_rules","plugin_queue","siteurl","blogname"]',
    "option_id NOT BETWEEN 2 AND 6" => '["empty_option","siteurl"]',
];

foreach ($filterCases as $filterSql => $expected) {
    $tests['json aggregate order distinct current source next86 desc filter ' . $filterSql] = static function (TestRunner $t) use ($tables, $filterSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_group_array(DISTINCT option_name ORDER BY option_id DESC) FILTER (WHERE {$filterSql}) AS names FROM wp_options",
            $tables,
        );

        $t->same($expected, $rows[0]['names']);
    };
}

foreach (['siteurl', 'blogname', 'plugin_rules', 'plugin_queue', 'empty_option'] as $optionName) {
    $tests['json aggregate order distinct current source next86 grouped having desc membership ' . $optionName] = static function (TestRunner $t) use ($tables, $optionName): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY option_id DESC) LIKE '%{$optionName}%' ORDER BY autoload",
            $tables,
        );

        $expected = in_array($optionName, ['siteurl', 'blogname'], true) ? ['yes'] : ['no'];
        $t->same($expected, array_map(static fn (array $row): mixed => $row['autoload'], $rows));
    };
}

foreach ([0, 10, 20, 30, 60, 70, 80] as $minimum) {
    $tests['json aggregate order distinct current source next86 grouped filter priority desc ' . $minimum] = static function (TestRunner $t) use ($tables, $minimum): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority DESC) FILTER (WHERE priority >= {$minimum}) AS names FROM wp_options GROUP BY autoload ORDER BY autoload",
            $tables,
        );

        $expectedNo = match ($minimum) {
            0 => '["plugin_rules","plugin_queue","empty_option"]',
            10, 20, 30, 60 => '["plugin_rules","plugin_queue"]',
            70 => '["plugin_rules","plugin_queue"]',
            80 => '["plugin_rules"]',
            default => '[]',
        };
        $expectedYes = match ($minimum) {
            0, 10 => '["siteurl","blogname"]',
            20 => '["siteurl","blogname"]',
            30 => '["siteurl"]',
            default => '[]',
        };
        $t->same($expectedNo, $rows[0]['names']);
        $t->same($expectedYes, $rows[1]['names']);
    };
}

foreach ([
    'empty_option' => ['no'],
    'plugin_rules' => ['no'],
    'plugin_queue' => ['no'],
    'siteurl' => ['yes'],
    'blogname' => ['yes'],
    'missing' => [],
    'core desc pair' => ['yes'],
    'plugin desc pair' => ['no'],
] as $needle => $expectedAutoloads) {
    $tests['json aggregate order distinct current source next86 desc having pattern ' . $needle] = static function (TestRunner $t) use ($tables, $needle, $expectedAutoloads): void {
        $pattern = match ($needle) {
            'core desc pair' => '%siteurl%blogname%',
            'plugin desc pair' => '%plugin_rules%plugin_queue%',
            default => '%' . $needle . '%',
        };
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY priority DESC) LIKE '{$pattern}' ORDER BY autoload",
            $tables,
        );

        $t->same($expectedAutoloads, array_map(static fn (array $row): mixed => $row['autoload'], $rows));
    };
}

$mixedTypeTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'n', 'option_value' => null, 'rank' => 1],
        ['option_id' => 2, 'option_name' => 'i', 'option_value' => 7, 'rank' => 2],
        ['option_id' => 3, 'option_name' => 's', 'option_value' => '7', 'rank' => 3],
        ['option_id' => 4, 'option_name' => 't', 'option_value' => 'seven', 'rank' => 4],
        ['option_id' => 5, 'option_name' => 'i2', 'option_value' => 7, 'rank' => 5],
    ],
];

$tests['json aggregate order distinct current source next86 desc mixed values keep sqlite distinct classes'] = static function (TestRunner $t) use ($mixedTypeTables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_value ORDER BY rank DESC) AS values_json FROM wp_options',
        $mixedTypeTables,
    );

    $t->same('[7,"seven","7",null]', $rows[0]['values_json']);
};

$tests['json aggregate order distinct current source next86 jsonb desc mixed values keep sqlite distinct classes'] = static function (TestRunner $t) use ($mixedTypeTables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT jsonb_group_array(DISTINCT option_value ORDER BY rank DESC) AS values_json FROM wp_options',
        $mixedTypeTables,
    );

    $t->same([7, 'seven', '7', null], SQLiteJsonB::decode($rows[0]['values_json']->bytes));
};

$tests['json aggregate order distinct current source next86 rejects malformed direction token'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY option_id SIDEWAYS) AS names FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate order distinct current source next86 accepts desc expression order term'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY option_id + 1 DESC) AS names FROM wp_options',
        $tables,
    );

    $t->same('["empty_option","plugin_rules","plugin_queue","siteurl","blogname"]', $rows[0]['names']);
};

return $tests;
