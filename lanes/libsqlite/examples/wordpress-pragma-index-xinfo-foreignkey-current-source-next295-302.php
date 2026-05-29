<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_terms_295', 'wp_terms_295', 2, 'CREATE TABLE wp_terms_295(term_id INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_termmeta_295', 'wp_termmeta_295', 3, 'CREATE TABLE wp_termmeta_295(term_id TEXT NOT NULL DEFAULT \'0\' REFERENCES wp_terms_295(term_id))', 2),
    $record('index', 'wp_termmeta_295_term_id', 'wp_termmeta_295', 4, 'CREATE INDEX wp_termmeta_295_term_id ON wp_termmeta_295(term_id)', 3),
    $record('table', 'wp_options_296', 'wp_options_296', 5, 'CREATE TABLE wp_options_296(option_name TEXT COLLATE BINARY PRIMARY KEY)', 4),
    $record('table', 'wp_blog_options_296', 'wp_blog_options_296', 6, 'CREATE TABLE wp_blog_options_296(option_name TEXT COLLATE NOCASE NOT NULL DEFAULT \'\' REFERENCES wp_options_296(option_name))', 5),
    $record('index', 'wp_blog_options_296_name', 'wp_blog_options_296', 7, 'CREATE INDEX wp_blog_options_296_name ON wp_blog_options_296(option_name)', 6),
    $record('table', 'wp_multisite_297', 'wp_multisite_297', 8, 'CREATE TABLE wp_multisite_297(site_id INTEGER, blog_id INTEGER, PRIMARY KEY(site_id, blog_id))', 7),
    $record('table', 'wp_sitemeta_297', 'wp_sitemeta_297', 9, 'CREATE TABLE wp_sitemeta_297(site_id INTEGER NOT NULL DEFAULT 0, blog_id INTEGER, FOREIGN KEY(site_id, blog_id) REFERENCES wp_multisite_297(site_id, blog_id))', 8),
    $record('index', 'wp_sitemeta_297_site_blog', 'wp_sitemeta_297', 10, 'CREATE INDEX wp_sitemeta_297_site_blog ON wp_sitemeta_297(site_id, blog_id)', 9),
    $record('table', 'wp_comments_298', 'wp_comments_298', 11, 'CREATE TABLE wp_comments_298(comment_ID INTEGER PRIMARY KEY, comment_parent INTEGER NOT NULL DEFAULT 0 REFERENCES wp_comments_298(comment_ID))', 10),
    $record('index', 'wp_comments_298_parent', 'wp_comments_298', 12, 'CREATE INDEX wp_comments_298_parent ON wp_comments_298(comment_parent)', 11),
    $record('table', 'wp_terms_299', 'wp_terms_299', 13, 'CREATE TABLE wp_terms_299(term_id INTEGER PRIMARY KEY, parent INTEGER NOT NULL DEFAULT 0 REFERENCES wp_terms_299(term_id) ON DELETE CASCADE)', 12),
    $record('index', 'wp_terms_299_parent', 'wp_terms_299', 14, 'CREATE INDEX wp_terms_299_parent ON wp_terms_299(parent)', 13),
    $record('table', 'wp_posts_300', 'wp_posts_300', 15, 'CREATE TABLE wp_posts_300(ID INTEGER PRIMARY KEY)', 14),
    $record('table', 'wp_comments_300', 'wp_comments_300', 16, 'CREATE TABLE wp_comments_300(comment_post_ID INTEGER NOT NULL DEFAULT 0 REFERENCES wp_posts_300(ID) ON DELETE RESTRICT)', 15),
    $record('table', 'wp_users_301', 'wp_users_301', 17, 'CREATE TABLE wp_users_301(ID INTEGER PRIMARY KEY)', 16),
    $record('table', 'wp_usermeta_301', 'wp_usermeta_301', 18, 'CREATE TABLE wp_usermeta_301(user_id INTEGER NOT NULL DEFAULT 0 REFERENCES wp_users_301(ID))', 17),
    $record('table', 'wp_blogs_302', 'wp_blogs_302', 19, 'CREATE TABLE wp_blogs_302(blog_id INTEGER PRIMARY KEY)', 18),
    $record('table', 'wp_blogmeta_302', 'wp_blogmeta_302', 20, 'CREATE TABLE wp_blogmeta_302(blog_id INTEGER NOT NULL DEFAULT 0 REFERENCES wp_blogs_302(blog_id) DEFERRABLE INITIALLY DEFERRED)', 19),
    $record('index', 'wp_blogmeta_302_blog_id', 'wp_blogmeta_302', 21, 'CREATE INDEX wp_blogmeta_302_blog_id ON wp_blogmeta_302(blog_id)', 20),
];

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next295-302',
    'wordpressUse' => 'WordPress-style schema upgrades can preflight FK relationship metadata that is visible through PRAGMA foreign_key_list, table_info, index_list, and index_xinfo before enabling constraints.',
    'next295_child_parent_affinity_mismatch' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'child_parent_affinity_mismatch')),
    'next296_child_parent_collation_mismatch' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'child_parent_collation_mismatch')),
    'next297_composite_child_nullable_partial_key' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'composite_child_nullable_partial_key')),
    'next298_self_referential_foreign_key' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'self_referential_foreign_key')),
    'next299_cascading_self_reference' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'cascading_self_reference')),
    'next300_restrict_without_child_lookup_index' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'restrict_without_child_lookup_index')),
    'next301_no_action_without_child_lookup_index' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'no_action_without_child_lookup_index')),
    'next302_deferrable_foreign_key_clause' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'deferrable_foreign_key_clause')),
    'implemented_pages' => array_values(array_filter(
        range(295, 302),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next295-302 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== [295, 296, 297, 298, 299, 300, 301, 302]) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next295-302 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next295-302 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
