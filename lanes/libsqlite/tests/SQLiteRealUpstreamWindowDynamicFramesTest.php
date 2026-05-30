<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$settingsRows = [
    ['setting_id' => 1, 'setting_group' => 'odd', 'key_name' => 'one', 'load_policy' => 'eager', 'weight' => 1],
    ['setting_id' => 2, 'setting_group' => 'even', 'key_name' => 'two', 'load_policy' => 'lazy', 'weight' => 2],
    ['setting_id' => 3, 'setting_group' => 'odd', 'key_name' => 'three', 'load_policy' => 'eager', 'weight' => 3],
    ['setting_id' => 4, 'setting_group' => 'even', 'key_name' => 'four', 'load_policy' => 'lazy', 'weight' => 4],
    ['setting_id' => 5, 'setting_group' => 'odd', 'key_name' => 'five', 'load_policy' => 'eager', 'weight' => 5],
    ['setting_id' => 6, 'setting_group' => 'even', 'key_name' => 'six', 'load_policy' => 'lazy', 'weight' => 6],
];

$singleRows = [
    ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
    ['a' => 5, 'b' => 6, 'c' => 7, 'd' => 8],
    ['a' => 9, 'b' => 10, 'c' => 11, 'd' => 12],
];

$tables = [
    'app_settings' => $settingsRows,
    'window_seed' => $singleRows,
];

$column = static fn (string $sql, string $column): array => array_column(SQLiteSelectSql::execute($sql, $tables), $column);
$pairs = static function (string $sql, string $left, string $right) use ($tables): array {
    return array_map(
        static fn (array $row): array => [$row[$left], $row[$right]],
        SQLiteSelectSql::execute($sql, $tables),
    );
};

$tests = [];

$window1Cases = [
    'window1 1.1 global sum repeats for every row' => [
        "SELECT sum(b) OVER () AS total FROM window_seed",
        'total',
        [18, 18, 18],
    ],
    'window1 1.3 scalar expression composes global sum' => [
        "SELECT a, 4 + sum(b) OVER () AS total FROM window_seed",
        'total',
        [22, 22, 22],
    ],
    'window1 1.5 partition by unique column keeps each row sum' => [
        "SELECT a, sum(b) OVER (PARTITION BY c) AS total FROM window_seed",
        'total',
        [2, 6, 10],
    ],
    'window1 4.4 ordered cumulative sum' => [
        "SELECT setting_id, sum(setting_id) OVER (ORDER BY setting_id) AS running FROM app_settings ORDER BY setting_id",
        'running',
        [1, 3, 6, 10, 15, 21],
    ],
    'window1 4.5 partitioned cumulative sum' => [
        "SELECT setting_id, sum(setting_id) OVER (PARTITION BY setting_group ORDER BY setting_id) AS running FROM app_settings ORDER BY setting_id",
        'running',
        [1, 2, 4, 6, 9, 12],
    ],
    'window1 4.7 descending partitioned cumulative sum' => [
        "SELECT setting_id, sum(setting_id) OVER (PARTITION BY setting_group ORDER BY setting_id DESC) AS running FROM app_settings ORDER BY setting_id",
        'running',
        [9, 12, 8, 10, 5, 6],
    ],
    'window1 4.9 cumulative average' => [
        "SELECT setting_id, avg(setting_id) OVER (ORDER BY setting_id) AS running_avg FROM app_settings ORDER BY setting_id",
        'running_avg',
        [1.0, 1.5, 2.0, 2.5, 3.0, 3.5],
    ],
    'window1 4.10.2 cumulative group concat descending' => [
        "SELECT setting_id, group_concat(setting_id, '.') OVER (ORDER BY setting_id DESC) AS chain FROM app_settings ORDER BY setting_id DESC",
        'chain',
        ['6', '6.5', '6.5.4', '6.5.4.3', '6.5.4.3.2', '6.5.4.3.2.1'],
    ],
];

foreach ($window1Cases as $name => [$sql, $columnName, $expected]) {
    $tests['real upstream window dynamic frames ' . $name] = static function (TestRunner $t) use ($column, $sql, $columnName, $expected): void {
        $t->same($expected, $column($sql, $columnName));
    };
}

