<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms_parent', 'wp_terms_parent', 2, 'CREATE TABLE wp_terms_parent(term_slug TEXT NOT NULL, taxonomy TEXT NOT NULL, locale TEXT NOT NULL, term_id INTEGER PRIMARY KEY, UNIQUE(term_slug, taxonomy))', 1),
    $record('index', 'sqlite_autoindex_wp_terms_parent_1', 'wp_terms_parent', 3, null, 2),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, 'CREATE TABLE wp_termmeta_import(meta_id INTEGER PRIMARY KEY, term_slug TEXT NOT NULL, taxonomy TEXT NOT NULL, FOREIGN KEY(term_slug, taxonomy) REFERENCES wp_terms_parent(term_slug, taxonomy))', 3),
];

$next = [
    $record('table', 'wp_terms_parent', 'wp_terms_parent', 2, 'CREATE TABLE wp_terms_parent(term_slug TEXT NOT NULL, taxonomy TEXT NOT NULL, locale TEXT NOT NULL, term_id INTEGER, PRIMARY KEY(term_slug, taxonomy), UNIQUE(locale)) WITHOUT ROWID', 1),
    $record('index', 'sqlite_autoindex_wp_terms_parent_1', 'wp_terms_parent', 3, null, 2),
    $record('index', 'sqlite_autoindex_wp_terms_parent_2', 'wp_terms_parent', 4, null, 3),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, 'CREATE TABLE wp_termmeta_import(meta_id INTEGER PRIMARY KEY, locale TEXT NOT NULL, FOREIGN KEY(locale) REFERENCES wp_terms_parent(locale))', 4),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page239(
    $current,
    $next,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_parent_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['operation'] !== 'pragma-index-xinfo-foreignkey-current-source-next239'
        || $page['current']['foreign_key_parent_auxiliary_index']['rowid_auxiliary'] !== 2
        || $page['next_counts']['foreign_key_parent_auxiliary_index']['without_rowid_primary_key_auxiliary'] !== 2
        || $page['delta']['foreign_key_parent_auxiliary_index_changed'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next239 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next239 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next239',
    'applicationUse' => 'Copied Application term metadata imports can trust PRAGMA index_xinfo key=0 auxiliary rows as storage-only tails, not extra UNIQUE parent-key columns for foreign-key admission.',
    'operation' => $page['operation'],
    'current_rowid_auxiliary_rows' => $page['current']['foreign_key_parent_auxiliary_index']['rowid_auxiliary'],
    'next_without_rowid_auxiliary_rows' => $page['next_counts']['foreign_key_parent_auxiliary_index']['without_rowid_primary_key_auxiliary'],
    'auxiliary_changed' => $page['delta']['foreign_key_parent_auxiliary_index_changed'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_parent_1)',
        'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    ],
    'rows' => $page['current_source']['foreign_key_parent_auxiliary_index'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
