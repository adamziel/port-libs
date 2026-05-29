<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'bucket' => 'core', 'option_name' => 'siteurl', 'bytes' => 10, 'enabled' => 1],
        ['option_id' => 2, 'autoload' => 'yes', 'bucket' => 'core', 'option_name' => 'home', 'bytes' => 20, 'enabled' => 1],
        ['option_id' => 3, 'autoload' => 'yes', 'bucket' => 'theme', 'option_name' => 'theme_mods', 'bytes' => null, 'enabled' => 0],
        ['option_id' => 4, 'autoload' => 'no', 'bucket' => 'cache', 'option_name' => '_transient_feed', 'bytes' => 5, 'enabled' => 1],
        ['option_id' => 5, 'autoload' => 'no', 'bucket' => 'cache', 'option_name' => '_transient_timeout_feed', 'bytes' => null, 'enabled' => 1],
        ['option_id' => 6, 'autoload' => 'no', 'bucket' => 'rules', 'option_name' => 'rewrite_rules', 'bytes' => 40, 'enabled' => 0],
    ],
];

$column = static function (string $sql, string $column) use ($tables): array {
    return array_column(SQLiteSelectSql::execute($sql, $tables), $column);
};

$cases = [
    'sum default range cumulative peers' => [
        'SELECT option_id, sum(bytes) OVER (PARTITION BY autoload ORDER BY bucket) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [30, 30, 30, 5, 5, 45],
    ],
    'count star default whole partition without order' => [
        'SELECT option_id, count(*) OVER (PARTITION BY autoload) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [3, 3, 3, 3, 3, 3],
    ],
    'count value default whole partition without order skips nulls' => [
        'SELECT option_id, count(bytes) OVER (PARTITION BY autoload) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [2, 2, 2, 2, 2, 2],
    ],
    'total default whole partition returns float' => [
        'SELECT option_id, total(bytes) OVER (PARTITION BY autoload) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [30.0, 30.0, 30.0, 45.0, 45.0, 45.0],
    ],
    'avg default range includes order peers' => [
        'SELECT option_id, avg(bytes) OVER (PARTITION BY autoload ORDER BY bucket) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [15.0, 15.0, 15.0, 5.0, 5.0, 22.5],
    ],
    'min default range peers' => [
        'SELECT option_id, min(bytes) OVER (PARTITION BY autoload ORDER BY bucket) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [10, 10, 10, 5, 5, 5],
    ],
    'max default range peers' => [
        'SELECT option_id, max(bytes) OVER (PARTITION BY autoload ORDER BY bucket) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [20, 20, 20, 5, 5, 40],
    ],
    'group concat default range peers' => [
        'SELECT option_id, group_concat(option_name) OVER (PARTITION BY autoload ORDER BY bucket) AS names FROM wp_options ORDER BY option_id',
        'names',
        ['siteurl,home', 'siteurl,home', 'siteurl,home,theme_mods', '_transient_feed,_transient_timeout_feed', '_transient_feed,_transient_timeout_feed', '_transient_feed,_transient_timeout_feed,rewrite_rules'],
    ],
    'sum filter default range' => [
        'SELECT option_id, sum(bytes) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY bucket) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [30, 30, 30, 5, 5, 5],
    ],
    'total filter empty frame returns zero' => [
        'SELECT option_id, total(bytes) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY bucket ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [10.0, 20.0, 0.0, 5.0, 0.0, 0.0],
    ],
    'count star explicit range no order error removed by order' => [
        'SELECT option_id, count(*) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [1, 2, 2, 2, 2, 2],
    ],
    'count value explicit rows skips nulls' => [
        'SELECT option_id, count(bytes) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [1, 2, 1, 1, 1, 1],
    ],
    'sum explicit rows accepts null-only current frame' => [
        'SELECT option_id, sum(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [10, 20, null, 5, null, 40],
    ],
    'total explicit rows accepts null-only current frame' => [
        'SELECT option_id, total(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [10.0, 20.0, 0.0, 5.0, 0.0, 40.0],
    ],
    'avg explicit rows accepts null-only current frame' => [
        'SELECT option_id, avg(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [10.0, 20.0, null, 5.0, null, 40.0],
    ],
    'min explicit rows with following' => [
        'SELECT option_id, min(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [10, 20, 5, 5, 40, 40],
    ],
    'max explicit rows with following' => [
        'SELECT option_id, max(bytes) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [20, 20, 5, 5, 40, 40],
    ],
    'group concat explicit rows with following' => [
        'SELECT option_id, group_concat(option_name) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS names FROM wp_options ORDER BY option_id',
        'names',
        ['siteurl,home', 'home,theme_mods', 'theme_mods,_transient_feed', '_transient_feed,_transient_timeout_feed', '_transient_timeout_feed,rewrite_rules', 'rewrite_rules'],
    ],
    'sum groups frame sees peer group' => [
        'SELECT option_id, sum(bytes) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [30, 30, null, 5, 5, 40],
    ],
    'total groups frame sees peer group' => [
        'SELECT option_id, total(bytes) OVER (ORDER BY bucket GROUPS BETWEEN CURRENT ROW AND CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [30.0, 30.0, 0.0, 5.0, 5.0, 40.0],
    ],
    'sum range numeric offset' => [
        'SELECT option_id, sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN 10 PRECEDING AND CURRENT ROW) AS metric FROM wp_options WHERE bytes IS NOT NULL ORDER BY option_id',
        'metric',
        [15, 30, 5, 40],
    ],
    'total range numeric offset' => [
        'SELECT option_id, total(bytes) OVER (ORDER BY bytes RANGE BETWEEN 10 PRECEDING AND CURRENT ROW) AS metric FROM wp_options WHERE bytes IS NOT NULL ORDER BY option_id',
        'metric',
        [15.0, 30.0, 5.0, 40.0],
    ],
    'sum exclude current row' => [
        'SELECT option_id, sum(bytes) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [20, 10, 25, null, 45, null],
    ],
    'total exclude current row' => [
        'SELECT option_id, total(bytes) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [20.0, 10.0, 25.0, 0.0, 45.0, 0.0],
    ],
    'count filter explicit frame' => [
        'SELECT option_id, count(bytes) FILTER (WHERE enabled) OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric FROM wp_options ORDER BY option_id',
        'metric',
        [1, 2, 2, 2, 1, 1],
    ],
    'group concat filter explicit frame' => [
        'SELECT option_id, group_concat(option_name) FILTER (WHERE enabled) OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS names FROM wp_options ORDER BY option_id',
        'names',
        ['siteurl', 'siteurl,home', 'siteurl,home', 'home,_transient_feed', '_transient_feed,_transient_timeout_feed', '_transient_feed,_transient_timeout_feed'],
    ],
];

foreach ($cases as $name => [$sql, $field, $expected]) {
    $tests['select aggregate window current ' . $name] = static function (TestRunner $t) use ($column, $sql, $field, $expected): void {
        $t->same($expected, $column($sql, $field));
    };
}

$tests['select aggregate window current plan recognizes total window'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan('SELECT total(bytes) OVER (PARTITION BY autoload) AS total_bytes FROM wp_options', $tables);
    $t->same('window', $plan['select'][0]['type']);
    $t->same('total', $plan['select'][0]['function']);
};

$tests['select aggregate window current rejects distinct numeric window aggregate'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT sum(DISTINCT bytes) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM wp_options',
        $tables,
    ));
};

$tests['select aggregate window current rejects total without argument'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT total() OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS metric FROM wp_options',
        $tables,
    ));
};

$tests['select aggregate window current rejects range text order offset'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT sum(bytes) OVER (ORDER BY bucket RANGE BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM wp_options',
        $tables,
    ));
};

return $tests;
