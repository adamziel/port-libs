<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'bucket' => 'autoload', 'priority' => 20, 'enabled' => 1, 'option_name' => 'siteurl', 'payload' => 'site'],
        ['option_id' => 2, 'autoload' => 'yes', 'bucket' => 'autoload', 'priority' => 10, 'enabled' => 1, 'option_name' => 'home', 'payload' => 'home'],
        ['option_id' => 3, 'autoload' => 'yes', 'bucket' => 'network', 'priority' => 30, 'enabled' => 1, 'option_name' => 'blogname', 'payload' => 'blog'],
        ['option_id' => 4, 'autoload' => 'yes', 'bucket' => 'network', 'priority' => 5, 'enabled' => 0, 'option_name' => 'disabled_network', 'payload' => 'off'],
        ['option_id' => 5, 'autoload' => 'yes', 'bucket' => 'theme', 'priority' => 40, 'enabled' => 1, 'option_name' => 'theme_mods', 'payload' => 'theme'],
        ['option_id' => 6, 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 50, 'enabled' => 1, 'option_name' => 'plugin_rules', 'payload' => 'rules'],
        ['option_id' => 7, 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 45, 'enabled' => 1, 'option_name' => 'plugin_queue', 'payload' => 'queue'],
        ['option_id' => 8, 'autoload' => 'no', 'bucket' => 'transient', 'priority' => 35, 'enabled' => 1, 'option_name' => '_transient_feed', 'payload' => 'feed'],
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

$defaultRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY priority DESC) OVER (PARTITION BY autoload ORDER BY bucket) AS summary FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl","home"]',
    2 => '["siteurl","home"]',
    3 => '["blogname","siteurl","home","disabled_network"]',
    4 => '["blogname","siteurl","home","disabled_network"]',
    5 => '["theme_mods","blogname","siteurl","home","disabled_network"]',
    6 => '["plugin_rules","plugin_queue"]',
    7 => '["plugin_rules","plugin_queue"]',
    8 => '["plugin_rules","plugin_queue","_transient_feed"]',
] as $optionId => $expected) {
    $tests['json aggregate text range window current default cumulative id ' . $optionId] = static function (TestRunner $t) use ($defaultRows, $optionId, $expected): void {
        $rows = $defaultRows();
        $t->same($expected, $rows[$optionId]['summary']);
    };
}

$filteredRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY priority DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY bucket RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS summary FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl","home"]',
    2 => '["siteurl","home"]',
    3 => '["blogname","siteurl","home"]',
    4 => '["blogname","siteurl","home"]',
    5 => '["theme_mods","blogname","siteurl","home"]',
    6 => '["plugin_rules","plugin_queue"]',
    8 => '["plugin_rules","plugin_queue","_transient_feed"]',
] as $optionId => $expected) {
    $tests['json aggregate text range window current filtered explicit cumulative id ' . $optionId] = static function (TestRunner $t) use ($filteredRows, $optionId, $expected): void {
        $rows = $filteredRows();
        $t->same($expected, $rows[$optionId]['summary']);
    };
}

$followingRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY priority DESC) OVER (PARTITION BY autoload ORDER BY bucket RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS summary FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["theme_mods","blogname","siteurl","home","disabled_network"]',
    3 => '["theme_mods","blogname","disabled_network"]',
    5 => '["theme_mods"]',
    6 => '["plugin_rules","plugin_queue","_transient_feed"]',
    8 => '["_transient_feed"]',
] as $optionId => $expected) {
    $tests['json aggregate text range window current unbounded following id ' . $optionId] = static function (TestRunner $t) use ($followingRows, $optionId, $expected): void {
        $rows = $followingRows();
        $t->same($expected, $rows[$optionId]['summary']);
    };
}

$currentRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY priority DESC) OVER (PARTITION BY autoload ORDER BY bucket RANGE BETWEEN CURRENT ROW AND CURRENT ROW) AS summary FROM wp_options ORDER BY option_id',
);

$tests['json aggregate text range window current bounded current row keeps peer group only'] = static function (TestRunner $t) use ($currentRows): void {
    $rows = $currentRows();
    $t->same('["siteurl","home"]', $rows[1]['summary']);
    $t->same('["blogname","disabled_network"]', $rows[3]['summary']);
    $t->same('["theme_mods"]', $rows[5]['summary']);
};

$jsonbRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(option_name ORDER BY priority DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY bucket RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS summary FROM wp_options ORDER BY option_id',
);

$tests['json aggregate text range window current jsonb cumulative frame'] = static function (TestRunner $t) use ($jsonbRows): void {
    $rows = $jsonbRows();
    $t->true($rows[5]['summary'] instanceof SQLiteBlobValue);
    $t->same(['theme_mods', 'blogname', 'siteurl', 'home'], SQLiteJsonB::decode($rows[5]['summary']->bytes));
};

$objectRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_object(option_name, payload ORDER BY priority DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY bucket RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS summary FROM wp_options ORDER BY option_id',
);

$tests['json aggregate text range window current object cumulative frame'] = static function (TestRunner $t) use ($objectRows): void {
    $rows = $objectRows();
    $t->same('{"theme_mods":"theme","blogname":"blog","siteurl":"site","home":"home"}', $rows[5]['summary']);
    $t->same('{"plugin_rules":"rules","plugin_queue":"queue","_transient_feed":"feed"}', $rows[8]['summary']);
};

$tests['json aggregate text range window current plan keeps range unbounded frame'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan(
        'SELECT json_group_array(option_name) OVER (PARTITION BY autoload ORDER BY bucket RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS summary FROM wp_options',
        $tables,
    );

    $t->same('RANGE', $plan['select'][0]['frame']['unit']);
    $t->true(is_infinite($plan['select'][0]['frame']['preceding']));
    $t->same(0, $plan['select'][0]['frame']['following']);
};

return $tests;
