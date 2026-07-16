<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(slug_key TEXT PRIMARY KEY)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        slug_ref TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL NOT NULL REFERENCES wp_terms(slug_key)
    )", 3),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, 'CREATE TABLE wp_termmeta_import(meta_id INTEGER PRIMARY KEY, raw_slug TEXT NOT NULL, slug_ref TEXT NOT NULL REFERENCES wp_terms(slug_key))', 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page250(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next250',
    'applicationUse' => 'Copied Application taxonomy imports can distinguish generated child FK columns that PRAGMA table_info omits from actually missing child-column metadata.',
    'status' => $page['status'],
    'current_generated_child_columns' => $page['current']['foreign_key_generated_child_columns']['generated_child_column'],
    'next_generated_child_columns' => $page['next_counts']['foreign_key_generated_child_columns']['generated_child_column'],
    'generated_child_repaired' => $page['delta']['foreign_key_generated_child_repaired'],
    'source' => $page['current_source']['foreign_key_generated_child_column_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_1)',
        'PRAGMA main.foreign_key_list(wp_termmeta_import)',
        'PRAGMA main.table_xinfo(wp_termmeta_import)',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_generated_child_columns'] !== 1
        || $summary['next_generated_child_columns'] !== 0
        || $summary['generated_child_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next250 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next250 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
