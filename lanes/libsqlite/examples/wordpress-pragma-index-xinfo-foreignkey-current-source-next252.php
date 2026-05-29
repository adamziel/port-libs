<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(slug_key TEXT PRIMARY KEY, taxonomy_key TEXT UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record('index', 'sqlite_autoindex_wp_terms_2', 'wp_terms', 4, null, 3),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        slug_ref TEXT REFERENCES wp_terms(slug_key),
        FOREIGN KEY(taxonomy_ref) REFERENCES wp_terms(taxonomy_key)
    )", 4),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, 'CREATE TABLE wp_termmeta_import(meta_id INTEGER PRIMARY KEY, raw_slug TEXT NOT NULL, slug_ref TEXT REFERENCES wp_terms(slug_key), taxonomy_ref TEXT REFERENCES wp_terms(taxonomy_key))', 4),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page252(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next252',
    'wordpressUse' => 'Copied WordPress taxonomy import DDL can catch foreign-key child columns that are absent even from PRAGMA table_xinfo before replaying schema repair.',
    'status' => $page['status'],
    'current_missing_child_columns' => $page['current']['foreign_key_missing_child_columns']['missing_child_column'],
    'next_missing_child_columns' => $page['next_counts']['foreign_key_missing_child_columns']['missing_child_column'],
    'missing_child_repaired' => $page['delta']['foreign_key_missing_child_repaired'],
    'source' => $page['current_source']['foreign_key_missing_child_column_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_1)',
        'PRAGMA main.foreign_key_list(wp_termmeta_import)',
        'PRAGMA main.table_xinfo(wp_termmeta_import)',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_missing_child_columns'] !== 1
        || $summary['next_missing_child_columns'] !== 0
        || $summary['missing_child_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next252 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next252 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
