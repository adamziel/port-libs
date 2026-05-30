<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonbPatchGeneratedIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$generatedColumns = [
    "plugin_enabled INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{enabled:true,source:\"wp-import\"}}'), '$.plugin.enabled')) VIRTUAL",
    "plugin_channel TEXT GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{channel:\"stable\"}}'), '$.plugin.channel')) STORED",
];

$indexes = [
    [
        'name' => 'wp_options_patch_enabled_autoload',
        'rootPage' => 44,
        'estimatedRows' => 180,
        'coveringColumns' => ['option_name', 'autoload', 'plugin_enabled'],
        'sql' => "CREATE INDEX wp_options_patch_enabled_autoload ON wp_options(plugin_enabled) WHERE autoload='yes'",
    ],
    [
        'name' => 'wp_options_patch_channel_order',
        'rootPage' => 45,
        'estimatedRows' => 70,
        'coveringColumns' => ['option_name', 'autoload', 'plugin_channel'],
        'sql' => 'CREATE INDEX wp_options_patch_channel_order ON wp_options(plugin_channel DESC)',
    ],
];

$plan = SQLiteJsonbPatchGeneratedIndexPlan::choose(
    $indexes,
    $generatedColumns,
    [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '=', 'left' => ['generatedColumn' => 'plugin_enabled'], 'right' => 1],
            ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ],
    ],
    [],
    ['option_name', 'autoload'],
);

echo json_encode([
    'scenario' => 'application-jsonb-patch-indexed-generated-current-next27',
    'applicationUse' => 'Preview copied wp_options imports that query indexed generated columns derived from jsonb_patch(option_value, patch) without requiring ext/sqlite.',
    'chosenIndex' => $plan['name'] ?? null,
    'rootPage' => $plan['rootPage'] ?? null,
    'generatedColumn' => $plan['generatedColumn'] ?? null,
    'patchJson' => $plan['patchJson'] ?? null,
    'jsonPath' => $plan['path'] ?? null,
    'covering' => $plan['covering'] ?? false,
    'partial' => $plan['partial'] ?? false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
