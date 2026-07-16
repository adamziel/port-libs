<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'base_score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'base_score' => 200],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'base_score' => 300],
    ['option_id' => 4, 'option_name' => 'missing_option', 'autoload' => 'no', 'base_score' => 400],
];
$meta = [
    ['option_name' => 'siteurl', 'meta_key' => 'size', 'bytes' => 10, 'priority' => 1],
    ['option_name' => 'siteurl', 'meta_key' => 'autoload', 'bytes' => 5, 'priority' => 2],
    ['option_name' => 'home', 'meta_key' => 'size', 'bytes' => 7, 'priority' => 3],
    ['option_name' => 'active_plugins', 'meta_key' => 'size', 'bytes' => 12, 'priority' => 4],
    ['option_name' => 'active_plugins', 'meta_key' => 'autoload', 'bytes' => 3, 'priority' => 5],
];
$archive = [
    ['option_name' => 'siteurl', 'archived_bytes' => 2],
    ['option_name' => 'home', 'archived_bytes' => 9],
    ['option_name' => 'active_plugins', 'archived_bytes' => 4],
    ['option_name' => 'rewrite_rules', 'archived_bytes' => 30],
];

$tables = [
    'wp_options' => $options,
    'wp_optionmeta' => $meta,
    'wp_option_archive' => $archive,
];

$rows = static fn (string $sql): array => SQLiteSelectSql::execute($sql, $tables);
$column = static fn (string $sql, string $name): array => array_column($rows($sql), $name);

$cases = [
    'sum plus outer id beats count arm' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [16, 9, 18, 4],
    ],
    'count plus outer score wins for empty current source' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.base_score AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.base_score AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [115, 207, 315, 400],
    ],
    'union distinct removes equal composed aggregate current source values' => [
        "SELECT option_name, (SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [3, 3, 5, 4],
    ],
    'intersect keeps matching count aggregate expression' => [
        "SELECT option_name, (SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name INTERSECT SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [3, 3, 5, 4],
    ],
    'except removes matching count aggregate expression' => [
        "SELECT option_name, (SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name EXCEPT SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [null, null, null, null],
    ],
    'except keeps sum expression when count expression differs' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name EXCEPT SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [16, 9, 18, null],
    ],
    'left arm min expression can be ordered through compound alias' => [
        "SELECT option_name, (SELECT min(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT max(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [6, 9, 6, null],
    ],
    'right arm max expression can be selected by descending order' => [
        "SELECT option_name, (SELECT min(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT max(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [11, 9, 15, null],
    ],
    'aggregate arithmetic can include two outer columns' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.option_id + wp_options.base_score AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id + wp_options.base_score AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [116, 209, 318, 404],
    ],
    'aggregate arithmetic can include outer column on the left side' => [
        "SELECT option_name, (SELECT wp_options.option_id + sum(bytes) AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT wp_options.option_id + count(*) AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [16, 9, 18, 4],
    ],
    'aggregate arithmetic can be multiplied before compound ordering' => [
        "SELECT option_name, (SELECT sum(bytes) * 2 + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) * 10 + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [31, 16, 33, 4],
    ],
    'aggregate expression supports having before compound combine' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name HAVING sum(bytes) >= 10 UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [16, 3, 18, 4],
    ],
    'having can use composed aggregate expression' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name HAVING sum(bytes) + wp_options.option_id > 10 UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [16, 3, 18, 4],
    ],
    'order by can use composed aggregate alias from left arm' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [16, 9, 18, 4],
    ],
    'correlated aggregate compound can feed outer predicate' => [
        "SELECT option_name FROM wp_options WHERE (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) >= 10 ORDER BY option_id",
        'option_name',
        ['siteurl', 'active_plugins'],
    ],
    'correlated aggregate compound can feed outer ordering' => [
        "SELECT option_name FROM wp_options ORDER BY (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) DESC, option_id LIMIT 3",
        'option_name',
        ['active_plugins', 'siteurl', 'home'],
    ],
    'correlated aggregate compound can compare against archive arm' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT max(archived_bytes) + wp_options.option_id AS metric FROM wp_option_archive WHERE wp_option_archive.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [16, 11, 18, null],
    ],
    'intersect aggregate current source with archive aggregate' => [
        "SELECT option_name, (SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name INTERSECT SELECT count(*) + wp_options.option_id AS metric FROM wp_option_archive WHERE wp_option_archive.option_name = wp_options.option_name) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [null, 3, null, 4],
    ],
    'except aggregate current source against archive aggregate' => [
        "SELECT option_name, (SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name EXCEPT SELECT count(*) + wp_options.option_id AS metric FROM wp_option_archive WHERE wp_option_archive.option_name = wp_options.option_name) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [3, null, 5, null],
    ],
    'compound offset can choose count arm after composed aggregate order' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1 OFFSET 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [3, 3, 5, null],
    ],
    'compound comma limit can choose count arm after composed aggregate order' => [
        "SELECT option_name, (SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1, 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [3, 3, 5, null],
    ],
    'count can compose empty aggregate current source' => [
        "SELECT option_name, (SELECT count(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [3, 3, 5, 4],
    ],
    'group concat aggregate can compose with outer option name' => [
        "SELECT option_name, (SELECT group_concat(meta_key) || ':' || wp_options.option_name AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT CAST(count(*) AS TEXT) || ':' || wp_options.option_name AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        ['size|autoload:siteurl', 'size:home', 'size|autoload:active_plugins', '0:missing_option'],
    ],
    'total aggregate composes through union all' => [
        "SELECT option_name, (SELECT total(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [16.0, 9.0, 18.0, 4.0],
    ],
    'avg aggregate composes through union all' => [
        "SELECT option_name, (SELECT avg(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [8.5, 9.0, 10.5, 4],
    ],
    'count distinct aggregate composes through union all' => [
        "SELECT option_name, (SELECT count(DISTINCT bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id",
        'metric',
        [3, 3, 5, 4],
    ],
];

