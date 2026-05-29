<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 2),
    $record('index', 'wp_option_names_name', 'wp_option_names', 6, 'CREATE INDEX wp_option_names_name ON wp_option_names(name COLLATE BINARY)', 3),
];
$nextRecords = [
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 2),
    $record('index', 'wp_option_names_name', 'wp_option_names', 6, 'CREATE UNIQUE INDEX wp_option_names_name ON wp_option_names(name COLLATE NOCASE)', 3),
];
$foreignKeys = [
    ['id' => 156, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
        ['child' => 'option_name', 'parent' => 'name'],
    ]],
];
$currentTables = [
    'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
    'wp_options' => [
        ['rowid' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 'plugin-orphan', 'option_name' => 'plugin_missing', 'autoload' => 'no'],
    ],
];
$nextTables = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl'],
        ['rowid' => 2, 'name' => 'plugin_missing'],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPage156(
    $currentRecords,
    $foreignKeys,
    $currentTables,
    $nextRecords,
    $foreignKeys,
    $nextTables,
    'PRAGMA index_xinfo(wp_option_names_name)',
);

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next156',
    'status' => $page['status'],
    'current_index_blockers' => $page['current']['index_blockers'],
    'current_foreign_key_violations' => $page['current']['foreign_key_violations'],
    'next_ready' => $page['next_state']['ready'],
    'delta_total_blockers' => $page['delta']['total_blockers'],
    'parent_index' => $page['next_counts']['parent_indexes'][0] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
