<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbPatchGeneratedIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$generatedColumns = [
    "plugin_channel TEXT GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.channel')) STORED",
    "plugin_priority INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.priority')) VIRTUAL",
];

$indexes = [
    [
        'name' => 'idx_wp_options_jsonb_channel_covering',
        'rootPage' => 91,
        'estimatedRows' => 120,
        'coveringColumns' => ['option_id', 'option_name', 'autoload', 'plugin_channel', 'plugin_priority'],
        'sql' => "CREATE INDEX idx_wp_options_jsonb_channel_covering ON wp_options(plugin_channel, option_name) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_wp_options_jsonb_priority_covering',
        'rootPage' => 92,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_id', 'option_name', 'autoload', 'plugin_priority'],
        'sql' => "CREATE INDEX idx_wp_options_jsonb_priority_covering ON wp_options(json_extract(jsonb_patch(option_value, '{plugin:{source:\"wp-import\"}}'), '$.plugin.priority')) WHERE autoload='yes'",
    ],
];

$rows = [
    [
        'option_id' => 101,
        'option_name' => 'plugin_alpha_settings',
        'autoload' => 'yes',
        'option_value' => $jsonb(['plugin' => ['enabled' => false, 'channel' => 'beta', 'priority' => 3]]),
    ],
    [
        'option_id' => 102,
        'option_name' => 'plugin_beta_settings',
        'autoload' => 'no',
        'option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'nightly', 'priority' => 11]]),
    ],
];

$incoming = [
    [
        'option_id' => 101,
        'option_name' => 'plugin_alpha_settings',
        'autoload' => 'yes',
        'option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 5]]),
    ],
    [
        'option_id' => 103,
        'option_name' => 'plugin_delta_settings',
        'autoload' => 'yes',
        'option_value' => $jsonb(['plugin' => ['enabled' => true, 'channel' => 'stable', 'priority' => 13]]),
    ],
];

$plan = SQLiteJsonbPatchGeneratedIndexPlan::planUpsertCoveringTable(
    $indexes,
    $generatedColumns,
    $rows,
    $incoming,
    ['option_name'],
    [
        'autoload' => static fn (array $current, array $inserted): mixed => $inserted['autoload'] ?? $current['autoload'],
        'option_value' => static fn (array $current, array $inserted): mixed => $inserted['option_value'] ?? $current['option_value'],
    ],
    null,
    [['option_name'], ['option_id']],
    'option_id',
    [
        'operator' => 'AND',
        'terms' => [
            ['operator' => '=', 'left' => ['generatedColumn' => 'plugin_channel'], 'right' => 'stable'],
            ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ],
    ],
    [['column' => 'plugin_channel']],
    ['option_id', 'option_name', 'autoload', 'plugin_channel', 'plugin_priority'],
);

echo json_encode([
    'scenario' => 'application-jsonb-table-upsert-covering-current-next48',
    'applicationUse' => 'Preview copied wp_options INSERT ... ON CONFLICT DO UPDATE rows whose JSONB option_value feeds generated/path indexes, yielding current index deletes, next inserts, inserted-row entries, and covering RETURNING-style rows without ext/sqlite.',
    'coveringIndex' => $plan['covering_plan']['name'] ?? null,
    'changes' => $plan['upsert']['changes'],
    'updatedOptionIds' => array_column($plan['upsert']['updated_rows'], 'option_id'),
    'insertedOptionIds' => array_column($plan['upsert']['inserted_rows'], 'option_id'),
    'deleteEntries' => array_map(static fn (array $entry): array => [
        'index' => $entry['index'],
        'rowid' => $entry['rowid'],
        'value' => $entry['value'],
    ], $plan['index_changes']['delete_entries']),
    'nextEntries' => array_map(static fn (array $entry): array => [
        'index' => $entry['index'],
        'rowid' => $entry['rowid'],
        'value' => $entry['value'],
    ], array_merge($plan['index_changes']['insert_entries'], $plan['insert_entries'])),
    'coveringRows' => $plan['returning_covering_rows'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
