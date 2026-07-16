<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tree = [
    ['name' => 'Alice', 'boss' => null],
    ['name' => 'Bob', 'boss' => 'Alice'],
    ['name' => 'Cindy', 'boss' => 'Alice'],
    ['name' => 'Dave', 'boss' => 'Bob'],
    ['name' => 'Emma', 'boss' => 'Bob'],
    ['name' => 'Fred', 'boss' => 'Cindy'],
    ['name' => 'Gail', 'boss' => 'Cindy'],
];

$edges = [
    ['src' => 1, 'dst' => 2],
    ['src' => 1, 'dst' => 3],
    ['src' => 2, 'dst' => 4],
    ['src' => 3, 'dst' => 5],
    ['src' => 4, 'dst' => 2],
    ['src' => 5, 'dst' => 5],
];

$cases = [
    'depth first recursive order by ordinal desc' => [
        "WITH RECURSIVE under(name, level) AS (VALUES ('Alice', 0) UNION ALL SELECT org.name, under.level + 1 FROM org JOIN under ON org.boss = under.name ORDER BY 2 DESC) SELECT name, level FROM under",
        ['org' => $tree],
        [
            ['name' => 'Alice', 'level' => 0],
            ['name' => 'Bob', 'level' => 1],
            ['name' => 'Dave', 'level' => 2],
            ['name' => 'Emma', 'level' => 2],
            ['name' => 'Cindy', 'level' => 1],
            ['name' => 'Fred', 'level' => 2],
            ['name' => 'Gail', 'level' => 2],
        ],
    ],
    'breadth first recursive order by ordinal asc' => [
        "WITH RECURSIVE under(name, level) AS (VALUES ('Alice', 0) UNION ALL SELECT org.name, under.level + 1 FROM org JOIN under ON org.boss = under.name ORDER BY 2 ASC) SELECT name, level FROM under",
        ['org' => $tree],
        [
            ['name' => 'Alice', 'level' => 0],
            ['name' => 'Bob', 'level' => 1],
            ['name' => 'Cindy', 'level' => 1],
            ['name' => 'Dave', 'level' => 2],
            ['name' => 'Emma', 'level' => 2],
            ['name' => 'Fred', 'level' => 2],
            ['name' => 'Gail', 'level' => 2],
        ],
    ],
    'recursive queue order can use output column name' => [
        "WITH RECURSIVE under(name, level) AS (VALUES ('Alice', 0) UNION ALL SELECT org.name, under.level + 1 FROM org JOIN under ON org.boss = under.name ORDER BY level DESC) SELECT name FROM under",
        ['org' => $tree],
        [['name' => 'Alice'], ['name' => 'Bob'], ['name' => 'Dave'], ['name' => 'Emma'], ['name' => 'Cindy'], ['name' => 'Fred'], ['name' => 'Gail']],
    ],
    'recursive queue order applies to anchor rows' => [
        "WITH RECURSIVE roots(name, level) AS (VALUES ('Cindy', 1), ('Alice', 0), ('Bob', 1) UNION ALL SELECT org.name, roots.level + 1 FROM org JOIN roots ON org.boss = roots.name WHERE roots.level < 1 ORDER BY 2 ASC, 1 ASC) SELECT name, level FROM roots",
        ['org' => $tree],
        [['name' => 'Alice', 'level' => 0], ['name' => 'Bob', 'level' => 1], ['name' => 'Bob', 'level' => 1], ['name' => 'Cindy', 'level' => 1], ['name' => 'Cindy', 'level' => 1]],
    ],
    'recursive queue order preserves fifo ties' => [
        "WITH RECURSIVE under(name, level) AS (VALUES ('Alice', 0) UNION ALL SELECT org.name, under.level + 1 FROM org JOIN under ON org.boss = under.name ORDER BY 2 DESC) SELECT name FROM under WHERE level = 2",
        ['org' => $tree],
        [['name' => 'Dave'], ['name' => 'Emma'], ['name' => 'Fred'], ['name' => 'Gail']],
    ],
    'recursive queue order feeds outer order by independently' => [
        "WITH RECURSIVE under(name, level) AS (VALUES ('Alice', 0) UNION ALL SELECT org.name, under.level + 1 FROM org JOIN under ON org.boss = under.name ORDER BY 2 DESC) SELECT name, level FROM under ORDER BY name DESC LIMIT 3",
        ['org' => $tree],
        [['name' => 'Gail', 'level' => 2], ['name' => 'Fred', 'level' => 2], ['name' => 'Emma', 'level' => 2]],
    ],
    'recursive queue order feeds aggregate after traversal' => [
        "WITH RECURSIVE under(name, level) AS (VALUES ('Alice', 0) UNION ALL SELECT org.name, under.level + 1 FROM org JOIN under ON org.boss = under.name ORDER BY 2 DESC) SELECT level, count(name) AS total FROM under GROUP BY level ORDER BY level",
        ['org' => $tree],
        [['level' => 0, 'total' => 1], ['level' => 1, 'total' => 2], ['level' => 2, 'total' => 4]],
    ],
    'union search cycle follows current row before sibling' => [
        'WITH RECURSIVE walk(id, depth) AS (VALUES (1, 0) UNION SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < 3 ORDER BY 2 DESC) SELECT id, depth FROM walk',
        ['edges' => $edges],
        [['id' => 1, 'depth' => 0], ['id' => 2, 'depth' => 1], ['id' => 4, 'depth' => 2], ['id' => 2, 'depth' => 3], ['id' => 3, 'depth' => 1], ['id' => 5, 'depth' => 2], ['id' => 5, 'depth' => 3]],
    ],
    'union search cycle with node key converges' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC) SELECT id FROM walk',
        ['edges' => $edges],
        [['id' => 1], ['id' => 3], ['id' => 5], ['id' => 2], ['id' => 4]],
    ],
    'union all bounded search keeps repeated current-row cycle rows' => [
        'WITH RECURSIVE walk(id, depth) AS (VALUES (1, 0) UNION ALL SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < 2 ORDER BY 2 DESC, 1 DESC) SELECT id, depth FROM walk',
        ['edges' => $edges],
        [['id' => 1, 'depth' => 0], ['id' => 3, 'depth' => 1], ['id' => 5, 'depth' => 2], ['id' => 2, 'depth' => 1], ['id' => 4, 'depth' => 2]],
    ],
    'recursive queue order supports named bind root' => [
        'WITH RECURSIVE walk(id) AS (VALUES (:root) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC) SELECT id FROM walk',
        ['edges' => $edges],
        [['id' => 1], ['id' => 3], ['id' => 5], ['id' => 2], ['id' => 4]],
        [':root' => 1],
    ],
    'recursive queue order supports positional bind depth' => [
        'WITH RECURSIVE walk(id, depth) AS (VALUES (1, 0) UNION ALL SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < ?1 ORDER BY 2 DESC, 1 DESC) SELECT id, depth FROM walk',
        ['edges' => $edges],
        [['id' => 1, 'depth' => 0], ['id' => 3, 'depth' => 1], ['id' => 5, 'depth' => 2], ['id' => 2, 'depth' => 1], ['id' => 4, 'depth' => 2]],
        [1 => 2],
    ],
    'recursive queue order feeds exists predicate' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC) SELECT option_id FROM wp_options WHERE EXISTS (SELECT id FROM walk WHERE id = option_id) ORDER BY option_id',
        ['edges' => $edges, 'wp_options' => [['option_id' => 2], ['option_id' => 5], ['option_id' => 9]]],
        [['option_id' => 2], ['option_id' => 5]],
    ],
    'recursive queue order feeds not in predicate' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC) SELECT option_id FROM wp_options WHERE option_id NOT IN (SELECT id FROM walk) ORDER BY option_id',
        ['edges' => $edges, 'wp_options' => [['option_id' => 2], ['option_id' => 5], ['option_id' => 9]]],
        [['option_id' => 9]],
    ],
    'recursive queue order feeds join' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC) SELECT wp_options.option_name AS name FROM walk JOIN wp_options ON wp_options.option_id = walk.id ORDER BY name',
        ['edges' => $edges, 'wp_options' => [['option_id' => 2, 'option_name' => 'home'], ['option_id' => 5, 'option_name' => 'theme']]],
        [['name' => 'home'], ['name' => 'theme']],
    ],
    'recursive queue order feeds left join null extension' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC) SELECT walk.id AS id, wp_options.option_name AS name FROM walk LEFT JOIN wp_options ON wp_options.option_id = walk.id WHERE walk.id >= 4 ORDER BY walk.id',
        ['edges' => $edges, 'wp_options' => [['option_id' => 5, 'option_name' => 'theme']]],
        [['id' => 4, 'name' => null], ['id' => 5, 'name' => 'theme']],
    ],
    'recursive queue order works after ordinary root cte' => [
        'WITH RECURSIVE roots(id) AS (VALUES (1)), walk(id) AS (SELECT id FROM roots UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC) SELECT id FROM walk',
        ['edges' => $edges],
        [['id' => 1], ['id' => 3], ['id' => 5], ['id' => 2], ['id' => 4]],
    ],
    'recursive queue order feeds ordinary picked cte' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC), picked AS (SELECT id FROM walk WHERE id >= 4) SELECT id FROM picked ORDER BY id',
        ['edges' => $edges],
        [['id' => 4], ['id' => 5]],
    ],
    'recursive queue order supports outer distinct' => [
        'WITH RECURSIVE walk(id, depth) AS (VALUES (1, 0) UNION ALL SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < 3 ORDER BY 2 DESC) SELECT DISTINCT id FROM walk ORDER BY id',
        ['edges' => $edges],
        [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]],
    ],
    'recursive queue order supports outer compound' => [
        'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC) SELECT id FROM walk UNION SELECT option_id AS id FROM wp_options ORDER BY id',
        ['edges' => $edges, 'wp_options' => [['option_id' => 9]]],
        [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5], ['id' => 9]],
    ],
    'recursive queue order supports column alias without explicit cte columns' => [
        'WITH RECURSIVE walk AS (SELECT 1 AS id UNION SELECT edges.dst AS id FROM edges JOIN walk ON edges.src = walk.id ORDER BY id DESC) SELECT id FROM walk',
        ['edges' => $edges],
        [['id' => 1], ['id' => 3], ['id' => 5], ['id' => 2], ['id' => 4]],
    ],
    'recursive queue order with null priority first ascending' => [
        "WITH RECURSIVE q(name, priority) AS (VALUES ('root', NULL), ('later', 2) UNION ALL SELECT org.name, 1 FROM org JOIN q ON org.boss = q.name WHERE q.name = 'root' ORDER BY 2 ASC) SELECT name, priority FROM q",
        ['org' => [['name' => 'child', 'boss' => 'root']]],
        [['name' => 'root', 'priority' => null], ['name' => 'child', 'priority' => 1], ['name' => 'later', 'priority' => 2]],
    ],
    'recursive queue order with null priority last descending' => [
        "WITH RECURSIVE q(name, priority) AS (VALUES ('root', NULL), ('later', 2) UNION ALL SELECT org.name, 1 FROM org JOIN q ON org.boss = q.name WHERE q.name = 'root' ORDER BY 2 DESC) SELECT name, priority FROM q",
        ['org' => [['name' => 'child', 'boss' => 'root']]],
        [['name' => 'later', 'priority' => 2], ['name' => 'root', 'priority' => null], ['name' => 'child', 'priority' => 1]],
    ],
    'recursive queue order handles string descending tie breaker' => [
        "WITH RECURSIVE under(name, level) AS (VALUES ('Alice', 0) UNION ALL SELECT org.name, under.level + 1 FROM org JOIN under ON org.boss = under.name ORDER BY 2 ASC, 1 DESC) SELECT name, level FROM under",
        ['org' => $tree],
        [['name' => 'Alice', 'level' => 0], ['name' => 'Cindy', 'level' => 1], ['name' => 'Bob', 'level' => 1], ['name' => 'Gail', 'level' => 2], ['name' => 'Fred', 'level' => 2], ['name' => 'Emma', 'level' => 2], ['name' => 'Dave', 'level' => 2]],
    ],
    'recursive queue order can be observed before final sort' => [
        "WITH RECURSIVE under(name, level) AS (VALUES ('Alice', 0) UNION ALL SELECT org.name, under.level + 1 FROM org JOIN under ON org.boss = under.name ORDER BY 2 DESC) SELECT name FROM under LIMIT 4",
        ['org' => $tree],
        [['name' => 'Alice'], ['name' => 'Bob'], ['name' => 'Dave'], ['name' => 'Emma']],
    ],
    'recursive queue order can be sliced by outer offset' => [
        "WITH RECURSIVE under(name, level) AS (VALUES ('Alice', 0) UNION ALL SELECT org.name, under.level + 1 FROM org JOIN under ON org.boss = under.name ORDER BY 2 DESC) SELECT name FROM under LIMIT 3 OFFSET 2",
        ['org' => $tree],
        [['name' => 'Dave'], ['name' => 'Emma'], ['name' => 'Cindy']],
    ],
];

foreach ($cases as $name => $case) {
    [$sql, $tables, $expected] = $case;
    $parameters = $case[3] ?? [];
    $tests['recursive CTE search cycle current next19 ' . $name] = static function (TestRunner $t) use ($sql, $tables, $expected, $parameters): void {
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables, $parameters));
    };
}

$errorCases = [
    'order ordinal out of range' => 'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 2) SELECT id FROM walk',
    'order unknown column' => 'WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY missing) SELECT id FROM walk',
];

foreach ($errorCases as $name => $sql) {
    $tests['recursive CTE search cycle current next19 rejects ' . $name] = static function (TestRunner $t) use ($sql, $edges): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, ['edges' => $edges]));
    };
}

return $tests;
