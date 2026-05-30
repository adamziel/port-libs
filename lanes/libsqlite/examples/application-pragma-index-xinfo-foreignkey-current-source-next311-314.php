<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_posts_311', 'wp_posts_311', 2, 'CREATE TABLE wp_posts_311(ID INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_comments_311', 'wp_comments_311', 3, 'CREATE TABLE wp_comments_311(comment_post_ID INTEGER NOT NULL REFERENCES wp_posts_311(ID) ON UPDATE SET NULL)', 2),
    $record('index', 'wp_comments_311_post', 'wp_comments_311', 4, 'CREATE INDEX wp_comments_311_post ON wp_comments_311(comment_post_ID)', 3),
    $record('table', 'wp_posts_312', 'wp_posts_312', 5, 'CREATE TABLE wp_posts_312(ID INTEGER PRIMARY KEY)', 4),
    $record('table', 'wp_comments_312', 'wp_comments_312', 6, 'CREATE TABLE wp_comments_312(comment_post_ID INTEGER NOT NULL REFERENCES wp_posts_312(ID) ON DELETE SET NULL)', 5),
    $record('index', 'wp_comments_312_post', 'wp_comments_312', 7, 'CREATE INDEX wp_comments_312_post ON wp_comments_312(comment_post_ID)', 6),
    $record('table', 'wp_users_313', 'wp_users_313', 8, 'CREATE TABLE wp_users_313(ID INTEGER PRIMARY KEY)', 7),
    $record('table', 'wp_usermeta_313', 'wp_usermeta_313', 9, 'CREATE TABLE wp_usermeta_313(user_id INTEGER, FOREIGN KEY(user_id) REFERENCES wp_users_313(ID) ON UPDATE SET DEFAULT)', 8),
    $record('index', 'wp_usermeta_313_user', 'wp_usermeta_313', 10, 'CREATE INDEX wp_usermeta_313_user ON wp_usermeta_313(user_id)', 9),
    $record('table', 'wp_blogs_314', 'wp_blogs_314', 11, 'CREATE TABLE wp_blogs_314(blog_id INTEGER PRIMARY KEY)', 10),
    $record('table', 'wp_blogmeta_314', 'wp_blogmeta_314', 12, 'CREATE TABLE wp_blogmeta_314(blog_id INTEGER, FOREIGN KEY(blog_id) REFERENCES wp_blogs_314(blog_id) ON DELETE SET DEFAULT)', 11),
    $record('index', 'wp_blogmeta_314_blog', 'wp_blogmeta_314', 13, 'CREATE INDEX wp_blogmeta_314_blog ON wp_blogmeta_314(blog_id)', 12),
];

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next311-314',
    'applicationUse' => 'Application import previews can distinguish ON UPDATE from ON DELETE SET NULL and SET DEFAULT relationship blockers before trusting PRAGMA foreign_key_list replay.',
    'next311_update_set_null_notnull_child_column' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_set_null_notnull_child_column')),
    'next312_delete_set_null_notnull_child_column' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_set_null_notnull_child_column')),
    'next313_update_set_default_missing_child_default' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_set_default_missing_child_default')),
    'next314_delete_set_default_missing_child_default' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_set_default_missing_child_default')),
    'implemented_pages' => array_values(array_filter(
        range(311, 314),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next311-314 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== [311, 312, 313, 314]) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next311-314 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next311-314 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
