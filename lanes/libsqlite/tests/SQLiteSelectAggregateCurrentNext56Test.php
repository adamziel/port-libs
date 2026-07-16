<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bucket' => 'core', 'bytes' => 24, 'option_value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bucket' => 'core', 'bytes' => 24, 'option_value' => 'https://example.test'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bucket' => 'core', 'bytes' => 12, 'option_value' => 'Example Site'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bucket' => 'cache', 'bytes' => 12, 'option_value' => 'cached'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bucket' => 'cache', 'bytes' => 12, 'option_value' => 'cached'],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'bucket' => 'rules', 'bytes' => 48, 'option_value' => 'serialized-rules'],
    ['option_id' => 7, 'option_name' => 'theme_mods', 'autoload' => null, 'bucket' => 'theme', 'bytes' => null, 'option_value' => 'theme-json'],
    ['option_id' => 8, 'option_name' => 'widget_recent_posts', 'autoload' => null, 'bucket' => 'theme', 'bytes' => 0, 'option_value' => 'theme-json'],
];

$tables = ['wp_options' => $rows];
$run = static fn (string $sql): array => SQLiteSelectSql::execute($sql, $tables);
$column = static fn (string $sql, string $column): array => array_column($run($sql), $column);

$cases = [
    'group count star by autoload labels' => [
        'SELECT autoload, count(*) AS rows FROM wp_options GROUP BY autoload ORDER BY rows DESC, autoload',
        'autoload',
        ['no', 'yes', null],
    ],
    'group count star by autoload counts' => [
        'SELECT autoload, count(*) AS rows FROM wp_options GROUP BY autoload ORDER BY rows DESC, autoload',
        'rows',
        [3, 3, 2],
    ],
    'group count star keeps zero byte row' => [
        'SELECT bucket, count(*) AS rows FROM wp_options GROUP BY bucket ORDER BY bucket',
        'rows',
        [2, 3, 1, 2],
    ],
    'group count value ignores null bytes' => [
        'SELECT bucket, count(bytes) AS byte_rows FROM wp_options GROUP BY bucket ORDER BY bucket',
        'byte_rows',
        [2, 3, 1, 1],
    ],
    'group count distinct by autoload values' => [
        'SELECT autoload, count(DISTINCT bytes) AS distinct_bytes FROM wp_options GROUP BY autoload ORDER BY autoload',
        'distinct_bytes',
        [1, 2, 2],
    ],
    'group count distinct option values' => [
        'SELECT bucket, count(DISTINCT option_value) AS distinct_values FROM wp_options GROUP BY bucket ORDER BY bucket',
        'distinct_values',
        [1, 2, 1, 1],
    ],
    'group count distinct null ignored' => [
        'SELECT autoload, count(DISTINCT bytes) AS distinct_bytes FROM wp_options GROUP BY autoload HAVING autoload IS NULL',
        'distinct_bytes',
        [1],
    ],
    'implicit count star all rows' => [
        'SELECT count(*) AS total_rows FROM wp_options',
        'total_rows',
        [8],
    ],
    'implicit count value ignores null' => [
        'SELECT count(bytes) AS byte_rows FROM wp_options',
        'byte_rows',
        [7],
    ],
    'implicit count distinct bytes' => [
        'SELECT count(DISTINCT bytes) AS distinct_bytes FROM wp_options',
        'distinct_bytes',
        [4],
    ],
    'implicit count distinct autoload ignores null' => [
        'SELECT count(DISTINCT autoload) AS distinct_autoload FROM wp_options',
        'distinct_autoload',
        [2],
    ],
    'having count star filters grouped rows' => [
        'SELECT bucket, count(*) AS rows FROM wp_options GROUP BY bucket HAVING count(*) > 1 ORDER BY bucket',
        'bucket',
        ['cache', 'core', 'theme'],
    ],
    'having count distinct filters grouped rows' => [
        'SELECT bucket, count(DISTINCT bytes) AS distinct_bytes FROM wp_options GROUP BY bucket HAVING count(DISTINCT bytes) > 1 ORDER BY bucket',
        'bucket',
        ['core'],
    ],
    'having count distinct projection values' => [
        'SELECT bucket, count(DISTINCT bytes) AS distinct_bytes FROM wp_options GROUP BY bucket HAVING count(DISTINCT bytes) > 1 ORDER BY bucket',
        'distinct_bytes',
        [2],
    ],
    'having count distinct composed predicate' => [
        "SELECT bucket, count(DISTINCT option_value) AS distinct_values FROM wp_options GROUP BY bucket HAVING count(DISTINCT option_value) = 1 AND bucket != 'rules' ORDER BY bucket",
        'bucket',
        ['cache', 'theme'],
    ],
    'order by count star alias' => [
        'SELECT bucket, count(*) AS rows FROM wp_options GROUP BY bucket ORDER BY rows DESC, bucket',
        'bucket',
        ['core', 'cache', 'theme', 'rules'],
    ],
    'order by count distinct alias' => [
        'SELECT bucket, count(DISTINCT bytes) AS distinct_bytes FROM wp_options GROUP BY bucket ORDER BY distinct_bytes DESC, bucket',
        'bucket',
        ['core', 'cache', 'rules', 'theme'],
    ],
    'limit after grouped count star' => [
        'SELECT bucket, count(*) AS rows FROM wp_options GROUP BY bucket ORDER BY rows DESC, bucket LIMIT 2',
        'bucket',
        ['core', 'cache'],
    ],
    'offset after grouped count star' => [
        'SELECT bucket, count(*) AS rows FROM wp_options GROUP BY bucket ORDER BY rows DESC, bucket LIMIT 2 OFFSET 2',
        'bucket',
        ['theme', 'rules'],
    ],
    'where before grouped count distinct' => [
        "SELECT autoload, count(DISTINCT option_value) AS values_seen FROM wp_options WHERE bucket != 'rules' GROUP BY autoload ORDER BY values_seen DESC, autoload",
        'values_seen',
        [2, 1, 1],
    ],
    'where before grouped count star' => [
        "SELECT autoload, count(*) AS rows FROM wp_options WHERE bucket != 'rules' GROUP BY autoload ORDER BY rows DESC, autoload",
        'rows',
        [3, 2, 2],
    ],
    'compound aggregate arm count star' => [
        "SELECT autoload, count(*) AS rows FROM wp_options WHERE autoload = 'yes' GROUP BY autoload UNION ALL SELECT autoload, count(*) AS rows FROM wp_options WHERE autoload = 'no' GROUP BY autoload ORDER BY rows DESC",
        'autoload',
        ['yes', 'no'],
    ],
    'cte aggregate count distinct' => [
        'WITH selected AS (SELECT * FROM wp_options WHERE bytes IS NOT NULL) SELECT autoload, count(DISTINCT bytes) AS distinct_bytes FROM selected GROUP BY autoload ORDER BY autoload',
        'distinct_bytes',
        [1, 2, 2],
    ],
    'distinct aggregate is case insensitive' => [
        'SELECT count(distinct bytes) AS distinct_bytes FROM wp_options',
        'distinct_bytes',
        [4],
    ],
    'distinct aggregate with whitespace' => [
        "SELECT count( DISTINCT\n bytes ) AS distinct_bytes FROM wp_options",
        'distinct_bytes',
        [4],
    ],
    'count distinct after where prefix' => [
        "SELECT count(DISTINCT option_value) AS values_seen FROM wp_options WHERE option_name GLOB '_*'",
        'values_seen',
        [1],
    ],
    'count star after where prefix' => [
        "SELECT count(*) AS transient_rows FROM wp_options WHERE option_name GLOB '_*'",
        'transient_rows',
        [2],
    ],
    'group count star after where prefix' => [
        "SELECT autoload, count(*) AS transient_rows FROM wp_options WHERE option_name GLOB '_*' GROUP BY autoload",
        'transient_rows',
        [2],
    ],
    'count distinct literal duplicate across rows' => [
        "SELECT count(DISTINCT autoload) AS values_seen FROM wp_options WHERE option_id IN (1, 2, 3)",
        'values_seen',
        [1],
    ],
    'having count star equality' => [
        "SELECT bucket, count(*) AS rows FROM wp_options GROUP BY bucket HAVING count(*) = 1 ORDER BY bucket",
        'bucket',
        ['rules'],
    ],
    'having count distinct equality' => [
        "SELECT bucket, count(DISTINCT option_value) AS values_seen FROM wp_options GROUP BY bucket HAVING count(DISTINCT option_value) = 2",
        'bucket',
        ['core'],
    ],
    'count distinct ordered by alias desc' => [
        "SELECT autoload, count(DISTINCT option_value) AS values_seen FROM wp_options GROUP BY autoload ORDER BY values_seen DESC, autoload",
        'values_seen',
        [2, 2, 1],
    ],
    'count star ordered by ordinal desc' => [
        "SELECT autoload, count(*) AS rows FROM wp_options GROUP BY autoload ORDER BY 2 DESC, autoload",
        'rows',
        [3, 3, 2],
    ],
    'bound parameter aggregate filter' => [
        "SELECT autoload, count(*) AS rows FROM wp_options WHERE autoload = 'yes' GROUP BY autoload HAVING count(*) = 3",
        'rows',
        [3],
    ],
    'count star grouped with invariant column' => [
        'SELECT autoload, count(*) AS rows FROM wp_options GROUP BY autoload HAVING autoload = \'no\'',
        'autoload',
        ['no'],
    ],
];

