<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'priority' => 20],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'priority' => 5],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'priority' => 40],
];
$nextOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 30],
    ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'autoload' => 'no', 'priority' => 50],
    ['option_id' => 6, 'option_name' => 'cleanup_marker', 'autoload' => 'no', 'priority' => 7],
];
$tables = ['wp_options' => $options, 'wp_options_next' => $nextOptions];

$normalize = null;
$normalize = static function (mixed $value) use (&$normalize): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return ['blob' => bin2hex($value->bytes)];
    }
    if (is_array($value)) {
        return array_map(static fn (mixed $item): mixed => $normalize($item), $value);
    }

    return $value;
};

$tests = [];

$column = static fn (string $sql, string $name): array => array_column(SQLiteSelectSql::execute($sql, $tables), $name);

$cases = [
    'values left arm union all orders by ordinal with table current source' => [
        "VALUES (3, 'seed'), (1, 'first') UNION ALL SELECT option_id, option_name FROM wp_options WHERE option_id = 2 ORDER BY 1",
        'column1',
        [1, 2, 3],
    ],
    'values left arm output names survive right aliases' => [
        "VALUES (1, 'seed') UNION ALL SELECT option_id AS id, option_name AS name FROM wp_options WHERE option_id = 2",
        'KEYS',
        [['column1', 'column2'], ['column1', 'column2']],
    ],
    'values left arm union distinct removes current source duplicate integer' => [
        'VALUES (1), (2), (2) UNION SELECT option_id FROM wp_options WHERE option_id IN (1, 3) ORDER BY 1',
        'column1',
        [1, 2, 3],
    ],
    'values left arm union all preserves current source duplicates' => [
        'VALUES (1), (1) UNION ALL SELECT option_id FROM wp_options WHERE option_id = 1 ORDER BY 1',
        'column1',
        [1, 1, 1],
    ],
    'values left arm intersect matches current source integers' => [
        'VALUES (1), (3), (9) INTERSECT SELECT option_id FROM wp_options ORDER BY 1',
        'column1',
        [1, 3],
    ],
    'values left arm except removes current source integers' => [
        'VALUES (1), (3), (9) EXCEPT SELECT option_id FROM wp_options ORDER BY 1',
        'column1',
        [9],
    ],
    'values left arm compound keeps integer text distinct under union' => [
        "VALUES (1), ('1') UNION SELECT option_id FROM wp_options WHERE option_id = 1 ORDER BY 1",
        'column1',
        [1, '1'],
    ],
    'values left arm compound keeps integer text distinct under intersect' => [
        "VALUES (1), ('1') INTERSECT SELECT CAST(option_id AS TEXT) FROM wp_options WHERE option_id = 1 ORDER BY 1",
        'column1',
        ['1'],
    ],
    'values left arm compound numeric integer real duplicate under except' => [
        'VALUES (1), (2) EXCEPT SELECT 1.0 AS id ORDER BY 1',
        'column1',
        [2],
    ],
    'values left arm order uses storage class before text comparison' => [
        "VALUES ('0'), (2), (NULL), (X'30'), (1.5) UNION ALL SELECT option_name FROM wp_options WHERE option_id = 2 ORDER BY 1",
        'column1',
        [null, 1.5, 2, '0', 'home', new SQLiteBlobValue('0')],
    ],
    'values left arm order nulls last with mixed storage classes' => [
        "VALUES ('0'), (2), (NULL), (1) UNION ALL SELECT option_name FROM wp_options WHERE option_id = 2 ORDER BY 1 NULLS LAST",
        'column1',
        [1, 2, '0', 'home', null],
    ],
    'values left arm order desc nulls first with mixed storage classes' => [
        "VALUES ('0'), (2), (NULL), (1) UNION ALL SELECT option_name FROM wp_options WHERE option_id = 2 ORDER BY 1 DESC NULLS FIRST",
        'column1',
        [null, 'home', '0', 2, 1],
    ],
    'values left arm nocase collation orders table text result' => [
        "VALUES ('Zoo'), ('alpha') UNION ALL SELECT option_name FROM wp_options WHERE option_id IN (2, 4) ORDER BY 1 COLLATE NOCASE",
        'column1',
        ['active_plugins', 'alpha', 'home', 'Zoo'],
    ],
    'values left arm rtrim collation collapses trailing spaces order ties stably' => [
        "VALUES ('siteurl  '), ('siteurl') UNION ALL SELECT option_name FROM wp_options WHERE option_id = 1 ORDER BY 1 COLLATE RTRIM",
        'column1',
        ['siteurl  ', 'siteurl', 'siteurl'],
    ],
    'values left arm limit applies after compound order' => [
        'VALUES (4), (1), (3) UNION ALL SELECT option_id FROM wp_options WHERE option_id = 2 ORDER BY 1 LIMIT 2',
        'column1',
        [1, 2],
    ],
    'values left arm offset applies after compound order' => [
        'VALUES (4), (1), (3) UNION ALL SELECT option_id FROM wp_options WHERE option_id = 2 ORDER BY 1 LIMIT 2 OFFSET 1',
        'column1',
        [2, 3],
    ],
    'values left arm comma limit applies after compound order' => [
        'VALUES (4), (1), (3) UNION ALL SELECT option_id FROM wp_options WHERE option_id = 2 ORDER BY 1 LIMIT 1, 2',
        'column1',
        [2, 3],
    ],
    'values left arm supports final select expression order by matching select arm' => [
        'VALUES (31) UNION ALL SELECT option_id + priority FROM wp_options WHERE option_id IN (2, 3) ORDER BY option_id + priority',
        'column1',
        [8, 22, 31],
    ],
    'values left arm supports right arm alias order by position name from left' => [
        'VALUES (31) UNION ALL SELECT option_id + priority AS weight FROM wp_options WHERE option_id IN (2, 3) ORDER BY column1',
        'column1',
        [8, 22, 31],
    ],
    'values left arm with cte current source orders next source rows' => [
        'WITH seed(v) AS (VALUES (7), (50)) VALUES (30) UNION ALL SELECT v FROM seed ORDER BY 1',
        'column1',
        [7, 30, 50],
    ],
    'pure top level values now returns sqlite column names' => [
        "VALUES (1, 'siteurl'), (2, 'home')",
        'ROWS',
        [['column1' => 1, 'column2' => 'siteurl'], ['column1' => 2, 'column2' => 'home']],
    ],
    'plan exposes values arm output columns' => [
        'PLAN:VALUES (1, 2) UNION ALL SELECT option_id, priority FROM wp_options WHERE option_id = 2 ORDER BY 1',
        'PLAN_COLUMNS',
        [['column1', 'column2'], ['option_id', 'priority']],
    ],
    'plan exposes compound operator for values left arm' => [
        'PLAN:VALUES (1) INTERSECT SELECT option_id FROM wp_options',
        'PLAN_OPERATORS',
        ['INTERSECT'],
    ],
    'rejects final values arm with order by like sqlite' => [
        'ERR:SELECT option_id FROM wp_options UNION ALL VALUES (9) ORDER BY 1',
        null,
        [InvalidArgumentException::class],
    ],
    'rejects final values arm with limit like sqlite' => [
        'ERR:SELECT option_id FROM wp_options UNION ALL VALUES (9) LIMIT 1',
        null,
        [InvalidArgumentException::class],
    ],
    'rejects values left arm width mismatch' => [
        'ERR:VALUES (1, 2) UNION ALL SELECT option_id FROM wp_options',
        null,
        [InvalidArgumentException::class],
    ],
];

