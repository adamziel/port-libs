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
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'bytes' => 2048],
];

$column = static fn (string $sql, string $column, array $tables = [], array $parameters = []): array => array_column(
    SQLiteSelectSql::execute($sql, $tables, $parameters),
    $column,
);

$sequenceCases = [
    'materialized recursive limit counts anchor' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 10 LIMIT 4) SELECT x FROM seq',
        [],
        [1, 2, 3, 4],
    ],
    'not materialized recursive limit counts anchor' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 10 LIMIT 3) SELECT x FROM seq',
        [],
        [1, 2, 3],
    ],
    'materialized recursive offset skips anchor but recurses' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 5 LIMIT 3 OFFSET 2) SELECT x FROM seq',
        [],
        [3, 4, 5],
    ],
    'not materialized recursive offset skips first generated rows' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 6 LIMIT 2 OFFSET 3) SELECT x FROM seq',
        [],
        [4, 5],
    ],
    'materialized recursive zero limit suppresses anchor' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 5 LIMIT 0) SELECT x FROM seq',
        [],
        [],
    ],
    'materialized recursive zero limit suppresses multiple anchors' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1), (9) UNION ALL SELECT x + 1 FROM seq WHERE x < 5 LIMIT 0) SELECT x FROM seq',
        [],
        [],
    ],
    'not materialized recursive limit can stop before pending anchors' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1), (9) UNION ALL SELECT x + 1 FROM seq WHERE x < 5 LIMIT 1) SELECT x FROM seq',
        [],
        [1],
    ],
    'not materialized recursive negative limit is unbounded' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 4 LIMIT -1) SELECT x FROM seq',
        [],
        [1, 2, 3, 4],
    ],
    'materialized recursive comma limit uses offset count order' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 6 LIMIT 2, 3) SELECT x FROM seq',
        [],
        [3, 4, 5],
    ],
    'not materialized recursive named limit bind' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (:start) UNION ALL SELECT x + 1 FROM seq WHERE x < :stop LIMIT :take OFFSET :skip) SELECT x FROM seq',
        [],
        [3, 4],
        [':start' => 1, ':stop' => 6, ':take' => 2, ':skip' => 2],
    ],
    'materialized recursive positional limit bind' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (?1) UNION ALL SELECT x + ?2 FROM seq WHERE x < ?3 LIMIT ?4 OFFSET ?5) SELECT x FROM seq',
        [],
        [5, 7],
        [1 => 1, 2 => 2, 3 => 9, 4 => 2, 5 => 2],
    ],
    'materialized recursive order sorts queue before limit' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1), (10) UNION ALL SELECT x + 1 FROM seq WHERE x < 3 ORDER BY x DESC LIMIT 4) SELECT x FROM seq',
        [],
        [10, 1, 2, 3],
    ],
    'not materialized recursive order offset skips sorted anchor' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1), (10) UNION ALL SELECT x + 1 FROM seq WHERE x < 3 ORDER BY x DESC LIMIT 3 OFFSET 1) SELECT x FROM seq',
        [],
        [1, 2, 3],
    ],
    'materialized recursive descending queue limit' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1), (4) UNION ALL SELECT x + 1 FROM seq WHERE x < 5 ORDER BY x DESC LIMIT 5) SELECT x FROM seq',
        [],
        [4, 5, 1, 2, 3],
    ],
    'not materialized recursive ordinal order limit' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1), (5) UNION ALL SELECT x + 1 FROM seq WHERE x < 4 ORDER BY 1 DESC LIMIT 4) SELECT x FROM seq',
        [],
        [5, 1, 2, 3],
    ],
    'materialized recursive limit feeds outer order expression' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 7 LIMIT 5) SELECT x FROM seq ORDER BY 10 - x',
        [],
        [5, 4, 3, 2, 1],
    ],
    'not materialized recursive limit feeds distinct projection' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1), (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 2 LIMIT 4) SELECT DISTINCT x FROM seq ORDER BY x',
        [],
        [1, 2],
    ],
    'materialized recursive union limit deduplicates before cap' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1), (1) UNION SELECT x + 1 FROM seq WHERE x < 5 LIMIT 4) SELECT x FROM seq',
        [],
        [1, 2, 3, 4],
    ],
    'not materialized recursive union offset follows deduplicated queue' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1), (1) UNION SELECT x + 1 FROM seq WHERE x < 5 LIMIT 2 OFFSET 1) SELECT x FROM seq',
        [],
        [2, 3],
    ],
    'materialized recursive limit feeds aggregate' => [
        'WITH RECURSIVE seq(x, parity) AS MATERIALIZED (VALUES (1, 1) UNION ALL SELECT x + 1, (parity + 1) % 2 FROM seq WHERE x < 10 LIMIT 5) SELECT parity, count(x) AS total FROM seq GROUP BY parity ORDER BY parity',
        [],
        [0, 1],
    ],
    'not materialized recursive offset feeds aggregate' => [
        'WITH RECURSIVE seq(x, parity) AS NOT MATERIALIZED (VALUES (1, 1) UNION ALL SELECT x + 1, (parity + 1) % 2 FROM seq WHERE x < 10 LIMIT 3 OFFSET 2) SELECT parity, count(x) AS total FROM seq GROUP BY parity ORDER BY parity',
        [],
        [0, 1],
    ],
    'materialized recursive limit feeds compound select' => [
        'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 10 LIMIT 3) SELECT x FROM seq UNION SELECT 5 AS x ORDER BY x',
        [],
        [1, 2, 3, 5],
    ],
    'not materialized recursive offset feeds compound all' => [
        'WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 5 LIMIT 2 OFFSET 2) SELECT x FROM seq UNION ALL SELECT 9 AS x ORDER BY x',
        [],
        [3, 4, 9],
    ],
];

