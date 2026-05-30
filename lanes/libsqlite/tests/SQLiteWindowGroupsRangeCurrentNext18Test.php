<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'alpha_cache', 'autoload' => 'yes', 'bytes' => 10],
    ['option_id' => 2, 'option_name' => 'beta_cache', 'autoload' => 'yes', 'bytes' => 10],
    ['option_id' => 3, 'option_name' => 'cron_lock', 'autoload' => 'no', 'bytes' => 20],
    ['option_id' => 4, 'option_name' => 'plugin_rules', 'autoload' => 'no', 'bytes' => 30],
    ['option_id' => 5, 'option_name' => 'theme_mods', 'autoload' => 'no', 'bytes' => 30],
    ['option_id' => 6, 'option_name' => 'transient_blob', 'autoload' => 'no', 'bytes' => 40],
];

$query = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['wp_options' => $options]);
$column = static fn (string $sql, string $name): array => array_column($query($sql), $name);

$cases = [
    'groups current next count star peers' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [3, 3, 3, 3, 3, 1]],
    'groups current next sum peers' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [40, 40, 80, 100, 100, 40]],
    'groups current next concat peers' => [static fn (): mixed => $column('SELECT group_concat(option_name) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS names FROM wp_options ORDER BY option_id', 'names'), ['alpha_cache,beta_cache,cron_lock', 'alpha_cache,beta_cache,cron_lock', 'cron_lock,plugin_rules,theme_mods', 'plugin_rules,theme_mods,transient_blob', 'plugin_rules,theme_mods,transient_blob', 'transient_blob']],
    'groups current row count peers' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 0 FOLLOWING) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [2, 2, 1, 2, 2, 1]],
    'groups current row sum peers' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 0 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [20, 20, 20, 60, 60, 40]],
    'groups current two following count clamps' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [5, 5, 4, 3, 3, 1]],
    'groups current two following sum clamps' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [100, 100, 120, 100, 100, 40]],
    'groups current next exclude current count' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [2, 2, 2, 2, 2, 0]],
    'groups current next exclude current sum' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [30, 30, 60, 70, 70, null]],
    'groups current next exclude group count' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE GROUP) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [1, 1, 2, 1, 1, 0]],
    'groups current next exclude group sum' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE GROUP) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [20, 20, 60, 40, 40, null]],
    'groups current next exclude ties count' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [2, 2, 3, 2, 2, 1]],
    'groups current next exclude ties concat' => [static fn (): mixed => $column('SELECT group_concat(option_name) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS names FROM wp_options ORDER BY option_id', 'names'), ['alpha_cache,cron_lock', 'beta_cache,cron_lock', 'cron_lock,plugin_rules,theme_mods', 'plugin_rules,transient_blob', 'theme_mods,transient_blob', 'transient_blob']],
    'groups partitioned sum by autoload' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (PARTITION BY autoload ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [20, 20, 80, 100, 100, 40]],
    'groups partitioned count by autoload' => [static fn (): mixed => $column('SELECT count(*) OVER (PARTITION BY autoload ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [2, 2, 3, 3, 3, 1]],
    'range current ten count peers' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [3, 3, 3, 3, 3, 1]],
    'range current ten sum peers' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [40, 40, 80, 100, 100, 40]],
    'range current five count current peers only' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 5 FOLLOWING) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [2, 2, 1, 2, 2, 1]],
    'range current five concat current peers only' => [static fn (): mixed => $column('SELECT group_concat(option_name) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 5 FOLLOWING) AS names FROM wp_options ORDER BY option_id', 'names'), ['alpha_cache,beta_cache', 'alpha_cache,beta_cache', 'cron_lock', 'plugin_rules,theme_mods', 'plugin_rules,theme_mods', 'transient_blob']],
    'range current ten exclude group sum' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING EXCLUDE GROUP) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [20, 20, 60, 40, 40, null]],
    'range current ten exclude ties count' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING EXCLUDE TIES) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [2, 2, 3, 2, 2, 1]],
    'range fractional following count' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10.0 FOLLOWING) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [3, 3, 3, 3, 3, 1]],
    'range fractional following sum' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 9.5 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [20, 20, 20, 60, 60, 40]],
    'rows current next remains available count' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [2, 2, 2, 2, 2, 1]],
    'rows current next remains available sum' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [20, 30, 50, 60, 70, 40]],
    'range partitioned current ten count' => [static fn (): mixed => $column('SELECT count(*) OVER (PARTITION BY autoload ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [2, 2, 3, 3, 3, 1]],
    'range partitioned current ten sum' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (PARTITION BY autoload ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [20, 20, 80, 100, 100, 40]],
    'range current row exclude current count' => [static fn (): mixed => $column('SELECT count(*) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 0 FOLLOWING EXCLUDE CURRENT ROW) AS window_count FROM wp_options ORDER BY option_id', 'window_count'), [1, 1, 0, 1, 1, 0]],
    'range current row exclude current sum' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 0 FOLLOWING EXCLUDE CURRENT ROW) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [10, 10, null, 30, 30, null]],
    'groups current next count text argument' => [static fn (): mixed => $column('SELECT count(option_name) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS named_rows FROM wp_options ORDER BY option_id', 'named_rows'), [3, 3, 3, 3, 3, 1]],
    'range current next count text argument' => [static fn (): mixed => $column('SELECT count(option_name) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS named_rows FROM wp_options ORDER BY option_id', 'named_rows'), [3, 3, 3, 3, 3, 1]],
    'groups current next no others alias keys preserved' => [static fn (): mixed => array_keys($query('SELECT option_id AS id, sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id')[0]), ['id', 'window_sum']],
    'range current next limit applies after window' => [static fn (): mixed => $column('SELECT option_name AS name, sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id LIMIT 3', 'window_sum'), [40, 40, 80]],
    'groups current next offset applies after window' => [static fn (): mixed => $column('SELECT option_name AS name, sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id LIMIT 2 OFFSET 3', 'window_sum'), [100, 100]],
    'range current next where filters before window' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS window_sum FROM wp_options WHERE option_id >= 3 ORDER BY option_id', 'window_sum'), [80, 100, 100, 40]],
    'groups current next filter preserves peers before exclusion' => [static fn (): mixed => $column("SELECT sum(bytes) FILTER (WHERE autoload = 'no') OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id", 'window_sum'), [20, 20, 80, 100, 100, 40]],
    'groups current next filter exclude group applies after frame' => [static fn (): mixed => $column("SELECT count(*) FILTER (WHERE autoload = 'no') OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE GROUP) AS window_count FROM wp_options ORDER BY option_id", 'window_count'), [1, 1, 2, 1, 1, 0]],
    'range current next filter preserves numeric peer bounds' => [static fn (): mixed => $column("SELECT sum(bytes) FILTER (WHERE option_name LIKE '%_cache') OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id", 'window_sum'), [20, 20, null, null, null, null]],
    'range current row filter exclude current empties current-only peers' => [static fn (): mixed => $column("SELECT count(*) FILTER (WHERE autoload = 'no') OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 0 FOLLOWING EXCLUDE CURRENT ROW) AS window_count FROM wp_options ORDER BY option_id", 'window_count'), [0, 0, 0, 1, 1, 0]],
    'plan records groups frame unit' => [static fn (): mixed => SQLiteSelectSql::plan('SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_sum FROM wp_options', ['wp_options' => $options])['select'][0]['frame']['unit'], 'GROUPS'],
    'plan records range frame unit' => [static fn (): mixed => SQLiteSelectSql::plan('SELECT sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS window_sum FROM wp_options', ['wp_options' => $options])['select'][0]['frame']['unit'], 'RANGE'],
    'plan records following offset' => [static fn (): mixed => SQLiteSelectSql::plan('SELECT sum(bytes) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10.5 FOLLOWING) AS window_sum FROM wp_options', ['wp_options' => $options])['select'][0]['frame']['following'], 10.5],
    'plan records exclude mode' => [static fn (): mixed => SQLiteSelectSql::plan('SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS window_sum FROM wp_options', ['wp_options' => $options])['select'][0]['frame']['exclude'], 'TIES'],
    'query executes aggregate frame plan directly' => [static fn (): mixed => array_column(SQLiteSelectQuery::execute(['from' => $options, 'select' => [['type' => 'window', 'function' => 'sum', 'arguments' => [['type' => 'column', 'name' => 'bytes']], 'orderBy' => [['expression' => ['type' => 'column', 'name' => 'bytes'], 'direction' => 'ASC']], 'frame' => ['unit' => 'GROUPS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'], 'alias' => 'window_sum']]]), 'window_sum'), [40, 40, 80, 100, 100, 40]],
    'query direct count wildcard plan' => [static fn (): mixed => array_column(SQLiteSelectQuery::execute(['from' => $options, 'select' => [['type' => 'window', 'function' => 'count', 'arguments' => [['type' => 'wildcard']], 'orderBy' => [['expression' => ['type' => 'column', 'name' => 'bytes'], 'direction' => 'ASC']], 'frame' => ['unit' => 'RANGE', 'preceding' => 0, 'following' => 10, 'exclude' => 'NO OTHERS'], 'alias' => 'window_count']]]), 'window_count'), [3, 3, 3, 3, 3, 1]],
    'query direct filtered json range accepts boolean keys numerically' => [static fn (): mixed => array_column(SQLiteSelectQuery::execute(['from' => [['option_name' => 'off', 'enabled' => false, 'keep' => true], ['option_name' => 'on', 'enabled' => true, 'keep' => true], ['option_name' => 'skip', 'enabled' => true, 'keep' => false]], 'select' => [['type' => 'window', 'function' => 'json_group_array', 'arguments' => [['type' => 'column', 'name' => 'option_name']], 'filter' => ['type' => 'comparison', 'left' => ['type' => 'column', 'name' => 'keep'], 'operator' => '=', 'right' => ['type' => 'literal', 'value' => true]], 'orderBy' => [['expression' => ['type' => 'column', 'name' => 'enabled'], 'direction' => 'ASC']], 'frame' => ['unit' => 'RANGE', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'], 'alias' => 'names']]]), 'names'), ['["off","on"]', '["on"]', '["on"]']],
    'groups preceding current sum peers' => [static fn (): mixed => $column('SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW) AS window_sum FROM wp_options ORDER BY option_id', 'window_sum'), [20, 20, 40, 80, 80, 100]],
    'plan records preceding offset' => [static fn (): mixed => SQLiteSelectSql::plan('SELECT sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW) AS window_sum FROM wp_options', ['wp_options' => $options])['select'][0]['frame']['preceding'], 1],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upstream corpus window groups range current next18 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['upstream corpus window groups range current next18 rejects frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT sum(bytes) OVER (GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 reports sql frame without order'] = static function (TestRunner $t) use ($options): void {
    try {
        SQLiteSelectSql::execute('SELECT sum(bytes) OVER (GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options', ['wp_options' => $options]);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite SELECT SQL RANGE/GROUPS window frame needs ORDER BY', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected SQL GROUPS frame without ORDER BY to be rejected');
};

$tests['upstream corpus window groups range current next18 plan rejects frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::plan('SELECT sum(bytes) OVER (GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 rejects named groups frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT sum(bytes) OVER framed FROM wp_options WINDOW framed AS (GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING)', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 rejects named range frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::plan('SELECT sum(bytes) OVER framed FROM wp_options WINDOW framed AS (RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING)', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 rejects partitioned groups frame without order'] = static function (TestRunner $t) use ($options): void {
    try {
        SQLiteSelectSql::execute('SELECT sum(bytes) OVER (PARTITION BY autoload GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options', ['wp_options' => $options]);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite SELECT SQL RANGE/GROUPS window frame needs ORDER BY', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected partitioned SQL GROUPS frame without ORDER BY to be rejected');
};

$tests['upstream corpus window groups range current next18 rejects partitioned range frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::plan('SELECT last_value(bytes) OVER (PARTITION BY autoload RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) FROM wp_options', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 rejects named partitioned range frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT json_group_array(option_name) OVER framed FROM wp_options WINDOW framed AS (PARTITION BY autoload RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING)', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 named groups frame inherits order'] = static function (TestRunner $t) use ($options): void {
    $rows = SQLiteSelectSql::execute('SELECT sum(bytes) OVER framed AS window_sum FROM wp_options WINDOW framed AS (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) ORDER BY option_id', ['wp_options' => $options]);

    $t->same([40, 40, 80, 100, 100, 40], array_column($rows, 'window_sum'));
};

$tests['upstream corpus window groups range current next18 named range frame inherits order'] = static function (TestRunner $t) use ($options): void {
    $rows = SQLiteSelectSql::execute('SELECT count(*) OVER framed AS window_count FROM wp_options WINDOW framed AS (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) ORDER BY option_id', ['wp_options' => $options]);

    $t->same([3, 3, 3, 3, 3, 1], array_column($rows, 'window_count'));
};

$tests['upstream corpus window groups range current next18 rows frame without order remains valid'] = static function (TestRunner $t) use ($options): void {
    $t->same([20, 30, 50, 60, 70, 40], array_column(SQLiteSelectSql::execute('SELECT sum(bytes) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_sum FROM wp_options ORDER BY option_id', ['wp_options' => $options]), 'window_sum'));
};

$tests['upstream corpus window groups range current next18 plan accepts rows frame without order'] = static function (TestRunner $t) use ($options): void {
    $plan = SQLiteSelectSql::plan('SELECT count(*) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_count FROM wp_options', ['wp_options' => $options]);

    $t->same('ROWS', $plan['select'][0]['frame']['unit']);
    $t->same([], $plan['select'][0]['orderBy']);
};

$tests['upstream corpus window groups range current next18 named rows frame without order remains valid'] = static function (TestRunner $t) use ($options): void {
    $rows = SQLiteSelectSql::execute('SELECT count(*) OVER framed AS window_count FROM wp_options WINDOW framed AS (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) ORDER BY option_id', ['wp_options' => $options]);

    $t->same([2, 2, 2, 2, 2, 1], array_column($rows, 'window_count'));
};

$tests['upstream corpus window groups range current next18 direct query accepts rows frame without order'] = static function (TestRunner $t) use ($options): void {
    $rows = SQLiteSelectQuery::execute([
        'from' => $options,
        'select' => [[
            'type' => 'window',
            'function' => 'sum',
            'arguments' => [['type' => 'column', 'name' => 'bytes']],
            'frame' => ['unit' => 'ROWS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
            'alias' => 'window_sum',
        ]],
    ]);

    $t->same([20, 30, 50, 60, 70, 40], array_column($rows, 'window_sum'));
};

$tests['upstream corpus window groups range current next18 rejects json frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT json_group_array(option_name) OVER (GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 plan rejects range frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::plan('SELECT json_group_object(option_name, bytes) OVER (RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) FROM wp_options', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 rejects json object frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT json_group_object(option_name, bytes) OVER (RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) FROM wp_options', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 direct query rejects groups frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectQuery::execute([
        'from' => $options,
        'select' => [[
            'type' => 'window',
            'function' => 'sum',
            'arguments' => [['type' => 'column', 'name' => 'bytes']],
            'frame' => ['unit' => 'GROUPS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
            'alias' => 'window_sum',
        ]],
    ]));
};

$tests['upstream corpus window groups range current next18 direct query rejects partitioned groups frame without order'] = static function (TestRunner $t) use ($options): void {
    try {
        SQLiteSelectQuery::execute([
            'from' => $options,
            'select' => [[
                'type' => 'window',
                'function' => 'sum',
                'arguments' => [['type' => 'column', 'name' => 'bytes']],
                'partitionBy' => [['type' => 'column', 'name' => 'autoload']],
                'frame' => ['unit' => 'GROUPS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
                'alias' => 'window_sum',
            ]],
        ]);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite SELECT query RANGE/GROUPS window frame needs ORDER BY', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected direct partitioned GROUPS frame without ORDER BY to be rejected');
};

$tests['upstream corpus window groups range current next18 direct query accepts partitioned rows frame without order'] = static function (TestRunner $t) use ($options): void {
    $rows = SQLiteSelectQuery::execute([
        'from' => $options,
        'select' => [[
            'type' => 'window',
            'function' => 'sum',
            'arguments' => [['type' => 'column', 'name' => 'bytes']],
            'partitionBy' => [['type' => 'column', 'name' => 'autoload']],
            'frame' => ['unit' => 'ROWS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
            'alias' => 'window_sum',
        ]],
    ]);

    $t->same([20, 10, 50, 60, 70, 40], array_column($rows, 'window_sum'));
};

$tests['upstream corpus window groups range current next18 reports direct frame without order'] = static function (TestRunner $t) use ($options): void {
    try {
        SQLiteSelectQuery::execute([
            'from' => $options,
            'select' => [[
                'type' => 'window',
                'function' => 'sum',
                'arguments' => [['type' => 'column', 'name' => 'bytes']],
                'frame' => ['unit' => 'GROUPS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
                'alias' => 'window_sum',
            ]],
        ]);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite SELECT query RANGE/GROUPS window frame needs ORDER BY', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected direct GROUPS frame without ORDER BY to be rejected');
};

$tests['upstream corpus window groups range current next18 reports direct range frame without order'] = static function (TestRunner $t) use ($options): void {
    try {
        SQLiteSelectQuery::execute([
            'from' => $options,
            'select' => [[
                'type' => 'window',
                'function' => 'count',
                'arguments' => [['type' => 'wildcard']],
                'frame' => ['unit' => 'RANGE', 'preceding' => 0, 'following' => 10, 'exclude' => 'NO OTHERS'],
                'alias' => 'window_count',
            ]],
        ]);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite SELECT query RANGE/GROUPS window frame needs ORDER BY', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected direct RANGE frame without ORDER BY to be rejected');
};

$tests['upstream corpus window groups range current next18 direct query rejects json object range frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectQuery::execute([
        'from' => $options,
        'select' => [[
            'type' => 'window',
            'function' => 'json_group_object',
            'arguments' => [
                ['type' => 'column', 'name' => 'option_name'],
                ['type' => 'column', 'name' => 'bytes'],
            ],
            'frame' => ['unit' => 'RANGE', 'preceding' => 0, 'following' => 10, 'exclude' => 'NO OTHERS'],
            'alias' => 'option_bytes',
        ]],
    ]));
};

$tests['upstream corpus window groups range current next18 rejects nonnumeric range key'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT sum(bytes) OVER (ORDER BY option_name RANGE BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 direct query nonnumeric filtered range uses peer group'] = static function (TestRunner $t) use ($options): void {
    $rows = SQLiteSelectQuery::execute([
        'from' => $options,
        'select' => [[
            'type' => 'window',
            'function' => 'json_group_array',
            'arguments' => [['type' => 'column', 'name' => 'option_name']],
            'filter' => ['type' => 'comparison', 'left' => ['type' => 'column', 'name' => 'autoload'], 'operator' => '=', 'right' => ['type' => 'literal', 'value' => 'no']],
            'orderBy' => [['expression' => ['type' => 'column', 'name' => 'option_name'], 'direction' => 'ASC']],
            'frame' => ['unit' => 'RANGE', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
            'alias' => 'names',
        ]],
    ]);

    $t->same(['[]', '[]', '["cron_lock"]', '["plugin_rules"]', '["theme_mods"]', '["transient_blob"]'], array_column($rows, 'names'));
};

$tests['upstream corpus window groups range current next18 rejects framed ranking function'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT row_number() OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 rejects value range frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT last_value(bytes) OVER (RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) FROM wp_options', ['wp_options' => $options]));
};

$tests['upstream corpus window groups range current next18 direct query rejects value groups frame without order'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectQuery::execute([
        'from' => $options,
        'select' => [[
            'type' => 'window',
            'function' => 'last_value',
            'arguments' => [['type' => 'column', 'name' => 'bytes']],
            'frame' => ['unit' => 'GROUPS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
            'alias' => 'last_bytes',
        ]],
    ]));
};

return $tests;
