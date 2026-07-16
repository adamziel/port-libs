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

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('view', 'active_options', 'active_options', 0, "CREATE VIEW active_options(option_id, option_name, option_value) AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 2),
        $record('trigger', 'active_options_insert', 'active_options', 0, "CREATE TRIGGER active_options_insert INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); END", 3),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 4),
        $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options(option_id, temp_name) AS SELECT option_id, temp_name FROM temp.wp_options', 5),
        $record('trigger', 'temp_active_insert', 'active_options', null, 'CREATE TEMP TRIGGER temp_active_insert INSTEAD OF INSERT ON active_options BEGIN SELECT new.option_id, new.temp_name; END', 6),
    ],
);

$catalog->attach('site', '/srv/www/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 7),
    $record('view', 'active_options', 'active_options', 0, "CREATE VIEW site.active_options(blog_id, option_name) AS SELECT blog_id, option_name FROM wp_options WHERE option_value <> ''", 8),
    $record('trigger', 'site_active_insert', 'active_options', 0, 'CREATE TRIGGER site_active_insert INSTEAD OF INSERT ON active_options BEGIN SELECT new.blog_id, new.option_name; END', 9),
]);

$summary = SQLiteAttachTempViewTriggerResolution::summary($catalog);
$temp = SQLiteAttachTempViewTriggerResolution::resolve($catalog, 'temp_active_insert');
$main = SQLiteAttachTempViewTriggerResolution::resolve($catalog, 'main.active_options_insert');
$site = SQLiteAttachTempViewTriggerResolution::resolve($catalog, 'site.site_active_insert');

echo json_encode([
    'scenario' => 'application attach temp view trigger current-source resolution',
    'summary' => $summary,
    'targets' => [
        $temp['trigger'] => $temp['targetSchema'] . '.' . $temp['target'],
        $main['trigger'] => $main['targetSchema'] . '.' . $main['target'],
        $site['trigger'] => $site['targetSchema'] . '.' . $site['target'],
    ],
    'columns' => [
        $temp['trigger'] => $temp['columns'],
        $main['trigger'] => $main['columns'],
        $site['trigger'] => $site['columns'],
    ],
], JSON_PRETTY_PRINT) . PHP_EOL;
