<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_term_id INTEGER NOT NULL,
        slug_ref TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL NOT NULL REFERENCES wp_terms(slug) ON DELETE SET NULL,
        term_ref INTEGER GENERATED ALWAYS AS (raw_term_id) STORED NOT NULL,
        FOREIGN KEY(term_ref) REFERENCES wp_terms(term_id) ON UPDATE SET DEFAULT
    )", 3),
];

$next = [
    $current[0],
    $current[1],
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_term_id INTEGER NOT NULL,
        slug_ref TEXT REFERENCES wp_terms(slug) ON DELETE SET NULL,
        term_ref INTEGER DEFAULT 0 REFERENCES wp_terms(term_id) ON UPDATE SET DEFAULT
    )", 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253::page(
    $current,
    $next,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    0,
    200,
);

echo json_encode([
    'operation' => $page['operation'],
    'current_generated_child_action_blockers' => $page['current']['foreign_key_generated_child_actions']['blocked'],
    'next_generated_child_action_blockers' => $page['next_counts']['foreign_key_generated_child_actions']['blocked'],
    'repaired' => $page['delta']['foreign_key_generated_child_action_repaired'],
    'source' => $page['current_source']['foreign_key_generated_child_action_source'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
