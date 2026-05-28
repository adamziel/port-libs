<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaCatalogDdlPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, option_value TEXT, autoload TEXT)', 1),
    new SQLiteSchemaRecord('index', 'wp_options_autoload', 'wp_options', 3, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 2),
    new SQLiteSchemaRecord('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, "CREATE VIEW wp_autoloaded_options AS SELECT option_name FROM wp_options WHERE autoload = 'yes'", 3),
];

$ddl = <<<'SQL'
DROP VIEW IF EXISTS wp_autoloaded_options;
DROP INDEX wp_options_autoload;
ALTER TABLE wp_options RENAME TO wp_options_archive;
CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, option_value TEXT, autoload TEXT);
CREATE INDEX wp_options_autoload ON wp_options(autoload, option_name);
CREATE VIEW wp_autoloaded_options AS SELECT option_name FROM wp_options WHERE autoload = 'yes';
SQL;

$plan = SQLiteSchemaCatalogDdlPlan::currentNext($records, $ddl, [
    'schema_version' => 41,
    'data_version' => 12,
]);

echo json_encode([
    'operation' => $plan['operation'],
    'applied_count' => $plan['applied_count'],
    'schema_version_after' => $plan['schema_version_after'],
    'next_names' => array_column($plan['next'], 'name'),
    'renamed' => $plan['renamed'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
