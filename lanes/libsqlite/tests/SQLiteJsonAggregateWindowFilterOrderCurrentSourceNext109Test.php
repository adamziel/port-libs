<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'score' => null, 'enabled' => 1, 'option_name' => 'siteurl', 'payload' => 'site'],
        ['option_id' => 2, 'autoload' => 'yes', 'score' => 30, 'enabled' => 1, 'option_name' => 'theme_mods', 'payload' => 'theme'],
        ['option_id' => 3, 'autoload' => 'yes', 'score' => 20, 'enabled' => 1, 'option_name' => 'blogname', 'payload' => 'blog'],
        ['option_id' => 4, 'autoload' => 'yes', 'score' => null, 'enabled' => 0, 'option_name' => 'disabled_yes', 'payload' => 'off'],
        ['option_id' => 5, 'autoload' => 'no', 'score' => null, 'enabled' => 1, 'option_name' => 'plugin_rules', 'payload' => 'rules'],
        ['option_id' => 6, 'autoload' => 'no', 'score' => 40, 'enabled' => 1, 'option_name' => 'plugin_queue', 'payload' => 'queue'],
        ['option_id' => 7, 'autoload' => 'no', 'score' => 35, 'enabled' => 0, 'option_name' => 'disabled_no', 'payload' => 'off'],
        ['option_id' => 8, 'autoload' => 'no', 'score' => 30, 'enabled' => 1, 'option_name' => 'plugin_rules', 'payload' => 'rules-refresh'],
        ['option_id' => 9, 'autoload' => 'no', 'score' => null, 'enabled' => 1, 'option_name' => null, 'payload' => null],
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

$ascNullsLastRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY score ASC NULLS LAST, option_id DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '["theme_mods","siteurl"]',
    3 => '["blogname","theme_mods","siteurl"]',
    4 => '["blogname","theme_mods","siteurl"]',
    5 => '["plugin_rules"]',
    6 => '["plugin_queue","plugin_rules"]',
    8 => '["plugin_rules","plugin_queue","plugin_rules"]',
    9 => '["plugin_rules","plugin_queue",null,"plugin_rules"]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next109 array asc nulls last id ' . $optionId] = static function (TestRunner $t) use ($ascNullsLastRows, $optionId, $expected): void {
        $rows = $ascNullsLastRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$ascNullsFirstRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY score ASC NULLS FIRST, option_id) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    2 => '["siteurl","theme_mods"]',
    3 => '["siteurl","blogname","theme_mods"]',
    4 => '["siteurl","blogname","theme_mods"]',
    6 => '["plugin_rules","plugin_queue"]',
    8 => '["plugin_rules","plugin_rules","plugin_queue"]',
    9 => '["plugin_rules",null,"plugin_rules","plugin_queue"]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next109 array asc nulls first id ' . $optionId] = static function (TestRunner $t) use ($ascNullsFirstRows, $optionId, $expected): void {
        $rows = $ascNullsFirstRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$descNullsFirstRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY score DESC NULLS FIRST, option_id) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    2 => '["siteurl","theme_mods"]',
    3 => '["siteurl","theme_mods","blogname"]',
    4 => '["siteurl","theme_mods","blogname"]',
    6 => '["plugin_rules","plugin_queue"]',
    8 => '["plugin_rules","plugin_queue","plugin_rules"]',
    9 => '["plugin_rules",null,"plugin_queue","plugin_rules"]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next109 array desc nulls first id ' . $optionId] = static function (TestRunner $t) use ($descNullsFirstRows, $optionId, $expected): void {
        $rows = $descNullsFirstRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$descNullsLastRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY score DESC NULLS LAST, option_id DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    2 => '["theme_mods","siteurl"]',
    3 => '["theme_mods","blogname","siteurl"]',
    4 => '["theme_mods","blogname","siteurl"]',
    6 => '["plugin_queue","plugin_rules"]',
    8 => '["plugin_queue","plugin_rules","plugin_rules"]',
    9 => '["plugin_queue","plugin_rules",null,"plugin_rules"]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next109 array desc nulls last id ' . $optionId] = static function (TestRunner $t) use ($descNullsLastRows, $optionId, $expected): void {
        $rows = $descNullsLastRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$distinctRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY score ASC NULLS FIRST, option_id DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    3 => '["siteurl","blogname","theme_mods"]',
    4 => '["siteurl","blogname","theme_mods"]',
    8 => '["plugin_rules","plugin_queue"]',
    9 => '[null,"plugin_rules","plugin_queue"]',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next109 distinct nulls first id ' . $optionId] = static function (TestRunner $t) use ($distinctRows, $optionId, $expected): void {
        $rows = $distinctRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$jsonbRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(option_name ORDER BY score ASC NULLS LAST, option_id DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    3 => ['blogname', 'theme_mods', 'siteurl'],
    8 => ['plugin_rules', 'plugin_queue', 'plugin_rules'],
    9 => ['plugin_rules', 'plugin_queue', null, 'plugin_rules'],
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next109 jsonb nulls last id ' . $optionId] = static function (TestRunner $t) use ($jsonbRows, $optionId, $expected): void {
        $rows = $jsonbRows();
        $t->true($rows[$optionId]['names'] instanceof SQLiteBlobValue);
        $t->same($expected, SQLiteJsonB::decode($rows[$optionId]['names']->bytes));
    };
}

$jsonbNullsFirstRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(option_name ORDER BY score DESC NULLS FIRST, option_id) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    3 => ['siteurl', 'theme_mods', 'blogname'],
    8 => ['plugin_rules', 'plugin_queue', 'plugin_rules'],
    9 => ['plugin_rules', null, 'plugin_queue', 'plugin_rules'],
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next109 jsonb nulls first id ' . $optionId] = static function (TestRunner $t) use ($jsonbNullsFirstRows, $optionId, $expected): void {
        $rows = $jsonbNullsFirstRows();
        $t->true($rows[$optionId]['names'] instanceof SQLiteBlobValue);
        $t->same($expected, SQLiteJsonB::decode($rows[$optionId]['names']->bytes));
    };
}

$objectRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_object(option_name, payload ORDER BY score ASC NULLS LAST, option_id DESC) FILTER (WHERE enabled AND option_name IS NOT NULL) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) AS payloads FROM wp_options ORDER BY option_id',
);

foreach ([
    3 => '{"blogname":"blog","theme_mods":"theme","siteurl":"site"}',
    4 => '{"blogname":"blog","theme_mods":"theme","siteurl":"site"}',
    8 => '{"plugin_rules":"rules-refresh","plugin_queue":"queue","plugin_rules":"rules"}',
    9 => '{"plugin_rules":"rules-refresh","plugin_queue":"queue","plugin_rules":"rules"}',
] as $optionId => $expected) {
    $tests['json aggregate window filter order current source next109 object nulls last id ' . $optionId] = static function (TestRunner $t) use ($objectRows, $optionId, $expected): void {
        $rows = $objectRows();
        $t->same($expected, $rows[$optionId]['payloads']);
    };
}

$tests['json aggregate window filter order current source next109 rejects malformed nulls modifier'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(option_name ORDER BY score NULLS MIDDLE) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS names FROM wp_options',
        $tables,
    ));
};

return $tests;
