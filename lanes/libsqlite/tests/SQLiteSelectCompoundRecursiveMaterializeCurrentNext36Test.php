<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'feed', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'option_value' => 'plugins', 'autoload' => 'no'],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'option_value' => 'rules', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'theme_mods_twentyfour', 'option_value' => 'theme', 'autoload' => 'yes'],
];

$edges = [
    ['src' => 1, 'dst' => 2, 'kind' => 'core'],
    ['src' => 2, 'dst' => 3, 'kind' => 'core'],
    ['src' => 2, 'dst' => 5, 'kind' => 'cache'],
    ['src' => 3, 'dst' => 4, 'kind' => 'cache'],
    ['src' => 5, 'dst' => 6, 'kind' => 'cache'],
    ['src' => 6, 'dst' => 7, 'kind' => 'theme'],
    ['src' => 7, 'dst' => 2, 'kind' => 'cycle'],
];

$tables = ['wp_options' => $options, 'edges' => $edges];

$column = static fn (string $sql, string $column, array $parameters = []) => array_column(
    SQLiteSelectSql::execute($sql, $tables, $parameters),
    $column,
);

$cases = [
    'multiple anchor values feed one recursive arm' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION VALUES (4) UNION ALL SELECT id + 1 FROM walk WHERE id < 5) SELECT id FROM walk ORDER BY id',
        'id',
        [1, 2, 3, 4, 4, 5, 5],
    ],
    'multiple anchor select arms use union dedupe before recursion' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (SELECT 1 AS id UNION SELECT 1 AS id UNION SELECT 2 AS id UNION SELECT id + 1 FROM walk WHERE id < 4) SELECT id FROM walk ORDER BY id',
        'id',
        [1, 2, 3, 4],
    ],
    'union all anchor duplicates remain visible' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION ALL VALUES (1) UNION ALL SELECT id + 1 FROM walk WHERE id < 2 LIMIT 4) SELECT id FROM walk',
        'id',
        [1, 1, 2, 2],
    ],
    'two recursive arms append next rows in arm order' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM walk WHERE id < 3 UNION ALL SELECT id + 10 FROM walk WHERE id = 1 LIMIT 5) SELECT id FROM walk',
        'id',
        [1, 2, 11, 3],
    ],
    'two recursive arms share union dedupe' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION SELECT id + 1 FROM walk WHERE id < 3 UNION SELECT id + 1 FROM walk WHERE id < 3) SELECT id FROM walk ORDER BY id',
        'id',
        [1, 2, 3],
    ],
    'recursive compound queue order applies across generated arms' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM walk WHERE id < 3 UNION ALL SELECT id + 10 FROM walk WHERE id = 1 ORDER BY id DESC LIMIT 4) SELECT id FROM walk',
        'id',
        [1, 11, 2, 3],
    ],
    'recursive compound offset skips sorted current rows but still recurses' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM walk WHERE id < 4 UNION ALL SELECT id + 10 FROM walk WHERE id = 1 ORDER BY id DESC LIMIT 3 OFFSET 1) SELECT id FROM walk',
        'id',
        [11, 2, 3],
    ],
    'not materialized hint accepts multiple recursive arms' => [
        'WITH RECURSIVE walk(id) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM walk WHERE id < 3 UNION ALL SELECT id + 20 FROM walk WHERE id = 1 LIMIT 4) SELECT id FROM walk',
        'id',
        [1, 2, 21, 3],
    ],
    'compound recursive CTE feeds outer compound select' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM walk WHERE id < 3 UNION ALL SELECT 9 FROM walk WHERE id = 1 LIMIT 4) SELECT id FROM walk UNION SELECT 99 AS id ORDER BY id',
        'id',
        [1, 2, 3, 9, 99],
    ],
    'compound recursive CTE feeds aggregate' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM walk WHERE id < 4 UNION ALL SELECT id + 2 FROM walk WHERE id < 3 LIMIT 6) SELECT id FROM walk ORDER BY id',
        'id',
        [1, 2, 3, 3, 4, 4],
    ],
    'compound recursive CTE accepts named bind limits' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (:root) UNION ALL SELECT id + 1 FROM walk WHERE id < :stop UNION ALL SELECT id + 10 FROM walk WHERE id = :root LIMIT :take) SELECT id FROM walk',
        'id',
        [1, 2, 11, 3],
        [':root' => 1, ':stop' => 3, ':take' => 4],
    ],
    'compound recursive CTE accepts positional bind offsets' => [
        'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (?1) UNION ALL SELECT id + ?2 FROM walk WHERE id < ?3 UNION ALL SELECT id + 10 FROM walk WHERE id = ?1 LIMIT ?4 OFFSET ?5) SELECT id FROM walk',
        'id',
        [2, 11, 3],
        [1 => 1, 2 => 1, 3 => 4, 4 => 3, 5 => 1],
    ],
];