$window2Cases = [
    'window2 1.1 partition by text order by text' => [
        "SELECT key_name, sum(weight) OVER (PARTITION BY setting_group ORDER BY key_name) AS total FROM app_settings ORDER BY setting_group, key_name",
        'key_name',
        'total',
        [['four', 4], ['six', 10], ['two', 12], ['five', 5], ['one', 6], ['three', 9]],
    ],
    'window2 2.1 rows preceding to following clamps left edge' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight ROWS BETWEEN 1000 PRECEDING AND 1 FOLLOWING) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, 3], [2, 6], [3, 10], [4, 15], [5, 21], [6, 21]],
    ],
    'window2 2.3 rows preceding to far following shrinks right edge' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight ROWS BETWEEN 1 PRECEDING AND 1000 FOLLOWING) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, 21], [2, 21], [3, 20], [4, 18], [5, 15], [6, 11]],
    ],
    'window2 2.4 rows one preceding one following' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, 3], [2, 6], [3, 9], [4, 12], [5, 15], [6, 11]],
    ],
    'window2 2.6 partitioned rows one preceding one following' => [
        "SELECT setting_id, sum(weight) OVER (PARTITION BY setting_group ORDER BY weight ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS total FROM app_settings ORDER BY setting_group, weight",
        'setting_id',
        'total',
        [[2, 6], [4, 12], [6, 10], [1, 4], [3, 9], [5, 8]],
    ],
    'window2 2.8 current row to two following' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, 6], [2, 9], [3, 12], [4, 15], [5, 11], [6, 6]],
    ],
    'window2 2.11 two preceding to current row' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, 1], [2, 3], [3, 6], [4, 9], [5, 12], [6, 15]],
    ],
    'window2 2.14 preceding-only frame returns null at left edge' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight ROWS BETWEEN 3 PRECEDING AND 1 PRECEDING) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, null], [2, 1], [3, 3], [4, 6], [5, 9], [6, 12]],
    ],
    'window2 2.16 partitioned exact previous row frame' => [
        "SELECT setting_id, sum(weight) OVER (PARTITION BY setting_group ORDER BY weight ROWS BETWEEN 1 PRECEDING AND 1 PRECEDING) AS total FROM app_settings ORDER BY setting_group, weight",
        'setting_id',
        'total',
        [[2, null], [4, 2], [6, 4], [1, null], [3, 1], [5, 3]],
    ],
    'window2 2.17 inverted previous frame remains empty' => [
        "SELECT setting_id, sum(weight) OVER (PARTITION BY setting_group ORDER BY weight ROWS BETWEEN 1 PRECEDING AND 2 PRECEDING) AS total FROM app_settings ORDER BY setting_group, weight",
        'setting_id',
        'total',
        [[2, null], [4, null], [6, null], [1, null], [3, null], [5, null]],
    ],
    'window2 2.19 following-only partitioned frame' => [
        "SELECT setting_id, sum(weight) OVER (PARTITION BY setting_group ORDER BY weight ROWS BETWEEN 1 FOLLOWING AND 3 FOLLOWING) AS total FROM app_settings ORDER BY setting_group, weight",
        'setting_id',
        'total',
        [[2, 10], [4, 6], [6, null], [1, 8], [3, 5], [5, null]],
    ],
    'window2 2.20 following-only global frame' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight ROWS BETWEEN 1 FOLLOWING AND 2 FOLLOWING) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, 5], [2, 7], [3, 9], [4, 11], [5, 6], [6, null]],
    ],
    'window2 2.23 current row to unbounded following' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, 21], [2, 20], [3, 18], [4, 15], [5, 11], [6, 6]],
    ],
    'window2 2.24 partition expression to unbounded following' => [
        "SELECT setting_id, sum(weight) OVER (PARTITION BY setting_id%2 ORDER BY weight ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS total FROM app_settings ORDER BY setting_id%2, weight",
        'setting_id',
        'total',
        [[2, 12], [4, 10], [6, 6], [1, 9], [3, 8], [5, 5]],
    ],
    'window2 2.29 range current row to unbounded following over numeric order' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, 21], [2, 20], [3, 18], [4, 15], [5, 11], [6, 6]],
    ],
    'window2 2.30 range current row to unbounded following over peer text order' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY setting_group RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS total FROM app_settings ORDER BY setting_group, setting_id",
        'setting_id',
        'total',
        [[2, 21], [4, 21], [6, 21], [1, 9], [3, 9], [5, 9]],
    ],
    'window2 3.4 order expression frame' => [
        "SELECT setting_id, sum(weight) OVER (ORDER BY weight/2 ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total FROM app_settings",
        'setting_id',
        'total',
        [[1, 1], [2, 3], [3, 6], [4, 10], [5, 15], [6, 21]],
    ],
];

foreach ($window2Cases as $name => [$sql, $left, $right, $expected]) {
    $tests['real upstream window dynamic frames ' . $name] = static function (TestRunner $t) use ($pairs, $sql, $left, $right, $expected): void {
        $t->same($expected, $pairs($sql, $left, $right));
    };
}

$tests['real upstream window dynamic frames cites exact upstream sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window1.test:1.1,1.3,1.5,4.4,4.5,4.7,4.9,4.10.2',
            'window2.test:1.1,2.1,2.3,2.4,2.6,2.8,2.11,2.14,2.16,2.17,2.19,2.20,2.23,2.24,2.29,2.30,3.4',
        ],
        [
            'window1.test:1.1,1.3,1.5,4.4,4.5,4.7,4.9,4.10.2',
            'window2.test:1.1,2.1,2.3,2.4,2.6,2.8,2.11,2.14,2.16,2.17,2.19,2.20,2.23,2.24,2.29,2.30,3.4',
        ],
    );
};

return $tests;
