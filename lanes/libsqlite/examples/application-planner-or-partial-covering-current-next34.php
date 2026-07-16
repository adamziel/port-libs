<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mode = $argv[1] ?? 'autoload-mixed';

$indexes = [
    [
        'name' => 'idx_wp_options_autoload_yes_lower_cover',
        'rootPage' => 301,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_wp_options_autoload_yes_lower_cover ON wp_options(lower(option_name), autoload) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_wp_options_autoload_no_lower_cover',
        'rootPage' => 302,
        'estimatedRows' => 45,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_wp_options_autoload_no_lower_cover ON wp_options(lower(option_name), autoload) WHERE autoload='no'",
    ],
    [
        'name' => 'idx_wp_options_plugin_upper_cover',
        'rootPage' => 303,
        'estimatedRows' => 110,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_wp_options_plugin_upper_cover ON wp_options(upper(option_name) DESC, autoload) WHERE option_name >= 'plugin_' AND option_name < 'plugin`'",
    ],
];

$lowerArm = static fn (string $autoload, string $name): array => [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['function' => 'lower', 'column' => 'option_name'], 'right' => $name],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => $autoload],
    ],
];
$pluginArm = static fn (string $lower, string $upper): array => [
    'operator' => 'AND',
    'terms' => [
        ['operator' => 'BETWEEN', 'left' => ['function' => 'upper', 'column' => 'option_name'], 'lower' => strtoupper($lower), 'upper' => strtoupper($upper)],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_'],
        ['operator' => '<', 'left' => ['column' => 'option_name'], 'right' => 'plugin`'],
    ],
];

$predicate = match ($mode) {
    'plugin-or-autoload' => [
        'operator' => 'OR',
        'terms' => [
            $lowerArm('yes', 'siteurl'),
            $pluginArm('plugin_cache', 'plugin_update'),
        ],
    ],
    'unsafe' => [
        'operator' => 'OR',
        'terms' => [
            $lowerArm('yes', 'siteurl'),
            $lowerArm('maybe', 'home'),
        ],
    ],
    default => [
        'operator' => 'OR',
        'terms' => [
            $lowerArm('yes', 'siteurl'),
            $lowerArm('no', '_transient_timeout_update_plugins'),
        ],
    ],
};

$plan = SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan(
    $indexes,
    $predicate,
    [['column' => 'autoload'], ['column' => 'option_name']],
    ['option_name', 'autoload', 'option_value'],
);

echo json_encode([
    'scenario' => 'application-planner-or-partial-covering-current-next34',
    'mode' => $mode,
    'usable' => $plan !== null,
    'type' => $plan['type'] ?? null,
    'armCount' => $plan['armCount'] ?? 0,
    'indexNames' => $plan['indexNames'] ?? [],
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'estimatedCost' => $plan['estimatedCost'] ?? null,
    'covering' => (bool) ($plan['covering'] ?? false),
    'partial' => (bool) ($plan['partial'] ?? false),
    'dedupeRowidsRequired' => (bool) ($plan['dedupeRowidsRequired'] ?? false),
    'armRoots' => $plan === null ? [] : array_column($plan['arms'], 'rootPage'),
    'applicationUse' => 'Preview copied wp_options OR predicates where each arm can be served by a proven partial covering expression index, avoiding unsafe broad scans and table b-tree fetches without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
