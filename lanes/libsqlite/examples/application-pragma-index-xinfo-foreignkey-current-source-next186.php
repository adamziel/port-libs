<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM)', 1),
    $record('table', 'wp_posts', 'wp_posts', 5, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT COLLATE NOCASE, post_type TEXT COLLATE RTRIM)', 2),
    $record('table', 'wp_term_relationships', 'wp_term_relationships', 6, 'CREATE TABLE wp_term_relationships(object_id INTEGER, term_slug TEXT COLLATE NOCASE, term_taxonomy TEXT COLLATE RTRIM, post_slug TEXT COLLATE NOCASE, post_type TEXT COLLATE RTRIM, missing_slug TEXT COLLATE NOCASE, FOREIGN KEY(term_slug, term_taxonomy) REFERENCES wp_terms(slug, taxonomy), FOREIGN KEY(post_slug, post_type) REFERENCES wp_posts(post_name, post_type), FOREIGN KEY(missing_slug) REFERENCES wp_terms(slug))', 3),
    $record('index', 'wp_terms_slug_taxonomy_key', 'wp_terms', 7, 'CREATE UNIQUE INDEX wp_terms_slug_taxonomy_key ON wp_terms(slug COLLATE NOCASE, taxonomy COLLATE RTRIM)', 4),
    $record('index', 'wp_posts_slug_type_key', 'wp_posts', 8, 'CREATE UNIQUE INDEX wp_posts_slug_type_key ON wp_posts(post_name COLLATE NOCASE, post_type COLLATE RTRIM)', 5),
    $record('index', 'wp_terms_slug_key', 'wp_terms', 9, 'CREATE UNIQUE INDEX wp_terms_slug_key ON wp_terms(slug COLLATE NOCASE)', 6),
    $record('index', 'wp_term_relationships_term_lookup_bad', 'wp_term_relationships', 10, 'CREATE INDEX wp_term_relationships_term_lookup_bad ON wp_term_relationships(term_slug, term_taxonomy)', 7),
    $record('index', 'wp_term_relationships_post_lookup_bad', 'wp_term_relationships', 11, 'CREATE INDEX wp_term_relationships_post_lookup_bad ON wp_term_relationships(post_slug COLLATE NOCASE, post_type)', 8),
];
$nextRecords = [
    $records[0],
    $records[1],
    $records[2],
    $records[3],
    $records[4],
    $records[5],
    $record('index', 'wp_term_relationships_term_lookup', 'wp_term_relationships', 12, 'CREATE INDEX wp_term_relationships_term_lookup ON wp_term_relationships(term_slug COLLATE NOCASE, term_taxonomy COLLATE RTRIM)', 9),
    $record('index', 'wp_term_relationships_post_lookup', 'wp_term_relationships', 13, 'CREATE INDEX wp_term_relationships_post_lookup ON wp_term_relationships(post_slug COLLATE NOCASE, post_type COLLATE RTRIM)', 10),
    $record('index', 'wp_term_relationships_missing_lookup', 'wp_term_relationships', 14, 'CREATE INDEX wp_term_relationships_missing_lookup ON wp_term_relationships(missing_slug COLLATE NOCASE)', 11),
];
$tables = [
    'wp_terms' => [['rowid' => 1, 'term_id' => 1, 'slug' => 'news', 'taxonomy' => 'category']],
    'wp_posts' => [['rowid' => 1, 'ID' => 1, 'post_name' => 'hello-world', 'post_type' => 'post']],
    'wp_term_relationships' => [['rowid' => 1, 'object_id' => 1, 'term_slug' => 'news', 'term_taxonomy' => 'category', 'post_slug' => 'hello-world', 'post_type' => 'post', 'missing_slug' => 'news']],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog186(
    $records,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_term_relationships_term_lookup)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_child_collations']['mismatch'] !== 3
        || $page['next_counts']['foreign_key_child_collations']['mismatch'] !== 0
        || $page['delta']['foreign_key_child_collation_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next186 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next186 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next186',
    'applicationUse' => 'Surface child-key index collation mismatches beside PRAGMA index_xinfo and foreign_key_list rows before a Application taxonomy/post import relies on those indexes.',
    'status' => $page['status'],
    'current_child_collations' => $page['current']['foreign_key_child_collations'],
    'next_child_collations' => $page['next_counts']['foreign_key_child_collations'],
    'delta' => $page['delta'],
    'child_collation_rows' => array_values(array_filter($page['rows'], static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_child_collation')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
