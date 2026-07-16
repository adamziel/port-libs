<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempViewTriggerYieldPlan;
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
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, option_name text)', 2),
        $record('view', 'active_options', 'active_options', 0, "CREATE VIEW active_options(option_id, option_name, option_value, autoload) AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = 'yes'", 3),
        $record('trigger', 'active_options_when_insert', 'active_options', 0, "CREATE TRIGGER active_options_when_insert INSTEAD OF INSERT ON active_options WHEN new.autoload = 'yes' BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'main-when', new.option_name); END", 4),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, autoload text)', 5),
        $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, temp_name text)', 6),
        $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value, autoload) AS SELECT option_id, temp_name, option_value, autoload FROM temp.wp_options', 7),
        $record('trigger', 'temp_active_when_insert', 'active_options', null, "CREATE TEMP TRIGGER temp_active_when_insert INSTEAD OF INSERT ON active_options WHEN new.autoload = 'yes' OR new.temp_name = 'force' BEGIN INSERT INTO wp_options(option_id, temp_name, option_value, autoload) VALUES(new.option_id, new.temp_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, temp_name) VALUES(new.option_id, 'temp-when', new.temp_name); END", 8),
    ],
);

$catalog->attach('site', '/srv/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 9),
    $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, option_name text)', 10),
    $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW site.active_options(blog_id, option_name, option_value, autoload) AS SELECT blog_id, option_name, option_value, autoload FROM wp_options', 11),
    $record('trigger', 'site_active_when_insert', 'active_options', 0, "CREATE TRIGGER site_active_when_insert INSTEAD OF INSERT ON active_options WHEN new.blog_id = 3 AND new.option_name != 'skip_me' BEGIN INSERT INTO wp_options(blog_id, option_name, option_value, autoload) VALUES(new.blog_id, new.option_name, new.option_value, new.autoload); INSERT INTO site.wp_option_audit(blog_id, label, option_name) VALUES(new.blog_id, 'site-when', new.option_name); END", 12),
]);

$main = SQLiteAttachTempViewTriggerYieldPlan::yield($catalog, 'active_options_when_insert', [
    'option_id' => 7,
    'option_name' => 'siteurl',
    'option_value' => 'https://example.test',
    'autoload' => 'yes',
]);
$tempSkipped = SQLiteAttachTempViewTriggerYieldPlan::yield($catalog, 'temp_active_when_insert', [
    'option_id' => 501,
    'temp_name' => 'plugin_import_preview',
    'option_value' => '{"dryRun":true}',
    'autoload' => 'no',
]);
$site = SQLiteAttachTempViewTriggerYieldPlan::yield($catalog, 'site.site_active_when_insert', [
    'blog_id' => 3,
    'option_name' => 'home',
    'option_value' => 'https://network.test',
    'autoload' => 'yes',
]);

echo json_encode([
    'scenario' => 'application attach temp view trigger WHEN-yielded operations',
    'applicationUse' => 'Preview copied Application option imports through TEMP, main, and attached INSTEAD OF view triggers while honoring SQLite WHEN admission before yielding writes.',
    'mainStatus' => $main['status'],
    'tempSkippedStatus' => $tempSkipped['status'],
    'siteStatus' => $site['status'],
    'writesBySchema' => [
        'main' => $main['writesBySchema'],
        'tempSkipped' => $tempSkipped['writesBySchema'],
        'site' => $site['writesBySchema'],
    ],
    'firstOperations' => [
        'main' => $main['operations'][0],
        'site' => $site['operations'][0],
    ],
], JSON_PRETTY_PRINT) . PHP_EOL;
