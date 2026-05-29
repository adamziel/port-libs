<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, locale TEXT NOT NULL, UNIQUE(term_id, locale))', 1),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record('table', 'wp_postmeta_import', 'wp_postmeta_import', 4, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        locale TEXT NOT NULL,
        parent_term INTEGER,
        FOREIGN KEY(term_id, locale) REFERENCES wp_terms(term_id, locale) MATCH FULL ON UPDATE CASCADE,
        FOREIGN KEY(parent_term) REFERENCES wp_terms(term_id) MATCH SIMPLE ON DELETE SET NULL
    )", 3),
    $record('index', 'wp_postmeta_import_term_locale', 'wp_postmeta_import', 5, 'CREATE INDEX wp_postmeta_import_term_locale ON wp_postmeta_import(term_id, locale)', 4),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $record('table', 'wp_postmeta_import', 'wp_postmeta_import', 4, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        locale TEXT NOT NULL,
        parent_term INTEGER,
        FOREIGN KEY(term_id, locale) REFERENCES wp_terms(term_id, locale) ON UPDATE CASCADE,
        FOREIGN KEY(parent_term) REFERENCES wp_terms(term_id) ON DELETE SET NULL
    )", 3),
    $currentRecords[3],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page223(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_postmeta_import_term_locale)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next223',
    'wordpressUse' => 'Copied WordPress import schemas can surface custom MATCH names from PRAGMA foreign_key_list before assuming SQLite will enforce alternate MATCH FULL/PARTIAL semantics.',
    'status' => $page['status'],
    'current_match_rows' => $page['current']['foreign_key_match_clause']['rows'],
    'current_custom_match_rows' => $page['current']['foreign_key_match_clause']['custom_match'],
    'next_custom_match_rows' => $page['next_counts']['foreign_key_match_clause']['custom_match'],
    'match_clause_repaired' => $page['delta']['foreign_key_match_clause_repaired'],
    'source' => $page['current_source']['foreign_key_match_clause_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_match_rows'] !== 3
        || $summary['current_custom_match_rows'] !== 2
        || $summary['next_custom_match_rows'] !== 0
        || $summary['match_clause_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next223 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next223 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
