<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$edgeRows = [
    ['src' => 1, 'dst' => 2],
    ['src' => 2, 'dst' => 3],
    ['src' => 3, 'dst' => 1],
    ['src' => 3, 'dst' => 4],
    ['src' => 4, 'dst' => 4],
];

$cases = [
    'union deduplicates duplicate values anchors' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1), (1), (2) UNION SELECT id FROM walk WHERE id < 2) SELECT id FROM walk ORDER BY id',
        [],
        [['id' => 1], ['id' => 2]],
    ],
    'union all preserves duplicate values anchors' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1), (1) UNION ALL SELECT id + 1 FROM walk WHERE id < 2) SELECT id FROM walk ORDER BY id',
        [],
        [['id' => 1], ['id' => 1], ['id' => 2], ['id' => 2]],
    ],
    'union deduplicates duplicate select anchors' => [
        'WITH RECURSIVE walk(id) AS (SELECT option_id FROM wp_options WHERE option_id <= 2 UNION SELECT id FROM walk WHERE id < 2) SELECT id FROM walk ORDER BY id',
        ['wp_options' => [['option_id' => 1], ['option_id' => 1], ['option_id' => 2]]],
        [['id' => 1], ['id' => 2]],
    ],
    'union cycle terminates on self edge' => [
        'WITH RECURSIVE walk(id) AS (VALUES (4) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT id FROM walk ORDER BY id',
        ['edges' => $edgeRows],
        [['id' => 4]],
    ],
    'union cycle terminates on three node loop' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT id FROM walk ORDER BY id',
        ['edges' => $edgeRows],
        [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]],
    ],
    'union cycle can start from duplicate roots' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1), (1), (3) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT id FROM walk ORDER BY id',
        ['edges' => $edgeRows],
        [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]],
    ],
    'union cycle supports outer in predicate' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT option_id FROM wp_options WHERE option_id IN (SELECT id FROM walk) ORDER BY option_id',
        ['edges' => $edgeRows, 'wp_options' => [['option_id' => 1], ['option_id' => 4], ['option_id' => 9]]],
        [['option_id' => 1], ['option_id' => 4]],
    ],
    'union cycle supports exists predicate' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT option_id FROM wp_options WHERE EXISTS (SELECT id FROM walk WHERE id = option_id) ORDER BY option_id',
        ['edges' => $edgeRows, 'wp_options' => [['option_id' => 2], ['option_id' => 4], ['option_id' => 9]]],
        [['option_id' => 2], ['option_id' => 4]],
    ],
    'union cycle supports not exists predicate' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT option_id FROM wp_options WHERE NOT EXISTS (SELECT id FROM walk WHERE id = option_id) ORDER BY option_id',
        ['edges' => $edgeRows, 'wp_options' => [['option_id' => 2], ['option_id' => 9]]],
        [['option_id' => 9]],
    ],
    'union cycle can join final rows to wp options' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT wp_options.option_name AS name FROM walk JOIN wp_options ON wp_options.option_id = walk.id ORDER BY name',
        ['edges' => $edgeRows, 'wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 4, 'option_name' => 'cache'], ['option_id' => 9, 'option_name' => 'other']]],
        [['name' => 'cache'], ['name' => 'siteurl']],
    ],
    'union cycle supports computed state columns' => [
        'WITH RECURSIVE walk(id, depth) AS (VALUES (1, 0) UNION SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < 2) SELECT id, depth FROM walk ORDER BY depth, id',
        ['edges' => $edgeRows],
        [['id' => 1, 'depth' => 0], ['id' => 2, 'depth' => 1], ['id' => 3, 'depth' => 2]],
    ],
    'union cycle supports outer aggregate' => [
        'WITH RECURSIVE walk(id, parity) AS (VALUES (1, 1), (1, 1) UNION SELECT edges.dst, edges.dst % 2 FROM edges JOIN walk ON edges.src = walk.id) SELECT parity, count(id) AS total, sum(id) AS summed FROM walk GROUP BY parity ORDER BY parity',
        ['edges' => $edgeRows],
        [['parity' => 0, 'total' => 2, 'summed' => 6], ['parity' => 1, 'total' => 2, 'summed' => 4]],
    ],
    'union cycle supports limit offset' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT id FROM walk ORDER BY id LIMIT 2 OFFSET 1',
        ['edges' => $edgeRows],
        [['id' => 2], ['id' => 3]],
    ],
    'union cycle supports named bind root' => [
        'WITH RECURSIVE walk(id) AS (VALUES (:root) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT id FROM walk ORDER BY id',
        ['edges' => $edgeRows],
        [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]],
        [':root' => 1],
    ],
    'union cycle supports positional bind root' => [
        'WITH RECURSIVE walk(id) AS (VALUES (?1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT id FROM walk ORDER BY id',
        ['edges' => $edgeRows],
        [['id' => 4]],
        [1 => 4],
    ],
    'union cycle preserves column aliases in plan' => [
        'WITH RECURSIVE walk(node) AS (VALUES (1), (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.node) SELECT node FROM walk ORDER BY node',
        ['edges' => $edgeRows],
        [['node' => 1], ['node' => 2], ['node' => 3], ['node' => 4]],
    ],
    'union cycle can feed ordinary cte' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id), picked AS (SELECT id FROM walk WHERE id >= 3) SELECT id FROM picked ORDER BY id',
        ['edges' => $edgeRows],
        [['id' => 3], ['id' => 4]],
    ],
    'union cycle after ordinary cte' => [
        'WITH RECURSIVE roots(id) AS (VALUES (1), (1)), walk(id) AS (SELECT id FROM roots UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT id FROM walk ORDER BY id',
        ['edges' => $edgeRows],
        [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]],
    ],
    'union cycle supports duplicate edges once' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id) SELECT id FROM walk ORDER BY id',
        ['edges' => array_merge($edgeRows, [['src' => 1, 'dst' => 2], ['src' => 2, 'dst' => 3]])],
        [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]],
    ],
    'union all bounded cycle keeps duplicate edge rows' => [
        'WITH RECURSIVE walk(id, depth) AS (VALUES (1, 0) UNION ALL SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < 1) SELECT id, depth FROM walk ORDER BY id, depth',
        ['edges' => [['src' => 1, 'dst' => 2], ['src' => 1, 'dst' => 2]]],
        [['id' => 1, 'depth' => 0], ['id' => 2, 'depth' => 1], ['id' => 2, 'depth' => 1]],
    ],
];

