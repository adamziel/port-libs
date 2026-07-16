<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = [[
    'name' => 'idx_wp_options_autoload_name_stat4_next92',
    'rootPage' => 9201,
    'estimatedRows' => 120,
    'stat4Samples' => [
        ['neq' => '7 2', 'nlt' => '13 3', 'ndlt' => '2 2', 'sample' => ['yes', 'plugin_alpha']],
        ['neq' => '4 1', 'nlt' => '20 5', 'ndlt' => '3 3', 'sample' => ['yes', 'plugin_beta']],
        ['neq' => '6 1', 'nlt' => '24 6', 'ndlt' => '4 4', 'sample' => ['yes', 'plugin_gamma']],
        ['neq' => '3 1', 'nlt' => '30 7', 'ndlt' => '5 5', 'sample' => ['yes', 'siteurl']],
        ['neq' => '10 3', 'nlt' => '33 8', 'ndlt' => '6 6', 'sample' => ['yes', 'theme_mods_twentysix']],
    ],
    'sql' => 'CREATE INDEX idx_wp_options_autoload_name_stat4_next92 ON wp_options(autoload, option_name, option_value)',
]];

$plan = SQLiteMultiColumnRangePlan::stat4RangeOrder(
    $indexes,
    $and(
        $point('autoload', 'yes'),
        $range('option_name', '>=', 'plugin_'),
        $range('option_name', '<', 'theme_'),
    ),
    [['column' => 'option_name']],
    ['option_name'],
);

if (($argv[1] ?? null) === '--self-test') {
    $checks = [
        $plan['status'] === 'usable',
        $plan['orderBySatisfied'] === true,
        $plan['blockSortRequired'] === false,
        $plan['rangeCurrentSourceKeys']['lowerNext'] === 'plugin_alpha',
        $plan['rangeCurrentSourceKeys']['upperNext'] === 'theme_mods_twentysix',
    ];
    if (in_array(false, $checks, true)) {
        fwrite(STDERR, 'application-stat4-range-order-current-source-next92 self-test failed' . PHP_EOL);
        exit(1);
    }
    echo 'application-stat4-range-order-current-source-next92 self-test passed' . PHP_EOL;

    return;
}

echo json_encode([
    'scenario' => 'copied wp_options STAT4 range ORDER BY current source next92',
    'selected' => $plan['selected'],
    'rangeOrderMode' => $plan['rangeOrderMode'],
    'orderBySatisfied' => $plan['orderBySatisfied'],
    'blockSortRequired' => $plan['blockSortRequired'],
    'currentSourceColumn' => $plan['currentSourceColumn'],
    'rangeCurrentSourceKeys' => $plan['rangeCurrentSourceKeys'],
    'matchedCurrentSourceKeys' => $plan['matchedCurrentSourceKeys'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