foreach ($cases as $name => [$sql, $columnName, $expected]) {
    $tests['compound values affinity order current source next127 ' . $name] = static function (TestRunner $t) use ($sql, $columnName, $expected, $tables, $normalize): void {
        if (str_starts_with($sql, 'ERR:')) {
            $t->throws($expected[0], static fn () => SQLiteSelectSql::execute(substr($sql, 4), $tables));

            return;
        }
        if (str_starts_with($sql, 'PLAN:')) {
            $plan = SQLiteSelectSql::plan(substr($sql, 5), $tables);
            if ($columnName === 'PLAN_COLUMNS') {
                $actual = [];
                foreach ($plan['compound']['arms'] as $arm) {
                    $actual[] = array_column($arm['select'], 'name');
                }
                $t->same($expected, $actual);

                return;
            }
            $t->same($expected, $plan['compound']['operators']);

            return;
        }

        $rows = SQLiteSelectSql::execute($sql, $tables);
        if ($columnName === 'KEYS') {
            $t->same($expected, array_map('array_keys', $rows));

            return;
        }
        if ($columnName === 'ROWS') {
            $t->same($normalize($expected), $normalize($rows));

            return;
        }

        $t->same($normalize($expected), $normalize(array_column($rows, (string) $columnName)));
    };
}

foreach (range(1, 24) as $id) {
    $tests['compound values affinity order current source next127 generated union all value order ' . $id] = static function (TestRunner $t) use ($column, $id): void {
        $sql = 'VALUES (' . ($id + 20) . "), ('" . ($id + 20) . "'), (" . $id . ') UNION ALL SELECT ' . ($id + 10) . ' AS v ORDER BY 1';
        $t->same([$id, $id + 10, $id + 20, (string) ($id + 20)], $column($sql, 'column1'));
    };
}

foreach (range(1, 12) as $id) {
    $tests['compound values affinity order current source next127 generated intersect numeric text split ' . $id] = static function (TestRunner $t) use ($column, $id): void {
        $sql = "VALUES ({$id}), ('{$id}') INTERSECT SELECT '{$id}' AS v ORDER BY 1";
        $t->same([(string) $id], $column($sql, 'column1'));
    };
}

return $tests;
