<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewTriggerNameResolution;

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    new SQLiteSchemaRecord('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text)', 2),
    new SQLiteSchemaRecord('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW autoloaded_options(option_id, option_name, option_value) AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
    new SQLiteSchemaRecord('trigger', 'autoloaded_options_insert', 'autoloaded_options', 0, "CREATE TRIGGER autoloaded_options_insert INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); INSERT INTO wp_option_audit(option_id, label) VALUES(new.option_id, 'autoloaded'); END", 4),
];

echo json_encode([
    'resolvedTrigger' => SQLiteViewTriggerNameResolution::resolveTrigger($records, 'autoloaded_options_insert'),
    'summary' => SQLiteViewTriggerNameResolution::summary($records),
    'applicationUse' => 'Preview copied Application sqlite_schema import checks that resolve INSTEAD OF triggers against autoloaded-option view output columns before replaying trigger bodies without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
