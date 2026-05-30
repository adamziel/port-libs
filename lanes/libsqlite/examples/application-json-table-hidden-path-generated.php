<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 143,
    'option_name' => 'wp_plugin_hidden_path_generated',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":true},{"slug":"forms","priority":4,"enabled":false}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 143,
    'option_name' => 'wp_plugin_hidden_path_generated',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":8,"enabled":false},{"slug":"forms","priority":4,"enabled":false},{"slug":"shop","priority":8,"enabled":true}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenPathGenerated(
    'json_tree',
    $current,
    $next,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 5],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
    [
        ['name' => 'slug', 'path' => '$.slug', 'value' => 'cache'],
        ['name' => 'priority', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [5, 9]],
        ['name' => 'enabled', 'path' => '$.enabled', 'operator' => '=', 'value' => 1],
    ],
);

echo json_encode([
    'option' => $current['option_name'],
    'currentMatched' => $plan['currentHiddenPathGeneratedSource']['generatedMatched'],
    'nextMatched' => $plan['nextHiddenPathGeneratedSource']['generatedMatched'],
    'nextPolicy' => $plan['nextReaderPolicy'],
    'reasons' => $plan['hiddenPathGeneratedReplanReasons'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
