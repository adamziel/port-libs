<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAnalyzeStatPlanner;

$statRows = [
    ['tbl' => 'wp_options', 'idx' => null, 'stat' => '12000'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name', 'stat' => '12000 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_autoload_name', 'stat' => '12000 6000 2'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name_autoload', 'stat' => '12000 1 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name_updated', 'stat' => '12000 1 1'],
    ['tbl' => 'wp_postmeta', 'idx' => null, 'stat' => '240000'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_key', 'stat' => '240000 80 4'],
    ['tbl' => 'wp_posts', 'idx' => null, 'stat' => '30000'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_type_status_date', 'stat' => '30000 5000 700 40'],
];

$indexes = [
    ['name' => 'wp_options_name', 'table' => 'wp_options', 'columns' => ['option_name'], 'unique' => true],
    ['name' => 'wp_options_autoload_name', 'table' => 'wp_options', 'columns' => ['autoload', 'option_name']],
    ['name' => 'wp_options_name_autoload', 'table' => 'wp_options', 'columns' => ['option_name', 'autoload']],
    ['name' => 'wp_options_name_updated', 'table' => 'wp_options', 'columns' => ['option_name', 'last_updated']],
    ['name' => 'wp_postmeta_post_key', 'table' => 'wp_postmeta', 'columns' => ['post_id', 'meta_key']],
    ['name' => 'wp_posts_type_status_date', 'table' => 'wp_posts', 'columns' => ['post_type', 'post_status', 'post_date']],
];

$range = static fn (string $column, string $operator, mixed $value): array => [
    'column' => $column,
    'operator' => $operator,
    'value' => $value,
];
$point = static fn (string $column, mixed $value): array => [
    'column' => $column,
    'operator' => '=',
    'value' => $value,
];
$inList = static fn (string $column, array $values): array => [
    'column' => $column,
    'operator' => 'IN',
    'values' => $values,
];

$tests = [];

$cases = [
    'paired option name lower upper becomes one between range' => [
        'wp_options',
        [$range('option_name', '>=', '_transient_'), $range('option_name', '<', '_transient_timeout_')],
        'wp_options_name',
        ['option_name'],
        'BETWEEN',
        4,
        'SEARCH wp_options USING INDEX wp_options_name (option_nameBETWEEN?)',
    ],
    'paired option name reversed upper lower still becomes one range' => [
        'wp_options',
        [$range('option_name', '<', '_transient_timeout_'), $range('option_name', '>=', '_transient_')],
        'wp_options_name',
        ['option_name'],
        'BETWEEN',
        4,
        'SEARCH wp_options USING INDEX wp_options_name (option_nameBETWEEN?)',
    ],
    'option name equality dominates surrounding range terms' => [
        'wp_options',
        [$range('option_name', '>=', 'active_'), $point('option_name', 'active_plugins'), $range('option_name', '<', 'active_q')],
        'wp_options_name',
        ['option_name'],
        '=',
        1,
        'SEARCH wp_options USING INDEX wp_options_name (option_name=?)',
    ],
    'option name equality dominates when it appears before ranges' => [
        'wp_options',
        [$point('option_name', 'siteurl'), $range('option_name', '>=', 's'), $range('option_name', '<', 't')],
        'wp_options_name',
        ['option_name'],
        '=',
        1,
        'SEARCH wp_options USING INDEX wp_options_name (option_name=?)',
    ],
    'option name in list dominates surrounding range terms' => [
        'wp_options',
        [$range('option_name', '>=', 'plugin_'), $inList('option_name', ['plugin_a', 'plugin_b']), $range('option_name', '<', 'plugin_z')],
        'wp_options_name',
        ['option_name'],
        'IN',
        2,
        'SEARCH wp_options USING INDEX wp_options_name (option_nameIN?)',
    ],
    'autoload equality then paired name range keeps composite prefix' => [
        'wp_options',
        [$point('autoload', 'no'), $range('option_name', '>=', '_transient_'), $range('option_name', '<', '_transient_timeout_')],
        'wp_options_name',
        ['option_name'],
        'BETWEEN',
        4,
        'SEARCH wp_options USING INDEX wp_options_name (option_nameBETWEEN?)',
    ],
    'name range then autoload equality stops before trailing equality' => [
        'wp_options',
        [$range('option_name', '>=', 'plugin_'), $range('option_name', '<', 'plugin_z'), $point('autoload', 'yes')],
        'wp_options_name',
        ['option_name'],
        'BETWEEN',
        4,
        'SEARCH wp_options USING INDEX wp_options_name (option_nameBETWEEN?)',
    ],
    'postmeta equality then paired meta key range uses second prefix range' => [
        'wp_postmeta',
        [$point('post_id', 42), $range('meta_key', '>=', '_wp_'), $range('meta_key', '<', '_wq_')],
        'wp_postmeta_post_key',
        ['post_id', 'meta_key'],
        'BETWEEN',
        16,
        'SEARCH wp_postmeta USING INDEX wp_postmeta_post_key (post_id=?,meta_keyBETWEEN?)',
    ],
    'posts type status equality plus paired date range keeps third prefix' => [
        'wp_posts',
        [$point('post_type', 'post'), $point('post_status', 'publish'), $range('post_date', '>=', '2026-01-01'), $range('post_date', '<', '2027-01-01')],
        'wp_posts_type_status_date',
        ['post_type', 'post_status', 'post_date'],
        'BETWEEN',
        160,
        'SEARCH wp_posts USING INDEX wp_posts_type_status_date (post_type=?,post_status=?,post_dateBETWEEN?)',
    ],
    'case-insensitive paired range column names are combined' => [
        'wp_options',
        [$range('OPTION_NAME', '>=', 'theme_'), $range('option_name', '<=', 'theme_z')],
        'wp_options_name',
        ['OPTION_NAME'],
        'BETWEEN',
        4,
        'SEARCH wp_options USING INDEX wp_options_name (OPTION_NAMEBETWEEN?)',
    ],
    'single lower range remains a current range when no upper exists' => [
        'wp_options',
        [$range('option_name', '>=', 'rss_')],
        'wp_options_name',
        ['option_name'],
        '>=',
        4,
        'SEARCH wp_options USING INDEX wp_options_name (option_name>=?)',
    ],
    'single upper range remains a current range when no lower exists' => [
        'wp_options',
        [$range('option_name', '<', 'rss_')],
        'wp_options_name',
        ['option_name'],
        '<',
        4,
        'SEARCH wp_options USING INDEX wp_options_name (option_name<?)',
    ],
];

foreach ($cases as $name => [$table, $constraints, $expectedIndex, $expectedColumns, $expectedOperator, $expectedRows, $expectedDetail]) {
    $tests['analyze stat1 current next75 ' . $name] = static function (TestRunner $t) use ($statRows, $indexes, $table, $constraints, $expectedIndex, $expectedColumns, $expectedOperator, $expectedRows, $expectedDetail): void {
        $plan = SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, $table, $constraints);
        $t->same('index', $plan['access']);
        $t->same($expectedIndex, $plan['index']);
        $t->same($expectedColumns, $plan['matchedColumns']);
        $t->same($expectedOperator, $plan['matchedConstraints'][array_key_last($plan['matchedConstraints'])]['operator']);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same($expectedDetail, $plan['detail']);
    };
}

$tests['analyze stat1 current next75 paired ranges expose both current bounds'] = static function (TestRunner $t) use ($statRows, $indexes, $range): void {
    $plan = SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, 'wp_options', [
        $range('option_name', '>=', '_transient_'),
        $range('option_name', '<', '_transient_timeout_'),
    ]);
    $constraint = $plan['matchedConstraints'][0];

    $t->same('BETWEEN', $constraint['operator']);
    $t->same(['_transient_', '_transient_timeout_'], $constraint['values']);
    $t->same([
        ['operator' => '>=', 'value' => '_transient_'],
        ['operator' => '<', 'value' => '_transient_timeout_'],
    ], $constraint['rangeConstraints']);
};

$tests['analyze stat1 current next75 equality suppresses stale range constraints'] = static function (TestRunner $t) use ($statRows, $indexes, $range, $point): void {
    $plan = SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, 'wp_options', [
        $range('option_name', '>=', 'active_'),
        $point('option_name', 'active_plugins'),
        $range('option_name', '<', 'active_q'),
    ]);
    $constraint = $plan['matchedConstraints'][0];

    $t->same('=', $constraint['operator']);
    $t->same('active_plugins', $constraint['value']);
    $t->same(false, isset($constraint['rangeConstraints']));
};

$tests['analyze stat1 current next75 in list suppresses stale range constraints'] = static function (TestRunner $t) use ($statRows, $indexes, $range, $inList): void {
    $plan = SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, 'wp_options', [
        $range('option_name', '>=', 'plugin_'),
        $inList('option_name', ['plugin_a', 'plugin_b', 'plugin_c']),
        $range('option_name', '<', 'plugin_z'),
    ]);
    $constraint = $plan['matchedConstraints'][0];

    $t->same('IN', $constraint['operator']);
    $t->same(['plugin_a', 'plugin_b', 'plugin_c'], $constraint['values']);
    $t->same(false, isset($constraint['rangeConstraints']));
};

return $tests;