foreach ($sequenceCases as $name => $case) {
    [$sql, $tables, $expected] = $case;
    $parameters = $case[3] ?? [];
    $tests['recursive CTE materialized current next26 ' . $name] = static function (TestRunner $t) use ($column, $sql, $tables, $expected, $parameters): void {
        $t->same($expected, $column($sql, array_key_first(SQLiteSelectSql::execute($sql, $tables, $parameters)[0] ?? ['x' => null]) ?? 'x', $tables, $parameters));
    };
}

$wpCases = [
    'materialized id span limits copied option imports' => [
        'WITH RECURSIVE wanted(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 6 LIMIT 4) SELECT option_name FROM wp_options WHERE option_id IN (SELECT id FROM wanted) ORDER BY option_id',
        ['wp_options' => $options],
        ['siteurl', 'home', 'blogname', '_transient_feed'],
    ],
    'not materialized offset skips early copied options' => [
        'WITH RECURSIVE wanted(id) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 6 LIMIT 3 OFFSET 2) SELECT option_name FROM wp_options WHERE option_id IN (SELECT id FROM wanted) ORDER BY option_id',
        ['wp_options' => $options],
        ['blogname', '_transient_feed', '_site_transient_update_plugins'],
    ],
    'materialized recursive source joins copied options' => [
        'WITH RECURSIVE wanted(id) AS MATERIALIZED (VALUES (2) UNION ALL SELECT id + 1 FROM wanted WHERE id < 6 LIMIT 3) SELECT wp_options.option_name AS name FROM wp_options JOIN wanted ON wp_options.option_id = wanted.id ORDER BY wanted.id',
        ['wp_options' => $options],
        ['home', 'blogname', '_transient_feed'],
    ],
    'not materialized recursive source left joins copied options' => [
        'WITH RECURSIVE wanted(id) AS NOT MATERIALIZED (VALUES (5) UNION ALL SELECT id + 1 FROM wanted WHERE id < 7 LIMIT 4) SELECT wanted.id AS id, wp_options.option_name AS name FROM wanted LEFT JOIN wp_options ON wp_options.option_id = wanted.id ORDER BY wanted.id',
        ['wp_options' => $options],
        [5, 6, 7],
    ],
    'materialized recursive source filters autoload options' => [
        "WITH RECURSIVE wanted(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 6 LIMIT 6 OFFSET 1) SELECT option_name FROM wp_options WHERE autoload = 'yes' AND option_id IN (SELECT id FROM wanted) ORDER BY option_id",
        ['wp_options' => $options],
        ['home', 'blogname', 'rewrite_rules'],
    ],
    'not materialized recursive source feeds exists option scan' => [
        'WITH RECURSIVE wanted(id) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT id + 2 FROM wanted WHERE id < 6 LIMIT 3) SELECT option_name FROM wp_options WHERE EXISTS (SELECT id FROM wanted WHERE id = option_id) ORDER BY option_id',
        ['wp_options' => $options],
        ['siteurl', 'blogname', '_site_transient_update_plugins'],
    ],
    'materialized recursive source feeds not exists option scan' => [
        'WITH RECURSIVE wanted(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 2 FROM wanted WHERE id < 6 LIMIT 3) SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT id FROM wanted WHERE id = option_id) ORDER BY option_id',
        ['wp_options' => $options],
        ['home', '_transient_feed', 'rewrite_rules'],
    ],
    'not materialized recursive source feeds grouped copied options' => [
        'WITH RECURSIVE wanted(id) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 6 LIMIT 5) SELECT autoload, count(option_id) AS total FROM wp_options WHERE option_id IN (SELECT id FROM wanted) GROUP BY autoload ORDER BY autoload',
        ['wp_options' => $options],
        ['no', 'yes'],
    ],
    'materialized recursive source participates in limit subquery' => [
        'WITH RECURSIVE wanted(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 6 LIMIT 3) SELECT option_name FROM wp_options ORDER BY option_id LIMIT (SELECT id FROM wanted ORDER BY id DESC LIMIT 1)',
        ['wp_options' => $options],
        ['siteurl', 'home', 'blogname'],
    ],
    'not materialized recursive source participates in offset subquery' => [
        'WITH RECURSIVE wanted(id) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 6 LIMIT 2 OFFSET 2) SELECT option_name FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT id - 1 FROM wanted ORDER BY id LIMIT 1)',
        ['wp_options' => $options],
        ['blogname', '_transient_feed'],
    ],
];

