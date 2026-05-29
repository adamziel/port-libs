<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(site_id INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_option_defaults', 'wp_option_defaults', 3, "CREATE TABLE wp_option_defaults(option_name TEXT PRIMARY KEY, option_value TEXT NOT NULL DEFAULT '')", 2),
    $record('index', 'wp_option_defaults_name_unique', 'wp_option_defaults', 4, 'CREATE UNIQUE INDEX wp_option_defaults_name_unique ON wp_option_defaults(option_name)', 3),
    $record('table', 'wp_imported_options', 'wp_imported_options', 5, "CREATE TABLE wp_imported_options(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        FOREIGN KEY(site_id) REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        FOREIGN KEY(option_name) REFERENCES wp_option_defaults(option_name) ON UPDATE SET DEFAULT
    )", 4),
];

$next = [
    $current[0],
    $current[1],
    $current[2],
    $record('table', 'wp_imported_options', 'wp_imported_options', 5, "CREATE TABLE wp_imported_options(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL DEFAULT 1,
        option_name TEXT NOT NULL DEFAULT 'home',
        FOREIGN KEY(site_id) REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        FOREIGN KEY(option_name) REFERENCES wp_option_defaults(option_name) ON UPDATE SET DEFAULT
    )", 4),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page247(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_option_defaults_name_unique)',
    'PRAGMA main.foreign_key_list(wp_imported_options)',
    0,
    200,
);

echo json_encode([
    'operation' => $page['operation'],
    'current_blockers' => $page['current']['foreign_key_set_default']['blocked'],
    'next_blockers' => $page['next_counts']['foreign_key_set_default']['blocked'],
    'repaired' => $page['delta']['foreign_key_set_default_repaired'],
    'summaries' => $page['next_source']['foreign_key_set_default'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
