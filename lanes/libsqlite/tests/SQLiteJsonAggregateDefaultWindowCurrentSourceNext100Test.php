<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'score' => 10, 'rank' => 20, 'tie' => 'b', 'enabled' => 1, 'option_name' => 'siteurl', 'payload' => new SQLiteJsonSubtypeValue('{"name":"siteurl"}')],
        ['option_id' => 2, 'autoload' => 'yes', 'score' => 20, 'rank' => 40, 'tie' => 'a', 'enabled' => 1, 'option_name' => 'theme_mods', 'payload' => new SQLiteJsonSubtypeValue('{"name":"theme"}')],
        ['option_id' => 3, 'autoload' => 'yes', 'score' => 20, 'rank' => 30, 'tie' => 'z', 'enabled' => 1, 'option_name' => 'siteurl', 'payload' => new SQLiteJsonSubtypeValue('{"name":"siteurl"}')],
        ['option_id' => 4, 'autoload' => 'yes', 'score' => 30, 'rank' => 10, 'tie' => 'c', 'enabled' => 0, 'option_name' => 'blogname', 'payload' => 'Blog'],
        ['option_id' => 5, 'autoload' => 'no', 'score' => 5, 'rank' => 50, 'tie' => 'a', 'enabled' => 1, 'option_name' => 'plugin_rules', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'rules']))],
        ['option_id' => 6, 'autoload' => 'no', 'score' => 5, 'rank' => 45, 'tie' => 'b', 'enabled' => 1, 'option_name' => 'plugin_queue', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'queue']))],
        ['option_id' => 7, 'autoload' => 'no', 'score' => 15, 'rank' => 35, 'tie' => 'z', 'enabled' => 1, 'option_name' => 'plugin_rules', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'rules']))],
        ['option_id' => 8, 'autoload' => 'no', 'score' => 25, 'rank' => 0, 'tie' => 'n', 'enabled' => 1, 'option_name' => null, 'payload' => null],
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
    'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY score) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '["theme_mods","siteurl"]',
    3 => '["theme_mods","siteurl"]',
    4 => '["theme_mods","siteurl","blogname"]',
    5 => '["plugin_rules","plugin_queue"]',
    6 => '["plugin_rules","plugin_queue"]',
    7 => '["plugin_rules","plugin_queue"]',
    8 => '["plugin_rules","plugin_queue",null]',
] as $optionId => $expected) {
    $tests['json aggregate default window current source next100 range default id ' . $optionId] = static function (TestRunner $t) use ($defaultRows, $optionId, $expected): void {
        $rows = $defaultRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$filteredRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '["theme_mods","siteurl"]',
    3 => '["theme_mods","siteurl"]',
    4 => '["theme_mods","siteurl"]',
    5 => '["plugin_rules","plugin_queue"]',
    6 => '["plugin_rules","plugin_queue"]',
    7 => '["plugin_rules","plugin_queue"]',
    8 => '["plugin_rules","plugin_queue",null]',
] as $optionId => $expected) {
    $tests['json aggregate default window current source next100 filter default id ' . $optionId] = static function (TestRunner $t) use ($filteredRows, $optionId, $expected): void {
        $rows = $filteredRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$secondaryRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY score ASC, tie DESC) OVER (PARTITION BY autoload ORDER BY score) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["siteurl"]',
    2 => '["siteurl","theme_mods"]',
    3 => '["siteurl","theme_mods"]',
    4 => '["siteurl","theme_mods","blogname"]',
    5 => '["plugin_queue","plugin_rules"]',
    6 => '["plugin_queue","plugin_rules"]',
    7 => '["plugin_queue","plugin_rules"]',
    8 => '["plugin_queue","plugin_rules",null]',
] as $optionId => $expected) {
    $tests['json aggregate default window current source next100 secondary order id ' . $optionId] = static function (TestRunner $t) use ($secondaryRows, $optionId, $expected): void {
        $rows = $secondaryRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$wholePartitionRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '["theme_mods","siteurl","blogname"]',
    2 => '["theme_mods","siteurl","blogname"]',
    3 => '["theme_mods","siteurl","blogname"]',
    4 => '["theme_mods","siteurl","blogname"]',
    5 => '["plugin_rules","plugin_queue",null]',
    6 => '["plugin_rules","plugin_queue",null]',
    7 => '["plugin_rules","plugin_queue",null]',
    8 => '["plugin_rules","plugin_queue",null]',
] as $optionId => $expected) {
    $tests['json aggregate default window current source next100 no order whole partition id ' . $optionId] = static function (TestRunner $t) use ($wholePartitionRows, $optionId, $expected): void {
        $rows = $wholePartitionRows();
        $t->same($expected, $rows[$optionId]['names']);
    };
}

$objectRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_object(DISTINCT option_name, payload ORDER BY rank DESC, tie ASC) FILTER (WHERE enabled AND option_name IS NOT NULL) OVER (PARTITION BY autoload ORDER BY score) AS object_json FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '{"siteurl":{"name":"siteurl"}}',
    2 => '{"theme_mods":{"name":"theme"},"siteurl":{"name":"siteurl"}}',
    3 => '{"theme_mods":{"name":"theme"},"siteurl":{"name":"siteurl"}}',
    4 => '{"theme_mods":{"name":"theme"},"siteurl":{"name":"siteurl"}}',
    5 => '{"plugin_rules":{"name":"rules"},"plugin_queue":{"name":"queue"}}',
    6 => '{"plugin_rules":{"name":"rules"},"plugin_queue":{"name":"queue"}}',
    7 => '{"plugin_rules":{"name":"rules"},"plugin_queue":{"name":"queue"}}',
    8 => '{"plugin_rules":{"name":"rules"},"plugin_queue":{"name":"queue"}}',
] as $optionId => $expected) {
    $tests['json aggregate default window current source next100 object default id ' . $optionId] = static function (TestRunner $t) use ($objectRows, $optionId, $expected): void {
        $rows = $objectRows();
        $t->same($expected, $rows[$optionId]['object_json']);
    };
}

$jsonbRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY score) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => ['siteurl'],
    2 => ['theme_mods', 'siteurl'],
    3 => ['theme_mods', 'siteurl'],
    4 => ['theme_mods', 'siteurl', 'blogname'],
    5 => ['plugin_rules', 'plugin_queue'],
    6 => ['plugin_rules', 'plugin_queue'],
    7 => ['plugin_rules', 'plugin_queue'],
    8 => ['plugin_rules', 'plugin_queue', null],
] as $optionId => $expected) {
    $tests['json aggregate default window current source next100 jsonb default id ' . $optionId] = static function (TestRunner $t) use ($jsonbRows, $optionId, $expected): void {
        $rows = $jsonbRows();
        $t->true($rows[$optionId]['names'] instanceof SQLiteBlobValue);
        $t->same($expected, SQLiteJsonB::decode($rows[$optionId]['names']->bytes));
    };
}

$payloadRows = static fn (): array => $rowsById(
    'SELECT option_id, json_group_array(DISTINCT payload ORDER BY rank DESC, tie ASC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score) AS payloads FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => '[{"name":"siteurl"}]',
    2 => '[{"name":"theme"},{"name":"siteurl"}]',
    3 => '[{"name":"theme"},{"name":"siteurl"}]',
    5 => '[{"name":"rules"},{"name":"queue"}]',
    8 => '[{"name":"rules"},{"name":"queue"},null]',
] as $optionId => $expected) {
    $tests['json aggregate default window current source next100 payload class id ' . $optionId] = static function (TestRunner $t) use ($payloadRows, $optionId, $expected): void {
        $rows = $payloadRows();
        $t->same($expected, $rows[$optionId]['payloads']);
    };
}

$tests['json aggregate default window current source next100 final order can use default frame output'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY score) AS names FROM wp_options ORDER BY names DESC, option_id',
        $tables,
    );

    $t->same([2, 3, 4, 1, 5, 6, 7, 8], array_column($rows, 'option_id'));
};

$tests['json aggregate default window current source next100 default frame differs from current row explicit frame'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY score ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
        $tables,
    );

    $t->same('["theme_mods"]', $rows[1]['names']);
    $t->same('["siteurl"]', $rows[2]['names']);
};

$tests['json aggregate default window current source next100 rejects distinct wildcard without frame'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT *) OVER (PARTITION BY autoload ORDER BY score) AS names FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate default window current source next100 rejects object missing value without frame'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_object(option_name ORDER BY score) OVER (PARTITION BY autoload ORDER BY score) AS names FROM wp_options',
        $tables,
    ));
};

$tests['json aggregate default window current source next100 rejects bad aggregate order without frame'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY rank SIDEWAYS) OVER (PARTITION BY autoload ORDER BY score) AS names FROM wp_options',
        $tables,
    ));
};

return $tests;
