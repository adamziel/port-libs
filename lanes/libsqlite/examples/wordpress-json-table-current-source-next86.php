<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 11,
    'option_name' => 'wp_plugin_settings',
    'option_value' => '{"plugins":[{"slug":"seo","enabled":true,"priority":2},{"slug":"cache","enabled":false,"priority":7},{"slug":"forms","enabled":true,"priority":4}],"meta":{"site":"main"}}',
    'json_root' => '$.plugins',
];
$nextOption = [
    'option_id' => 11,
    'option_name' => 'wp_plugin_settings',
    'option_value' => '{"plugins":[{"slug":"seo","enabled":true,"priority":3},{"slug":"cache","enabled":true,"priority":6},{"slug":"forms","enabled":true,"priority":4},{"slug":"shop","enabled":true,"priority":5}],"meta":{"site":"main"}}',
    'json_root' => '$.plugins',
];

$plan = SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
        ['column' => 'key', 'operator' => 'IN', 'value' => [0, 1, 2, 3]],
        ['column' => 'limit', 'operator' => '=', 'value' => 4],
    ],
    'json_root',
    [['column' => 'id']],
);

echo json_encode([
    'scenario' => 'wordpress-json-table-current-source-next86',
    'wordpressUse' => 'Copied wp_options JSON settings can keep the active json_tree cursor pinned to the current option_value while preparing the next option_value constraint tape, including source JSON/root transitions, filter argument changes, row counts, and malformed or SQL NULL source guards without requiring ext/sqlite.',
    'replanRequired' => $plan['replanRequired'],
    'replanReasons' => $plan['replanReasons'],
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'sourceTransitions' => $plan['sourceTransitions'],
    'currentRows' => array_map(static fn (array $row): array => [
        'key' => $row['key'],
        'fullkey' => $row['fullkey'],
        'json' => $row['json'],
    ], $plan['currentRows']),
    'nextRows' => array_map(static fn (array $row): array => [
        'key' => $row['key'],
        'fullkey' => $row['fullkey'],
        'json' => $row['json'],
    ], $plan['nextRows']),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
