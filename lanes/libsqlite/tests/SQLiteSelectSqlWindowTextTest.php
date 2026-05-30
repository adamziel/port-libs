<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 9],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 5],
    ['option_id' => 4, 'option_name' => 'cron', 'autoload' => 'no', 'weight' => 4],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 4],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'weight' => 1],
];
$tables = ['wp_options' => $rows];

$column = static fn (string $sql, string $name): array => array_column(SQLiteSelectSql::execute($sql, $tables), $name);

$cases = [
    'row number weight desc names' => [
        "SELECT option_name, row_number() OVER (ORDER BY weight DESC, option_id ASC) AS rn FROM wp_options ORDER BY rn",
        'option_name',
        ['siteurl', 'home', 'blogname', 'cron', 'rewrite_rules', 'theme_mods'],
    ],
    'row number weight desc values' => [
        "SELECT option_name, row_number() OVER (ORDER BY weight DESC, option_id ASC) AS rn FROM wp_options ORDER BY rn",
        'rn',
        [1, 2, 3, 4, 5, 6],
    ],
    'rank ties by weight' => [
        'SELECT option_name, rank() OVER (ORDER BY weight DESC) AS r FROM wp_options ORDER BY option_id',
        'r',
        [1, 1, 3, 4, 4, 6],
    ],
    'dense rank ties by weight' => [
        'SELECT option_name, dense_rank() OVER (ORDER BY weight DESC) AS r FROM wp_options ORDER BY option_id',
        'r',
        [1, 1, 2, 3, 3, 4],
    ],
    'percent rank ties by weight' => [
        'SELECT option_name, percent_rank() OVER (ORDER BY weight DESC) AS p FROM wp_options ORDER BY option_id',
        'p',
        [0.0, 0.0, 0.4, 0.6, 0.6, 1.0],
    ],
    'cume dist ties by weight' => [
        'SELECT option_name, cume_dist() OVER (ORDER BY weight DESC) AS c FROM wp_options ORDER BY option_id',
        'c',
        [1 / 3, 1 / 3, 0.5, 5 / 6, 5 / 6, 1.0],
    ],
    'ntile distributes ordered rows' => [
        'SELECT option_name, ntile(4) OVER (ORDER BY option_id) AS bucket FROM wp_options ORDER BY option_id',
        'bucket',
        [1, 1, 2, 2, 3, 4],
    ],
    'lag previous option name' => [
        "SELECT option_name, lag(option_name) OVER (ORDER BY option_id) AS previous_name FROM wp_options ORDER BY option_id",
        'previous_name',
        [null, 'siteurl', 'home', 'blogname', 'cron', 'rewrite_rules'],
    ],
    'lag offset default option name' => [
        "SELECT option_name, lag(option_name, 2, 'start') OVER (ORDER BY option_id) AS previous_name FROM wp_options ORDER BY option_id",
        'previous_name',
        ['start', 'start', 'siteurl', 'home', 'blogname', 'cron'],
    ],
    'lead next option name' => [
        "SELECT option_name, lead(option_name) OVER (ORDER BY option_id) AS next_name FROM wp_options ORDER BY option_id",
        'next_name',
        ['home', 'blogname', 'cron', 'rewrite_rules', 'theme_mods', null],
    ],
    'lead offset default option name' => [
        "SELECT option_name, lead(option_name, 2, 'end') OVER (ORDER BY option_id) AS next_name FROM wp_options ORDER BY option_id",
        'next_name',
        ['blogname', 'cron', 'rewrite_rules', 'theme_mods', 'end', 'end'],
    ],
    'first value per autoload partition' => [
        'SELECT option_name, first_value(option_name) OVER (PARTITION BY autoload ORDER BY weight DESC, option_id ASC) AS first_name FROM wp_options ORDER BY option_id',
        'first_name',
        ['siteurl', 'siteurl', 'siteurl', 'cron', 'cron', 'cron'],
    ],
    'last value per autoload partition' => [
        'SELECT option_name, last_value(option_name) OVER (PARTITION BY autoload ORDER BY weight DESC, option_id ASC) AS last_name FROM wp_options ORDER BY option_id',
        'last_name',
        ['blogname', 'blogname', 'blogname', 'theme_mods', 'theme_mods', 'theme_mods'],
    ],
    'nth value per autoload partition' => [
        'SELECT option_name, nth_value(option_name, 2) OVER (PARTITION BY autoload ORDER BY weight DESC, option_id ASC) AS second_name FROM wp_options ORDER BY option_id',
        'second_name',
        ['home', 'home', 'home', 'rewrite_rules', 'rewrite_rules', 'rewrite_rules'],
    ],
    'partition row numbers reset' => [
        'SELECT option_name, row_number() OVER (PARTITION BY autoload ORDER BY weight DESC, option_id ASC) AS rn FROM wp_options ORDER BY option_id',
        'rn',
        [1, 2, 3, 1, 2, 3],
    ],
    'partition rank resets' => [
        'SELECT option_name, rank() OVER (PARTITION BY autoload ORDER BY weight DESC) AS r FROM wp_options ORDER BY option_id',
        'r',
        [1, 1, 3, 1, 1, 3],
    ],
    'partition dense rank resets' => [
        'SELECT option_name, dense_rank() OVER (PARTITION BY autoload ORDER BY weight DESC) AS r FROM wp_options ORDER BY option_id',
        'r',
        [1, 1, 2, 1, 1, 2],
    ],
    'partition ntile resets' => [
        'SELECT option_name, ntile(2) OVER (PARTITION BY autoload ORDER BY option_id) AS bucket FROM wp_options ORDER BY option_id',
        'bucket',
        [1, 1, 2, 1, 1, 2],
    ],
    'where filters before windowing' => [
        "SELECT option_name, row_number() OVER (ORDER BY weight DESC, option_id) AS rn FROM wp_options WHERE autoload = 'no' ORDER BY rn",
        'option_name',
        ['cron', 'rewrite_rules', 'theme_mods'],
    ],
    'where filtered row numbers are compact' => [
        "SELECT option_name, row_number() OVER (ORDER BY weight DESC, option_id) AS rn FROM wp_options WHERE autoload = 'no' ORDER BY rn",
        'rn',
        [1, 2, 3],
    ],
    'limit applies after projected window order' => [
        'SELECT option_name, row_number() OVER (ORDER BY weight DESC, option_id) AS rn FROM wp_options ORDER BY rn LIMIT 2',
        'option_name',
        ['siteurl', 'home'],
    ],
    'offset applies after projected window order' => [
        'SELECT option_name, row_number() OVER (ORDER BY weight DESC, option_id) AS rn FROM wp_options ORDER BY rn LIMIT 2 OFFSET 2',
        'option_name',
        ['blogname', 'cron'],
    ],
    'comma limit applies after projected window order' => [
        'SELECT option_name, row_number() OVER (ORDER BY weight DESC, option_id) AS rn FROM wp_options ORDER BY rn LIMIT 4, 2',
        'option_name',
        ['rewrite_rules', 'theme_mods'],
    ],
    'window alias can be final order term' => [
        'SELECT option_name, dense_rank() OVER (ORDER BY weight DESC) AS bucket FROM wp_options ORDER BY bucket DESC, option_id ASC',
        'option_name',
        ['theme_mods', 'cron', 'rewrite_rules', 'blogname', 'siteurl', 'home'],
    ],
    'window with text order asc' => [
        'SELECT option_name, row_number() OVER (ORDER BY option_name ASC) AS rn FROM wp_options ORDER BY option_id',
        'rn',
        [5, 3, 1, 2, 4, 6],
    ],
    'window with text order desc' => [
        'SELECT option_name, row_number() OVER (ORDER BY option_name DESC) AS rn FROM wp_options ORDER BY option_id',
        'rn',
        [2, 4, 6, 5, 3, 1],
    ],
    'window with expression order' => [
        'SELECT option_name, row_number() OVER (ORDER BY length(option_name), option_id) AS rn FROM wp_options ORDER BY option_id',
        'rn',
        [3, 1, 4, 2, 6, 5],
    ],
    'window partition by expression' => [
        'SELECT option_name, row_number() OVER (PARTITION BY length(autoload) ORDER BY option_id) AS rn FROM wp_options ORDER BY option_id',
        'rn',
        [1, 2, 3, 1, 2, 3],
    ],
    'lag in filtered partition' => [
        "SELECT option_name, lag(option_name, 1, 'none') OVER (PARTITION BY autoload ORDER BY option_id) AS previous_name FROM wp_options WHERE autoload = 'yes' ORDER BY option_id",
        'previous_name',
        ['none', 'siteurl', 'home'],
    ],
    'lead in filtered partition' => [
        "SELECT option_name, lead(option_name, 1, 'none') OVER (PARTITION BY autoload ORDER BY option_id) AS next_name FROM wp_options WHERE autoload = 'yes' ORDER BY option_id",
        'next_name',
        ['home', 'blogname', 'none'],
    ],
    'distinct keeps unique window rows' => [
        'SELECT DISTINCT autoload, dense_rank() OVER (PARTITION BY autoload ORDER BY weight DESC) AS r FROM wp_options ORDER BY autoload, r',
        'r',
        [1, 2, 1, 2],
    ],
    'distinct keeps partition labels' => [
        'SELECT DISTINCT autoload, dense_rank() OVER (PARTITION BY autoload ORDER BY weight DESC) AS r FROM wp_options ORDER BY autoload, r',
        'autoload',
        ['no', 'no', 'yes', 'yes'],
    ],
    'cte rows can be windowed' => [
        "WITH selected AS (SELECT option_name, weight FROM wp_options WHERE autoload = 'yes') SELECT option_name, row_number() OVER (ORDER BY weight DESC, option_name) AS rn FROM selected ORDER BY rn",
        'option_name',
        ['home', 'siteurl', 'blogname'],
    ],
    'cte window row numbers' => [
        "WITH selected AS (SELECT option_name, weight FROM wp_options WHERE autoload = 'yes') SELECT option_name, row_number() OVER (ORDER BY weight DESC, option_name) AS rn FROM selected ORDER BY rn",
        'rn',
        [1, 2, 3],
    ],
    'joined rows can be windowed' => [
        "SELECT o.option_name, row_number() OVER (ORDER BY m.rank ASC) AS rn FROM wp_options AS o JOIN meta AS m ON o.option_name = m.option_name ORDER BY rn",
        'o.option_name',
        ['home', 'siteurl', 'blogname'],
    ],
    'joined window row numbers' => [
        "SELECT o.option_name, row_number() OVER (ORDER BY m.rank ASC) AS rn FROM wp_options AS o JOIN meta AS m ON o.option_name = m.option_name ORDER BY rn",
        'rn',
        [1, 2, 3],
    ],
    'joined rank uses joined order expression' => [
        "SELECT o.option_name, rank() OVER (ORDER BY m.rank ASC) AS r FROM wp_options AS o JOIN meta AS m ON o.option_name = m.option_name ORDER BY o.option_id",
        'r',
        [2, 1, 3],
    ],
    'joined lead can read left values' => [
        "SELECT o.option_name, lead(o.option_name, 1, 'none') OVER (ORDER BY m.rank ASC) AS next_name FROM wp_options AS o JOIN meta AS m ON o.option_name = m.option_name ORDER BY m.rank",
        'next_name',
        ['siteurl', 'blogname', 'none'],
    ],
    'joined lag can read right values' => [
        "SELECT o.option_name, lag(m.rank, 1, 0) OVER (ORDER BY m.rank ASC) AS previous_rank FROM wp_options AS o JOIN meta AS m ON o.option_name = m.option_name ORDER BY m.rank",
        'previous_rank',
        [0, 1, 2],
    ],
    'aggregate sum defaults through current row' => [
        'SELECT option_name, sum(weight) OVER (ORDER BY option_id) AS running_weight FROM wp_options ORDER BY option_id',
        'running_weight',
        [9, 18, 23, 27, 31, 32],
    ],
    'aggregate count star defaults through current row' => [
        'SELECT option_name, count(*) OVER (ORDER BY option_id) AS running_count FROM wp_options ORDER BY option_id',
        'running_count',
        [1, 2, 3, 4, 5, 6],
    ],
    'aggregate avg partitions over text buckets' => [
        'SELECT option_name, avg(weight) OVER (PARTITION BY autoload ORDER BY option_id) AS running_average FROM wp_options ORDER BY option_id',
        'running_average',
        [9.0, 9.0, 23 / 3, 4.0, 4.0, 3.0],
    ],
    'aggregate max respects explicit row frame' => [
        'SELECT option_name, max(weight) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS local_max FROM wp_options ORDER BY option_id',
        'local_max',
        [9, 9, 9, 5, 4, 4],
    ],
];

