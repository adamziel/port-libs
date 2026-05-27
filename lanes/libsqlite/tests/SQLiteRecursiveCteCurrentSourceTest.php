<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$cases = [
    'integer sequence' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 5) SELECT x FROM seq',
        [],
        [['x' => 1], ['x' => 2], ['x' => 3], ['x' => 4], ['x' => 5]],
    ],
    'descending sequence' => [
        'WITH RECURSIVE seq(x) AS (VALUES (5) UNION ALL SELECT x - 1 FROM seq WHERE x > 1) SELECT x FROM seq',
        [],
        [['x' => 5], ['x' => 4], ['x' => 3], ['x' => 2], ['x' => 1]],
    ],
    'step by two' => [
        'WITH RECURSIVE seq(x) AS (VALUES (0) UNION ALL SELECT x + 2 FROM seq WHERE x < 6) SELECT x FROM seq',
        [],
        [['x' => 0], ['x' => 2], ['x' => 4], ['x' => 6]],
    ],
    'frontier source avoids duplicate expansion' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 4) SELECT x FROM seq ORDER BY x',
        [],
        [['x' => 1], ['x' => 2], ['x' => 3], ['x' => 4]],
    ],
    'computed projection from sequence' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 3) SELECT x, x * x AS square FROM seq ORDER BY x',
        [],
        [['x' => 1, 'square' => 1], ['x' => 2, 'square' => 4], ['x' => 3, 'square' => 9]],
    ],
    'recursive rows can be filtered by outer query' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 6) SELECT x FROM seq WHERE x BETWEEN 3 AND 5 ORDER BY x',
        [],
        [['x' => 3], ['x' => 4], ['x' => 5]],
    ],
    'recursive rows can be limited by outer query' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 8) SELECT x FROM seq ORDER BY x DESC LIMIT 3',
        [],
        [['x' => 8], ['x' => 7], ['x' => 6]],
    ],
    'recursive cte feeds wp options id filter' => [
        'WITH RECURSIVE wanted(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 3) SELECT option_id, option_name FROM wp_options WHERE option_id IN (SELECT id FROM wanted) ORDER BY option_id',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home'], ['option_id' => 4, 'option_name' => 'blogname']]],
        [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home']],
    ],
    'recursive cte joins wp options' => [
        'WITH RECURSIVE wanted(id) AS (VALUES (2) UNION ALL SELECT id + 1 FROM wanted WHERE id < 4) SELECT wp_options.option_id AS id, wp_options.option_name AS name FROM wp_options JOIN wanted ON wp_options.option_id = wanted.id ORDER BY id',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname'], ['option_id' => 4, 'option_name' => 'theme']]],
        [['id' => 2, 'name' => 'home'], ['id' => 3, 'name' => 'blogname'], ['id' => 4, 'name' => 'theme']],
    ],
    'recursive cte left joins wp options' => [
        'WITH RECURSIVE wanted(id) AS (VALUES (3) UNION ALL SELECT id + 1 FROM wanted WHERE id < 5) SELECT wanted.id AS id, wp_options.option_name AS name FROM wanted LEFT JOIN wp_options ON wanted.id = wp_options.option_id ORDER BY id',
        ['wp_options' => [['option_id' => 3, 'option_name' => 'blogname'], ['option_id' => 4, 'option_name' => 'theme']]],
        [['id' => 3, 'name' => 'blogname'], ['id' => 4, 'name' => 'theme'], ['id' => 5, 'name' => null]],
    ],
    'recursive cte after ordinary values cte' => [
        'WITH RECURSIVE limit_row(max_id) AS (VALUES (3)), wanted(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 3) SELECT id FROM wanted WHERE id <= (SELECT max_id FROM limit_row)',
        [],
        [['id' => 1], ['id' => 2], ['id' => 3]],
    ],
    'ordinary cte after recursive cte' => [
        'WITH RECURSIVE wanted(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 3), picked AS (SELECT id FROM wanted WHERE id > 1) SELECT id FROM picked',
        [],
        [['id' => 2], ['id' => 3]],
    ],
    'recursive cte with two columns' => [
        'WITH RECURSIVE seq(id, label) AS (VALUES (1, \'item1\') UNION ALL SELECT id + 1, label || \':next\' FROM seq WHERE id < 3) SELECT id, label FROM seq',
        [],
        [['id' => 1, 'label' => 'item1'], ['id' => 2, 'label' => 'item1:next'], ['id' => 3, 'label' => 'item1:next:next']],
    ],
    'recursive cte with null state' => [
        'WITH RECURSIVE seq(id, previous) AS (VALUES (1, NULL) UNION ALL SELECT id + 1, id FROM seq WHERE id < 3) SELECT id, previous FROM seq',
        [],
        [['id' => 1, 'previous' => null], ['id' => 2, 'previous' => 1], ['id' => 3, 'previous' => 2]],
    ],
    'recursive cte union removes duplicate anchor row' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1) UNION SELECT x FROM seq WHERE x < 2) SELECT x FROM seq',
        [],
        [['x' => 1]],
    ],
    'recursive cte union converges on duplicate recursive row' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1) UNION SELECT x + 1 FROM seq WHERE x < 3) SELECT x FROM seq',
        [],
        [['x' => 1], ['x' => 2], ['x' => 3]],
    ],
    'recursive cte preserves union all duplicates from frontier' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1), (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 2) SELECT x FROM seq ORDER BY x',
        [],
        [['x' => 1], ['x' => 1], ['x' => 2], ['x' => 2]],
    ],
    'recursive cte distinct outer projection' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1), (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 2) SELECT DISTINCT x FROM seq ORDER BY x',
        [],
        [['x' => 1], ['x' => 2]],
    ],
    'recursive cte aggregate outer query' => [
        'WITH RECURSIVE seq(x, parity) AS (VALUES (1, 1) UNION ALL SELECT x + 1, (parity + 1) % 2 FROM seq WHERE x < 4) SELECT parity, count(x) AS total, sum(x) AS summed FROM seq GROUP BY parity ORDER BY parity',
        [],
        [['parity' => 0, 'total' => 2, 'summed' => 6], ['parity' => 1, 'total' => 2, 'summed' => 4]],
    ],
    'recursive cte feeds exists predicate' => [
        'WITH RECURSIVE wanted(id) AS (VALUES (2) UNION ALL SELECT id + 1 FROM wanted WHERE id < 3) SELECT option_id, option_name FROM wp_options WHERE EXISTS (SELECT id FROM wanted WHERE id = option_id) ORDER BY option_id',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname']]],
        [['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname']],
    ],
    'recursive cte feeds not exists predicate' => [
        'WITH RECURSIVE wanted(id) AS (VALUES (2) UNION ALL SELECT id + 1 FROM wanted WHERE id < 3) SELECT option_id, option_name FROM wp_options WHERE NOT EXISTS (SELECT id FROM wanted WHERE id = option_id) ORDER BY option_id',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname']]],
        [['option_id' => 1, 'option_name' => 'siteurl']],
    ],
    'recursive cte supports named bind limit' => [
        'WITH RECURSIVE seq(x) AS (VALUES (:start) UNION ALL SELECT x + 1 FROM seq WHERE x < :stop) SELECT x FROM seq',
        [],
        [['x' => 2], ['x' => 3], ['x' => 4]],
        [':start' => 2, ':stop' => 4],
    ],
    'recursive cte supports positional bind limit' => [
        'WITH RECURSIVE seq(x) AS (VALUES (?1) UNION ALL SELECT x + ?2 FROM seq WHERE x < ?3) SELECT x FROM seq',
        [],
        [['x' => 1], ['x' => 3], ['x' => 5]],
        [1 => 1, 2 => 2, 3 => 5],
    ],
    'recursive cte can start empty' => [
        'WITH RECURSIVE seq(x) AS (SELECT option_id FROM wp_options WHERE option_id < 0 UNION ALL SELECT x + 1 FROM seq WHERE x < 3) SELECT x FROM seq',
        ['wp_options' => [['option_id' => 1], ['option_id' => 2]]],
        [],
    ],
    'recursive cte preserves anchor column names without column list' => [
        'WITH RECURSIVE seq AS (VALUES (1) UNION ALL SELECT column1 + 1 FROM seq WHERE column1 < 3) SELECT column1 FROM seq',
        [],
        [['column1' => 1], ['column1' => 2], ['column1' => 3]],
    ],
    'recursive cte supports outer expression order' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 4) SELECT x FROM seq ORDER BY 5 - x',
        [],
        [['x' => 4], ['x' => 3], ['x' => 2], ['x' => 1]],
    ],
    'recursive cte supports compound outer select' => [
        'WITH RECURSIVE seq(x) AS (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 2) SELECT x FROM seq UNION SELECT option_id AS x FROM wp_options ORDER BY x',
        ['wp_options' => [['option_id' => 2], ['option_id' => 3]]],
        [['x' => 1], ['x' => 2], ['x' => 3]],
    ],
];

foreach ($cases as $name => $case) {
    [$sql, $tables, $expected] = $case;
    $parameters = $case[3] ?? [];
    $tests['recursive CTE current source ' . $name] = static function (TestRunner $t) use ($sql, $tables, $expected, $parameters): void {
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables, $parameters));
    };
}

$errorCases = [
    'intersect operator' => 'WITH RECURSIVE seq(x) AS (VALUES (1) INTERSECT SELECT x FROM seq) SELECT x FROM seq',
    'anchor self reference' => 'WITH RECURSIVE seq(x) AS (SELECT x FROM seq UNION ALL SELECT x + 1 FROM seq WHERE x < 3) SELECT x FROM seq',
    'recursive arm without self reference' => 'WITH RECURSIVE seq(x) AS (VALUES (1) UNION ALL SELECT option_id FROM wp_options) SELECT x FROM seq',
    'recursive width mismatch' => 'WITH RECURSIVE seq(x) AS (VALUES (1) UNION ALL SELECT x + 1, x FROM seq WHERE x < 3) SELECT x FROM seq',
];

foreach ($errorCases as $name => $sql) {
    $tests['recursive CTE current source rejects ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, ['wp_options' => [['option_id' => 1]]]));
    };
}

return $tests;