foreach ($wpCases as $name => [$sql, $tables, $expected]) {
    $tests['recursive CTE materialized current next26 wp ' . $name] = static function (TestRunner $t) use ($column, $sql, $tables, $expected): void {
        $rows = SQLiteSelectSql::execute($sql, $tables);
        $field = array_key_first($rows[0] ?? ['option_name' => null]) ?? 'option_name';
        $t->same($expected, array_column($rows, $field));
    };
}

foreach (range(1, 20) as $stop) {
    $limit = max(0, min(6, $stop));
    $expected = $limit === 0 ? [] : range(1, $limit);
    $tests['recursive CTE materialized current next26 generated materialized limit ' . $stop] = static function (TestRunner $t) use ($column, $stop, $limit, $expected): void {
        $sql = "WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < {$stop} LIMIT {$limit}) SELECT x FROM seq";
        $t->same($expected, $column($sql, 'x'));
    };
}

foreach (range(1, 20) as $stop) {
    $limit = 2;
    $offset = min(4, $stop);
    $last = min($stop, $offset + $limit);
    $expected = $last > $offset ? range($offset + 1, $last) : [];
    $tests['recursive CTE materialized current next26 generated not materialized offset ' . $stop] = static function (TestRunner $t) use ($column, $stop, $limit, $offset, $expected): void {
        $sql = "WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < {$stop} LIMIT {$limit} OFFSET {$offset}) SELECT x FROM seq";
        $t->same($expected, $column($sql, 'x'));
    };
}

$errorCases = [
    'negative offset rejected' => 'WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 5 LIMIT 2 OFFSET -1) SELECT x FROM seq',
    'non numeric limit rejected' => "WITH RECURSIVE seq(x) AS NOT MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 5 LIMIT 'two') SELECT x FROM seq",
    'non numeric offset rejected' => "WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 5 LIMIT 2 OFFSET 'one') SELECT x FROM seq",
];

foreach ($errorCases as $name => $sql) {
    $tests['recursive CTE materialized current next26 rejects ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, []));
    };
}

return $tests;
