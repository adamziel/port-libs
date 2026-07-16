<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'bytes' => 44],
    ['option_id' => 7, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => 3],
];

$meta = [
    ['option_name' => 'siteurl', 'priority' => 30, 'bucket' => 'core', 'keep' => 1],
    ['option_name' => 'home', 'priority' => 20, 'bucket' => 'core', 'keep' => 1],
    ['option_name' => 'blogname', 'priority' => 10, 'bucket' => 'content', 'keep' => 1],
    ['option_name' => '_transient_feed', 'priority' => 40, 'bucket' => 'transient', 'keep' => 0],
    ['option_name' => '_site_transient_update_plugins', 'priority' => 60, 'bucket' => 'transient', 'keep' => 0],
    ['option_name' => 'rewrite_rules', 'priority' => 50, 'bucket' => 'rewrite', 'keep' => 1],
];

$tables = [
    'wp_options' => $options,
    'option_meta' => $meta,
];

$select = static fn (string $sql, string $column): array => array_column(SQLiteSelectSql::execute($sql, $tables), $column);
$rows = static fn (string $sql): array => SQLiteSelectSql::execute($sql, $tables);

$cases = [
    'named window row number priority names' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY rn",
        'option_name',
        ['orphaned', 'blogname', 'home', 'siteurl', '_transient_feed', 'rewrite_rules', '_site_transient_update_plugins'],
    ],
    'named window row number priority values' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY option_id",
        'rn',
        [4, 3, 2, 5, 7, 6, 1],
    ],
    'named window partition by scalar subquery resets' => [
        "SELECT option_name, row_number() OVER buckets AS rn FROM wp_options WINDOW buckets AS (PARTITION BY (SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name) ORDER BY bytes DESC, option_id) ORDER BY option_id",
        'rn',
        [1, 2, 1, 2, 1, 1, 1],
    ],
    'named window partition rank by scalar subquery' => [
        "SELECT option_name, rank() OVER buckets AS r FROM wp_options WINDOW buckets AS (PARTITION BY (SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name) ORDER BY bytes DESC) ORDER BY option_id",
        'r',
        [1, 1, 1, 2, 1, 1, 1],
    ],
    'named window order by subquery descending' => [
        "SELECT option_name, dense_rank() OVER ranked AS r FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name) DESC) ORDER BY option_id",
        'r',
        [4, 5, 6, 3, 1, 2, 7],
    ],
    'named window lead follows subquery priority' => [
        "SELECT option_name, lead(option_name, 1, 'end') OVER ranked AS next_name FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY option_id",
        'next_name',
        ['_transient_feed', 'siteurl', 'home', 'rewrite_rules', 'end', '_site_transient_update_plugins', 'blogname'],
    ],
    'named window lag follows subquery priority' => [
        "SELECT option_name, lag(option_name, 1, 'start') OVER ranked AS previous_name FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY option_id",
        'previous_name',
        ['home', 'blogname', 'orphaned', 'siteurl', 'rewrite_rules', '_transient_feed', 'start'],
    ],
    'named window ntile subquery order' => [
        "SELECT option_name, ntile(3) OVER ranked AS bucket FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY option_id",
        'bucket',
        [2, 1, 1, 2, 3, 3, 1],
    ],
    'named frame count with subquery filter' => [
        "SELECT option_name, count(*) FILTER (WHERE (SELECT keep FROM option_meta WHERE option_meta.option_name = wp_options.option_name) = 1) OVER framed AS kept FROM wp_options WINDOW framed AS (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) ORDER BY option_id",
        'kept',
        [1, 2, 2, 1, 0, 1, 1],
    ],
    'named frame sum with subquery partition' => [
        "SELECT option_name, sum(bytes) OVER framed AS bytes_total FROM wp_options WINDOW framed AS (PARTITION BY (SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name) ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) ORDER BY option_id",
        'bytes_total',
        [24, 48, 9, 12, 122, 44, 3],
    ],
    'named frame first value by subquery partition' => [
        "SELECT option_name, first_value(option_name) OVER framed AS first_name FROM wp_options WINDOW framed AS (PARTITION BY (SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name) ORDER BY bytes DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) ORDER BY option_id",
        'first_name',
        ['siteurl', 'siteurl', 'blogname', '_site_transient_update_plugins', '_site_transient_update_plugins', 'rewrite_rules', 'orphaned'],
    ],
    'named frame last value by subquery partition' => [
        "SELECT option_name, last_value(option_name) OVER framed AS last_name FROM wp_options WINDOW framed AS (PARTITION BY (SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name) ORDER BY bytes DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) ORDER BY option_id",
        'last_name',
        ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins', 'rewrite_rules', 'orphaned'],
    ],
    'named frame group concat with subquery filter' => [
        "SELECT option_name, group_concat(option_name) FILTER (WHERE (SELECT keep FROM option_meta WHERE option_meta.option_name = wp_options.option_name) = 1) OVER framed AS names FROM wp_options WINDOW framed AS (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) ORDER BY option_id",
        'names',
        ['siteurl', 'siteurl,home', 'home,blogname', 'blogname', null, 'rewrite_rules', 'rewrite_rules'],
    ],
    'two named windows can coexist' => [
        "SELECT option_name, row_number() OVER by_id AS id_rank, row_number() OVER by_priority AS priority_rank FROM wp_options WINDOW by_id AS (ORDER BY option_id), by_priority AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY option_id",
        'priority_rank',
        [4, 3, 2, 5, 7, 6, 1],
    ],
    'named window can appear before final order by' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY bytes DESC, option_id) ORDER BY rn LIMIT 3",
        'option_name',
        ['_site_transient_update_plugins', 'rewrite_rules', 'siteurl'],
    ],
    'named window can appear before limit without order by' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY option_id) LIMIT 2",
        'rn',
        [1, 2],
    ],
    'named window survives where filtering before windowing' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WHERE autoload = 'no' WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name)) ORDER BY rn",
        'option_name',
        ['_transient_feed', 'rewrite_rules', '_site_transient_update_plugins'],
    ],
    'named window survives having before window clause' => [
        "SELECT autoload, count(option_id) AS total, row_number() OVER ranked AS rn FROM wp_options GROUP BY autoload HAVING count(option_id) >= 1 WINDOW ranked AS (ORDER BY autoload) ORDER BY rn",
        'autoload',
        [null, 'no', 'yes'],
    ],
    'named window scalar subquery sees current source after where' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WHERE option_id IN (1, 2, 3) WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name)) ORDER BY rn",
        'option_name',
        ['blogname', 'home', 'siteurl'],
    ],
    'named window subquery nulls sort first' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name)) ORDER BY rn LIMIT 1",
        'option_name',
        ['orphaned'],
    ],
    'named window final order can use expanded alias' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY rn DESC LIMIT 3",
        'option_name',
        ['_site_transient_update_plugins', 'rewrite_rules', '_transient_feed'],
    ],
    'named window offset after expanded alias order' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY rn LIMIT 2 OFFSET 2",
        'option_name',
        ['home', 'siteurl'],
    ],
    'named window comma limit after expanded alias order' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY rn LIMIT 4, 2",
        'option_name',
        ['_transient_feed', 'rewrite_rules'],
    ],
    'named window cume dist by subquery bucket' => [
        "SELECT option_name, cume_dist() OVER ranked AS cd FROM wp_options WINDOW ranked AS (PARTITION BY (SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name) ORDER BY bytes DESC) ORDER BY option_id",
        'cd',
        [1.0, 1.0, 1.0, 1.0, 0.5, 1.0, 1.0],
    ],
    'named window percent rank by subquery bucket' => [
        "SELECT option_name, percent_rank() OVER ranked AS pr FROM wp_options WINDOW ranked AS (PARTITION BY (SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name) ORDER BY bytes DESC) ORDER BY option_id",
        'pr',
        [0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0],
    ],
    'named window nth value by subquery order' => [
        "SELECT option_name, nth_value(option_name, 2) OVER ranked AS second_name FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY option_id",
        'second_name',
        ['blogname', 'blogname', 'blogname', 'blogname', 'blogname', 'blogname', 'blogname'],
    ],
    'named window first value global subquery order' => [
        "SELECT option_name, first_value(option_name) OVER ranked AS first_name FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY option_id",
        'first_name',
        ['orphaned', 'orphaned', 'orphaned', 'orphaned', 'orphaned', 'orphaned', 'orphaned'],
    ],
    'named window last value global subquery order' => [
        "SELECT option_name, last_value(option_name) OVER ranked AS last_name FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY option_id",
        'last_name',
        ['_site_transient_update_plugins', '_site_transient_update_plugins', '_site_transient_update_plugins', '_site_transient_update_plugins', '_site_transient_update_plugins', '_site_transient_update_plugins', '_site_transient_update_plugins'],
    ],
    'named window partition null bucket row number' => [
        "SELECT option_name, row_number() OVER buckets AS rn FROM wp_options WHERE option_id IN (1, 7) WINDOW buckets AS (PARTITION BY (SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name) ORDER BY option_id) ORDER BY option_id",
        'rn',
        [1, 1],
    ],
    'named window partition scalar expression composition' => [
        "SELECT option_name, row_number() OVER buckets AS rn FROM wp_options WINDOW buckets AS (PARTITION BY coalesce((SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name), 'missing') || ':' || autoload ORDER BY option_id) ORDER BY option_id",
        'rn',
        [1, 2, 1, 1, 2, 1, 1],
    ],
    'named window order scalar expression composition' => [
        "SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY coalesce((SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), 0) + bytes DESC) ORDER BY rn LIMIT 3",
        'option_name',
        ['_site_transient_update_plugins', 'rewrite_rules', 'siteurl'],
    ],
    'named window filter subquery excludes transients' => [
        "SELECT option_name, count(*) FILTER (WHERE coalesce((SELECT bucket FROM option_meta WHERE option_meta.option_name = wp_options.option_name), '') <> 'transient') OVER framed AS kept FROM wp_options WINDOW framed AS (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) ORDER BY option_id",
        'kept',
        [1, 2, 3, 2, 1, 1, 2],
    ],
    'named window frame current row with subquery filter' => [
        "SELECT option_name, count(*) FILTER (WHERE (SELECT keep FROM option_meta WHERE option_meta.option_name = wp_options.option_name) = 1) OVER framed AS kept FROM wp_options WINDOW framed AS (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) ORDER BY option_id",
        'kept',
        [1, 1, 1, 0, 0, 1, 0],
    ],
    'named window frame following with subquery filter' => [
        "SELECT option_name, count(*) FILTER (WHERE (SELECT keep FROM option_meta WHERE option_meta.option_name = wp_options.option_name) = 1) OVER framed AS kept FROM wp_options WINDOW framed AS (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) ORDER BY option_id",
        'kept',
        [2, 2, 1, 0, 1, 1, 0],
    ],
    'named window range peer frame over subquery order' => [
        "SELECT option_name, count(*) OVER framed AS peers FROM wp_options WINDOW framed AS (ORDER BY coalesce((SELECT keep FROM option_meta WHERE option_meta.option_name = wp_options.option_name), -1) RANGE BETWEEN CURRENT ROW AND CURRENT ROW) ORDER BY option_id",
        'peers',
        [4, 4, 4, 2, 2, 4, 1],
    ],
    'named window groups frame over subquery order' => [
        "SELECT option_name, count(*) OVER framed AS peers FROM wp_options WINDOW framed AS (ORDER BY (SELECT keep FROM option_meta WHERE option_meta.option_name = wp_options.option_name) GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) ORDER BY option_id",
        'peers',
        [4, 4, 4, 6, 6, 4, 3],
    ],
    'named window exclude current row with subquery order' => [
        "SELECT option_name, count(*) OVER framed AS nearby FROM wp_options WINDOW framed AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE CURRENT ROW) ORDER BY option_id",
        'nearby',
        [2, 2, 2, 2, 1, 2, 1],
    ],
    'named window can be used in derived table' => [
        "SELECT option_name FROM (SELECT option_name, row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id)) AS ranked WHERE rn <= 3 ORDER BY rn",
        'option_name',
        ['orphaned', 'blogname', 'home'],
    ],
    'named window can be used in scalar subquery select' => [
        "SELECT option_name, (SELECT row_number() OVER ranked FROM option_meta WHERE option_meta.option_name = wp_options.option_name WINDOW ranked AS (ORDER BY priority)) AS meta_rank FROM wp_options ORDER BY option_id",
        'meta_rank',
        [1, 1, 1, 1, 1, 1, null],
    ],
    'named window can be used in cte materialization' => [
        "WITH ranked AS (SELECT option_name, row_number() OVER by_priority AS rn FROM wp_options WINDOW by_priority AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id)) SELECT option_name FROM ranked WHERE rn <= 2 ORDER BY rn",
        'option_name',
        ['orphaned', 'blogname'],
    ],
    'named window can be used in constant select' => [
        "SELECT row_number() OVER one AS rn WINDOW one AS (ORDER BY 1)",
        'rn',
        [1],
    ],
];

