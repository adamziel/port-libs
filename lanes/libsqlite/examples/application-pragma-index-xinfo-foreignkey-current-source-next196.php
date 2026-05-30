<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT UNIQUE)', 3),
    $record('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 5, null, 4),
    $record('table', 'wp_term_relationships', 'wp_term_relationships', 6, "CREATE TABLE wp_term_relationships(
        object_id INTEGER NOT NULL REFERENCES wp_posts(ID) ON DELETE CASCADE,
        term_taxonomy_id INTEGER NOT NULL,
        term_slug TEXT,
        locale TEXT DEFAULT 'en_US',
        FOREIGN KEY(term_slug) REFERENCES wp_terms(slug) ON DELETE SET NULL ON UPDATE CASCADE
    )", 5),
    $record('index', 'wp_tr_lookup', 'wp_term_relationships', 7, "CREATE INDEX wp_tr_lookup ON wp_term_relationships(lower(term_slug) COLLATE nocase, object_id DESC)", 6),
];

$next = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE, locale TEXT DEFAULT \'en_US\', UNIQUE(slug, locale))', 1),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record('index', 'sqlite_autoindex_wp_terms_2', 'wp_terms', 8, null, 3),
    $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT UNIQUE)', 4),
    $record('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 5, null, 5),
    $record('table', 'wp_term_relationships', 'wp_term_relationships', 6, "CREATE TABLE wp_term_relationships(
        object_id INTEGER NOT NULL REFERENCES wp_posts(ID) ON DELETE CASCADE,
        term_taxonomy_id INTEGER NOT NULL,
        term_slug TEXT,
        locale TEXT DEFAULT 'en_US',
        FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale) ON DELETE SET NULL ON UPDATE CASCADE MATCH SIMPLE
    )", 6),
    $record('index', 'wp_tr_lookup', 'wp_term_relationships', 7, "CREATE INDEX wp_tr_lookup ON wp_term_relationships(lower(term_slug) COLLATE nocase, locale COLLATE rtrim, object_id DESC)", 7),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page196(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_tr_lookup)',
    'PRAGMA main.foreign_key_list(wp_term_relationships)',
    0,
    8,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['index_xinfo'] !== 3
        || $page['next_counts']['index_xinfo'] !== 4
        || $page['current']['foreign_key_list'] !== 2
        || $page['next_counts']['foreign_key_list'] !== 3
        || $page['delta']['total'] !== 2
        || ($page['next']['offset'] ?? null) !== 8
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next196 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next196 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next196',
    'applicationUse' => 'Before applying a copied taxonomy schema reparse, page the current and next PRAGMA index_xinfo plus foreign_key_list rows together so expression-index metadata and composite foreign-key metadata advance from the same catalog snapshot.',
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
    'first_rows' => $page['rows'],
    'next' => $page['next'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
