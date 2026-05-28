<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$jsonbPlugin = new SQLiteBlobValue(SQLiteJsonB::encode(['source' => 'plugin', 'enabled' => true]));
$jsonbTheme = new SQLiteBlobValue(SQLiteJsonB::encode(['source' => 'theme', 'enabled' => true]));

$helperRows = [
    ['siteurl', 'https://example.test', 1, 1],
    ['blogname', 'Port Libs', 2, 1],
    ['siteurl', 'https://example.test', 3, 1],
    ['plugin_rules', $jsonbPlugin, 4, 1],
    ['plugin_rules', $jsonbPlugin, 5, 1],
    ['theme_rules', $jsonbTheme, 6, 0],
    ['empty_option', null, 7, 1],
];

$helperRowsFactory = static fn (): array => $helperRows;

$helperFrames = static fn (): array => SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRows(
    $helperRowsFactory(),
    1,
    2,
);

foreach ([
    0 => '{"siteurl":"https://example.test","blogname":"Port Libs"}',
    1 => '{"siteurl":"https://example.test","blogname":"Port Libs","plugin_rules":{"source":"plugin","enabled":true}}',
    2 => '{"blogname":"Port Libs","siteurl":"https://example.test","plugin_rules":{"source":"plugin","enabled":true}}',
    3 => '{"siteurl":"https://example.test","plugin_rules":{"source":"plugin","enabled":true}}',
    4 => '{"plugin_rules":{"source":"plugin","enabled":true},"empty_option":null}',
    5 => '{"plugin_rules":{"source":"plugin","enabled":true},"empty_option":null}',
    6 => '{"empty_option":null}',
] as $index => $expected) {
    $tests['json aggregate distinct filter window current source next112 helper rows frame ' . $index] = static function (TestRunner $t) use ($helperFrames, $index, $expected): void {
        $frames = $helperFrames();
        $t->same($expected, $frames[$index]);
    };
}

$helperGroupsFrames = static fn (): array => SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRowsByUnit(
    [
        ['siteurl', 'https://example.test', 10, 1],
        ['blogname', 'Port Libs', 10, 1],
        ['siteurl', 'https://example.test', 20, 1],
        ['plugin_rules', $jsonbPlugin, 20, 1],
        ['theme_rules', $jsonbTheme, 30, 1],
    ],
    'GROUPS',
    0,
    1,
    'TIES',
);

foreach ([
    0 => '{"siteurl":"https://example.test","plugin_rules":{"source":"plugin","enabled":true}}',
    1 => '{"blogname":"Port Libs","siteurl":"https://example.test","plugin_rules":{"source":"plugin","enabled":true}}',
    2 => '{"siteurl":"https://example.test","theme_rules":{"source":"theme","enabled":true}}',
    3 => '{"plugin_rules":{"source":"plugin","enabled":true},"theme_rules":{"source":"theme","enabled":true}}',
    4 => '{"theme_rules":{"source":"theme","enabled":true}}',
] as $index => $expected) {
    $tests['json aggregate distinct filter window current source next112 helper groups exclude ties ' . $index] = static function (TestRunner $t) use ($helperGroupsFrames, $index, $expected): void {
        $frames = $helperGroupsFrames();
        $t->same($expected, $frames[$index]);
    };
}

$helperRangeFrames = static fn (): array => SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRowsByUnit(
    [
        ['siteurl', 'https://example.test', 10, 1],
        ['siteurl', 'https://example.test', 11, 1],
        ['blogname', 'Port Libs', 12, 1],
        ['plugin_rules', $jsonbPlugin, 18, 1],
        ['theme_rules', $jsonbTheme, 19, 0],
    ],
    'RANGE',
    2,
    0,
);

foreach ([
    0 => '{"siteurl":"https://example.test"}',
    1 => '{"siteurl":"https://example.test"}',
    2 => '{"siteurl":"https://example.test","blogname":"Port Libs"}',
    3 => '{"plugin_rules":{"source":"plugin","enabled":true}}',
    4 => '{"plugin_rules":{"source":"plugin","enabled":true}}',
] as $index => $expected) {
    $tests['json aggregate distinct filter window current source next112 helper range filter ' . $index] = static function (TestRunner $t) use ($helperRangeFrames, $index, $expected): void {
        $frames = $helperRangeFrames();
        $t->same($expected, $frames[$index]);
    };
}

$jsonbFrames = static fn (): array => SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRowsSqlFunction(
    'jsonb_group_object',
    $helperRowsFactory(),
    1,
    1,
);

