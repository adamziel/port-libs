<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWordPressSchemaImportSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteWordPressSchemaImportSavepointPlan::plan(
    ['wp_commentmeta' => ['type' => 'table', 'sql' => 'CREATE TABLE wp_commentmeta(meta_id INTEGER);']],
    [
        [
            'name' => 'core_schema',
            'dump' => <<<'SQL'
CREATE TABLE wp_options (
  option_id INTEGER PRIMARY KEY AUTOINCREMENT,
  option_name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes'
);
CREATE UNIQUE INDEX wp_options_option_name ON wp_options(option_name COLLATE NOCASE);
SQL,
        ],
        [
            'name' => 'plugin_schema',
            'dump' => <<<'SQL'
CREATE TABLE wp_plugin_settings (
  option_name TEXT NOT NULL,
  setting_key TEXT NOT NULL,
  setting_value TEXT NOT NULL,
  PRIMARY KEY(option_name, setting_key)
);
CREATE INDEX wp_plugin_settings_option ON wp_plugin_settings(option_name);
SQL,
            'release' => false,
        ],
        [
            'name' => 'duplicate_plugin',
            'dump' => 'CREATE TABLE wp_plugin_settings(id INTEGER);',
            'on_error' => 'rollback',
        ],
    ],
    ['schema_version' => 30, 'data_version' => 6, 'next_rootpage' => 12, 'page_size' => 1024]
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'planned');
    assert($plan['applied_count'] === 4);
    assert($plan['rolled_back_batches'] === ['duplicate_plugin']);
    assert(in_array('wp_plugin_settings', $plan['visible_names'], true));
    assert(!in_array('wp_plugin_settings', $plan['released_names'], true));
}

echo json_encode([
    'status' => $plan['status'],
    'applied_count' => $plan['applied_count'],
    'rolled_back_batches' => $plan['rolled_back_batches'],
    'open_batches' => $plan['open_batches'],
    'schema_version_after' => $plan['schema_version_after'],
    'visible_names' => $plan['visible_names'],
    'released_names' => $plan['released_names'],
], JSON_PRETTY_PRINT) . "\n";
