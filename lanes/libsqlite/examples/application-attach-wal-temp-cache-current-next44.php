<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachUriSchemaCache;
use PortLibs\LibSqlite\SQLiteAttachWalTempCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, ?int $root, string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)'),
        $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TEMP TABLE wp_options(option_name TEXT, option_value TEXT)'),
        $record('table', 'wp_import_stage', 'wp_import_stage', 21, 'CREATE TEMP TABLE wp_import_stage(option_name TEXT, option_value TEXT)'),
    ],
);

$currentArchive = [
    $record('table', 'wp_options', 'wp_options', 40, 'CREATE TABLE wp_options(option_name TEXT, archived_at TEXT)'),
    $record('index', 'wp_options_archive_name', 'wp_options', 41, 'CREATE INDEX wp_options_archive_name ON wp_options(option_name)'),
];
$nextArchive = [
    $record('table', 'wp_options', 'wp_options', 70, 'CREATE TABLE wp_options(option_name TEXT, archived_at TEXT, wal_commit INTEGER)'),
    $record('index', 'wp_options_archive_name', 'wp_options', 71, 'CREATE INDEX wp_options_archive_name ON wp_options(option_name, archived_at)'),
    $record('table', 'wp_terms', 'wp_terms', 72, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT)'),
];

$plan = SQLiteAttachWalTempCachePlan::currentNext(
    $catalog,
    new SQLiteAttachUriSchemaCache(),
    "ATTACH 'file:/srv/wp/archive.sqlite?mode=rw&cache=shared&vfs=unix-dotfile' AS archive",
    static fn (): array => $currentArchive,
    $nextArchive,
    12,
    13,
    ['wp_options', 'archive.wp_options', 'wp_terms', 'wp_import_stage'],
    ['wp_options_archive_name'],
);

echo json_encode([
    'status' => $plan['status'],
    'schema' => $plan['schema'],
    'file' => $plan['file'],
    'cookieChanged' => $plan['cookie_changed'],
    'tempShadowTables' => $plan['temp_shadow_tables'],
    'attachedChangedTables' => $plan['attached_changed_tables'],
    'attachedChangedIndexes' => $plan['attached_changed_indexes'],
    'nextRequiresReload' => $plan['attach']['next']['requires_reload'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
