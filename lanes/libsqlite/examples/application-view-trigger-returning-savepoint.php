<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewTriggerReturningSavepointPlan;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, new_value text)', 2),
    $record('view', 'active_options', 'active_options', 0, "CREATE VIEW active_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
    $record('trigger', 'active_options_insert_current', 'active_options', 0, "CREATE TRIGGER active_options_insert_current INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'view-insert', new.option_value); SELECT new.option_id, new.option_name; END", 4),
]);

$result = SQLiteViewTriggerReturningSavepointPlan::insertIntoView(
    $catalog,
    'active_options_insert_current',
    [
        'main.wp_options' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
        ],
        'main.wp_option_audit' => [],
    ],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://new.test'],
    'wp_import_view_insert',
    ['option_id', 'option_name', 'value' => 'option_value']
);

echo json_encode([
    'returning' => $result['returning_rows'],
    'changes' => $result['changes'],
    'writesBySchema' => $result['writes_by_schema'],
    'optionNames' => array_column($result['tables']['main.wp_options'], 'option_name'),
    'auditLabels' => array_column($result['tables']['main.wp_option_audit'], 'label'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
