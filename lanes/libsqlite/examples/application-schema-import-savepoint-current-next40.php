<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaImportSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteSchemaImportSavepointPlan::plan(
    ['app_comment_meta' => ['type' => 'table', 'sql' => 'CREATE TABLE app_comment_meta(meta_id INTEGER);']],
    [
        [
            'name' => 'settings_schema',
            'dump' => <<<'SQL'
CREATE TABLE app_settings (
  setting_id INTEGER PRIMARY KEY AUTOINCREMENT,
  key_name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  key_value TEXT NOT NULL DEFAULT '',
  load_policy TEXT NOT NULL DEFAULT 'yes'
);
CREATE UNIQUE INDEX app_settings_key_name ON app_settings(key_name COLLATE NOCASE);
SQL,
        ],
        [
            'name' => 'module_schema',
            'dump' => <<<'SQL'
CREATE TABLE app_module_settings (
  key_name TEXT NOT NULL,
  module_key TEXT NOT NULL,
  module_value TEXT NOT NULL,
  PRIMARY KEY(key_name, module_key)
);
CREATE INDEX app_module_settings_key ON app_module_settings(key_name);
SQL,
            'release' => false,
        ],
        [
            'name' => 'duplicate_module',
            'dump' => 'CREATE TABLE app_module_settings(id INTEGER);',
            'on_error' => 'rollback',
        ],
    ],
    ['schema_version' => 30, 'data_version' => 6, 'next_rootpage' => 12, 'page_size' => 1024]
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'planned');
    assert($plan['applied_count'] === 4);
    assert($plan['rolled_back_batches'] === ['duplicate_module']);
    assert(in_array('app_module_settings', $plan['visible_names'], true));
    assert(!in_array('app_module_settings', $plan['released_names'], true));
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
