<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, guid TEXT COLLATE NOCASE NOT NULL)', 1),
    $record('index', 'wp_posts_guid_unique', 'wp_posts', 3, 'CREATE UNIQUE INDEX wp_posts_guid_unique ON wp_posts(guid COLLATE NOCASE)', 2),
    $record('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(site_id INTEGER NOT NULL, slug TEXT COLLATE NOCASE NOT NULL, PRIMARY KEY(site_id, slug))', 3),
    $record('table', 'wp_comment_import', 'wp_comment_import', 5, "CREATE TABLE wp_comment_import(
        comment_id INTEGER PRIMARY KEY,
        owner_id INTEGER REFERENCES wp_posts ON UPDATE CASCADE,
        term_site INTEGER NOT NULL,
        term_slug TEXT COLLATE NOCASE NOT NULL,
        explicit_guid TEXT COLLATE NOCASE REFERENCES wp_posts(guid),
        FOREIGN KEY(term_site, term_slug) REFERENCES wp_terms ON DELETE CASCADE
    )", 4),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('table', 'wp_comment_import', 'wp_comment_import', 5, "CREATE TABLE wp_comment_import(
        comment_id INTEGER PRIMARY KEY,
        owner_id INTEGER REFERENCES wp_posts(ID) ON UPDATE CASCADE,
        term_site INTEGER NOT NULL,
        term_slug TEXT COLLATE NOCASE NOT NULL,
        explicit_guid TEXT COLLATE NOCASE REFERENCES wp_posts(guid),
        FOREIGN KEY(term_site, term_slug) REFERENCES wp_terms(site_id, slug) ON DELETE CASCADE
    )", 4),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241::page(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_posts_guid_unique)',
    'PRAGMA main.foreign_key_list(wp_comment_import)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next241',
    'wordpressUse' => 'Copied WordPress import schemas can compare raw PRAGMA foreign_key_list NULL parent columns with current-source primary-key resolution before emitting explicit FK DDL.',
    'status' => $page['status'],
    'current_implicit_parent_references' => $page['current']['foreign_key_implicit_parent_references']['implicit'],
    'next_implicit_parent_references' => $page['next_counts']['foreign_key_implicit_parent_references']['implicit'],
    'current_resolved_parent_columns' => $page['current']['foreign_key_implicit_parent_references']['resolved'],
    'implicit_parent_reference_changed' => $page['delta']['foreign_key_implicit_parent_reference_changed'],
    'source' => $page['current_source']['foreign_key_implicit_parent_reference_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_implicit_parent_references'] !== 3
        || $summary['next_implicit_parent_references'] !== 0
        || $summary['current_resolved_parent_columns'] !== 4
        || $summary['implicit_parent_reference_changed'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next241 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next241 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
