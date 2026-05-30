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
  site_id INTEGER,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.slug')) STORED,
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.rank')) VIRTUAL
)
SQL;

$rows = [
    ['option_id' => 301, 'option_name' => 'plugin_alpha', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'alpha', 'rank' => 20]])), 'autoload' => 'yes', 'site_id' => 1],
    ['option_id' => 302, 'option_name' => 'plugin_beta', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'beta', 'rank' => 10]])), 'autoload' => 'yes', 'site_id' => 1],
    ['option_id' => 303, 'option_name' => 'plugin_gamma', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'gamma', 'rank' => 30]])), 'autoload' => 'no', 'site_id' => 2],
];

$indexes = [
    ['name' => 'idx_plugin_slug_covering', 'rootPage' => 71, 'unique' => true, 'coveringColumns' => ['plugin_slug', 'autoload', 'site_id', 'option_id'], 'sql' => 'CREATE UNIQUE INDEX idx_plugin_slug_covering ON wp_options(plugin_slug COLLATE NOCASE, autoload, site_id, option_id) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_plugin_rank_covering', 'rootPage' => 72, 'coveringColumns' => ['plugin_rank', 'option_name', 'autoload', 'option_id'], 'sql' => 'CREATE INDEX idx_plugin_rank_covering ON wp_options(plugin_rank DESC, option_name, autoload, option_id) WHERE plugin_rank IS NOT NULL'],
];

$plan = SQLiteGeneratedJsonPathIndexPlan::coveringDeleteYieldPlan($createTableSql, $rows, $indexes, [302], 512);

echo json_encode([
    'deletedRows' => array_column($plan['deleted_rows'], 'option_name'),
    'remainingRows' => array_column($plan['after'], 'option_name'),
    'deleteActions' => array_map(static fn (array $action): array => [
        'index' => $action['index'],
        'key' => $action['key'],
        'record' => $action['record'],
        'cellBytes' => $action['cell_bytes'],
    ], $plan['btree_actions']),
    'changedIndexPages' => array_keys(array_filter($plan['btree_indexes'], static fn (array $index): bool => $index['leaf_page_changed'])),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
