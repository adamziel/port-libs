<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaCatalogDdlPlan.php';
require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';

use PortLibs\LibSqlite\SQLiteSchemaCatalogDdlPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    new SQLiteSchemaRecord('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id INTEGER, label TEXT, option_name TEXT)', 2),
    new SQLiteSchemaRecord('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name FROM main.wp_options WHERE autoload = 'yes'", 3),
    new SQLiteSchemaRecord('trigger', 'autoloaded_options_insert', 'autoloaded_options', 0, "CREATE TRIGGER main.autoloaded_options_insert INSTEAD OF INSERT ON autoloaded_options BEGIN SELECT new.option_name; END", 4),
];

$ddl = <<<'SQL'
DROP TRIGGER IF EXISTS main.autoloaded_options_insert;
DROP VIEW IF EXISTS main.autoloaded_options;
CREATE TEMP VIEW IF NOT EXISTS temp.autoloaded_options AS
  SELECT option_id, option_name, option_value FROM main.wp_options WHERE autoload = 'yes';
CREATE TEMP TRIGGER IF NOT EXISTS temp.autoloaded_options_insert
INSTEAD OF INSERT ON main.autoloaded_options
BEGIN
  INSERT INTO main.wp_options(option_id, option_name, option_value, autoload)
  VALUES(new.option_id, new.option_name, new.option_value, 'yes');
  INSERT INTO main.wp_option_audit(option_id, label, option_name)
  VALUES(new.option_id, 'view insert; schema qualified', new.option_name);
END;
SQL;

$plan = SQLiteSchemaCatalogDdlPlan::currentNext($records, $ddl, [
    'schema_version' => 73,
    'data_version' => 11,
    'next_rowid' => 20,
]);

echo json_encode([
    'operation' => $plan['operation'],
    'schemaVersionBefore' => $plan['schema_version_before'],
    'schemaVersionAfter' => $plan['schema_version_after'],
    'dropped' => $plan['dropped'],
    'nextObjects' => array_map(
        static fn (array $row): array => [
            'type' => $row['type'],
            'name' => $row['name'],
            'tbl_name' => $row['tbl_name'],
            'rootpage' => $row['rootpage'],
            'rowid' => $row['rowid'],
        ],
        $plan['next'],
    ),
], JSON_PRETTY_PRINT) . PHP_EOL;
