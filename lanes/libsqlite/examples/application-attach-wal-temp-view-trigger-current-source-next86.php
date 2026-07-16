<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempViewTriggerResolution;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = static function (bool $next) use ($record): SQLiteAttachedSchemaCatalog {
    $view = $next
        ? "CREATE VIEW active_options(option_id, option_name, option_value, autoload, blog_id) AS SELECT option_id, option_name, option_value, autoload, 1 AS blog_id FROM wp_options WHERE autoload = 'yes'"
        : "CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = 'yes'";
    $trigger = $next
        ? "CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name, blog_id) VALUES(new.option_id, 'next', new.option_name, new.blog_id); END"
        : "CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'current', new.option_name); END";

    return new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, option_name text, blog_id integer)', 2),
        $record('view', 'active_options', 'active_options', 0, $view, 3),
        $record('trigger', 'active_options_insert', 'active_options', 0, $trigger, 4),
    ], [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, autoload text)', 5),
    ]);
};

$plan = SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan(
    $catalog(false),
    $catalog(true),
    'main.active_options_insert',
);

if (in_array('--self-test', $argv, true)) {
    if ($plan['status'] !== 'reprepare-required' || $plan['current']['columns'] === $plan['next']['columns']) {
        fwrite(STDERR, "application-attach-wal-temp-view-trigger-current-source-next86 self-test failed\n");
        exit(1);
    }

    echo "application-attach-wal-temp-view-trigger-current-source-next86 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application attach WAL temp view trigger current-source next86',
    'applicationUse' => 'Preview whether a copied Application option-import trigger prepared against current view columns must be reprepared after WAL-backed schema DDL changes the next trigger/view source.',
    'status' => $plan['status'],
    'requiresReprepare' => $plan['requiresReprepare'],
    'changedFields' => $plan['changedFields'],
    'walSchemas' => $plan['walSchemas'],
    'tempSchemas' => $plan['tempSchemas'],
    'currentColumns' => $plan['current']['columns'],
    'nextColumns' => $plan['next']['columns'],
], JSON_PRETTY_PRINT) . PHP_EOL;