foreach ($cases as $name => $case) {
    [$sql, $field, $expected] = $case;
    $parameters = $case[3] ?? [];
    $tests['select compound recursive materialize current next36 ' . $name] = static function (TestRunner $t) use ($column, $sql, $field, $expected, $parameters): void {
        $t->same($expected, $column($sql, $field, $parameters));
    };
}

$wpCases = [
    'compound anchor imports core and cache option spans' => [
        "WITH RECURSIVE wanted(id, source) AS MATERIALIZED (
            VALUES (1, 'core')
            UNION
            VALUES (4, 'cache')
            UNION
            SELECT edges.dst, wanted.source FROM edges JOIN wanted ON edges.src = wanted.id WHERE wanted.source IN ('core', 'cache')
            UNION
            SELECT edges.dst, edges.kind FROM edges JOIN wanted ON edges.src = wanted.id WHERE edges.kind = 'theme'
        )
        SELECT option_name FROM wp_options JOIN wanted ON wanted.id = wp_options.option_id ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', '_transient_feed', '_transient_feed', '_site_transient_update_plugins', 'rewrite_rules', 'theme_mods_twentyfour', 'theme_mods_twentyfour'],
    ],
    'compound recursive arms preserve current source labels' => [
        "WITH RECURSIVE wanted(id, source) AS MATERIALIZED (
            VALUES (1, 'core')
            UNION ALL
            VALUES (5, 'cache')
            UNION ALL
            SELECT edges.dst, wanted.source FROM edges JOIN wanted ON edges.src = wanted.id WHERE wanted.source = 'core'
            UNION ALL
            SELECT edges.dst, edges.kind FROM edges JOIN wanted ON edges.src = wanted.id WHERE wanted.source = 'cache'
            LIMIT 6
        )
        SELECT source FROM wanted",
        'source',
        ['core', 'cache', 'core', 'cache', 'core', 'core'],
    ],
    'compound recursive queue order prioritizes cache repair rows' => [
        "WITH RECURSIVE wanted(id, priority) AS MATERIALIZED (
            VALUES (1, 1)
            UNION ALL
            VALUES (5, 9)
            UNION ALL
            SELECT edges.dst, wanted.priority + 1 FROM edges JOIN wanted ON edges.src = wanted.id
            UNION ALL
            SELECT edges.dst, 20 FROM edges JOIN wanted ON edges.src = wanted.id WHERE edges.kind = 'theme'
            ORDER BY priority DESC LIMIT 5
        )
        SELECT option_name FROM wp_options JOIN wanted ON wanted.id = wp_options.option_id",
        'option_name',
        ['home', 'blogname', '_site_transient_update_plugins', 'rewrite_rules', 'theme_mods_twentyfour'],
    ],
    'compound recursive offset supports copied option resume' => [
        "WITH RECURSIVE wanted(id) AS NOT MATERIALIZED (
            VALUES (1)
            UNION ALL
            SELECT edges.dst FROM edges JOIN wanted ON edges.src = wanted.id
            UNION ALL
            SELECT edges.dst + 1 FROM edges JOIN wanted ON edges.src = wanted.id WHERE edges.kind = 'core'
            LIMIT 4 OFFSET 2
        )
        SELECT option_name FROM wp_options WHERE option_id IN (SELECT id FROM wanted) ORDER BY option_id",
        'option_name',
        ['blogname', '_transient_feed', '_site_transient_update_plugins'],
    ],
    'compound recursive materialized source feeds scalar subquery' => [
        "WITH RECURSIVE wanted(id) AS MATERIALIZED (
            VALUES (1)
            UNION ALL
            VALUES (2)
            UNION ALL
            SELECT edges.dst FROM edges JOIN wanted ON edges.src = wanted.id WHERE edges.kind = 'core'
            UNION ALL
            SELECT edges.dst FROM edges JOIN wanted ON edges.src = wanted.id WHERE edges.kind = 'cache'
            LIMIT 6
        )
        SELECT option_name FROM wp_options WHERE option_id <= (SELECT id FROM wanted ORDER BY id DESC LIMIT 1) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins'],
    ],
    'compound recursive materialized source feeds exists scan' => [
        "WITH RECURSIVE wanted(id) AS MATERIALIZED (
            VALUES (1)
            UNION
            VALUES (5)
            UNION
            SELECT edges.dst FROM edges JOIN wanted ON edges.src = wanted.id
            UNION
            SELECT edges.src FROM edges JOIN wanted ON edges.dst = wanted.id
        )
        SELECT option_name FROM wp_options WHERE EXISTS (SELECT id FROM wanted WHERE id = option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins', 'rewrite_rules', 'theme_mods_twentyfour'],
    ],
    'compound recursive materialized source feeds grouped option scan' => [
        "WITH RECURSIVE wanted(id) AS MATERIALIZED (
            VALUES (1)
            UNION ALL
            SELECT edges.dst FROM edges JOIN wanted ON edges.src = wanted.id WHERE edges.kind = 'core'
            UNION ALL
            SELECT edges.dst FROM edges JOIN wanted ON edges.src = wanted.id WHERE edges.kind = 'cache'
            LIMIT 5
        )
        SELECT autoload, count(option_id) AS total FROM wp_options WHERE option_id IN (SELECT id FROM wanted) GROUP BY autoload ORDER BY autoload",
        'autoload',
        ['no', 'yes'],
    ],
    'compound recursive materialized source feeds left join' => [
        "WITH RECURSIVE wanted(id) AS MATERIALIZED (
            VALUES (6)
            UNION ALL
            SELECT id + 1 FROM wanted WHERE id < 8
            UNION ALL
            SELECT id + 10 FROM wanted WHERE id = 6 LIMIT 4
        )
        SELECT wanted.id AS id FROM wanted LEFT JOIN wp_options ON wp_options.option_id = wanted.id ORDER BY wanted.id",
        'id',
        [6, 7, 8, 16],
    ],
];

