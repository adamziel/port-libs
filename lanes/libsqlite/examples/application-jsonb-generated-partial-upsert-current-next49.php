<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedPartialUpsertPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  migration_generation INTEGER,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.slug')) STORED,
  plugin_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.enabled')) VIRTUAL,
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.rank')) VIRTUAL
)
SQL;

$plan = SQLiteJsonbGeneratedPartialUpsertPlan::plan(
    $createTableSql,
    [
        ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'enabled' => 1, 'rank' => 20], 'source' => 'current']), 'autoload' => 'yes', 'migration_generation' => 3],
        ['option_id' => 2, 'option_name' => 'plugin_beta', 'option_value' => $jsonb(['plugin' => ['slug' => 'beta', 'enabled' => 0, 'rank' => 40], 'source' => 'current']), 'autoload' => 'no', 'migration_generation' => 7],
    ],
    [
        ['option_id' => 10, 'option_name' => 'plugin_alpha', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'enabled' => 0, 'rank' => 25], 'source' => 'import']), 'autoload' => 'no', 'migration_generation' => 9],
        ['option_id' => 11, 'option_name' => 'plugin_beta', 'option_value' => $jsonb(['plugin' => ['slug' => 'beta', 'enabled' => 1, 'rank' => 45], 'source' => 'import']), 'autoload' => 'yes', 'migration_generation' => 8],
        ['option_id' => 12, 'option_name' => 'plugin_epsilon', 'option_value' => $jsonb(['plugin' => ['slug' => 'epsilon', 'enabled' => 1, 'rank' => 5], 'source' => 'import']), 'autoload' => 'yes', 'migration_generation' => 2],
    ],
    [
        ['name' => 'idx_enabled_partial', 'rootPage' => 31, 'sql' => 'CREATE INDEX idx_enabled_partial ON wp_options(plugin_enabled) WHERE plugin_enabled = 1'],
        ['name' => 'idx_slug_partial', 'rootPage' => 32, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_slug_partial ON wp_options(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
        ['name' => 'idx_rank_partial', 'rootPage' => 33, 'sql' => 'CREATE INDEX idx_rank_partial ON wp_options(plugin_rank DESC) WHERE plugin_rank IS NOT NULL'],
    ],
    [
        '$.plugin.enabled' => ['excluded_json' => '$.plugin.enabled'],
        '$.plugin.rank' => ['excluded_json' => '$.plugin.rank'],
        '$.source' => ['excluded_json' => '$.source'],
        '$.previous_generation' => ['current_column' => 'migration_generation'],
        '$.wp_import' => ['json' => '{"tool":"data-liberation","batch":49}'],
    ],
    static fn (array $current, array $excluded): bool => (int) $excluded['migration_generation'] >= (int) $current['migration_generation'],
);

echo json_encode([
    'changes' => $plan['changes'],
    'inserted' => array_column($plan['inserted_rows'], 'option_name'),
    'updated' => array_column($plan['updated_rows'], 'option_name'),
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
