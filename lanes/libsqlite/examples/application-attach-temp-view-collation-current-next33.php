<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempViewCollationPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text COLLATE NOCASE, option_value text COLLATE RTRIM, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, audit_name text COLLATE RTRIM, option_name text COLLATE NOCASE, source text)', 2),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW active_options(option_id, option_name, option_value) AS SELECT option_id, option_name COLLATE NOCASE, option_value COLLATE RTRIM FROM wp_options', 3),
        $record('trigger', 'active_options_insert_collate', 'active_options', 0, "CREATE TRIGGER active_options_insert_collate INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); INSERT INTO wp_option_audit(option_id, audit_name, option_name, source) VALUES(new.option_id, new.option_name, new.option_name, 'main'); SELECT new.option_name COLLATE NOCASE, new.option_value COLLATE RTRIM; END", 4),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer primary key, temp_name text COLLATE RTRIM, option_value text COLLATE NOCASE)', 5),
        $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name, option_value) AS SELECT option_id, temp_name COLLATE RTRIM, option_value COLLATE NOCASE FROM temp.wp_options', 6),
        $record('trigger', 'temp_active_insert_collate', 'active_options', null, "CREATE TEMP TRIGGER temp_active_insert_collate INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, temp_name, option_value) VALUES(new.option_id, new.temp_name, new.option_value); SELECT new.temp_name COLLATE RTRIM, new.option_value COLLATE NOCASE; END", 7),
    ],
);

$catalog->attach('site', '/srv/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text COLLATE NOCASE, option_value text)', 8),
    $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW site.active_options(blog_id, option_name, option_value) AS SELECT blog_id, option_name COLLATE NOCASE, option_value FROM wp_options', 9),
    $record('trigger', 'site_active_insert_collate', 'active_options', 0, "CREATE TRIGGER site_active_insert_collate INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(blog_id, option_name, option_value) VALUES(new.blog_id, new.option_name, new.option_value); SELECT new.option_name COLLATE NOCASE, new.option_value; END", 10),
]);

$main = SQLiteAttachTempViewCollationPlan::forTrigger($catalog, 'active_options_insert_collate');
$temp = SQLiteAttachTempViewCollationPlan::forTrigger($catalog, 'temp_active_insert_collate');
$site = SQLiteAttachTempViewCollationPlan::forTrigger($catalog, 'site.site_active_insert_collate');

echo json_encode([
    'main_view_collations' => $main['targetCollations'],
    'main_body_collations' => $main['bodyCollationsBySchema'],
    'temp_view_collations' => $temp['targetCollations'],
    'temp_body_schema' => $temp['body'][0]['schema'],
    'site_view_collations' => $site['targetCollations'],
    'summary' => SQLiteAttachTempViewCollationPlan::summary($catalog),
], JSON_PRETTY_PRINT) . "\n";
