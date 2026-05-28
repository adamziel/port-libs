<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCollationPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text COLLATE NOCASE, option_value text COLLATE RTRIM, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, audit_name text COLLATE RTRIM, option_name text COLLATE NOCASE)', 2),
        $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW main.autoloaded_options(option_id, option_name, option_value) AS SELECT option_id, option_name COLLATE NOCASE, option_value COLLATE RTRIM FROM wp_options', 3),
        $record('trigger', 'main_autoloaded_insert', 'autoloaded_options', 0, "CREATE TRIGGER main_autoloaded_insert INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); INSERT INTO wp_option_audit(option_id, audit_name, option_name) VALUES(new.option_id, new.option_name, new.option_name); SELECT new.option_name COLLATE NOCASE, new.option_value COLLATE RTRIM; END", 4),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer primary key, temp_name text COLLATE RTRIM, option_value text COLLATE NOCASE)', 5),
        $record('view', 'autoloaded_options', 'autoloaded_options', null, 'CREATE TEMP VIEW autoloaded_options(option_id, temp_name, option_value) AS SELECT option_id, temp_name COLLATE RTRIM, option_value COLLATE NOCASE FROM temp.wp_options', 6),
        $record('trigger', 'temp_autoloaded_insert', 'autoloaded_options', null, "CREATE TEMP TRIGGER temp_autoloaded_insert INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(option_id, temp_name, option_value) VALUES(new.option_id, new.temp_name, new.option_value); SELECT new.temp_name COLLATE RTRIM, new.option_value COLLATE NOCASE; END", 7),
    ],
);

$catalog->attach('site', '/srv/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text COLLATE NOCASE, option_value text COLLATE BINARY)', 8),
    $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW site.autoloaded_options(blog_id, option_name, option_value) AS SELECT blog_id, option_name COLLATE NOCASE, option_value FROM wp_options', 9),
    $record('trigger', 'site_autoloaded_insert', 'autoloaded_options', 0, "CREATE TRIGGER site_autoloaded_insert INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(blog_id, option_name, option_value) VALUES(new.blog_id, new.option_name, new.option_value); SELECT new.option_name COLLATE NOCASE, new.option_value; END", 10),
]);

$plan = SQLiteAttachWalTempViewCollationPlan::plan(
    $catalog,
    [
        'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 13, 'tables' => ['wp_options', 'wp_option_audit', 'autoloaded_options'], 'file' => '/srv/wp/current.sqlite'],
        'temp' => ['schema_cookie' => 4, 'tables' => ['wp_options', 'autoloaded_options'], 'file' => ''],
        'site' => ['schema_cookie' => 6, 'wal_frames' => [['page' => 1, 'schema_cookie' => 7, 'commit' => true]], 'tables' => ['wp_options', 'autoloaded_options'], 'file' => '/srv/site.sqlite'],
    ],
    ['main_autoloaded_insert', 'temp_autoloaded_insert', 'site.site_autoloaded_insert'],
);

echo json_encode([
    'operation' => $plan['operation'],
    'changed_schemas' => $plan['changed_schemas'],
    'reprepare_triggers' => $plan['reprepare_triggers'],
    'stable_triggers' => $plan['stable_triggers'],
    'collation_counts' => $plan['collation_counts'],
], JSON_PRETTY_PRINT) . PHP_EOL;
