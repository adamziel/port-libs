<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 10],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 10],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 20],
    ['option_id' => 4, 'option_name' => 'cron', 'autoload' => 'no', 'bytes' => 30],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'bytes' => 30],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'bytes' => 40],
    ['option_id' => 7, 'option_name' => 'transient_feed', 'autoload' => 'no', 'bytes' => 50],
    ['option_id' => 8, 'option_name' => 'plugin_settings', 'autoload' => 'no', 'bytes' => 50],
];
$tables = ['wp_options' => $rows];

$column = static fn (string $sql, string $field): array => array_column(SQLiteSelectSql::execute($sql, $tables), $field);

$rowCases = [
    'rows exclude current count star' => [
        'SELECT count(*) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [2, 2, 2, 2, 2, 2, 1, 0],
    ],
    'rows exclude current count value' => [
        'SELECT count(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [2, 2, 2, 2, 2, 2, 1, 0],
    ],
    'rows exclude current sum' => [
        'SELECT sum(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [30, 50, 60, 70, 90, 100, 50, null],
    ],
    'rows exclude current concat' => [
        'SELECT group_concat(option_name) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        ['home,blogname', 'blogname,cron', 'cron,rewrite_rules', 'rewrite_rules,theme_mods', 'theme_mods,transient_feed', 'transient_feed,plugin_settings', 'plugin_settings', null],
    ],
    'partition rows exclude current sum' => [
        'SELECT sum(bytes) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [10, 20, 30, 40, null, 50, 50, null],
    ],
    'partition rows exclude current concat' => [
        'SELECT group_concat(option_name) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        ['home', 'blogname', 'rewrite_rules', 'theme_mods', null, 'transient_feed', 'plugin_settings', null],
    ],
];

foreach ($rowCases as $name => [$sql, $expected]) {
    foreach ($expected as $index => $value) {
        $tests['select window frame exclude current next31 ' . $name . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($column, $sql, $index, $value): void {
            $t->same($value, $column($sql, 'v')[$index]);
        };
    }
}

$wholeColumnCases = [
    'groups current following exclude current count' => [
        'SELECT count(*) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [2, 2, 2, 2, 2, 2, 1, 1],
    ],
    'groups current following exclude current sum' => [
        'SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [30, 30, 60, 70, 70, 100, 50, 50],
    ],
    'groups current following exclude current concat' => [
        'SELECT group_concat(option_name) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        ['home,blogname', 'siteurl,blogname', 'cron,rewrite_rules', 'rewrite_rules,theme_mods', 'cron,theme_mods', 'transient_feed,plugin_settings', 'plugin_settings', 'transient_feed'],
    ],
    'range current zero exclude current count peers' => [
        'SELECT count(*) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 0 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [1, 1, 0, 1, 1, 0, 1, 1],
    ],
    'range current zero exclude current sum peers' => [
        'SELECT sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 0 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [10, 10, null, 30, 30, null, 50, 50],
    ],
    'range current ten exclude current count' => [
        'SELECT count(*) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [2, 2, 2, 2, 2, 2, 1, 1],
    ],
    'range current ten exclude current sum' => [
        'SELECT sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id',
        [30, 30, 60, 70, 70, 100, 50, 50],
    ],
    'where filters before exclude current windows' => [
        "SELECT sum(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options WHERE autoload = 'no' ORDER BY option_id",
        [90, 100, 50, null],
    ],
    'limit applies after exclude current windows' => [
        'SELECT option_name, sum(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id LIMIT 3',
        [30, 50, 60],
    ],
    'offset applies after exclude current windows' => [
        'SELECT option_name, sum(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id LIMIT 3 OFFSET 4',
        [90, 100, 50],
    ],
];

foreach ($wholeColumnCases as $name => [$sql, $expected]) {
    $tests['select window frame exclude current next31 ' . $name] = static function (TestRunner $t) use ($column, $sql, $expected): void {
        $t->same($expected, $column($sql, 'v'));
    };
}

$tests['select window frame exclude current next31 plan records exclude current'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan('SELECT sum(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW) AS next_bytes FROM wp_options', $tables);
    $t->same('window', $plan['select'][0]['type']);
    $t->same('CURRENT ROW', $plan['select'][0]['frame']['exclude']);
    $t->same(2, $plan['select'][0]['frame']['following']);
};

$tests['select window frame exclude current next31 direct query plan rows'] = static function (TestRunner $t) use ($rows): void {
    $actual = SQLiteSelectQuery::execute([
        'from' => $rows,
        'select' => [[
            'type' => 'window',
            'function' => 'sum',
            'arguments' => [['type' => 'column', 'name' => 'bytes']],
            'orderBy' => [['expression' => ['type' => 'column', 'name' => 'option_id'], 'direction' => 'ASC']],
            'frame' => ['unit' => 'ROWS', 'preceding' => 0, 'following' => 2, 'exclude' => 'CURRENT ROW'],
            'alias' => 'v',
        ]],
    ]);
    $t->same([30, 50, 60, 70, 90, 100, 50, null], array_column($actual, 'v'));
};

$tests['select window frame exclude current next31 rejects missing exclude mode'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectQuery::execute([
        'from' => $tables['wp_options'],
        'select' => [[
            'type' => 'window',
            'function' => 'sum',
            'arguments' => [['type' => 'column', 'name' => 'bytes']],
            'orderBy' => [['expression' => ['type' => 'column', 'name' => 'option_id'], 'direction' => 'ASC']],
            'frame' => ['unit' => 'ROWS', 'preceding' => 0, 'following' => 1],
            'alias' => 'v',
        ]],
    ]));
};

$tests['select window frame exclude current next31 rejects framed ranking function'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT row_number() OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options', $tables));
};

return $tests;