$tests = [];
foreach ($cases as $name => [$sql, $column, $expected]) {
    $tests['sqlite select sql named window subquery current source next107 ' . $name] = static function (TestRunner $t) use ($select, $sql, $column, $expected): void {
        $t->same($expected, $select($sql, $column));
    };
}

$tests['sqlite select sql named window subquery current source next107 returns both named window columns'] = static function (TestRunner $t) use ($rows): void {
    $t->same(
        [
            ['option_name' => 'siteurl', 'id_rank' => 1, 'priority_rank' => 4],
            ['option_name' => 'home', 'id_rank' => 2, 'priority_rank' => 3],
        ],
        $rows("SELECT option_name, row_number() OVER by_id AS id_rank, row_number() OVER by_priority AS priority_rank FROM wp_options WINDOW by_id AS (ORDER BY option_id), by_priority AS (ORDER BY (SELECT priority FROM option_meta WHERE option_meta.option_name = wp_options.option_name), option_id) ORDER BY option_id LIMIT 2")
    );
};

$tests['sqlite select sql named window subquery current source next107 rejects missing named window'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT row_number() OVER missing AS rn FROM wp_options WINDOW ranked AS (ORDER BY option_id)', $tables));
};

$tests['sqlite select sql named window subquery current source next107 rejects duplicate named window'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT row_number() OVER ranked AS rn FROM wp_options WINDOW ranked AS (ORDER BY option_id), ranked AS (ORDER BY bytes)', $tables));
};

$tests['sqlite select sql named window subquery current source next107 rejects malformed named window'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT row_number() OVER ranked AS rn FROM wp_options WINDOW ranked ORDER BY option_id', $tables));
};

$tests['sqlite select sql named window subquery current source next107 rejects base window chaining'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT row_number() OVER child AS rn FROM wp_options WINDOW base AS (ORDER BY option_id), child AS (base PARTITION BY autoload)', $tables));
};

return $tests;
