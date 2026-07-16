<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewTriggerDdlCorpus;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    new SQLiteSchemaRecord('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, action text)', 2),
    new SQLiteSchemaRecord('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW autoloaded_options(option_id, option_name) AS SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes'", 3),
    new SQLiteSchemaRecord('trigger', 'option_audit_insert', 'wp_options', 0, "CREATE TRIGGER option_audit_insert AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, action) VALUES (new.option_id, 'insert'); END", 4),
    new SQLiteSchemaRecord('trigger', 'option_view_insert', 'autoloaded_options', 0, "CREATE TRIGGER option_view_insert INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(option_id, option_name, autoload) VALUES (new.option_id, new.option_name, 'yes'); END", 5),
];

echo json_encode([
    'applicationUse' => 'Preview copied Application sqlite_schema view and trigger DDL before import tools preserve autoloaded option views, INSTEAD OF triggers, and DROP bookkeeping without requiring ext/sqlite.',
    'summary' => SQLiteViewTriggerDdlCorpus::summary($records),
    'views' => SQLiteViewTriggerDdlCorpus::views($records),
    'triggers' => SQLiteViewTriggerDdlCorpus::triggers($records),
    'dropAutoloadedOptionsView' => SQLiteViewTriggerDdlCorpus::dropView($records, 'autoloaded_options'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