foreach ($cases as $name => $case) {
    [$sql, $tables, $expected] = $case;
    $parameters = $case[3] ?? [];
    $tests['recursive CTE cycle edge ' . $name] = static function (TestRunner $t) use ($sql, $tables, $expected, $parameters): void {
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables, $parameters));
    };
}

$errorCases = [
    'anchor delete arm' => 'WITH RECURSIVE walk(id) AS (DELETE FROM wp_options UNION SELECT id FROM walk) SELECT id FROM walk',
    'anchor update arm' => "WITH RECURSIVE walk(id) AS (UPDATE wp_options SET option_name = 'x' UNION SELECT id FROM walk) SELECT id FROM walk",
    'anchor insert arm' => "WITH RECURSIVE walk(id) AS (INSERT INTO wp_options VALUES (1) UNION SELECT id FROM walk) SELECT id FROM walk",
    'recursive delete arm' => 'WITH RECURSIVE walk(id) AS (VALUES (1) UNION DELETE FROM walk) SELECT id FROM walk',
    'recursive update arm' => "WITH RECURSIVE walk(id) AS (VALUES (1) UNION UPDATE walk SET id = id + 1) SELECT id FROM walk",
    'recursive insert arm' => "WITH RECURSIVE walk(id) AS (VALUES (1) UNION INSERT INTO walk VALUES (2)) SELECT id FROM walk",
    'nested dml cte body' => 'WITH RECURSIVE bad(id) AS (WITH x AS (DELETE FROM wp_options) SELECT id FROM bad) SELECT id FROM bad',
    'malformed cycle dml token' => 'WITH RECURSIVE walk(id) AS (VALUES (1) UNION ALL DELETE FROM walk WHERE id = 1) SELECT id FROM walk',
];

foreach ($errorCases as $name => $sql) {
    $tests['recursive CTE cycle edge rejects ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl']]]));
    };
}

return $tests;
