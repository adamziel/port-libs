<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'score' => 10, 'bucket' => 1, 'enabled' => 1, 'option_name' => 'siteurl', 'payload' => 'site'],
        ['option_id' => 2, 'autoload' => 'yes', 'score' => 20, 'bucket' => 1, 'enabled' => 0, 'option_name' => 'disabled_yes', 'payload' => 'off'],
        ['option_id' => 3, 'autoload' => 'yes', 'score' => 30, 'bucket' => 2, 'enabled' => 1, 'option_name' => 'blogname', 'payload' => 'blog'],
        ['option_id' => 4, 'autoload' => 'yes', 'score' => 40, 'bucket' => 3, 'enabled' => 1, 'option_name' => 'theme_mods', 'payload' => 'theme'],
        ['option_id' => 5, 'autoload' => 'no', 'score' => 10, 'bucket' => 1, 'enabled' => 1, 'option_name' => 'plugin_rules', 'payload' => 'rules'],
        ['option_id' => 6, 'autoload' => 'no', 'score' => 20, 'bucket' => 1, 'enabled' => 1, 'option_name' => 'plugin_queue', 'payload' => 'queue'],
        ['option_id' => 7, 'autoload' => 'no', 'score' => 30, 'bucket' => 2, 'enabled' => 0, 'option_name' => 'disabled_no', 'payload' => 'off'],
        ['option_id' => 8, 'autoload' => 'no', 'score' => 40, 'bucket' => 3, 'enabled' => 1, 'option_name' => 'rewrite_rules', 'payload' => 'rewrite'],
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

$currentRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '[]',
    3 => '["blogname"]',
    4 => '["theme_mods"]',
    5 => '["plugin_rules"]',
    6 => '["plugin_queue"]',
    7 => '[]',
    8 => '["rewrite_rules"]',
] as $optionId => $expected) {
    $tests['json aggregate filter window frame current source next117 rows current row id ' . $optionId] = static function (TestRunner $t) use ($currentRows, $optionId, $expected): void {
        $rows = $currentRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$precedingRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY score DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS 2 PRECEDING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '["siteurl"]',
    3 => '["blogname","siteurl"]',
    4 => '["theme_mods","blogname"]',
    5 => '["plugin_rules"]',
    6 => '["plugin_queue","plugin_rules"]',
    7 => '["plugin_queue","plugin_rules"]',
    8 => '["rewrite_rules","plugin_queue"]',
] as $optionId => $expected) {
    $tests['json aggregate filter window frame current source next117 rows two preceding id ' . $optionId] = static function (TestRunner $t) use ($precedingRows, $optionId, $expected): void {
        $rows = $precedingRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$unboundedRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY score ASC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS UNBOUNDED PRECEDING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '["siteurl"]',
    3 => '["siteurl","blogname"]',
    4 => '["siteurl","blogname","theme_mods"]',
    5 => '["plugin_rules"]',
    6 => '["plugin_rules","plugin_queue"]',
    7 => '["plugin_rules","plugin_queue"]',
    8 => '["plugin_rules","plugin_queue","rewrite_rules"]',
] as $optionId => $expected) {
    $tests['json aggregate filter window frame current source next117 rows unbounded preceding id ' . $optionId] = static function (TestRunner $t) use ($unboundedRows, $optionId, $expected): void {
        $rows = $unboundedRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$rangeRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY option_id) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score RANGE CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '[]',
    3 => '["blogname"]',
    4 => '["theme_mods"]',
    5 => '["plugin_rules"]',
    6 => '["plugin_queue"]',
    7 => '[]',
    8 => '["rewrite_rules"]',
] as $optionId => $expected) {
    $tests['json aggregate filter window frame current source next117 range current row id ' . $optionId] = static function (TestRunner $t) use ($rangeRows, $optionId, $expected): void {
        $rows = $rangeRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$groupsRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY option_id) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY bucket GROUPS CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '["siteurl"]',
    3 => '["blogname"]',
    4 => '["theme_mods"]',
    5 => '["plugin_rules","plugin_queue"]',
    6 => '["plugin_rules","plugin_queue"]',
    7 => '[]',
    8 => '["rewrite_rules"]',
] as $optionId => $expected) {
    $tests['json aggregate filter window frame current source next117 groups current row id ' . $optionId] = static function (TestRunner $t) use ($groupsRows, $optionId, $expected): void {
        $rows = $groupsRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$unboundedFollowingRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(option_name ORDER BY option_id) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl","blogname","theme_mods"]',
    2 => '["blogname","theme_mods"]',
    3 => '["blogname","theme_mods"]',
    4 => '["theme_mods"]',
    5 => '["plugin_rules","plugin_queue","rewrite_rules"]',
    6 => '["plugin_queue","rewrite_rules"]',
    7 => '["rewrite_rules"]',
    8 => '["rewrite_rules"]',
] as $optionId => $expected) {
    $tests['json aggregate filter window frame current source next117 rows unbounded following id ' . $optionId] = static function (TestRunner $t) use ($unboundedFollowingRows, $optionId, $expected): void {
        $rows = $unboundedFollowingRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$jsonbRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(option_name ORDER BY option_id) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => ['siteurl'],
    2 => [],
    5 => ['plugin_rules'],
    7 => [],
] as $optionId => $expected) {
    $tests['json aggregate filter window frame current source next117 jsonb rows current id ' . $optionId] = static function (TestRunner $t) use ($jsonbRows, $optionId, $expected): void {
        $rows = $jsonbRows();
        $t->true($rows[$optionId]['names'] instanceof SQLiteBlobValue);
        $t->same($expected, SQLiteJsonB::decode($rows[$optionId]['names']->bytes));
    };
}

$objectRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_object(option_name, payload ORDER BY option_id) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS CURRENT ROW) AS payloads FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '{"siteurl":"site"}',
    2 => '{}',
    5 => '{"plugin_rules":"rules"}',
    7 => '{}',
] as $optionId => $expected) {
    $tests['json aggregate filter window frame current source next117 object rows current id ' . $optionId] = static function (TestRunner $t) use ($objectRows, $optionId, $expected): void {
        $rows = $objectRows();
        $t->same($expected, $rows[$optionId]['payloads']);
    };
}

$tests['json aggregate filter window frame current source next117 rejects start following shorthand'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(option_name) FILTER (WHERE enabled) OVER (ORDER BY option_id ROWS 1 FOLLOWING) AS names FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate filter window frame current source next117 rejects end unbounded preceding'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(option_name) FILTER (WHERE enabled) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND UNBOUNDED PRECEDING) AS names FROM wp_options',
        $tables,
    ));
};

return $tests;
