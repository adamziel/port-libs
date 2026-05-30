<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

require_once __DIR__ . '/../src/SQLiteWindowFunction.php';

$optionRows = [
    ['option_name' => 'plugin_alpha_enabled', 'autoload' => 'yes', 'score' => 5],
    ['option_name' => 'plugin_alpha_rules', 'autoload' => 'yes', 'score' => 10],
    ['option_name' => 'plugin_beta_enabled', 'autoload' => 'no', 'score' => 15],
    ['option_name' => 'plugin_cache_payload', 'autoload' => 'yes', 'score' => 20],
    ['option_name' => 'plugin_cache_timeout', 'autoload' => 'yes', 'score' => 25],
    ['option_name' => 'plugin_debug_flag', 'autoload' => 'no', 'score' => 30],
];

$values = array_column($optionRows, 'score');
$orderKeys = array_column($optionRows, 'autoload');
$autoloadFilters = array_map(static fn (array $row): int => $row['autoload'] === 'yes' ? 1 : 0, $optionRows);

$slidingRows = SQLiteWindowFunction::aggregateRows($values, $orderKeys, 1, 2);
$autoloadOnlyRows = SQLiteWindowFunction::aggregateRows($values, $orderKeys, 1, 2, 'NO OTHERS', $autoloadFilters);
$excludePeerRows = SQLiteWindowFunction::aggregateRows($values, $orderKeys, 99, 99, 'GROUP');

$summary = [];
foreach ($optionRows as $index => $row) {
    $summary[] = [
        'option_name' => $row['option_name'],
        'sliding_sum' => $slidingRows[$index]['sum'],
        'sliding_frame' => $slidingRows[$index]['frame'],
        'autoload_only_sum' => $autoloadOnlyRows[$index]['sum'],
        'exclude_peer_autoload_sum' => $excludePeerRows[$index]['sum'],
    ];
}

echo json_encode([
    'scenario' => 'application-window-frame-boundary-summary',
    'rows' => $summary,
], JSON_PRETTY_PRINT) . PHP_EOL;
