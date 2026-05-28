<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$jsonbRules = new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'rules', 'enabled' => true]));
$jsonbQueue = new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'queue', 'enabled' => true]));
$jsonbRulesCopy = new SQLiteBlobValue(SQLiteJsonB::encode(['kind' => 'rules', 'enabled' => true]));

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'enabled' => 1, 'payload' => new SQLiteJsonSubtypeValue('{"kind":"site","enabled":true}')],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'blogname', 'enabled' => 1, 'payload' => new SQLiteJsonSubtypeValue('{"kind":"blog","enabled":true}')],
        ['option_id' => 3, 'autoload' => 'yes', 'option_name' => 'siteurl', 'enabled' => 1, 'payload' => new SQLiteJsonSubtypeValue('{"kind":"site","enabled":true}')],
        ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'enabled' => 1, 'payload' => $jsonbRules],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'enabled' => 1, 'payload' => $jsonbQueue],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'enabled' => 1, 'payload' => $jsonbRulesCopy],
        ['option_id' => 7, 'autoload' => 'no', 'option_name' => 'disabled_plugin', 'enabled' => 0, 'payload' => new SQLiteJsonSubtypeValue('{"kind":"disabled","enabled":false}')],
        ['option_id' => 8, 'autoload' => 'no', 'option_name' => 'empty_option', 'enabled' => 1, 'payload' => null],
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

$decode = static function (mixed $value): mixed {
    if (!$value instanceof SQLiteBlobValue) {
        throw new RuntimeException('expected JSONB aggregate result');
    }

    return SQLiteJsonB::decode($value->bytes);
};

$nameRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(DISTINCT option_name) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => ['siteurl', 'blogname'],
    2 => ['blogname', 'siteurl'],
    3 => ['siteurl'],
    4 => ['plugin_rules', 'plugin_queue'],
    5 => ['plugin_queue', 'plugin_rules', 'disabled_plugin'],
    6 => ['plugin_rules', 'disabled_plugin', 'empty_option'],
    7 => ['disabled_plugin', 'empty_option'],
    8 => ['empty_option'],
] as $optionId => $expected) {
    $tests['json aggregate window jsonb distinct current source next105 names current frame id ' . $optionId] = static function (TestRunner $t) use ($nameRows, $decode, $optionId, $expected): void {
        $rows = $nameRows();
        $t->same($expected, $decode($rows[$optionId]['names']));
    };
}

$payloadRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(DISTINCT payload) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS payloads FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => [['kind' => 'site', 'enabled' => true], ['kind' => 'blog', 'enabled' => true]],
    2 => [['kind' => 'site', 'enabled' => true], ['kind' => 'blog', 'enabled' => true]],
    3 => [['kind' => 'blog', 'enabled' => true], ['kind' => 'site', 'enabled' => true]],
    4 => [['kind' => 'rules', 'enabled' => true], ['kind' => 'queue', 'enabled' => true]],
    5 => [['kind' => 'rules', 'enabled' => true], ['kind' => 'queue', 'enabled' => true]],
    6 => [['kind' => 'queue', 'enabled' => true], ['kind' => 'rules', 'enabled' => true], ['kind' => 'disabled', 'enabled' => false]],
    7 => [['kind' => 'rules', 'enabled' => true], ['kind' => 'disabled', 'enabled' => false], null],
    8 => [['kind' => 'disabled', 'enabled' => false], null],
] as $optionId => $expected) {
    $tests['json aggregate window jsonb distinct current source next105 payload current frame id ' . $optionId] = static function (TestRunner $t) use ($payloadRows, $decode, $optionId, $expected): void {
        $rows = $payloadRows();
        $t->same($expected, $decode($rows[$optionId]['payloads']));
    };
}

$filteredRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(DISTINCT option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    4 => ['plugin_rules', 'plugin_queue'],
    5 => ['plugin_queue', 'plugin_rules', 'empty_option'],
    6 => ['plugin_rules', 'empty_option'],
    7 => ['empty_option'],
    8 => ['empty_option'],
] as $optionId => $expected) {
    $tests['json aggregate window jsonb distinct current source next105 filter before distinct id ' . $optionId] = static function (TestRunner $t) use ($filteredRows, $decode, $optionId, $expected): void {
        $rows = $filteredRows();
        $t->same($expected, $decode($rows[$optionId]['names']));
    };
}

$excludeRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(DISTINCT option_name) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    1 => ['blogname'],
    2 => ['siteurl'],
    3 => ['blogname'],
    4 => ['plugin_queue'],
    5 => ['plugin_rules'],
    6 => ['plugin_queue', 'disabled_plugin'],
    7 => ['plugin_rules', 'empty_option'],
    8 => ['disabled_plugin'],
] as $optionId => $expected) {
    $tests['json aggregate window jsonb distinct current source next105 exclude current id ' . $optionId] = static function (TestRunner $t) use ($excludeRows, $decode, $optionId, $expected): void {
        $rows = $excludeRows();
        $t->same($expected, $decode($rows[$optionId]['names']));
    };
}

$groupsRows = static fn (): array => $rowsById(
    'SELECT option_id, jsonb_group_array(DISTINCT option_name) OVER (PARTITION BY autoload ORDER BY enabled GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS names FROM wp_options ORDER BY option_id',
);

foreach ([
    4 => ['plugin_rules'],
    5 => ['plugin_queue'],
    6 => ['plugin_rules'],
    7 => ['disabled_plugin', 'plugin_rules', 'plugin_queue', 'empty_option'],
    8 => ['empty_option'],
] as $optionId => $expected) {
    $tests['json aggregate window jsonb distinct current source next105 groups exclude ties id ' . $optionId] = static function (TestRunner $t) use ($groupsRows, $decode, $optionId, $expected): void {
        $rows = $groupsRows();
        $t->same($expected, $decode($rows[$optionId]['names']));
    };
}

$tests['json aggregate window jsonb distinct current source next105 helper preserves current source order'] = static function (TestRunner $t) use ($jsonbRules, $jsonbQueue, $jsonbRulesCopy): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctWindowFrameRowsSqlFunction(
        'jsonb_group_array',
        [
            [$jsonbRules, 1],
            [$jsonbQueue, 2],
            [$jsonbRulesCopy, 3],
            [null, 4],
        ],
        0,
        2,
    );

    $t->same([['kind' => 'rules', 'enabled' => true], ['kind' => 'queue', 'enabled' => true]], $decode = SQLiteJsonB::decode($frames[0]->bytes));
    $t->same([['kind' => 'queue', 'enabled' => true], ['kind' => 'rules', 'enabled' => true], null], SQLiteJsonB::decode($frames[1]->bytes));
};

$tests['json aggregate window jsonb distinct current source next105 helper filters before distinct'] = static function (TestRunner $t) use ($jsonbRules, $jsonbQueue): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctWindowFrameRowsSqlFunction(
        'json_group_array',
        [
            [$jsonbRules, 1, 1],
            [$jsonbRules, 2, 1],
            [$jsonbQueue, 3, 0],
        ],
        0,
        2,
    );

    $t->same('[{"kind":"rules","enabled":true}]', $frames[0]);
    $t->same('[{"kind":"rules","enabled":true}]', $frames[1]);
};

$tests['json aggregate window jsonb distinct current source next105 helper supports range unit'] = static function (TestRunner $t) use ($jsonbRules, $jsonbQueue): void {
    $frames = SQLiteJsonAggregate::jsonGroupArrayDistinctWindowFrameRowsByUnitSqlFunction(
        'jsonb_group_array',
        [
            [$jsonbRules, 10],
            [$jsonbQueue, 15],
            [$jsonbRules, 20],
        ],
        'RANGE',
        5,
        0,
    );

    $t->same([['kind' => 'rules', 'enabled' => true]], SQLiteJsonB::decode($frames[0]->bytes));
    $t->same([['kind' => 'rules', 'enabled' => true], ['kind' => 'queue', 'enabled' => true]], SQLiteJsonB::decode($frames[1]->bytes));
};

$tests['json aggregate window jsonb distinct current source next105 helper rejects invalid function'] = static function (TestRunner $t) use ($jsonbRules): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctWindowFrameRowsSqlFunction('json_object', [[$jsonbRules, 1]], 0, 0));
};

$tests['json aggregate window jsonb distinct current source next105 helper rejects malformed rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctWindowFrameRows([['missing order']], 0, 0));
};

$tests['json aggregate window jsonb distinct current source next105 sql rejects distinct wildcard'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT jsonb_group_array(DISTINCT *) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS names FROM wp_options',
        $tables,
    ));
};

return $tests;
