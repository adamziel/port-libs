<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectCompound;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'depth' => 0],
    ['option_id' => 8, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'depth' => 0],
    ['option_id' => 10, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'depth' => 0],
    ['option_id' => 12, 'option_name' => 'widget_text', 'autoload' => 'yes', 'depth' => 0],
];

$tables = ['wp_options' => $options];

$column = static fn (string $sql, string $column = 'id'): array => array_column(SQLiteSelectSql::execute($sql, $tables), $column);
$rows = static fn (string $sql): array => SQLiteSelectSql::execute($sql, $tables);
$trace = static fn (string $sql): array => SQLiteSelectSql::recursiveCteCycleTrace($sql, $tables);

$cases = [
    'preserves left recursive column name over current source arm' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 9 LIMIT 4) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options WHERE autoload = 'no' ORDER BY id",
        'id',
        [1, 2, 3, 4, 8],
    ],
    'renames current source alias to left recursive name' => [
        "WITH RECURSIVE seq(id) AS (VALUES (2) UNION ALL SELECT id + 2 FROM seq WHERE id < 8 LIMIT 4) SELECT id FROM seq UNION ALL SELECT option_id AS current_id FROM wp_options WHERE option_id = 10 ORDER BY id",
        'id',
        [2, 4, 6, 8, 10],
    ],
    'keeps left alias across recursive and current arms' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 5 LIMIT 3) SELECT id AS option_id FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id IN (4, 8) ORDER BY option_id",
        'option_id',
        [1, 2, 3, 4, 8],
    ],
    'applies final limit after recursive queue limit and current arm' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 9 LIMIT 5) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options ORDER BY id DESC LIMIT 4",
        'id',
        [12, 10, 8, 5],
    ],
    'applies final offset after recursive queue limit and current arm' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 9 LIMIT 5) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options ORDER BY id LIMIT 4 OFFSET 3",
        'id',
        [4, 4, 5, 8],
    ],
    'applies comma limit after recursive queue limit and current arm' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 9 LIMIT 5) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options ORDER BY id LIMIT 2, 5",
        'id',
        [3, 4, 4, 5, 8],
    ],
    'deduplicates union by position after right arm rename' => [
        "WITH RECURSIVE seq(id) AS (VALUES (4) UNION ALL SELECT id + 4 FROM seq WHERE id < 12 LIMIT 3) SELECT id FROM seq UNION SELECT option_id FROM wp_options ORDER BY id",
        'id',
        [4, 8, 10, 12],
    ],
    'intersects recursive ids with current source by position' => [
        "WITH RECURSIVE seq(id) AS (VALUES (4) UNION ALL SELECT id + 4 FROM seq WHERE id < 12 LIMIT 4) SELECT id FROM seq INTERSECT SELECT option_id FROM wp_options ORDER BY id",
        'id',
        [4, 8, 12],
    ],
    'except removes current source ids by position' => [
        "WITH RECURSIVE seq(id) AS (VALUES (4) UNION ALL SELECT id + 4 FROM seq WHERE id < 16 LIMIT 4) SELECT id FROM seq EXCEPT SELECT option_id FROM wp_options ORDER BY id",
        'id',
        [16],
    ],
    'uses recursive queue offset before compound current source rows' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 7 LIMIT 3 OFFSET 2) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id = 4 ORDER BY id",
        'id',
        [3, 4, 4, 5],
    ],
    'uses recursive queue order before limit then compounds current source rows' => [
        "WITH RECURSIVE seq(id, depth) AS (VALUES (1, 0) UNION ALL SELECT id + 1, depth + 1 FROM seq WHERE id < 5 ORDER BY 2 DESC LIMIT 4) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id = 10 ORDER BY id",
        'id',
        [1, 2, 3, 4, 10],
    ],
    'keeps left-most two-column names with current source aliases' => [
        "WITH RECURSIVE seq(id, label) AS (VALUES (1, 'seed') UNION ALL SELECT id + 1, label || ':' || (id + 1) FROM seq WHERE id < 3 LIMIT 3) SELECT id, label FROM seq UNION ALL SELECT option_id AS option_id, option_name AS option_name FROM wp_options WHERE option_id = 4 ORDER BY id",
        'label',
        ['seed', 'seed:2', 'seed:2:3', 'active_plugins'],
    ],
    'orders by first left-most name after right arm rename' => [
        "WITH RECURSIVE seq(id, label) AS (VALUES (1, 'seed') UNION ALL SELECT id + 1, label || ':' || (id + 1) FROM seq WHERE id < 3 LIMIT 3) SELECT id, label FROM seq UNION ALL SELECT option_id AS option_id, option_name AS option_name FROM wp_options WHERE option_id = 4 ORDER BY label DESC",
        'id',
        [3, 2, 1, 4],
    ],
    'supports recursive left expression column over current source column' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 4 LIMIT 4) SELECT id + 100 FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id = 12 ORDER BY expr1",
        'expr1',
        [12, 101, 102, 103, 104],
    ],
    'supports current source expression renamed to recursive expression column' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 4 LIMIT 4) SELECT id + 100 FROM seq UNION ALL SELECT option_id + 100 AS current_expr FROM wp_options WHERE option_id = 4 ORDER BY expr1",
        'expr1',
        [101, 102, 103, 104, 104],
    ],
    'supports union distinct with expression left column and current source alias' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 4 LIMIT 4) SELECT id % 2 FROM seq UNION SELECT option_id AS current_mod FROM wp_options ORDER BY expr1",
        'expr1',
        [0, 1, 4, 8, 10, 12],
    ],
    'supports intersect with expression left column and current source expression' => [
        "WITH RECURSIVE seq(id) AS (VALUES (4) UNION ALL SELECT id + 4 FROM seq WHERE id < 12 LIMIT 4) SELECT id % 8 FROM seq INTERSECT SELECT option_id % 8 FROM wp_options ORDER BY expr1",
        'expr1',
        [0, 4],
    ],
    'supports except with expression left column and current source expression' => [
        "WITH RECURSIVE seq(id) AS (VALUES (4) UNION ALL SELECT id + 4 FROM seq WHERE id < 16 LIMIT 4) SELECT id % 10 FROM seq EXCEPT SELECT option_id % 10 FROM wp_options ORDER BY expr1",
        'expr1',
        [6],
    ],
    'preserves duplicate recursive and current rows for union all' => [
        "WITH RECURSIVE seq(id) AS (VALUES (4) UNION ALL SELECT id FROM seq WHERE id = 4 LIMIT 3) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id = 4 ORDER BY id",
        'id',
        [4, 4, 4, 4],
    ],
    'deduplicates recursive union before current compound union' => [
        "WITH RECURSIVE seq(id) AS (VALUES (4) UNION SELECT id FROM seq WHERE id = 4 LIMIT 5) SELECT id FROM seq UNION SELECT option_id FROM wp_options WHERE option_id IN (4, 8) ORDER BY id",
        'id',
        [4, 8],
    ],
    'limit zero recursive cte still compounds current source rows' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 4 LIMIT 0) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id = 8 ORDER BY id",
        'id',
        [8],
    ],
    'negative recursive limit keeps current source rows after full recursion' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 3 LIMIT -1) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id = 4 ORDER BY id",
        'id',
        [1, 2, 3, 4],
    ],
    'final limit zero suppresses recursive and current compound rows' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 3 LIMIT -1) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options LIMIT 0",
        'id',
        [],
    ],
    'compound output row keys are left-most names only' => [
        "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 2 LIMIT 2) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id = 4 ORDER BY id",
        'KEYS',
        [['id'], ['id'], ['id']],
    ],
];

