<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 3, 'CREATE TABLE wp_termmeta_import(term_id INTEGER REFERENCES wp_terms(term_id) ON DELETE CASCADE)', 2),
    $record('index', 'wp_termmeta_import_term_id', 'wp_termmeta_import', 4, 'CREATE INDEX wp_termmeta_import_term_id ON wp_termmeta_import(term_id)', 3),
];

$next = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 3, 'CREATE TABLE wp_termmeta_import(term_id INTEGER REFERENCES wp_terms(term_id) ON DELETE CASCADE)', 2),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page275(
    $current,
    $next,
    'PRAGMA index_xinfo(wp_termmeta_import_term_id)',
    'PRAGMA foreign_key_list(wp_termmeta_import)',
);

echo json_encode([
    'operation' => $page['operation'],
    'next275' => $page['next_counts']['foreign_key_action_index_xinfo_next275'],
    'changed' => $page['delta']['foreign_key_action_index_xinfo_changed_next275'],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
