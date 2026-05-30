<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempCollationCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$main = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE, option_value TEXT COLLATE RTRIM, autoload TEXT COLLATE BINARY)', 1),
]);
$main->attach('site', '/srv/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id INTEGER, option_name TEXT COLLATE BINARY, option_value TEXT COLLATE NOCASE, autoload TEXT COLLATE RTRIM)', 2),
]);

$withTempImport = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE, option_value TEXT COLLATE RTRIM, autoload TEXT COLLATE BINARY)', 1),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE RTRIM, option_value TEXT COLLATE NOCASE, autoload TEXT COLLATE BINARY)', 3),
    ],
);
$withTempImport->attach('site', '/srv/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id INTEGER, option_name TEXT COLLATE BINARY, option_value TEXT COLLATE NOCASE, autoload TEXT COLLATE RTRIM)', 2),
]);

$rows = SQLiteAttachTempCollationCachePlan::yieldCurrentNext(
    $main,
    'wp_options',
    ['option_name', 'option_value', 'autoload'],
    [['label' => 'temp_import_shadow', 'catalog' => $withTempImport]],
);

$summary = array_map(
    static fn (array $row): array => [
        'label' => $row['label'],
        'status' => $row['validation']['status'],
        'reason' => $row['validation']['reason'],
        'before_schema' => $row['validation']['before_schema'],
        'after_schema' => $row['validation']['after_schema'],
        'changed_columns' => $row['validation']['changed_columns'],
    ],
    $rows,
);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
