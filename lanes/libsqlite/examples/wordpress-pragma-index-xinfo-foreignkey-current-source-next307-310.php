<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_posts_307', 'wp_posts_307', 2, 'CREATE TABLE wp_posts_307(ID INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_comments_307', 'wp_comments_307', 3, 'CREATE TABLE wp_comments_307(comment_post_ID INTEGER NOT NULL REFERENCES wp_posts_307(ID) ON DELETE SET NULL)', 2),
    $record('index', 'wp_comments_307_post', 'wp_comments_307', 4, 'CREATE INDEX wp_comments_307_post ON wp_comments_307(comment_post_ID)', 3),
    $record('table', 'wp_users_308', 'wp_users_308', 5, 'CREATE TABLE wp_users_308(ID INTEGER PRIMARY KEY)', 4),
    $record('table', 'wp_usermeta_308', 'wp_usermeta_308', 6, 'CREATE TABLE wp_usermeta_308(user_id INTEGER, FOREIGN KEY(user_id) REFERENCES wp_users_308(ID) ON UPDATE SET DEFAULT)', 5),
    $record('index', 'wp_usermeta_308_user', 'wp_usermeta_308', 7, 'CREATE INDEX wp_usermeta_308_user ON wp_usermeta_308(user_id)', 6),
    $record('table', 'wp_terms_309', 'wp_terms_309', 8, 'CREATE TABLE wp_terms_309(term_id INTEGER PRIMARY KEY)', 7),
    $record('table', 'wp_termmeta_309', 'wp_termmeta_309', 9, 'CREATE TABLE wp_termmeta_309(term_id INTEGER NOT NULL DEFAULT 0 REFERENCES wp_terms_309(term_id) ON DELETE CASCADE)', 8),
    $record('table', 'wp_blogs_310', 'wp_blogs_310', 10, 'CREATE TABLE wp_blogs_310(blog_id INTEGER PRIMARY KEY)', 9),
    $record('table', 'wp_blogmeta_310', 'wp_blogmeta_310', 11, 'CREATE TABLE wp_blogmeta_310(blog_id INTEGER NOT NULL DEFAULT 0 REFERENCES wp_blogs_310(blog_id) ON DELETE SET DEFAULT)', 10),
];

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next307-310',
    'wordpressUse' => 'WordPress import previews can catch FK action combinations that PRAGMA foreign_key_list exposes but only become actionable when table_info and index_xinfo show child nullability, defaults, and lookup coverage.',
    'next307_set_null_notnull_child_column' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'set_null_notnull_child_column')),
    'next308_set_default_missing_child_default' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'set_default_missing_child_default')),
    'next309_cascade_without_child_lookup_index' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'cascade_without_child_lookup_index')),
    'next310_set_default_without_child_lookup_index' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::relationshipDiagnosticRows295($records, 'next', 'set_default_without_child_lookup_index')),
    'implemented_pages' => array_values(array_filter(
        range(307, 310),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next307-310 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== [307, 308, 309, 310]) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next307-310 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next307-310 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
