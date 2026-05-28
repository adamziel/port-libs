<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20, 'priority' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 10, 'priority' => 20],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 12, 'priority' => 10],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'bytes' => 64, 'priority' => 40],
];
$stage = [
    ['stage_id' => 10, 'stage_name' => 'blogdescription', 'load_flag' => 'yes', 'payload_bytes' => 18, 'rank' => 15],
    ['stage_id' => 11, 'stage_name' => 'active_plugins', 'load_flag' => 'no', 'payload_bytes' => 80, 'rank' => 50],
    ['stage_id' => 12, 'stage_name' => 'rewrite_rules', 'load_flag' => 'no', 'payload_bytes' => 120, 'rank' => 60],
    ['stage_id' => 13, 'stage_name' => 'siteurl', 'load_flag' => 'yes', 'payload_bytes' => 20, 'rank' => 35],
];
$network = [
    ['network_id' => 20, 'network_name' => 'home', 'network_load' => 'yes', 'network_bytes' => 10, 'network_rank' => 25],
    ['network_id' => 21, 'network_name' => 'siteurl', 'network_load' => 'yes', 'network_bytes' => 20, 'network_rank' => 30],
    ['network_id' => 22, 'network_name' => 'theme_mods', 'network_load' => 'yes', 'network_bytes' => 44, 'network_rank' => 45],
];

$tables = ['wp_options' => $current, 'wp_options_stage' => $stage, 'wp_sitemeta' => $network];

