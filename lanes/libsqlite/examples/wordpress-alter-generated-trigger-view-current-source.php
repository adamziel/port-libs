<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaAlterGeneratedTriggerViewPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT NOT NULL, autoload TEXT NOT NULL DEFAULT 'yes', option_slug TEXT AS (lower(option_name)) STORED)", 1),
    $record('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, "CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name, option_slug, option_value_len FROM wp_options WHERE autoload = 'yes'", 2),
    $record('trigger', 'wp_options_au', 'wp_options', 0, 'CREATE TRIGGER wp_options_au AFTER UPDATE OF option_value ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, option_value_len) VALUES(new.option_id, new.option_name, new.option_value_len); END', 3),
];

$plan = SQLiteSchemaAlterGeneratedTriggerViewPlan::plan(
    $records,
    'wp_options',
    'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) VIRTUAL',
    [
        ['name' => 'autoloaded-option-view', 'sql' => 'SELECT option_name, option_value_len FROM wp_autoloaded_options', 'columns' => ['option_name', 'option_value_len'], 'schema_version' => 116],
        ['name' => 'direct-option-table', 'sql' => 'SELECT option_value_len FROM wp_options WHERE option_name = ?', 'columns' => ['option_value_len'], 'schema_version' => 116],
    ],
    ['schema_version_before' => 116, 'schema_version_after' => 117],
);

echo json_encode([
    'operation' => $plan['operation'],
    'table' => $plan['table'],
    'addedGeneratedColumn' => $plan['addedGeneratedColumn']['name'],
    'resolvedViews' => $plan['resolvedViews'],
    'resolvedTriggers' => $plan['resolvedTriggers'],
    'invalidatedStatements' => array_column($plan['invalidatedStatements'], 'name'),
    'wordpressUse' => 'Preview an ALTER TABLE ADD generated column migration for copied wp_options databases where dependent views, triggers, and prepared WordPress option queries must be reparsed against the current sqlite_schema source after the schema cookie changes.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