$tests = [];

foreach ($cases as $name => [$sql, $resultColumn, $expected]) {
    $tests['compound correlated aggregate current source next124 ' . $name] = static function (TestRunner $t) use ($column, $sql, $resultColumn, $expected): void {
        $actual = $column($sql, $resultColumn);
        $t->same($expected, $actual);
        $t->same(count($expected), count($actual));
    };
}

$tests['compound correlated aggregate current source next124 plan rewrites aggregate inside binary expression'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan(
        'SELECT sum(bytes) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name UNION ALL SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name ORDER BY metric DESC',
        $tables,
        [],
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'base_score' => 100],
    );

    $firstSelect = $plan['compound']['arms'][0]['select'][0];
    $secondSelect = $plan['compound']['arms'][1]['select'][0];
    $t->same('binary', $firstSelect['type']);
    $t->same('sum', $firstSelect['left']['name']);
    $t->same('wp_options.option_id', $firstSelect['right']['name']);
    $t->same('countAll', $secondSelect['left']['name']);
    $t->same('metric', $firstSelect['alias']);
};

$tests['compound correlated aggregate current source next124 empty implicit aggregate keeps qualified outer row'] = static function (TestRunner $t) use ($rows): void {
    $actual = $rows("SELECT option_name, (SELECT count(*) + wp_options.option_id AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name AND meta_key = 'none' UNION ALL SELECT count(*) + wp_options.base_score AS metric FROM wp_optionmeta WHERE wp_optionmeta.option_name = wp_options.option_name AND meta_key = 'none' ORDER BY metric DESC LIMIT 1) AS metric FROM wp_options ORDER BY option_id");

    $t->same([100, 200, 300, 400], array_column($actual, 'metric'));
    $t->same(['option_name', 'metric'], array_keys($actual[0]));
};

return $tests;
