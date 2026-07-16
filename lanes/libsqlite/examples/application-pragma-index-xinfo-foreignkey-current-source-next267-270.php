<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_posts_267', 'wp_posts_267', 2, 'CREATE TABLE wp_posts_267(ID INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_postmeta_267', 'wp_postmeta_267', 3, 'CREATE TABLE wp_postmeta_267(post_id INTEGER REFERENCES wp_posts_267(ID) ON DELETE RESTRICT)', 2),
    $record('table', 'wp_terms_268', 'wp_terms_268', 4, 'CREATE TABLE wp_terms_268(term_id INTEGER PRIMARY KEY)', 3),
    $record('table', 'wp_termmeta_268', 'wp_termmeta_268', 5, 'CREATE TABLE wp_termmeta_268(term_id INTEGER REFERENCES wp_terms_268(term_id) ON UPDATE CASCADE)', 4),
    $record('table', 'wp_users_269', 'wp_users_269', 6, 'CREATE TABLE wp_users_269(ID INTEGER PRIMARY KEY)', 5),
    $record('table', 'wp_comments_269', 'wp_comments_269', 7, 'CREATE TABLE wp_comments_269(user_id INTEGER REFERENCES wp_users_269(ID) ON DELETE SET NULL)', 6),
    $record('table', 'wp_sites_270', 'wp_sites_270', 8, 'CREATE TABLE wp_sites_270(blog_id INTEGER PRIMARY KEY)', 7),
    $record('table', 'wp_options_270', 'wp_options_270', 9, 'CREATE TABLE wp_options_270(blog_id INTEGER DEFAULT 1 REFERENCES wp_sites_270(blog_id) ON UPDATE SET DEFAULT)', 8),
];

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next267-270',
    'applicationUse' => 'Copied Application-style relational tables can flag action-bearing foreign keys that would force child scans because no child lookup index has the FK columns as a PRAGMA index_xinfo key prefix.',
    'next267_restrict' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionChildLookupRows267($records, 'next', 'restrict_without_child_lookup_index')),
    'next268_cascade' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionChildLookupRows267($records, 'next', 'cascade_without_child_lookup_index')),
    'next269_set_null' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionChildLookupRows267($records, 'next', 'set_null_without_child_lookup_index')),
    'next270_set_default' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionChildLookupRows267($records, 'next', 'set_default_without_child_lookup_index')),
    'implemented_pages' => array_values(array_filter(
        range(267, 270),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['next267_restrict'] !== 1
        || $summary['next268_cascade'] !== 1
        || $summary['next269_set_null'] !== 1
        || $summary['next270_set_default'] !== 1
        || $summary['implemented_pages'] !== [267, 268, 269, 270]
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next267-270 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next267-270 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
