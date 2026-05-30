<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedCheckIndexPlan;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.slug')) STORED CHECK(plugin_slug <> ''),
  plugin_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.enabled')) VIRTUAL CHECK(plugin_enabled >= 0),
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.rank')) VIRTUAL CHECK(plugin_rank BETWEEN 1 AND 99)
)
SQL;

$rows = [
    ['option_id' => 101, 'option_name' => 'plugin_alpha_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'enabled' => 1, 'rank' => 10]]), 'autoload' => 'yes'],
    ['option_id' => 102, 'option_name' => 'plugin_beta_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'beta', 'enabled' => 0, 'rank' => 20]]), 'autoload' => 'yes'],
    ['option_id' => 103, 'option_name' => 'plugin_gamma_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'gamma', 'enabled' => 1, 'rank' => 30]]), 'autoload' => 'no'],
];

$plan = SQLiteJsonbGeneratedCheckIndexPlan::plan($schema, $rows, [
    ['name' => 'idx_plugin_slug_checked54', 'rootPage' => 54, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_plugin_slug_checked54 ON wp_options(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_plugin_enabled_checked54', 'rootPage' => 55, 'sql' => 'CREATE INDEX idx_plugin_enabled_checked54 ON wp_options(plugin_enabled) WHERE plugin_enabled = 1'],
    ['name' => 'idx_plugin_rank_checked54', 'rootPage' => 56, 'sql' => 'CREATE INDEX idx_plugin_rank_checked54 ON wp_options(plugin_rank DESC) WHERE plugin_rank IS NOT NULL'],
], [
    ['rowid' => 101, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 15],
        ['function' => 'jsonb_set', 'path' => '$.plugin.enabled', 'value' => 0],
    ]],
    ['rowid' => 102, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 120],
    ]],
    ['rowid' => 103, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.slug', 'value' => 'epsilon'],
    ]],
]);

echo json_encode([
    'scenario' => 'application-jsonb-generated-check-index-current-next54',
    'changes' => $plan['changes'],
    'acceptedRowids' => array_column($plan['accepted_updates'], 'rowid'),
    'rejectedRowids' => array_column($plan['rejected_updates'], 'rowid'),
    'indexActions' => $plan['index_action_count'],
    'finalSlugs' => array_column($plan['after'], 'plugin_slug'),
    'applicationUse' => 'Preflight copied wp_options JSONB setting imports so generated-column CHECK constraints reject bad current/next rows before partial generated indexes are rewritten.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
