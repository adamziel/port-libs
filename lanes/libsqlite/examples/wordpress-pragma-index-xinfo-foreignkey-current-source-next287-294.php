<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_posts_287', 'wp_posts_287', 2, 'CREATE TABLE wp_posts_287(ID INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_postmeta_287', 'wp_postmeta_287', 3, 'CREATE TABLE wp_postmeta_287(post_id INTEGER, FOREIGN KEY(missing_post_id) REFERENCES wp_posts_287(ID))', 2),
    $record('table', 'wp_terms_288', 'wp_terms_288', 4, 'CREATE TABLE wp_terms_288(term_id INTEGER PRIMARY KEY)', 3),
    $record('table', 'wp_termmeta_288', 'wp_termmeta_288', 5, 'CREATE TABLE wp_termmeta_288(raw_id INTEGER, term_id INTEGER GENERATED ALWAYS AS (raw_id) VIRTUAL, FOREIGN KEY(term_id) REFERENCES wp_terms_288(term_id))', 4),
    $record('table', 'wp_users_289', 'wp_users_289', 6, 'CREATE TABLE wp_users_289(ID INTEGER PRIMARY KEY)', 5),
    $record('table', 'wp_usermeta_289', 'wp_usermeta_289', 7, 'CREATE TABLE wp_usermeta_289(user_id INTEGER, FOREIGN KEY(user_id) REFERENCES wp_users_289(ID) ON DELETE SET NULL)', 6),
    $record('table', 'wp_posts_290', 'wp_posts_290', 8, 'CREATE TABLE wp_posts_290(ID INTEGER PRIMARY KEY)', 7),
    $record('table', 'wp_comments_290', 'wp_comments_290', 9, 'CREATE TABLE wp_comments_290(comment_post_ID INTEGER NOT NULL, FOREIGN KEY(comment_post_ID) REFERENCES wp_posts_290(ID) ON DELETE SET DEFAULT)', 8),
    $record('table', 'wp_posts_291', 'wp_posts_291', 10, 'CREATE TABLE wp_posts_291(ID INTEGER PRIMARY KEY)', 9),
    $record('table', 'wp_postmeta_291', 'wp_postmeta_291', 11, 'CREATE TABLE wp_postmeta_291(post_id INTEGER NOT NULL DEFAULT 0 REFERENCES wp_posts_291(ID))', 10),
    $record('table', 'wp_terms_292', 'wp_terms_292', 12, 'CREATE TABLE wp_terms_292(term_id INTEGER PRIMARY KEY)', 11),
    $record('table', 'wp_termmeta_292', 'wp_termmeta_292', 13, 'CREATE TABLE wp_termmeta_292(term_id INTEGER NOT NULL DEFAULT 0 REFERENCES wp_terms_292(term_id))', 12),
    $record('index', 'wp_termmeta_292_partial', 'wp_termmeta_292', 14, 'CREATE INDEX wp_termmeta_292_partial ON wp_termmeta_292(term_id) WHERE term_id > 0', 13),
    $record('table', 'wp_posts_293', 'wp_posts_293', 15, 'CREATE TABLE wp_posts_293(ID INTEGER PRIMARY KEY)', 14),
    $record('table', 'wp_postmeta_293', 'wp_postmeta_293', 16, 'CREATE TABLE wp_postmeta_293(post_id INTEGER NOT NULL DEFAULT 0 REFERENCES wp_posts_293(ID))', 15),
    $record('index', 'wp_postmeta_293_expr', 'wp_postmeta_293', 17, 'CREATE INDEX wp_postmeta_293_expr ON wp_postmeta_293(abs(post_id))', 16),
    $record('table', 'wp_multisite_294', 'wp_multisite_294', 18, 'CREATE TABLE wp_multisite_294(site_id INTEGER, blog_id INTEGER, PRIMARY KEY(site_id, blog_id))', 17),
    $record('table', 'wp_options_294', 'wp_options_294', 19, 'CREATE TABLE wp_options_294(site_id INTEGER NOT NULL DEFAULT 0, blog_id INTEGER NOT NULL DEFAULT 0, FOREIGN KEY(site_id, blog_id) REFERENCES wp_multisite_294(site_id, blog_id))', 18),
    $record('index', 'wp_options_294_blog_site', 'wp_options_294', 20, 'CREATE INDEX wp_options_294_blog_site ON wp_options_294(blog_id, site_id)', 19),
];

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next287-294',
    'wordpressUse' => 'Copied WordPress plugin schemas can page child-side PRAGMA foreign_key_list/table_info/index_xinfo diagnostics before enabling FK enforcement.',
    'next287_missing_child_column' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'missing_child_column')),
    'next288_generated_child_column' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'generated_child_column')),
    'next289_nullable_child_column' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'nullable_child_column')),
    'next290_missing_child_default' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'missing_child_default')),
    'next291_child_lookup_missing_index' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_missing_index')),
    'next292_child_lookup_partial_index' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_partial_index')),
    'next293_child_lookup_expression_index' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_expression_index')),
    'next294_child_lookup_order_mismatch' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_order_mismatch')),
    'implemented_pages' => array_values(array_filter(
        range(287, 294),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next287-294 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== [287, 288, 289, 290, 291, 292, 293, 294]) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next287-294 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next287-294 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
