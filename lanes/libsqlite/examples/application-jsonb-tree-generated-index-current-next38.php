<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteGeneratedJsonPathIndexPlan;
use PortLibs\LibSqlite\SQLiteJsonB;

$createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.slug')) STORED,
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.rank')) VIRTUAL,
  plugin_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.enabled')) VIRTUAL
)
SQL;

$plan = SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, [
    ['option_id' => 10, 'option_name' => 'plugin_alpha', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'Alpha', 'rank' => 20, 'enabled' => 1]])), 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'plugin_beta', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'beta', 'rank' => 10, 'enabled' => 0]])), 'autoload' => 'no'],
    ['option_id' => 12, 'option_name' => 'plugin_gamma', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'gamma', 'rank' => 30]])), 'autoload' => 'yes'],
], [
    ['name' => 'idx_plugin_slug', 'rootPage' => 22, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_plugin_slug ON wp_options(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_plugin_rank', 'rootPage' => 23, 'sql' => 'CREATE INDEX idx_plugin_rank ON wp_options(plugin_rank DESC) WHERE plugin_rank IS NOT NULL'],
    ['name' => 'idx_plugin_enabled', 'rootPage' => 24, 'sql' => 'CREATE INDEX idx_plugin_enabled ON wp_options(plugin_enabled) WHERE plugin_enabled IS NOT NULL'],
], [
    ['rowid' => 10, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.slug', 'value' => 'delta'],
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 40],
    ]],
    ['rowid' => 12, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.enabled', 'value' => 1],
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 5],
    ]],
]);

echo json_encode([
    'changes' => $plan['changes'],
    'btree_action_count' => $plan['btree_action_count'],
    'actions' => array_map(static fn (array $action): array => [
        'action' => $action['action'],
        'index' => $action['index'],
        'rowid' => $action['rowid'],
        'key' => $action['key'],
        'cell_bytes' => $action['cell_bytes'],
    ], $plan['btree_actions']),
    'next_slug_order' => array_column($plan['btree_indexes']['idx_plugin_slug']['next_entries'], 'key'),
    'next_rank_order' => array_column($plan['btree_indexes']['idx_plugin_rank']['next_entries'], 'key'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
