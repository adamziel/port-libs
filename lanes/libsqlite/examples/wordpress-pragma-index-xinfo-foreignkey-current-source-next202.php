<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_blogs', 'wp_blogs', 2, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_blogs_1', 'wp_blogs', 3, null, 2),
    $record('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)', 3),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 5, null, 4),
    $record('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 6, "CREATE TABLE wp_term_taxonomy(
        taxonomy_id INTEGER PRIMARY KEY,
        blog_id INTEGER REFERENCES wp_blogs(blog_id) ON UPDATE CASCADE ON DELETE CASCADE,
        term_slug TEXT REFERENCES wp_terms(slug) ON DELETE SET NULL,
        taxonomy TEXT DEFAULT 'category'
    )", 5),
    $record('index', 'wp_taxonomy_lookup', 'wp_term_taxonomy', 7, 'CREATE INDEX wp_taxonomy_lookup ON wp_term_taxonomy(lower(term_slug) COLLATE nocase, blog_id DESC)', 6),
];

$next = [
    $current[0],
    $current[1],
    $record('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT, locale TEXT DEFAULT \'en_US\', UNIQUE(slug, locale))', 3),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 5, null, 4),
    $record('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 6, "CREATE TABLE wp_term_taxonomy(
        taxonomy_id INTEGER PRIMARY KEY,
        blog_id INTEGER REFERENCES wp_blogs(blog_id) ON UPDATE CASCADE ON DELETE CASCADE,
        term_slug TEXT,
        locale TEXT DEFAULT 'en_US',
        taxonomy TEXT DEFAULT 'category',
        FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale) ON UPDATE CASCADE ON DELETE SET NULL MATCH SIMPLE
    )", 5),
    $record('index', 'wp_taxonomy_lookup', 'wp_term_taxonomy', 7, 'CREATE INDEX wp_taxonomy_lookup ON wp_term_taxonomy(lower(term_slug) COLLATE nocase, locale COLLATE rtrim, blog_id DESC)', 6),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page202(
    $current,
    $next,
    "pragma_index_xinfo('wp_taxonomy_lookup','main')",
    "pragma_foreign_key_list('wp_term_taxonomy','main')",
    0,
    9,
    tableValuedIndexXinfo: true,
    tableValuedForeignKeyList: true,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_list'] !== 2
        || $page['next_counts']['foreign_key_list'] !== 3
        || $page['current']['table_valued_foreign_key_rows'] !== 2
        || $page['next_counts']['table_valued_foreign_key_rows'] !== 3
        || $page['delta']['total'] !== 2
        || ($page['next']['offset'] ?? null) !== 9
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next202 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next202 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next202',
    'wordpressUse' => 'When a copied taxonomy schema is reparsed through SQLite table-valued PRAGMA functions, page index_xinfo and foreign_key_list from the same current-source snapshot before applying the migrated composite term key.',
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
    'first_rows' => $page['rows'],
    'next' => $page['next'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
