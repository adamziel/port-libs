<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempSchemaResolutionPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, option_name text)', 2),
        $record('trigger', 'wp_options_ai', 'wp_options', 0, "CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'main', new.option_name); END", 3),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 4),
        $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, temp_name text)', 5),
        $record('trigger', 'wp_options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, temp_name) VALUES(new.option_id, 'temp', new.temp_name); END", 6),
    ],
);

$loader = static fn (string $file, string $schema): array => $schema === 'site' ? [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_id integer, option_name text, option_value text)', 7),
    $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, option_name text)', 8),
    $record('trigger', 'wp_options_ai', 'wp_options', 0, "CREATE TRIGGER site.wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, label, option_name) VALUES(new.blog_id, 'site', new.option_name); END", 9),
] : [];

$trace = SQLiteAttachTempSchemaResolutionPlan::transitionTrace(
    $catalog,
    [
        ['label' => 'attach-site', 'sql' => "ATTACH DATABASE '/srv/site.sqlite' AS site"],
        ['label' => 'detach-site', 'sql' => 'DETACH DATABASE site'],
    ],
    [
        ['kind' => 'table', 'name' => 'wp_options'],
        ['kind' => 'table', 'name' => 'site.wp_options'],
        ['kind' => 'schema-table', 'name' => 'sqlite_temp_schema'],
        ['kind' => 'yield', 'name' => 'wp_options_ai', 'new' => ['option_id' => 501, 'temp_name' => 'plugin_cache', 'option_value' => '{"enabled":true}']],
        ['kind' => 'yield', 'name' => 'site.wp_options_ai', 'new' => ['blog_id' => 3, 'option_id' => 9, 'option_name' => 'home', 'option_value' => 'https://site.test']],
    ],
    $loader,
);

echo json_encode([
    'scenario' => 'application attach temp schema resolution current next32',
    'applicationUse' => 'Preview copied Application option imports while ATTACH and DETACH change current/next schema resolution; TEMP still shadows main for unqualified names and attached triggers appear only while the schema is attached.',
    'steps' => array_map(static fn (array $step): array => [
        'label' => $step['label'],
        'operation' => $step['operation'],
        'afterSearchOrder' => $step['after']['searchOrder'],
        'unqualifiedWpOptionsSchema' => $step['after']['probes']['table:wp_options']['schema'],
        'siteTriggerStatus' => $step['after']['probes']['yield:site.wp_options_ai']['status'],
        'tempYieldWrites' => $step['after']['probes']['yield:wp_options_ai']['writesBySchema'],
    ], $trace['steps']),
], JSON_PRETTY_PRINT) . PHP_EOL;