foreach ([
    0 => ['siteurl' => 'https://example.test', 'blogname' => 'Port Libs'],
    2 => ['blogname' => 'Port Libs', 'siteurl' => 'https://example.test', 'plugin_rules' => ['source' => 'plugin', 'enabled' => true]],
    5 => ['plugin_rules' => ['source' => 'plugin', 'enabled' => true], 'empty_option' => null],
] as $index => $expected) {
    $tests['json aggregate distinct filter window current source next112 helper jsonb frame ' . $index] = static function (TestRunner $t) use ($jsonbFrames, $index, $expected): void {
        $frames = $jsonbFrames();
        $t->true($frames[$index] instanceof SQLiteBlobValue);
        $t->same($expected, json_decode(json_encode(SQLiteJsonB::decode($frames[$index]->bytes), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    };
}

$tests['json aggregate distinct filter window current source next112 helper rejects invalid function'] = static function (TestRunner $t) use ($helperRowsFactory): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRowsSqlFunction('json_object', $helperRowsFactory(), 1, 1));
};

$tests['json aggregate distinct filter window current source next112 helper rejects malformed rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRows([['label-only']], 1, 1));
};

$tests['json aggregate distinct filter window current source next112 helper rejects bad unit'] = static function (TestRunner $t) use ($helperRowsFactory): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRowsByUnit($helperRowsFactory(), 'SPAN', 1, 1));
};

$tests['json aggregate distinct filter window current source next112 helper rejects negative bounds'] = static function (TestRunner $t) use ($helperRowsFactory): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRows($helperRowsFactory(), -1, 1));
};

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'enabled' => 1, 'payload' => 'https://example.test'],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'blogname', 'enabled' => 1, 'payload' => 'Port Libs'],
        ['option_id' => 3, 'autoload' => 'yes', 'option_name' => 'siteurl', 'enabled' => 1, 'payload' => 'https://example.test'],
        ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'enabled' => 1, 'payload' => $jsonbPlugin],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'enabled' => 1, 'payload' => $jsonbPlugin],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'theme_rules', 'enabled' => 0, 'payload' => new SQLiteJsonSubtypeValue('{"source":"theme","enabled":false}')],
        ['option_id' => 7, 'autoload' => 'no', 'option_name' => 'empty_option', 'enabled' => 1, 'payload' => null],
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

$sqlRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_object(DISTINCT option_name, payload) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND 2 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '{"siteurl":"https://example.test","blogname":"Port Libs"}',
    2 => '{"siteurl":"https://example.test","blogname":"Port Libs"}',
    3 => '{"blogname":"Port Libs","siteurl":"https://example.test"}',
    4 => '{"plugin_rules":{"source":"plugin","enabled":true}}',
    5 => '{"plugin_rules":{"source":"plugin","enabled":true},"empty_option":null}',
    6 => '{"plugin_rules":{"source":"plugin","enabled":true},"empty_option":null}',
    7 => '{"empty_option":null}',
] as $optionId => $expected) {
    $tests['json aggregate distinct filter window current source next112 select rows id ' . $optionId] = static function (TestRunner $t) use ($sqlRows, $optionId, $expected): void {
        $rows = $sqlRows();
        $t->same($expected, $rows[$optionId]['frame_json']);
    };
}

$namedRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_object(DISTINCT option_name, payload) FILTER (WHERE enabled) OVER win AS frame_json FROM wp_options WINDOW win AS (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) ORDER BY option_id',
);

foreach ([
    1 => '{"blogname":"Port Libs","siteurl":"https://example.test"}',
    2 => '{"siteurl":"https://example.test"}',
    3 => '{}',
    4 => '{"plugin_rules":{"source":"plugin","enabled":true}}',
    5 => '{"empty_option":null}',
    6 => '{"empty_option":null}',
    7 => '{}',
] as $optionId => $expected) {
    $tests['json aggregate distinct filter window current source next112 named window exclude current id ' . $optionId] = static function (TestRunner $t) use ($namedRows, $optionId, $expected): void {
        $rows = $namedRows();
        $t->same($expected, $rows[$optionId]['frame_json']);
    };
}

$jsonbSqlRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_object(DISTINCT option_name, payload) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS frame_jsonb FROM wp_options ORDER BY option_id',
);

foreach ([
    4 => ['plugin_rules' => ['source' => 'plugin', 'enabled' => true]],
    5 => ['plugin_rules' => ['source' => 'plugin', 'enabled' => true]],
    6 => ['plugin_rules' => ['source' => 'plugin', 'enabled' => true], 'empty_option' => null],
] as $optionId => $expected) {
    $tests['json aggregate distinct filter window current source next112 select jsonb id ' . $optionId] = static function (TestRunner $t) use ($jsonbSqlRows, $optionId, $expected): void {
        $rows = $jsonbSqlRows();
        $t->true($rows[$optionId]['frame_jsonb'] instanceof SQLiteBlobValue);
        $t->same($expected, json_decode(json_encode(SQLiteJsonB::decode($rows[$optionId]['frame_jsonb']->bytes), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    };
}

return $tests;