$tests = [];

foreach ($cases as $name => [$sql, $selectedColumn, $expected]) {
    $tests['compound recursive limit current source next117 ' . $name] = static function (TestRunner $t) use ($column, $rows, $sql, $selectedColumn, $expected): void {
        if ($selectedColumn === 'KEYS') {
            $t->same($expected, array_map('array_keys', $rows($sql)));

            return;
        }

        $t->same($expected, $column($sql, $selectedColumn));
    };
}

$tests['compound recursive limit current source next117 direct combiner renames right row by position'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectCompound::union([['id' => 1]], [['option_id' => 2]], true);
    $t->same([['id' => 1], ['id' => 2]], $rows);
};

$tests['compound recursive limit current source next117 direct combiner union distinct uses renamed values'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectCompound::union([['id' => 4]], [['option_id' => 4], ['option_id' => 8]]);
    $t->same([['id' => 4], ['id' => 8]], $rows);
};

$tests['compound recursive limit current source next117 direct combiner intersect uses positional columns'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectCompound::intersect([['id' => 4], ['id' => 8]], [['option_id' => 8], ['option_id' => 12]]);
    $t->same([['id' => 8]], $rows);
};

$tests['compound recursive limit current source next117 direct combiner except uses positional columns'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectCompound::except([['id' => 4], ['id' => 8]], [['option_id' => 8], ['option_id' => 12]]);
    $t->same([['id' => 4]], $rows);
};

$tests['compound recursive limit current source next117 direct combiner rejects right width mismatch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCompound::union([['id' => 1]], [['option_id' => 2, 'name' => 'x']], true));
};

$tests['compound recursive limit current source next117 trace records limit exhaustion before current source compound'] = static function (TestRunner $t) use ($trace): void {
    $result = $trace("WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 9 LIMIT 4) SELECT id FROM seq");
    $t->same([1, 2, 3, 4], array_column($result['rows'], 'id'));
    $t->same(0, $result['trace'][3]['limit_remaining']);
    $t->same([], $result['trace'][3]['queue_after']);
};

$tests['compound recursive limit current source next117 trace records offset skipped current rows'] = static function (TestRunner $t) use ($trace): void {
    $result = $trace("WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 5 LIMIT 2 OFFSET 2) SELECT id FROM seq");
    $t->same([3, 4], array_column($result['rows'], 'id'));
    $t->same(false, $result['trace'][0]['emitted']);
    $t->same(false, $result['trace'][1]['emitted']);
    $t->same(true, $result['trace'][2]['emitted']);
};

$tests['compound recursive limit current source next117 trace records accepted next after skipped offset row'] = static function (TestRunner $t) use ($trace): void {
    $result = $trace("WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id FROM seq");
    $t->same([['id' => 2]], $result['trace'][0]['accepted_next']);
    $t->same([2, 3], array_column($result['rows'], 'id'));
};

$tests['compound recursive limit current source next117 trace dependency includes recursive current row'] = static function (TestRunner $t) use ($trace): void {
    $result = $trace("WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 3 LIMIT 2) SELECT id FROM seq");
    $t->true(in_array('sqlite-recursive-cte-current-row', $result['dependencies'], true));
};

$tests['compound recursive limit current source next117 trace rejects negative offset'] = static function (TestRunner $t) use ($trace): void {
    $t->throws(InvalidArgumentException::class, static fn () => $trace("WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 3 LIMIT 2 OFFSET -1) SELECT id FROM seq"));
};

foreach (range(1, 12) as $limit) {
    $tests['compound recursive limit current source next117 generated queue limit ' . $limit] = static function (TestRunner $t) use ($column, $limit): void {
        $sql = "WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 20 LIMIT {$limit}) SELECT id FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id = 12 ORDER BY id";
        $expected = range(1, $limit);
        $expected[] = 12;
        sort($expected);
        $t->same($expected, $column($sql));
    };
}

return $tests;
