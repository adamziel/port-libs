<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM)', 1),
    $record('table', 'wp_termmeta', 'wp_termmeta', 5, 'CREATE TABLE wp_termmeta(meta_id INTEGER PRIMARY KEY, term_slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM, meta_key TEXT, FOREIGN KEY(term_slug, taxonomy) REFERENCES wp_terms(slug, taxonomy))', 2),
    $record('index', 'wp_terms_slug_taxonomy_unique', 'wp_terms', 6, 'CREATE UNIQUE INDEX wp_terms_slug_taxonomy_unique ON wp_terms(slug COLLATE BINARY, taxonomy COLLATE RTRIM)', 3),
    $record('index', 'wp_termmeta_term_lookup', 'wp_termmeta', 7, 'CREATE INDEX wp_termmeta_term_lookup ON wp_termmeta(term_slug COLLATE NOCASE, taxonomy COLLATE RTRIM)', 4),
];
$nextRecords = [
    $records[0],
    $records[1],
    $record('index', 'wp_terms_slug_taxonomy_unique', 'wp_terms', 6, 'CREATE UNIQUE INDEX wp_terms_slug_taxonomy_unique ON wp_terms(slug COLLATE NOCASE, taxonomy COLLATE RTRIM)', 3),
    $records[3],
];
$tables = [
    'wp_terms' => [
        ['rowid' => 1, 'term_id' => 1, 'slug' => 'News', 'taxonomy' => 'category'],
    ],
    'wp_termmeta' => [
        ['rowid' => 1, 'meta_id' => 1, 'term_slug' => 'news', 'taxonomy' => 'category', 'meta_key' => '_wp_attachment_metadata'],
    ],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog192(
    $records,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_terms_slug_taxonomy_unique)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next192',
    'applicationUse' => 'Copied termmeta imports can block taxonomy FK repair when PRAGMA index_xinfo shows a UNIQUE parent key uses BINARY for a parent column declared COLLATE NOCASE.',
    'status' => $page['status'],
    'current_parent_collation_mismatches' => $page['current']['foreign_key_rejected_parent_collations']['mismatch'],
    'next_parent_collation_mismatches' => $page['next_counts']['foreign_key_rejected_parent_collations']['mismatch'],
    'parent_collation_repaired' => $page['delta']['foreign_key_rejected_parent_collation_repaired'],
    'next_ready' => $page['next_state']['ready'],
    'pragmas' => [
        'PRAGMA index_xinfo(wp_terms_slug_taxonomy_unique)',
        'PRAGMA foreign_key_list(wp_termmeta)',
        'PRAGMA foreign_key_check',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_parent_collation_mismatches'] !== 1
        || $summary['next_parent_collation_mismatches'] !== 0
        || $summary['parent_collation_repaired'] !== true
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next192 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next192 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
