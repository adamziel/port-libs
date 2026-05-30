<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbPatchGeneratedIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$generatedColumns = [
    "plugin_enabled INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.enabled')) VIRTUAL",
    "plugin_channel TEXT GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.channel')) STORED",
];

$indexes = [
    [
        'name' => 'wp_options_generated_enabled_autoload',
        'rootPage' => 71,
        'sql' => "CREATE INDEX wp_options_generated_enabled_autoload ON wp_options(plugin_enabled) WHERE autoload='yes'",
    ],
    [
        'name' => 'wp_options_generated_channel',
        'rootPage' => 72,
        'sql' => 'CREATE INDEX wp_options_generated_channel ON wp_options(plugin_channel DESC)',
    ],
];

$rows = [
    [
        'option_id' => 101,
        'option_name' => 'plugin_alpha_settings',
        'autoload' => 'yes',
        'option_value' => $jsonb(['plugin' => ['enabled' => false, 'channel' => 'beta']]),
    ],
    [
        'option_id' => 102,
        'option_name' => 'plugin_beta_settings',
        'autoload' => 'no',
        'option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'stable']]),
    ],
];

$plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpdateIndexEntries(
    $indexes,
    $generatedColumns,
    $rows,
    [
        101 => ['option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'stable']])],
        102 => ['autoload' => 'yes'],
    ],
    'option_id',
);

echo json_encode([
    'scenario' => 'application-jsonb-generated-update-index-current-next37',
    'applicationUse' => 'Preview copied wp_options UPDATE statements that mutate JSONB option_value data feeding generated columns, producing current index deletes and next index inserts without requiring ext/sqlite.',
    'updatedRowids' => array_column($plan['updated_rows'], 'rowid'),
    'deleteEntries' => array_map(
        static fn (array $entry): array => [
            'index' => $entry['index'],
            'rowid' => $entry['rowid'],
            'value' => $entry['value'],
            'operation' => $entry['operation'],
        ],
        $plan['delete_entries'],
    ),
    'insertEntries' => array_map(
        static fn (array $entry): array => [
            'index' => $entry['index'],
            'rowid' => $entry['rowid'],
            'value' => $entry['value'],
            'operation' => $entry['operation'],
        ],
        $plan['insert_entries'],
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
