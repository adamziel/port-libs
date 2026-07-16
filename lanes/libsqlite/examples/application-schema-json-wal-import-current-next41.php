<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaJsonWalImportPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$schema = <<<'SQL'
CREATE TABLE wp_options (
  option_id INTEGER PRIMARY KEY AUTOINCREMENT,
  option_name TEXT NOT NULL UNIQUE,
  option_value TEXT NOT NULL,
  autoload TEXT NOT NULL DEFAULT 'yes'
);
CREATE TABLE wp_import_log(id INTEGER PRIMARY KEY, message TEXT NOT NULL);
CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name);
SQL;

$plan = SQLiteSchemaJsonWalImportPlan::plan($schema, [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => '{"enabled":false}',
        'autoload' => 'yes',
        'page_number' => 2,
    ],
], [
    [
        'statement' => 'enable_plugin',
        'option_name' => 'plugin_settings',
        'path' => '$.enabled',
        'value' => true,
    ],
], [], [
    'database_path' => '/tmp/wp-schema-json-wal-import-current-next41.sqlite',
    'schema' => ['schema_version' => 41, 'data_version' => 9],
]);

echo json_encode([
    'scenario' => 'application-schema-json-wal-import-current-next41',
    'applicationUse' => 'Plan a copied Application schema plus JSON wp_options import as one WAL-yielding transaction, preserving schema/data cookies, applied JSON option pages, checkpoint admission, and commit ordering without requiring ext/sqlite.',
    'status' => $plan['status'],
    'schemaApplied' => $plan['schema_applied_count'],
    'jsonApplied' => $plan['json_applied_count'],
    'walFrameCount' => $plan['wal_frame_count'],
    'dirtyPages' => $plan['dirty_pages'],
    'commitOrder' => $plan['commit_order'],
    'checkpointAdmission' => $plan['checkpoint_admission'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
