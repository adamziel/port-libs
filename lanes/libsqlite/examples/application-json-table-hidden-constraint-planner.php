<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 21,
    'option_name' => 'wp_plugin_rules',
    'option_value' => '{"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":false}],"fallback":[{"name":"safe"}]}',
    'json_root' => '$.rules',
];
$nextOption = [
    'option_id' => 21,
    'option_name' => 'wp_plugin_rules',
    'option_value' => '{"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":true},{"name":"forms","enabled":true}],"fallback":[{"name":"safe"}]}',
    'json_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner(
    'json_each',
    $currentOption,
    $nextOption,
    'option_value',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'json_root',
    [['column' => 'id']],
);

echo json_encode([
    'scenario' => 'application-json-table-hidden-constraint-planner',
    'applicationUse' => 'Copied wp_options JSON diagnostics can keep the first hidden json/root argument pinned to the active option row while retaining duplicate hidden constraints as residual planner evidence for the next row source, including row-count transitions, without requiring ext/sqlite.',
    'hiddenConstraintReplanReasons' => $plan['hiddenConstraintReplanReasons'],
    'currentHiddenResiduals' => $plan['currentHiddenResiduals'],
    'nextHiddenResiduals' => $plan['nextHiddenResiduals'],
    'rowCountTransition' => $plan['rowCountTransition'],
    'currentRows' => array_map(static fn (array $row): array => [
        'key' => $row['key'],
        'root' => $row['root'],
        'json' => $row['json'],
    ], $plan['currentRows']),
    'nextRows' => array_map(static fn (array $row): array => [
        'key' => $row['key'],
        'root' => $row['root'],
        'json' => $row['json'],
    ], $plan['nextRows']),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