foreach ($wpCases as $name => [$sql, $field, $expected]) {
    $tests['select compound recursive materialize current next36 wp ' . $name] = static function (TestRunner $t) use ($column, $sql, $field, $expected): void {
        $t->same($expected, $column($sql, $field));
    };
}

foreach (range(1, 20) as $stop) {
    $expected = range(1, min(max(2, $stop), 5));
    $tests['select compound recursive materialize current next36 generated union anchor stop ' . $stop] = static function (TestRunner $t) use ($column, $stop, $expected): void {
        $sql = "WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION VALUES (2) UNION SELECT id + 1 FROM walk WHERE id < {$stop} LIMIT 5) SELECT id FROM walk ORDER BY id";
        $t->same($expected, $column($sql, 'id'));
    };
}

foreach (range(1, 20) as $stop) {
    $take = $stop === 1 ? 1 : min(6, $stop + 1);
    $tests['select compound recursive materialize current next36 generated two recursive arms stop ' . $stop] = static function (TestRunner $t) use ($column, $stop, $take): void {
        $sql = "WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM walk WHERE id < {$stop} UNION ALL SELECT id + 2 FROM walk WHERE id < {$stop} LIMIT {$take}) SELECT id FROM walk";
        $rows = $column($sql, 'id');
        $t->same(1, $rows[0] ?? null);
        $t->same($take, count($rows));
    };
}

$errorCases = [
    'recursive arm before anchor is rejected' => 'WITH RECURSIVE walk(id) AS MATERIALIZED (SELECT id + 1 FROM walk UNION VALUES (1)) SELECT id FROM walk',
    'non contiguous recursive arms are rejected' => 'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION SELECT id + 1 FROM walk WHERE id < 2 UNION SELECT 9) SELECT id FROM walk',
    'mixed recursive operators are rejected' => 'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION SELECT id + 1 FROM walk WHERE id < 2 UNION ALL SELECT id + 2 FROM walk WHERE id < 2) SELECT id FROM walk',
    'recursive intersect operator is rejected' => 'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) INTERSECT SELECT id + 1 FROM walk WHERE id < 2) SELECT id FROM walk',
    'negative compound offset is rejected' => 'WITH RECURSIVE walk(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM walk WHERE id < 2 UNION ALL SELECT id + 2 FROM walk WHERE id < 2 LIMIT 1 OFFSET -1) SELECT id FROM walk',
];

foreach ($errorCases as $name => $sql) {
    $tests['select compound recursive materialize current next36 rejects ' . $name] = static function (TestRunner $t) use ($sql, $tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, $tables));
    };
}

return $tests;
