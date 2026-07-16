<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedPartialUpsertPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$createTableSql = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  migration_generation INTEGER,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(key_value, '$.plugin.slug')) STORED,
  plugin_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(key_value, '$.plugin.enabled')) VIRTUAL,
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(key_value, '$.plugin.rank')) VIRTUAL
)
SQL;

$plan = SQLiteJsonbGeneratedPartialUpsertPlan::plan(
    $createTableSql,
    [
        ['rowid' => 1, 'setting_id' => 1, 'key_name' => 'plugin_alpha', 'key_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'enabled' => 1, 'rank' => 20], 'source' => 'current']), 'load_policy' => 'yes', 'migration_generation' => 3],
        ['rowid' => 2, 'setting_id' => 2, 'key_name' => 'plugin_beta', 'key_value' => $jsonb(['plugin' => ['slug' => 'beta', 'enabled' => 0, 'rank' => 40], 'source' => 'current']), 'load_policy' => 'no', 'migration_generation' => 7],
    ],
    [
        ['rowid' => 10, 'setting_id' => 10, 'key_name' => 'plugin_alpha', 'key_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'enabled' => 0, 'rank' => 25], 'source' => 'import']), 'load_policy' => 'no', 'migration_generation' => 9],
        ['rowid' => 11, 'setting_id' => 11, 'key_name' => 'plugin_beta', 'key_value' => $jsonb(['plugin' => ['slug' => 'beta', 'enabled' => 1, 'rank' => 45], 'source' => 'import']), 'load_policy' => 'yes', 'migration_generation' => 8],
        ['rowid' => 12, 'setting_id' => 12, 'key_name' => 'plugin_epsilon', 'key_value' => $jsonb(['plugin' => ['slug' => 'epsilon', 'enabled' => 1, 'rank' => 5], 'source' => 'import']), 'load_policy' => 'yes', 'migration_generation' => 2],
    ],
    [
        ['name' => 'idx_enabled_partial', 'rootPage' => 31, 'sql' => 'CREATE INDEX idx_enabled_partial ON app_settings(plugin_enabled) WHERE plugin_enabled = 1'],
        ['name' => 'idx_slug_partial', 'rootPage' => 32, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_slug_partial ON app_settings(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
        ['name' => 'idx_rank_partial', 'rootPage' => 33, 'sql' => 'CREATE INDEX idx_rank_partial ON app_settings(plugin_rank DESC) WHERE plugin_rank IS NOT NULL'],
    ],
    [
        '$.plugin.enabled' => ['excluded_json' => '$.plugin.enabled'],
        '$.plugin.rank' => ['excluded_json' => '$.plugin.rank'],
        '$.source' => ['excluded_json' => '$.source'],
        '$.previous_generation' => ['current_column' => 'migration_generation'],
        '$.import_context' => ['json' => '{"tool":"data-liberation","batch":49}'],
    ],
    static fn (array $current, array $excluded): bool => (int) $excluded['migration_generation'] >= (int) $current['migration_generation'],
);

echo json_encode([
    'changes' => $plan['changes'],
    'inserted' => array_column($plan['inserted_rows'], 'key_name'),
    'updated' => array_column($plan['updated_rows'], 'key_name'),
    'partial_index_actions' => array_map(
        static fn (array $action): array => [
            'action' => $action['action'],
            'index' => $action['index'],
            'key' => $action['key'],
            'rowid' => $action['rowid'],
        ],
        $plan['index_actions'],
    ),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
