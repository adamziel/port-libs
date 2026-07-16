<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$settings = [
    ['id' => 1, 'setting_key' => 'alpha_cache', 'category' => 'core', 'bytes' => 10],
    ['id' => 2, 'setting_key' => 'beta_cache', 'category' => 'core', 'bytes' => 10],
    ['id' => 3, 'setting_key' => 'queue_lock', 'category' => 'runtime', 'bytes' => 20],
    ['id' => 4, 'setting_key' => 'render_rules', 'category' => 'runtime', 'bytes' => 30],
];

$tests['window groups range no order guard rejects sql groups frame'] = static function (TestRunner $t) use ($settings): void {
    try {
        SQLiteSelectSql::execute(
            'SELECT sum(bytes) OVER (GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM app_settings',
            ['app_settings' => $settings],
        );
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite SELECT SQL RANGE/GROUPS window frame needs ORDER BY', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected SQL GROUPS frame without ORDER BY to be rejected');
};

$tests['window groups range no order guard rejects sql range frame'] = static function (TestRunner $t) use ($settings): void {
    try {
        SQLiteSelectSql::plan(
            'SELECT count(*) OVER (RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) FROM app_settings',
            ['app_settings' => $settings],
        );
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite SELECT SQL RANGE/GROUPS window frame needs ORDER BY', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected SQL RANGE frame without ORDER BY to be rejected');
};

$tests['window groups range no order guard rejects named partitioned range frame'] = static function (TestRunner $t) use ($settings): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute(
            'SELECT sum(bytes) OVER framed FROM app_settings WINDOW framed AS (PARTITION BY category RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING)',
            ['app_settings' => $settings],
        ),
    );
};

$tests['window groups range no order guard allows rows frame without order'] = static function (TestRunner $t) use ($settings): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT setting_key, sum(bytes) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS byte_window FROM app_settings ORDER BY id',
        ['app_settings' => $settings],
    );

    $t->same([20, 30, 50, 30], array_column($rows, 'byte_window'));
};

$tests['window groups range no order guard direct query rejects groups frame'] = static function (TestRunner $t) use ($settings): void {
    try {
        SQLiteSelectQuery::execute([
            'from' => $settings,
            'select' => [[
                'type' => 'window',
                'function' => 'sum',
                'arguments' => [['type' => 'column', 'name' => 'bytes']],
                'frame' => ['unit' => 'GROUPS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
                'alias' => 'byte_window',
            ]],
        ]);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite SELECT query RANGE/GROUPS window frame needs ORDER BY', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected direct GROUPS frame without ORDER BY to be rejected');
};

$tests['window groups range no order guard direct query rejects range frame'] = static function (TestRunner $t) use ($settings): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectQuery::execute([
        'from' => $settings,
        'select' => [[
            'type' => 'window',
            'function' => 'count',
            'arguments' => [['type' => 'wildcard']],
            'partitionBy' => [['type' => 'column', 'name' => 'category']],
            'frame' => ['unit' => 'RANGE', 'preceding' => 0, 'following' => 10, 'exclude' => 'NO OTHERS'],
            'alias' => 'setting_count',
        ]],
    ]));
};

$tests['window groups range no order guard direct query allows rows frame'] = static function (TestRunner $t) use ($settings): void {
    $rows = SQLiteSelectQuery::execute([
        'from' => $settings,
        'select' => [[
            'type' => 'window',
            'function' => 'sum',
            'arguments' => [['type' => 'column', 'name' => 'bytes']],
            'frame' => ['unit' => 'ROWS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'],
            'alias' => 'byte_window',
        ]],
    ]);

    $t->same([20, 30, 50, 30], array_column($rows, 'byte_window'));
};

$tests['window groups range no order guard ordered range still works'] = static function (TestRunner $t) use ($settings): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT count(*) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) AS setting_count FROM app_settings ORDER BY id',
        ['app_settings' => $settings],
    );

    $t->same([3, 3, 2, 1], array_column($rows, 'setting_count'));
};

return $tests;