foreach ($cases as $name => [$sql, $field, $expected]) {
    $tests['select aggregate current next56 ' . $name] = static function (TestRunner $t) use ($column, $sql, $field, $expected): void {
        $t->same($expected, $column($sql, $field));
    };
}

$tests['select aggregate current next56 plan marks distinct aggregate'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan('SELECT count(DISTINCT bytes) AS distinct_bytes FROM wp_options', $tables);
    $t->same('countDistinct', $plan['select'][0]['name']);
    $t->same('distinct_bytes', $plan['select'][0]['alias']);
    $t->same('bytes', $plan['groupBy']['valueColumn']);
};

$tests['select aggregate current next56 grouped count star has null value column'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan('SELECT autoload, count(*) AS rows FROM wp_options GROUP BY autoload', $tables);
    $t->same(null, $plan['groupBy']['valueColumn']);
    $t->same('countAll', $plan['select'][1]['name']);
};

$tests['select aggregate current next56 rejects distinct star'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT count(DISTINCT *) AS rows FROM wp_options', $tables));
};

$tests['select aggregate current next56 rejects unsupported distinct aggregate'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT sum(DISTINCT bytes) AS total FROM wp_options', $tables));
};

$tests['select aggregate current next56 rejects mismatched count distinct and sum'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT count(DISTINCT option_value) AS values_seen, sum(bytes) AS total FROM wp_options', $tables));
};

return $tests;
