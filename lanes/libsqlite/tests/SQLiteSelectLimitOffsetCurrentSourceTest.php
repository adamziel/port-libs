<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => 0],
];
$meta = [
    ['option_id' => 1, 'meta_key' => 'public', 'priority' => 10],
    ['option_id' => 2, 'meta_key' => 'public', 'priority' => 20],
    ['option_id' => 3, 'meta_key' => 'private', 'priority' => 30],
    ['option_id' => 5, 'meta_key' => 'plugin', 'priority' => 40],
];

$cases = [
    'limit scalar subquery counts autoloaded rows' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT 3 FROM wp_options WHERE autoload = 'yes')",
        ['wp_options' => $options],
        [1, 2, 3],
        3,
        0,
    ],
    'offset scalar subquery counts hidden rows' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT 2 FROM wp_options WHERE option_name GLOB '_*')",
        ['wp_options' => $options],
        [3, 4],
        2,
        2,
    ],
    'comma limit resolves offset then count from source' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT 3 FROM wp_options WHERE autoload = 'yes'), (SELECT 2 FROM wp_options WHERE autoload = 'no')",
        ['wp_options' => $options],
        [4, 5],
        2,
        3,
    ],
    'limit expression composes scalar subquery arithmetic' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT 3 FROM wp_options WHERE autoload = 'yes') - 1",
        ['wp_options' => $options],
        [1, 2],
        2,
        0,
    ],
    'offset expression composes scalar subquery arithmetic' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT 3 FROM wp_options WHERE autoload = 'yes') - 1",
        ['wp_options' => $options],
        [3, 4],
        2,
        2,
    ],
    'limit subquery can read joined source table' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT 2 FROM option_meta WHERE meta_key = 'public')",
        ['wp_options' => $options, 'option_meta' => $meta],
        [1, 2],
        2,
        0,
    ],
    'offset subquery can read joined source table' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT 2 FROM option_meta WHERE priority < 30)",
        ['wp_options' => $options, 'option_meta' => $meta],
        [3, 4],
        2,
        2,
    ],
    'negative limit from subquery returns rows after offset' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT -1 FROM wp_options WHERE option_id = 1) OFFSET (SELECT 3 FROM wp_options WHERE autoload = 'yes')",
        ['wp_options' => $options],
        [4, 5, 6],
        -1,
        3,
    ],
    'empty scalar subquery rejects as non numeric limit' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT 0 FROM wp_options WHERE autoload = 'missing')",
        ['wp_options' => $options],
        InvalidArgumentException::class,
        null,
        null,
    ],
    'constant select limit subquery resolves lane source' => [
        "SELECT 'ready' AS status LIMIT (SELECT 1 FROM wp_options WHERE option_id = 1)",
        ['wp_options' => $options],
        ['ready'],
        1,
        0,
    ],
    'constant select empty limit subquery rejects' => [
        "SELECT 'ready' AS status LIMIT (SELECT 0 FROM wp_options WHERE option_id = 99)",
        ['wp_options' => $options],
        InvalidArgumentException::class,
        null,
        null,
    ],
    'constant select offset subquery skips single row' => [
        "SELECT 'ready' AS status LIMIT 1 OFFSET (SELECT 1 FROM wp_options WHERE option_id = 1)",
        ['wp_options' => $options],
        [],
        1,
        1,
    ],
    'compound limit subquery resolves current tables' => [
        "SELECT option_id FROM wp_options WHERE option_id <= 3 UNION ALL SELECT option_id FROM wp_options WHERE option_id >= 4 ORDER BY option_id LIMIT (SELECT 3 FROM wp_options WHERE autoload = 'yes')",
        ['wp_options' => $options],
        [1, 2, 3],
        3,
        0,
    ],
    'compound offset subquery resolves current tables' => [
        "SELECT option_id FROM wp_options WHERE option_id <= 3 UNION ALL SELECT option_id FROM wp_options WHERE option_id >= 4 ORDER BY option_id LIMIT 2 OFFSET (SELECT 3 FROM wp_options WHERE autoload = 'yes')",
        ['wp_options' => $options],
        [4, 5],
        2,
        3,
    ],
    'cte source participates in limit scalar subquery' => [
        "WITH picked AS (SELECT 3 AS row_limit FROM wp_options WHERE autoload = 'yes') SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT row_limit FROM picked)",
        ['wp_options' => $options],
        [1, 2, 3],
        3,
        0,
    ],
    'cte source participates in offset scalar subquery' => [
        "WITH picked AS (SELECT 2 AS row_offset FROM wp_options WHERE option_name GLOB '_*') SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT row_offset FROM picked)",
        ['wp_options' => $options],
        [3, 4],
        2,
        2,
    ],
    'json table source participates in limit scalar subquery' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT 3 FROM json_each('[1,2,3]'))",
        ['wp_options' => $options],
        [1, 2, 3],
        3,
        0,
    ],
    'json hidden source participates in offset scalar subquery' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT 2 FROM json_each WHERE json = '[1,2]')",
        ['wp_options' => $options],
        [3, 4],
        2,
        2,
    ],
    'ordered subquery limit source first row observes order' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT option_id FROM wp_options WHERE option_id IN (1, 3) ORDER BY option_id DESC)",
        ['wp_options' => $options],
        [1, 2, 3],
        3,
        0,
    ],
    'limit scalar subquery first row wins' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT option_id FROM wp_options WHERE option_id IN (2, 3) ORDER BY option_id)",
        ['wp_options' => $options],
        [1, 2],
        2,
        0,
    ],
    'offset scalar subquery first row wins' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT option_id FROM wp_options WHERE option_id IN (2, 3) ORDER BY option_id)",
        ['wp_options' => $options],
        [3, 4],
        2,
        2,
    ],
    'limit scalar subquery null rejects as non numeric' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT autoload FROM wp_options WHERE option_id = 6)",
        ['wp_options' => $options],
        InvalidArgumentException::class,
        null,
        null,
    ],
    'limit scalar subquery text rejects as non numeric' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT option_name FROM wp_options WHERE option_id = 1)",
        ['wp_options' => $options],
        InvalidArgumentException::class,
        null,
        null,
    ],
    'limit scalar subquery multi-column rejects' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT option_id, option_name FROM wp_options WHERE option_id = 1)",
        ['wp_options' => $options],
        InvalidArgumentException::class,
        null,
        null,
    ],
    'offset scalar subquery text rejects as non numeric' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 1 OFFSET (SELECT option_name FROM wp_options WHERE option_id = 1)",
        ['wp_options' => $options],
        InvalidArgumentException::class,
        null,
        null,
    ],
];

foreach ($cases as $name => [$sql, $tables, $expected, $expectedLimit, $expectedOffset]) {
    $tests['select limit offset current source resolves ' . $name] = static function (TestRunner $t) use ($sql, $tables, $expected, $expectedLimit, $expectedOffset): void {
        if ($expected === InvalidArgumentException::class) {
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, $tables));
            return;
        }

        $rows = SQLiteSelectSql::execute($sql, $tables);
        $firstColumn = $rows === [] ? [] : array_column($rows, array_key_first($rows[0]));
        $t->same($expected, $firstColumn);

        $plan = SQLiteSelectSql::plan($sql, $tables);
        if (isset($plan['compound'])) {
            $t->same($expectedLimit, $plan['compound']['limit']);
            $t->same($expectedOffset, $plan['compound']['offset']);
            return;
        }
        $t->same($expectedLimit, $plan['limit']);
        $t->same($expectedOffset, $plan['offset']);
    };
}

return $tests;
