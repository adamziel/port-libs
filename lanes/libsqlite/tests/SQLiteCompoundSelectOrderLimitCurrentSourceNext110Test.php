<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20, 'priority' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20, 'priority' => 20],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 12, 'priority' => 10],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'bytes' => 64, 'priority' => 40],
];
$stage = [
    ['option_id' => 10, 'option_name' => 'blogdescription', 'autoload' => 'yes', 'bytes' => 18, 'priority' => 15],
    ['option_id' => 11, 'option_name' => 'active_plugins', 'autoload' => 'no', 'bytes' => 80, 'priority' => 50],
    ['option_id' => 12, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'bytes' => 120, 'priority' => 60],
    ['option_id' => 13, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20, 'priority' => 35],
];

$tables = ['wp_options' => $current, 'wp_options_stage' => $stage];

$cases = [
    'orders union all by exact arithmetic expression' => [
        'SELECT option_id + 100 FROM wp_options UNION ALL SELECT option_id + 100 FROM wp_options_stage ORDER BY option_id + 100',
        'expr1',
        [101, 102, 103, 104, 110, 111, 112, 113],
    ],
    'orders union all by exact arithmetic expression descending' => [
        'SELECT option_id + 100 FROM wp_options UNION ALL SELECT option_id + 100 FROM wp_options_stage ORDER BY option_id + 100 DESC',
        'expr1',
        [113, 112, 111, 110, 104, 103, 102, 101],
    ],
    'applies limit after exact expression compound order' => [
        'SELECT option_id + 100 FROM wp_options UNION ALL SELECT option_id + 100 FROM wp_options_stage ORDER BY option_id + 100 DESC LIMIT 3',
        'expr1',
        [113, 112, 111],
    ],
    'applies offset after exact expression compound order' => [
        'SELECT option_id + 100 FROM wp_options UNION ALL SELECT option_id + 100 FROM wp_options_stage ORDER BY option_id + 100 DESC LIMIT 2 OFFSET 3',
        'expr1',
        [110, 104],
    ],
    'applies comma limit after exact expression compound order' => [
        'SELECT option_id + 100 FROM wp_options UNION ALL SELECT option_id + 100 FROM wp_options_stage ORDER BY option_id + 100 LIMIT 2, 3',
        'expr1',
        [103, 104, 110],
    ],
    'orders union distinct by exact arithmetic expression' => [
        'SELECT bytes + 1 FROM wp_options UNION SELECT bytes + 1 FROM wp_options_stage ORDER BY bytes + 1',
        'expr1',
        [13, 19, 21, 65, 81, 121],
    ],
    'orders intersect by exact arithmetic expression' => [
        'SELECT bytes + 1 FROM wp_options INTERSECT SELECT bytes + 1 FROM wp_options_stage ORDER BY bytes + 1',
        'expr1',
        [21],
    ],
    'orders except by exact arithmetic expression' => [
        'SELECT bytes + 1 FROM wp_options EXCEPT SELECT bytes + 1 FROM wp_options_stage ORDER BY bytes + 1',
        'expr1',
        [13, 65],
    ],
    'orders union all by exact concatenation expression' => [
        "SELECT option_name || ':' || autoload FROM wp_options WHERE option_id <= 2 UNION ALL SELECT option_name || ':' || autoload FROM wp_options_stage WHERE option_id IN (10, 11) ORDER BY option_name || ':' || autoload",
        'expr1',
        ['active_plugins:no', 'blogdescription:yes', 'home:yes', 'siteurl:yes'],
    ],
    'orders union all by exact concatenation expression descending with limit' => [
        "SELECT option_name || ':' || autoload FROM wp_options WHERE option_id <= 2 UNION ALL SELECT option_name || ':' || autoload FROM wp_options_stage WHERE option_id IN (10, 11) ORDER BY option_name || ':' || autoload DESC LIMIT 2",
        'expr1',
        ['siteurl:yes', 'home:yes'],
    ],
    'orders alias output using exact expression rather than alias text' => [
        "SELECT option_name || ':' || autoload AS label FROM wp_options WHERE option_id <= 2 UNION ALL SELECT option_name || ':' || autoload AS label FROM wp_options_stage WHERE option_id IN (10, 11) ORDER BY option_name || ':' || autoload",
        'label',
        ['active_plugins:no', 'blogdescription:yes', 'home:yes', 'siteurl:yes'],
    ],
    'orders exact expression with final collation' => [
        "SELECT option_name || autoload AS label FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT option_name || autoload AS label FROM wp_options_stage WHERE option_id IN (10, 11) ORDER BY option_name || autoload COLLATE NOCASE",
        'label',
        ['active_pluginsno', 'blogdescriptionyes', 'homeyes', 'siteurlyes'],
    ],
    'orders exact expression with final collation descending' => [
        "SELECT option_name || autoload AS label FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT option_name || autoload AS label FROM wp_options_stage WHERE option_id IN (10, 11) ORDER BY option_name || autoload COLLATE NOCASE DESC",
        'label',
        ['siteurlyes', 'homeyes', 'blogdescriptionyes', 'active_pluginsno'],
    ],
    'orders by exact scalar function expression' => [
        'SELECT length(option_name) FROM wp_options UNION ALL SELECT length(option_name) FROM wp_options_stage ORDER BY length(option_name), 1',
        'expr1',
        [4, 7, 7, 8, 13, 14, 14, 15],
    ],
    'orders by exact scalar function expression descending with offset' => [
        'SELECT length(option_name) FROM wp_options UNION ALL SELECT length(option_name) FROM wp_options_stage ORDER BY length(option_name) DESC LIMIT 3 OFFSET 1',
        'expr1',
        [14, 14, 13],
    ],
    'orders by exact unary expression' => [
        'SELECT -priority FROM wp_options UNION ALL SELECT -priority FROM wp_options_stage ORDER BY -priority',
        'expr1',
        [-60, -50, -40, -35, -30, -20, -15, -10],
    ],
    'orders by exact unary expression descending' => [
        'SELECT -priority FROM wp_options UNION ALL SELECT -priority FROM wp_options_stage ORDER BY -priority DESC LIMIT 4',
        'expr1',
        [-10, -15, -20, -30],
    ],
    'orders by exact cast expression' => [
        'SELECT CAST(priority AS TEXT) FROM wp_options WHERE option_id <= 3 UNION ALL SELECT CAST(priority AS TEXT) FROM wp_options_stage WHERE option_id <= 12 ORDER BY CAST(priority AS TEXT)',
        'expr1',
        ['10', '15', '20', '30', '50', '60'],
    ],
    'orders by exact cast expression descending with limit' => [
        'SELECT CAST(priority AS TEXT) FROM wp_options WHERE option_id <= 3 UNION ALL SELECT CAST(priority AS TEXT) FROM wp_options_stage WHERE option_id <= 12 ORDER BY CAST(priority AS TEXT) DESC LIMIT 2',
        'expr1',
        ['60', '50'],
    ],
    'orders by exact case expression' => [
        "SELECT CASE autoload WHEN 'yes' THEN 0 ELSE 1 END FROM wp_options UNION ALL SELECT CASE autoload WHEN 'yes' THEN 0 ELSE 1 END FROM wp_options_stage ORDER BY CASE autoload WHEN 'yes' THEN 0 ELSE 1 END, 1",
        'expr1',
        [0, 0, 0, 0, 0, 1, 1, 1],
    ],
    'orders by exact case expression descending' => [
        "SELECT CASE autoload WHEN 'yes' THEN 0 ELSE 1 END FROM wp_options UNION ALL SELECT CASE autoload WHEN 'yes' THEN 0 ELSE 1 END FROM wp_options_stage ORDER BY CASE autoload WHEN 'yes' THEN 0 ELSE 1 END DESC LIMIT 4",
        'expr1',
        [1, 1, 1, 0],
    ],
    'orders by exact parenthesized expression' => [
        'SELECT (option_id + priority) FROM wp_options UNION ALL SELECT (option_id + priority) FROM wp_options_stage ORDER BY option_id + priority',
        'expr1',
        [13, 22, 25, 31, 44, 48, 61, 72],
    ],
    'orders by exact expression before alias tie breaker' => [
        "SELECT bytes + priority AS weight, option_name AS name FROM wp_options UNION ALL SELECT bytes + priority AS weight, option_name AS name FROM wp_options_stage ORDER BY bytes + priority, name LIMIT 5",
        'name',
        ['blogname', 'blogdescription', 'home', 'siteurl', 'siteurl'],
    ],
    'orders by exact expression after alias tie breaker' => [
        "SELECT autoload AS flag, bytes + priority AS weight, option_name AS name FROM wp_options UNION ALL SELECT autoload AS flag, bytes + priority AS weight, option_name AS name FROM wp_options_stage ORDER BY flag DESC, bytes + priority LIMIT 5",
        'name',
        ['blogname', 'blogdescription', 'home', 'siteurl', 'siteurl'],
    ],
    'orders by exact expression with nulls last' => [
        "SELECT CASE autoload WHEN 'yes' THEN option_name ELSE NULL END AS maybe_name FROM wp_options UNION ALL SELECT CASE autoload WHEN 'yes' THEN option_name ELSE NULL END AS maybe_name FROM wp_options_stage ORDER BY CASE autoload WHEN 'yes' THEN option_name ELSE NULL END NULLS LAST LIMIT 5",
        'maybe_name',
        ['blogdescription', 'blogname', 'home', 'siteurl', 'siteurl'],
    ],
    'orders by exact expression with nulls first descending' => [
        "SELECT CASE autoload WHEN 'yes' THEN option_name ELSE NULL END AS maybe_name FROM wp_options UNION ALL SELECT CASE autoload WHEN 'yes' THEN option_name ELSE NULL END AS maybe_name FROM wp_options_stage ORDER BY CASE autoload WHEN 'yes' THEN option_name ELSE NULL END DESC NULLS FIRST LIMIT 4",
        'maybe_name',
        [null, null, null, 'siteurl'],
    ],
    'orders union distinct by exact expression after duplicate collapse' => [
        'SELECT option_id % 2 FROM wp_options UNION SELECT option_id % 2 FROM wp_options_stage ORDER BY option_id % 2 DESC',
        'expr1',
        [1, 0],
    ],
    'orders union all by exact modulo expression' => [
        'SELECT option_id % 2 FROM wp_options UNION ALL SELECT option_id % 2 FROM wp_options_stage ORDER BY option_id % 2, 1 LIMIT 6',
        'expr1',
        [0, 0, 0, 0, 1, 1],
    ],
    'orders intersect by exact modulo expression' => [
        'SELECT option_id % 3 FROM wp_options INTERSECT SELECT option_id % 3 FROM wp_options_stage ORDER BY option_id % 3',
        'expr1',
        [0, 1, 2],
    ],
    'orders except by exact modulo expression' => [
        'SELECT option_id % 3 FROM wp_options EXCEPT SELECT option_id % 3 FROM wp_options_stage ORDER BY option_id % 3',
        'expr1',
        [],
    ],
    'orders cte-fed compound by exact expression' => [
        'WITH picked(id, weight) AS (VALUES (1, 30), (2, 20), (9, 5)) SELECT id + weight FROM picked UNION ALL SELECT option_id + priority FROM wp_options_stage WHERE option_id IN (10, 11) ORDER BY id + weight',
        'expr1',
        [14, 22, 25, 31, 61],
    ],
    'orders cte-fed compound by exact expression with offset' => [
        'WITH picked(id, weight) AS (VALUES (1, 30), (2, 20), (9, 5)) SELECT id + weight FROM picked UNION ALL SELECT option_id + priority FROM wp_options_stage WHERE option_id IN (10, 11) ORDER BY id + weight LIMIT 2 OFFSET 2',
        'expr1',
        [25, 31],
    ],
    'orders recursive cte compound by exact expression' => [
        'WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 3) SELECT id + 10 FROM seq UNION ALL SELECT option_id + 10 FROM wp_options WHERE option_id = 4 ORDER BY id + 10',
        'expr1',
        [11, 12, 13, 14],
    ],
    'orders recursive cte compound by exact expression descending limit' => [
        'WITH RECURSIVE seq(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 3) SELECT id + 10 FROM seq UNION ALL SELECT option_id + 10 FROM wp_options WHERE option_id = 4 ORDER BY id + 10 DESC LIMIT 2',
        'expr1',
        [14, 13],
    ],
    'orders compound by exact json expression' => [
        "SELECT json_extract('{\"rank\":2}', '$.rank') FROM wp_options WHERE option_id = 1 UNION ALL SELECT json_extract('{\"rank\":1}', '$.rank') FROM wp_options_stage WHERE option_id = 10 ORDER BY json_extract('{\"rank\":2}', '$.rank')",
        'expr1',
        [1, 2],
    ],
    'orders compound by exact json expression descending' => [
        "SELECT json_extract('{\"rank\":2}', '$.rank') FROM wp_options WHERE option_id = 1 UNION ALL SELECT json_extract('{\"rank\":1}', '$.rank') FROM wp_options_stage WHERE option_id = 10 ORDER BY json_extract('{\"rank\":2}', '$.rank') DESC",
        'expr1',
        [2, 1],
    ],
    'orders compound by exact bitwise expression' => [
        'SELECT priority & 48 FROM wp_options UNION SELECT priority & 48 FROM wp_options_stage ORDER BY priority & 48',
        'expr1',
        [0, 16, 32, 48],
    ],
    'orders compound by exact shift expression' => [
        'SELECT priority >> 4 FROM wp_options UNION ALL SELECT priority >> 4 FROM wp_options_stage ORDER BY priority >> 4, 1',
        'expr1',
        [0, 0, 1, 1, 2, 2, 3, 3],
    ],
    'orders compound by exact multiplication expression' => [
        'SELECT option_id * 2 FROM wp_options UNION ALL SELECT option_id * 2 FROM wp_options_stage ORDER BY option_id * 2 DESC LIMIT 3',
        'expr1',
        [26, 24, 22],
    ],
    'orders compound by exact division expression' => [
        'SELECT priority / 10 FROM wp_options UNION ALL SELECT priority / 10 FROM wp_options_stage ORDER BY priority / 10',
        'expr1',
        [1, 1.5, 2, 3, 3.5, 4, 5, 6],
    ],
    'orders compound by exact expression with ordinal tail' => [
        'SELECT option_id + priority, option_name AS name FROM wp_options UNION ALL SELECT option_id + priority, option_name AS name FROM wp_options_stage ORDER BY option_id + priority DESC, 2 LIMIT 3',
        'name',
        ['rewrite_rules', 'active_plugins', 'siteurl'],
    ],
    'orders compound by ordinal before exact expression' => [
        'SELECT autoload AS flag, option_id + priority AS weight, option_name AS name FROM wp_options UNION ALL SELECT autoload AS flag, option_id + priority AS weight, option_name AS name FROM wp_options_stage ORDER BY 1, option_id + priority LIMIT 4',
        'name',
        ['active_plugins', 'active_plugins', 'rewrite_rules', 'blogname'],
    ],
    'plans exact expression order by against projected expression column' => [
        'PLAN:SELECT option_id + priority FROM wp_options UNION ALL SELECT option_id + priority FROM wp_options_stage ORDER BY option_id + priority DESC LIMIT 2',
        'column',
        ['expr1'],
    ],
    'plans exact expression order by against aliased projected expression column' => [
        'PLAN:SELECT option_id + priority AS weight FROM wp_options UNION ALL SELECT option_id + priority AS weight FROM wp_options_stage ORDER BY option_id + priority DESC LIMIT 2',
        'column',
        ['weight'],
    ],
    'rejects non-result expression in compound order by' => [
        'ERR:SELECT option_id + 1 FROM wp_options UNION ALL SELECT option_id + 1 FROM wp_options_stage ORDER BY option_id + 2',
        null,
        [InvalidArgumentException::class],
    ],
    'rejects unsupported exact-expression function in compound order by' => [
        'ERR:SELECT option_id + 1 FROM wp_options UNION ALL SELECT option_id + 1 FROM wp_options_stage ORDER BY missing_function(option_id)',
        null,
        [InvalidArgumentException::class],
    ],
];

$tests = [];

foreach ($cases as $name => [$sql, $column, $expected]) {
    $tests['compound select order limit current source next110 ' . $name] = static function (TestRunner $t) use ($tables, $sql, $column, $expected): void {
        if (str_starts_with($sql, 'ERR:')) {
            $t->throws($expected[0], static fn () => SQLiteSelectSql::execute(substr($sql, 4), $tables));

            return;
        }
        if (str_starts_with($sql, 'PLAN:')) {
            $plan = SQLiteSelectSql::plan(substr($sql, 5), $tables);
            $t->same($expected, array_column($plan['compound']['orderBy'], $column));

            return;
        }

        $t->same($expected, array_column(SQLiteSelectSql::execute($sql, $tables), (string) $column));
    };
}

return $tests;
