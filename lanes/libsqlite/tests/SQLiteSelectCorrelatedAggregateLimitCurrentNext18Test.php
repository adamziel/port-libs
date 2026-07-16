<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => 0],
];

$meta = [
    ['meta_option_id' => 1, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 1, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20],
    ['meta_option_id' => 2, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 2, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20],
    ['meta_option_id' => 3, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 4, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30],
    ['meta_option_id' => 4, 'meta_key' => 'ttl', 'meta_value' => 'short', 'weight' => 40],
    ['meta_option_id' => 5, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30],
    ['meta_option_id' => 5, 'meta_key' => 'ttl', 'meta_value' => 'long', 'weight' => 40],
    ['meta_option_id' => 5, 'meta_key' => 'kind', 'meta_value' => 'update', 'weight' => 50],
];

$tables = ['wp_options' => $options, 'option_meta' => $meta];

$cases = [
    'limit grouped count strips hidden order column' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING sum(weight) = 30 ORDER BY meta_option_id)",
        [1, 2],
        2,
        0,
    ],
    'limit grouped count order desc picks larger group first' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING sum(weight) >= 30 ORDER BY sum(weight) DESC)",
        [1, 2, 3],
        3,
        0,
    ],
    'limit grouped sum strips aggregate order expression' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT sum(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 3 ORDER BY sum(weight) + 1)",
        [1, 2, 3, 4, 5, 6],
        10,
        0,
    ],
    'limit grouped avg numeric casts to integer' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT avg(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 1 ORDER BY meta_option_id)",
        [1, 2, 3, 4, 5, 6],
        15,
        0,
    ],
    'limit grouped min returns first ordered qualifying group' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT min(weight) FROM option_meta GROUP BY meta_option_id HAVING count(weight) >= 2 ORDER BY meta_option_id DESC)",
        [1, 2, 3, 4, 5, 6],
        30,
        0,
    ],
    'limit grouped max returns hidden order expression safe scalar' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT max(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 2 ORDER BY max(weight) + count(weight))",
        [1, 2, 3, 4, 5, 6],
        20,
        0,
    ],
    'limit grouped total returns numeric scalar' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT total(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 3 ORDER BY meta_option_id)",
        [1, 2, 3, 4, 5, 6],
        10,
        0,
    ],
    'offset grouped count strips hidden order column' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING sum(weight) = 30 ORDER BY meta_option_id)",
        [3, 4],
        2,
        2,
    ],
    'offset grouped count desc uses first ordered group' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING sum(weight) >= 30 ORDER BY sum(weight) DESC)",
        [4, 5],
        2,
        3,
    ],
    'offset grouped sum hidden expression' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT sum(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 4 ORDER BY sum(weight) + count(weight))",
        [],
        2,
        70,
    ],
    'comma limit grouped offset then grouped count' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 1 ORDER BY meta_option_id), (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 5 ORDER BY meta_option_id)",
        [3, 4, 5],
        3,
        2,
    ],
    'limit arithmetic from grouped scalar subquery' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 5 ORDER BY count(weight) DESC) + 1",
        [1, 2, 3, 4],
        4,
        0,
    ],
    'offset arithmetic from grouped scalar subquery' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 5 ORDER BY count(weight) DESC) - 1",
        [3, 4],
        2,
        2,
    ],
    'limit grouped subquery cte source' => [
        "WITH weights(meta_option_id, weight) AS (SELECT meta_option_id, weight FROM option_meta) SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM weights GROUP BY meta_option_id HAVING meta_option_id = 5 ORDER BY meta_option_id)",
        [1, 2, 3],
        3,
        0,
    ],
    'offset grouped subquery cte source' => [
        "WITH weights(meta_option_id, weight) AS (SELECT meta_option_id, weight FROM option_meta) SELECT option_id FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT count(weight) FROM weights GROUP BY meta_option_id HAVING meta_option_id = 5 ORDER BY meta_option_id)",
        [4, 5],
        2,
        3,
    ],
    'limit grouped values cte source' => [
        "WITH weights(meta_option_id, weight) AS (VALUES (1, 10), (1, 20), (5, 30), (5, 40), (5, 50)) SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM weights GROUP BY meta_option_id HAVING sum(weight) = 120 ORDER BY meta_option_id)",
        [1, 2, 3],
        3,
        0,
    ],
    'limit grouped subquery predicate uses between having' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING sum(weight) BETWEEN 25 AND 75 ORDER BY sum(weight) DESC)",
        [1, 2],
        2,
        0,
    ],
    'limit grouped subquery predicate uses not between having' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING sum(weight) NOT BETWEEN 25 AND 75 ORDER BY sum(weight) DESC)",
        [1, 2, 3],
        3,
        0,
    ],
    'limit grouped subquery predicate uses in having' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING count(weight) IN (1, 3) ORDER BY count(weight) DESC)",
        [1, 2, 3],
        3,
        0,
    ],
    'limit grouped subquery predicate uses not in having' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING count(weight) NOT IN (1, 3) ORDER BY meta_option_id)",
        [1, 2],
        2,
        0,
    ],
    'constant select grouped limit strips hidden order' => [
        "SELECT 'ready' AS status LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 1 ORDER BY meta_option_id)",
        ['ready'],
        2,
        0,
    ],
    'constant select grouped offset strips hidden order' => [
        "SELECT 'ready' AS status LIMIT 1 OFFSET (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 1 ORDER BY meta_option_id)",
        [],
        1,
        2,
    ],
    'compound select grouped limit strips hidden order' => [
        "SELECT option_id FROM wp_options WHERE option_id <= 3 UNION ALL SELECT option_id FROM wp_options WHERE option_id >= 4 ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 5 ORDER BY meta_option_id)",
        [1, 2, 3],
        3,
        0,
    ],
    'compound select grouped offset strips hidden order' => [
        "SELECT option_id FROM wp_options WHERE option_id <= 3 UNION ALL SELECT option_id FROM wp_options WHERE option_id >= 4 ORDER BY option_id LIMIT 2 OFFSET (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 5 ORDER BY meta_option_id)",
        [4, 5],
        2,
        3,
    ],
    'empty grouped limit subquery rejects non numeric null' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight) FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 99 ORDER BY meta_option_id)",
        InvalidArgumentException::class,
        null,
        null,
    ],
    'grouped limit subquery still rejects projected hidden width when explicit two columns remain' => [
        "SELECT option_id FROM wp_options ORDER BY option_id LIMIT (SELECT count(weight), meta_option_id FROM option_meta GROUP BY meta_option_id HAVING meta_option_id = 1 ORDER BY meta_option_id)",
        InvalidArgumentException::class,
        null,
        null,
    ],
];

$tests = [];
foreach ($cases as $name => [$sql, $expected, $expectedLimit, $expectedOffset]) {
    $tests['select correlated aggregate limit current next18 ' . $name] = static function (TestRunner $t) use ($tables, $sql, $expected, $expectedLimit, $expectedOffset): void {
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
