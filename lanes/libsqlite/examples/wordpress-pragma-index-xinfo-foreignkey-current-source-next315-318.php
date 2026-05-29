<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_posts_315', 'wp_posts_315', 2, 'CREATE TABLE wp_posts_315(ID INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_comments_315', 'wp_comments_315', 3, 'CREATE TABLE wp_comments_315(comment_post_ID INTEGER NOT NULL DEFAULT NULL REFERENCES wp_posts_315(ID) ON UPDATE SET DEFAULT)', 2),
    $record('index', 'wp_comments_315_post', 'wp_comments_315', 4, 'CREATE INDEX wp_comments_315_post ON wp_comments_315(comment_post_ID)', 3),
    $record('table', 'wp_posts_316', 'wp_posts_316', 5, 'CREATE TABLE wp_posts_316(ID INTEGER PRIMARY KEY)', 4),
    $record('table', 'wp_comments_316', 'wp_comments_316', 6, 'CREATE TABLE wp_comments_316(comment_post_ID INTEGER NOT NULL DEFAULT NULL REFERENCES wp_posts_316(ID) ON DELETE SET DEFAULT)', 5),
    $record('index', 'wp_comments_316_post', 'wp_comments_316', 7, 'CREATE INDEX wp_comments_316_post ON wp_comments_316(comment_post_ID)', 6),
    $record('table', 'wp_posts_317', 'wp_posts_317', 8, 'CREATE TABLE wp_posts_317(ID INTEGER PRIMARY KEY)', 7),
    $record('table', 'wp_postmeta_317', 'wp_postmeta_317', 9, 'CREATE TABLE wp_postmeta_317(post_id INTEGER REFERENCES wp_posts_317(ID) ON UPDATE CASCADE)', 8),
    $record('table', 'wp_posts_318', 'wp_posts_318', 10, 'CREATE TABLE wp_posts_318(ID INTEGER PRIMARY KEY)', 9),
    $record('table', 'wp_postmeta_318', 'wp_postmeta_318', 11, 'CREATE TABLE wp_postmeta_318(post_id INTEGER REFERENCES wp_posts_318(ID) ON DELETE CASCADE)', 10),
];

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next315-318',
    'wordpressUse' => 'WordPress import previews can flag action-column-specific SET DEFAULT NULL and CASCADE child lookup blockers before replaying PRAGMA foreign_key_list output.',
    'next315_update_set_default_null_notnull_child_default' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_set_default_null_notnull_child_default')),
    'next316_delete_set_default_null_notnull_child_default' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_set_default_null_notnull_child_default')),
    'next317_update_cascade_without_child_lookup_index' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'update_cascade_without_child_lookup_index')),
    'next318_delete_cascade_without_child_lookup_index' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', 'delete_cascade_without_child_lookup_index')),
    'implemented_pages' => array_values(array_filter(
        range(315, 318),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next315-318 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== [315, 316, 317, 318]) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next315-318 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next315-318 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
