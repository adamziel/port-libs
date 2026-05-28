<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'score' => 10, 'enabled' => 1, 'option_name' => 'siteurl', 'payload' => new SQLiteJsonSubtypeValue('{"kind":"site"}')],
        ['option_id' => 2, 'autoload' => 'yes', 'score' => 30, 'enabled' => 1, 'option_name' => 'theme_mods', 'payload' => new SQLiteJsonSubtypeValue('{"kind":"theme"}')],
        ['option_id' => 3, 'autoload' => 'yes', 'score' => 20, 'enabled' => 1, 'option_name' => 'blogname', 'payload' => 'Blog'],
        ['option_id' => 4, 'autoload' => 'yes', 'score' => 25, 'enabled' => 0, 'option_name' => 'disabled_yes', 'payload' => 'off'],
        ['option_id' => 5, 'autoload' => 'no', 'score' => 50, 'enabled' => 1, 'option_name' => 'plugin_rules', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'rules']))],
        ['option_id' => 6, 'autoload' => 'no', 'score' => 40, 'enabled' => 1, 'option_name' => 'plugin_queue', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'queue']))],
        ['option_id' => 7, 'autoload' => 'no', 'score' => 35, 'enabled' => 0, 'option_name' => 'disabled_no', 'payload' => 'off'],
        ['option_id' => 8, 'autoload' => 'no', 'score' => 30, 'enabled' => 1, 'option_name' => 'plugin_rules', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'rules-refresh']))],
        ['option_id' => 9, 'autoload' => 'no', 'score' => 20, 'enabled' => 1, 'option_name' => null, 'payload' => null],
    ],
];

$rowsById = static function (string $sql) use ($tables): array {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $byId = [];
    foreach ($rows as $row) {
        $byId[$row['option_id']] = $row;
    }

    return $byId;
};

$arrayRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '["theme_mods","blogname"]',
    3 => '["blogname","siteurl"]',
    4 => '["blogname","siteurl"]',
    5 => '["plugin_rules","plugin_queue"]',
    6 => '["plugin_queue","plugin_rules"]',
    7 => '["plugin_rules",null]',
    8 => '["plugin_rules",null]',
    9 => '[null]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next104 array desc rows id ' . $optionId] = static function (TestRunner $t) use ($arrayRows, $optionId, $expected): void {
        $rows = $arrayRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$distinctRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(DISTINCT option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 4 FOLLOWING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    5 => '["plugin_rules","plugin_queue",null]',
    6 => '["plugin_queue","plugin_rules",null]',
    7 => '["plugin_rules",null]',
    8 => '["plugin_rules",null]',
    9 => '[null]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next104 distinct preserves descending source id ' . $optionId] = static function (TestRunner $t) use ($distinctRows, $optionId, $expected): void {
        $rows = $distinctRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$groupRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score DESC GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    2 => '["theme_mods"]',
    4 => '["blogname"]',
    3 => '["blogname","siteurl"]',
    1 => '["siteurl"]',
    5 => '["plugin_rules","plugin_queue"]',
    6 => '["plugin_queue"]',
    7 => '["plugin_rules"]',
    8 => '["plugin_rules",null]',
    9 => '[null]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next104 groups desc id ' . $optionId] = static function (TestRunner $t) use ($groupRows, $optionId, $expected): void {
        $rows = $groupRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$objectRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_object(option_name, payload) FILTER (WHERE enabled AND option_name IS NOT NULL) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS payloads FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '{"siteurl":{"kind":"site"}}',
    2 => '{"theme_mods":{"kind":"theme"},"blogname":"Blog"}',
    3 => '{"blogname":"Blog","siteurl":{"kind":"site"}}',
    4 => '{"blogname":"Blog","siteurl":{"kind":"site"}}',
    5 => '{"plugin_rules":{"kind":"rules"},"plugin_queue":{"kind":"queue"}}',
    6 => '{"plugin_queue":{"kind":"queue"},"plugin_rules":{"kind":"rules-refresh"}}',
    7 => '{"plugin_rules":{"kind":"rules-refresh"}}',
    8 => '{"plugin_rules":{"kind":"rules-refresh"}}',
    9 => '{}',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next104 object desc rows id ' . $optionId] = static function (TestRunner $t) use ($objectRows, $optionId, $expected): void {
        $rows = $objectRows();
        $t->same($expected, $rows[$optionId]['payloads']);
    };
}

$jsonbRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    2 => ['theme_mods', 'blogname'],
    3 => ['blogname', 'siteurl'],
    5 => ['plugin_rules', 'plugin_queue'],
    6 => ['plugin_queue', 'plugin_rules'],
    8 => ['plugin_rules', null],
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next104 jsonb desc rows id ' . $optionId] = static function (TestRunner $t) use ($jsonbRows, $optionId, $expected): void {
        $rows = $jsonbRows();
        $t->true($rows[$optionId]['names'] instanceof SQLiteBlobValue);
        $t->same($expected, SQLiteJsonB::decode($rows[$optionId]['names']->bytes));
    };
}

$excludeRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    2 => '["blogname"]',
    4 => '["blogname","siteurl"]',
    3 => '["siteurl"]',
    5 => '["plugin_queue"]',
    6 => '["plugin_rules"]',
    8 => '[null]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next104 exclude current preserves source order id ' . $optionId] = static function (TestRunner $t) use ($excludeRows, $optionId, $expected): void {
        $rows = $excludeRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$ascRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score ASC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl","blogname"]',
    3 => '["blogname","theme_mods"]',
    2 => '["theme_mods"]',
    9 => '[null,"plugin_rules"]',
    8 => '["plugin_rules","plugin_queue"]',
    6 => '["plugin_queue","plugin_rules"]',
    5 => '["plugin_rules"]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next104 asc rows stays source order id ' . $optionId] = static function (TestRunner $t) use ($ascRows, $optionId, $expected): void {
        $rows = $ascRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$tests['json aggregate window filter order current source next104 aggregate order still overrides source order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_array(option_name ORDER BY option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS names FROM wp_options ORDER BY option_id',
        $tables,
    );

    $t->same('["blogname","theme_mods"]', $rows[1]['names']);
    $t->same('["blogname","siteurl"]', $rows[2]['names']);
    $t->same('["plugin_queue","plugin_rules"]', $rows[4]['names']);
    $t->same('["plugin_queue","plugin_rules"]', $rows[5]['names']);
};

$tests['json aggregate window filter order current source next104 final order can use source ordered output'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_array(option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS names FROM wp_options ORDER BY names DESC, option_id',
        $tables,
    );

    $t->same([9, 2, 1, 7, 8, 5, 6, 3, 4], array_column($rows, 'option_id'));
};

return $tests;