$cases = [
    'orders by right arm alias and returns left name' => [
        "SELECT option_id AS id FROM wp_options WHERE option_id IN (2, 4) UNION ALL SELECT stage_id AS staged_id FROM wp_options_stage WHERE stage_id IN (10, 11) ORDER BY staged_id",
        'id',
        [2, 4, 10, 11],
    ],
    'orders by right arm alias descending' => [
        "SELECT option_id AS id FROM wp_options WHERE option_id IN (2, 4) UNION ALL SELECT stage_id AS staged_id FROM wp_options_stage WHERE stage_id IN (10, 11) ORDER BY staged_id DESC",
        'id',
        [11, 10, 4, 2],
    ],
    'orders by right arm source column and maps to left alias' => [
        "SELECT option_id AS id FROM wp_options WHERE option_id IN (1, 4) UNION ALL SELECT stage_id FROM wp_options_stage WHERE stage_id IN (10, 13) ORDER BY stage_id",
        'id',
        [1, 4, 10, 13],
    ],
    'orders by right arm source column descending with limit' => [
        "SELECT option_id AS id FROM wp_options WHERE option_id IN (1, 4) UNION ALL SELECT stage_id FROM wp_options_stage WHERE stage_id IN (10, 13) ORDER BY stage_id DESC LIMIT 2",
        'id',
        [13, 10],
    ],
    'orders by third arm alias' => [
        "SELECT option_name AS name FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT stage_name AS stage_label FROM wp_options_stage WHERE stage_id = 10 UNION ALL SELECT network_name AS network_label FROM wp_sitemeta WHERE network_id = 22 ORDER BY network_label",
        'name',
        ['blogdescription', 'home', 'siteurl', 'theme_mods'],
    ],
    'orders by third arm source column descending' => [
        "SELECT option_name AS name FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT stage_name FROM wp_options_stage WHERE stage_id = 10 UNION ALL SELECT network_name FROM wp_sitemeta WHERE network_id = 22 ORDER BY network_name DESC",
        'name',
        ['theme_mods', 'siteurl', 'home', 'blogdescription'],
    ],
    'orders by right arm arithmetic expression' => [
        "SELECT option_id + priority FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT stage_id + rank FROM wp_options_stage WHERE stage_id IN (10, 11) ORDER BY stage_id + rank",
        'expr1',
        [22, 25, 31, 61],
    ],
    'orders by right arm arithmetic expression with offset' => [
        "SELECT option_id + priority FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT stage_id + rank FROM wp_options_stage WHERE stage_id IN (10, 11) ORDER BY stage_id + rank LIMIT 2 OFFSET 1",
        'expr1',
        [25, 31],
    ],
    'orders by third arm arithmetic expression' => [
        "SELECT option_id + priority FROM wp_options WHERE option_id IN (2, 3) UNION ALL SELECT stage_id + rank FROM wp_options_stage WHERE stage_id = 10 UNION ALL SELECT network_id + network_rank FROM wp_sitemeta WHERE network_id IN (20, 22) ORDER BY network_id + network_rank",
        'expr1',
        [13, 22, 25, 45, 67],
    ],
    'orders by right arm concatenation expression' => [
        "SELECT option_name || ':' || autoload AS label FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT stage_name || ':' || load_flag FROM wp_options_stage WHERE stage_id IN (10, 11) ORDER BY stage_name || ':' || load_flag",
        'label',
        ['active_plugins:no', 'blogdescription:yes', 'home:yes', 'siteurl:yes'],
    ],
    'orders by right arm cast expression' => [
        "SELECT CAST(priority AS TEXT) FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT CAST(rank AS TEXT) FROM wp_options_stage WHERE stage_id IN (10, 11) ORDER BY CAST(rank AS TEXT)",
        'expr1',
        ['15', '20', '30', '50'],
    ],
    'orders by right arm case expression' => [
        "SELECT CASE autoload WHEN 'yes' THEN 0 ELSE 1 END AS bucket FROM wp_options UNION ALL SELECT CASE load_flag WHEN 'yes' THEN 0 ELSE 1 END FROM wp_options_stage ORDER BY CASE load_flag WHEN 'yes' THEN 0 ELSE 1 END, 1",
        'bucket',
        [0, 0, 0, 0, 0, 1, 1, 1],
    ],
    'union distinct orders by right arm alias after rename' => [
        "SELECT option_name AS name FROM wp_options WHERE option_id IN (1, 2) UNION SELECT stage_name AS staged_name FROM wp_options_stage WHERE stage_id IN (10, 13) ORDER BY staged_name",
        'name',
        ['blogdescription', 'home', 'siteurl'],
    ],
    'intersect orders by right arm alias after rename' => [
        "SELECT option_name AS name FROM wp_options UNION SELECT network_name AS network_name FROM wp_sitemeta INTERSECT SELECT stage_name AS staged_name FROM wp_options_stage ORDER BY staged_name",
        'name',
        ['active_plugins', 'siteurl'],
    ],
    'except orders by right arm alias after rename' => [
        "SELECT option_name AS name FROM wp_options EXCEPT SELECT stage_name AS staged_name FROM wp_options_stage ORDER BY staged_name",
        'name',
        ['blogname', 'home'],
    ],
    'comma limit follows right arm alias order resolution' => [
        "SELECT option_name AS name FROM wp_options UNION ALL SELECT stage_name AS staged_name FROM wp_options_stage ORDER BY staged_name LIMIT 2, 3",
        'name',
        ['blogdescription', 'blogname', 'home'],
    ],
    'nulls last follows right arm alias order resolution' => [
        "SELECT option_name AS name FROM wp_options UNION ALL SELECT CASE load_flag WHEN 'yes' THEN stage_name ELSE NULL END AS maybe_stage FROM wp_options_stage ORDER BY maybe_stage NULLS LAST LIMIT 5",
        'name',
        ['active_plugins', 'blogdescription', 'blogname', 'home', 'siteurl'],
    ],
    'right arm expression with final collation maps to left output' => [
        "SELECT option_name || '' AS name FROM wp_options WHERE option_id IN (1, 2) UNION ALL SELECT stage_name || '' FROM wp_options_stage WHERE stage_id IN (10, 11) ORDER BY stage_name || '' COLLATE NOCASE",
        'name',
        ['active_plugins', 'blogdescription', 'home', 'siteurl'],
    ],
    'right arm alias plan maps to left column' => [
        "PLAN:SELECT option_id AS id FROM wp_options UNION ALL SELECT stage_id AS staged_id FROM wp_options_stage ORDER BY staged_id",
        'column',
        ['id'],
    ],
    'right arm expression plan maps to left expression column' => [
        "PLAN:SELECT option_id + priority FROM wp_options UNION ALL SELECT stage_id + rank FROM wp_options_stage ORDER BY stage_id + rank",
        'column',
        ['expr1'],
    ],
    'third arm alias plan maps to left column' => [
        "PLAN:SELECT option_name AS name FROM wp_options UNION ALL SELECT stage_name AS stage_label FROM wp_options_stage UNION ALL SELECT network_name AS network_label FROM wp_sitemeta ORDER BY network_label",
        'column',
        ['name'],
    ],
    'rejects unknown compound order name' => [
        "ERR:SELECT option_id AS id FROM wp_options UNION ALL SELECT stage_id AS staged_id FROM wp_options_stage ORDER BY missing_name",
        null,
        [InvalidArgumentException::class],
    ],
    'rejects non-result right arm expression variant' => [
        "ERR:SELECT option_id + priority FROM wp_options UNION ALL SELECT stage_id + rank FROM wp_options_stage ORDER BY stage_id + rank + 1",
        null,
        [InvalidArgumentException::class],
    ],
];

$tests = [];

foreach ($cases as $name => [$sql, $column, $expected]) {
    $tests['compound name resolution current source next121 ' . $name] = static function (TestRunner $t) use ($tables, $sql, $column, $expected): void {
        if (str_starts_with($sql, 'ERR:')) {
            $t->throws($expected[0], static fn () => SQLiteSelectSql::execute(substr($sql, 4), $tables));

            return;
        }
        if (str_starts_with($sql, 'PLAN:')) {
            $plan = SQLiteSelectSql::plan(substr($sql, 5), $tables);
            $t->same($expected, array_column($plan['compound']['orderBy'], (string) $column));

            return;
        }

        $rows = SQLiteSelectSql::execute($sql, $tables);
        $t->same($expected, array_column($rows, (string) $column));
        $t->same(count($expected), count($rows));
        if ($rows !== []) {
            $t->same([(string) $column], array_keys($rows[0]));
        }
    };
}

return $tests;