$joinedTables = $tables + [
    'meta' => [
        ['option_name' => 'home', 'rank' => 1],
        ['option_name' => 'siteurl', 'rank' => 2],
        ['option_name' => 'blogname', 'rank' => 3],
    ],
];

foreach ($cases as $name => [$sql, $field, $expected]) {
    $tests['select sql window text ' . $name] = static function (TestRunner $t) use ($sql, $field, $expected, $column, $joinedTables): void {
        $sourceTables = str_contains($sql, 'meta') ? $joinedTables : null;
        $actual = $sourceTables === null
            ? $column($sql, $field)
            : array_column(SQLiteSelectSql::execute($sql, $sourceTables), $field);
        $t->same($expected, $actual);
    };
}

$tests['select sql window text plan keeps window expression metadata'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan('SELECT option_name, row_number() OVER (PARTITION BY autoload ORDER BY weight DESC) AS rn FROM wp_options', $tables);
    $t->same('window', $plan['select'][1]['type']);
    $t->same('row_number', $plan['select'][1]['function']);
    $t->same('rn', $plan['select'][1]['alias']);
    $t->same('autoload', $plan['select'][1]['partitionBy'][0]['name']);
    $t->same('weight', $plan['select'][1]['orderBy'][0]['expression']['name']);
    $t->same('DESC', $plan['select'][1]['orderBy'][0]['direction']);
};

$errorCases = [
    'missing over parens' => 'SELECT row_number() OVER ORDER BY option_id AS rn FROM wp_options',
    'unsupported function' => 'SELECT definitely_missing_window(weight) OVER (ORDER BY option_id) AS s FROM wp_options',
    'lag without argument' => 'SELECT lag() OVER (ORDER BY option_id) AS previous_name FROM wp_options',
    'ntile without argument' => 'SELECT ntile() OVER (ORDER BY option_id) AS bucket FROM wp_options',
    'ntile zero bucket' => 'SELECT ntile(0) OVER (ORDER BY option_id) AS bucket FROM wp_options',
    'bad partition by' => 'SELECT row_number() OVER (PARTITION BY ORDER BY option_id) AS rn FROM wp_options',
    'bad order by' => 'SELECT row_number() OVER (ORDER BY) AS rn FROM wp_options',
];

foreach ($errorCases as $name => $sql) {
    $tests['select sql window text rejects ' . $name] = static function (TestRunner $t) use ($sql, $tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, $tables));
    };
}

return $tests;
