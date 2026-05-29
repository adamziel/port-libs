<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_postmeta_279', 'wp_postmeta_279', 2, 'CREATE TABLE wp_postmeta_279(post_id INTEGER REFERENCES wp_posts_279(ID))', 1),
    $record('table', 'wp_terms_280', 'wp_terms_280', 3, 'CREATE TABLE wp_terms_280(term_id INTEGER PRIMARY KEY)', 2),
    $record('table', 'wp_termmeta_280', 'wp_termmeta_280', 4, 'CREATE TABLE wp_termmeta_280(term_code TEXT REFERENCES wp_terms_280(slug))', 3),
    $record('table', 'wp_users_281', 'wp_users_281', 5, 'CREATE TABLE wp_users_281(user_login TEXT)', 4),
    $record('table', 'wp_usermeta_281', 'wp_usermeta_281', 6, 'CREATE TABLE wp_usermeta_281(user_login TEXT REFERENCES wp_users_281(user_login))', 5),
    $record('table', 'wp_options_282', 'wp_options_282', 7, 'CREATE TABLE wp_options_282(option_name TEXT)', 6),
    $record('index', 'wp_options_282_name_nocase', 'wp_options_282', 8, 'CREATE UNIQUE INDEX wp_options_282_name_nocase ON wp_options_282(option_name COLLATE NOCASE)', 7),
    $record('table', 'wp_blog_options_282', 'wp_blog_options_282', 9, 'CREATE TABLE wp_blog_options_282(option_name TEXT REFERENCES wp_options_282(option_name))', 8),
    $record('table', 'wp_posts_283', 'wp_posts_283', 10, 'CREATE TABLE wp_posts_283(post_name TEXT)', 9),
    $record('index', 'wp_posts_283_slug_partial', 'wp_posts_283', 11, 'CREATE UNIQUE INDEX wp_posts_283_slug_partial ON wp_posts_283(post_name) WHERE post_name IS NOT NULL', 10),
    $record('table', 'wp_links_283', 'wp_links_283', 12, 'CREATE TABLE wp_links_283(post_name TEXT REFERENCES wp_posts_283(post_name))', 11),
    $record('table', 'wp_terms_284', 'wp_terms_284', 13, 'CREATE TABLE wp_terms_284(slug TEXT)', 12),
    $record('index', 'wp_terms_284_lower_slug', 'wp_terms_284', 14, 'CREATE UNIQUE INDEX wp_terms_284_lower_slug ON wp_terms_284(lower(slug))', 13),
    $record('table', 'wp_term_taxonomy_284', 'wp_term_taxonomy_284', 15, 'CREATE TABLE wp_term_taxonomy_284(slug TEXT REFERENCES wp_terms_284(slug))', 14),
    $record('table', 'wp_posts_285', 'wp_posts_285', 16, 'CREATE TABLE wp_posts_285(ID INTEGER PRIMARY KEY)', 15),
    $record('table', 'wp_comments_285', 'wp_comments_285', 17, 'CREATE TABLE wp_comments_285(comment_post_ID INTEGER REFERENCES wp_posts_285(rowid))', 16),
    $record('table', 'wp_multisite_286', 'wp_multisite_286', 18, 'CREATE TABLE wp_multisite_286(site_id INTEGER, blog_id INTEGER)', 17),
    $record('index', 'wp_multisite_286_blog_site', 'wp_multisite_286', 19, 'CREATE UNIQUE INDEX wp_multisite_286_blog_site ON wp_multisite_286(blog_id, site_id)', 18),
    $record('table', 'wp_options_286', 'wp_options_286', 20, 'CREATE TABLE wp_options_286(site_id INTEGER, blog_id INTEGER, FOREIGN KEY(site_id, blog_id) REFERENCES wp_multisite_286(site_id, blog_id))', 19),
];

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next279-286',
    'wordpressUse' => 'WordPress-style plugin schemas can preflight copied foreign keys against PRAGMA foreign_key_list, table_info, index_list, and index_xinfo before activating constraints.',
    'next279_missing_parent_table' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'missing_parent_table')),
    'next280_missing_parent_column' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'missing_parent_column')),
    'next281_no_unique_parent_key' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'no_unique_parent_key')),
    'next282_collation_mismatch_parent_key' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'collation_mismatch_parent_key')),
    'next283_partial_unique_parent_key' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'partial_unique_parent_key')),
    'next284_expression_unique_parent_key' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'expression_unique_parent_key')),
    'next285_implicit_rowid_parent_key' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'implicit_rowid_parent_key')),
    'next286_composite_parent_key_order_mismatch' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyDiagnosticRows279($records, 'next', 'composite_parent_key_order_mismatch')),
    'implemented_pages' => array_values(array_filter(
        range(279, 286),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'next279_missing_parent_table',
        'next280_missing_parent_column',
        'next281_no_unique_parent_key',
        'next282_collation_mismatch_parent_key',
        'next283_partial_unique_parent_key',
        'next284_expression_unique_parent_key',
        'next285_implicit_rowid_parent_key',
        'next286_composite_parent_key_order_mismatch',
    ];
    foreach ($expected as $key) {
        if ($summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next279-286 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== [279, 280, 281, 282, 283, 284, 285, 286]) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next279-286 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next279-286 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
