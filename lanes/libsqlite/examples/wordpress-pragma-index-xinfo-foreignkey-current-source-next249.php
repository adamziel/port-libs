<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_terms_parent', 'wp_terms_parent', 2, 'CREATE TABLE wp_terms_parent(slug TEXT PRIMARY KEY, taxonomy TEXT NOT NULL, term_id INTEGER)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_parent_1', 'wp_terms_parent', 3, null, 2),
    $record('table', 'wp_termmeta_generated_child', 'wp_termmeta_generated_child', 4, "CREATE TABLE wp_termmeta_generated_child(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_taxonomy TEXT NOT NULL,
        slug_key TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL NOT NULL REFERENCES wp_terms_parent(slug),
        taxonomy_key TEXT GENERATED ALWAYS AS (lower(raw_taxonomy)) STORED,
        FOREIGN KEY(slug_key, taxonomy_key) REFERENCES wp_terms_parent(slug, taxonomy)
    )", 3),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $record('table', 'wp_termmeta_generated_child', 'wp_termmeta_generated_child', 4, "CREATE TABLE wp_termmeta_generated_child(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_taxonomy TEXT NOT NULL,
        slug_key TEXT NOT NULL REFERENCES wp_terms_parent(slug),
        taxonomy_key TEXT NOT NULL,
        FOREIGN KEY(slug_key, taxonomy_key) REFERENCES wp_terms_parent(slug, taxonomy)
    )", 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page249(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_parent_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_generated_child)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next249',
    'wordpressUse' => 'Copied WordPress taxonomy imports can keep generated child foreign-key columns visible by joining PRAGMA foreign_key_list to PRAGMA table_xinfo instead of table_info-only metadata.',
    'status' => $page['status'],
    'current_generated_child_rows' => $page['current']['foreign_key_generated_child_columns']['rows'],
    'current_virtual_child_rows' => $page['current']['foreign_key_generated_child_columns']['virtual'],
    'current_stored_child_rows' => $page['current']['foreign_key_generated_child_columns']['stored'],
    'next_generated_child_rows' => $page['next_counts']['foreign_key_generated_child_columns']['rows'],
    'generated_child_columns_repaired' => $page['delta']['foreign_key_generated_child_columns_repaired'],
    'source' => $page['current_source']['foreign_key_generated_child_column_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_generated_child_rows'] !== 3
        || $summary['current_virtual_child_rows'] !== 2
        || $summary['current_stored_child_rows'] !== 1
        || $summary['next_generated_child_rows'] !== 0
        || $summary['generated_child_columns_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next249 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next249 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
