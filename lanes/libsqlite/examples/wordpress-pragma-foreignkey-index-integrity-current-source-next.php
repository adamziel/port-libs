<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePragmaForeignKeyIndexIntegrityCurrentSourceNext.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyIndexIntegrityYield.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';

use PortLibs\LibSqlite\SQLitePragmaForeignKeyIndexIntegrityCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$records = [
    $record('table', 'wp_option_names', 'wp_option_names', 2, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, source TEXT)', 1),
    $record('index', 'wp_option_names_name_u', 'wp_option_names', 3, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE nocase)', 2),
    $record('table', 'wp_legacy_names', 'wp_legacy_names', 4, 'CREATE TABLE wp_legacy_names(name TEXT)', 3),
    $record('index', 'wp_legacy_names_name', 'wp_legacy_names', 5, 'CREATE INDEX wp_legacy_names_name ON wp_legacy_names(name)', 4),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, legacy_name TEXT)', 5),
];

$foreignKeys = [
    ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
        ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
    ]],
    ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_legacy_names', 'columns' => ['legacy_name' => 'name']],
];

$tables = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'source' => 'core'],
    ],
    'wp_legacy_names' => [
        ['rowid' => 1, 'name' => 'old_home'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'legacy_name' => 'old_home'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing_option', 'legacy_name' => 'old_home'],
    ],
];

$page = SQLitePragmaForeignKeyIndexIntegrityCurrentSourceNext::page(
    $records,
    $foreignKeys,
    $tables,
    'cf317d66c8667530b6b7e4bc41f70f5a0397ef54',
    'pragma-foreignkey-index-integrity-current-source-next',
    0,
    2,
);

echo json_encode([
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'next_offset' => $page['next_offset'],
    'blocking' => $page['next']['blocking'] ?? [],
    'rows' => array_map(
        static fn (array $row): array => [
            'kind' => $row['kind'],
            'table' => $row['table'],
            'parent' => $row['parent'],
            'rowid' => $row['rowid'],
            'status' => $row['status'],
            'message' => $row['message'],
        ],
        $page['rows'],
    ),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
