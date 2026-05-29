<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_posts_parent', 'wp_posts_parent', 2, 'CREATE TABLE wp_posts_parent(post_id INTEGER PRIMARY KEY, author_id INTEGER NOT NULL, slug TEXT NOT NULL, UNIQUE(author_id, slug))', 1),
    $record('index', 'sqlite_autoindex_wp_posts_parent_1', 'wp_posts_parent', 3, null, 2),
    $record('table', 'wp_import_comments', 'wp_import_comments', 4, "CREATE TABLE wp_import_comments(
        comment_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        author_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        FOREIGN KEY(post_id) REFERENCES wp_posts_parent(post_id) ON UPDATE CASCADE ON DELETE SET NULL,
        FOREIGN KEY(author_id, slug) REFERENCES wp_posts_parent(author_id, slug) ON UPDATE SET DEFAULT ON DELETE RESTRICT
    )", 3),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $record('table', 'wp_import_comments', 'wp_import_comments', 5, "CREATE TABLE wp_import_comments(
        comment_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        author_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        FOREIGN KEY(post_id) REFERENCES wp_posts_parent(post_id),
        FOREIGN KEY(author_id, slug) REFERENCES wp_posts_parent(author_id, slug) ON UPDATE CASCADE ON DELETE CASCADE
    )", 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page225(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_posts_parent_1)',
    'PRAGMA main.foreign_key_list(wp_import_comments)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next225',
    'wordpressUse' => 'Copied WordPress comment imports can compare PRAGMA foreign_key_list action clauses before replaying FK enforcement around post and author slug repairs.',
    'status' => $page['status'],
    'current_action_rows' => $page['current']['foreign_key_action_clause']['rows'],
    'current_set_default_actions' => $page['current']['foreign_key_action_clause']['set_default_actions'],
    'next_cascade_actions' => $page['next_counts']['foreign_key_action_clause']['cascade_actions'],
    'actions_changed' => $page['delta']['foreign_key_action_clause_changed'],
    'source' => $page['current_source']['foreign_key_action_clause_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_action_rows'] !== 3
        || $summary['current_set_default_actions'] !== 2
        || $summary['next_cascade_actions'] !== 2
        || $summary['actions_changed'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next225 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next225 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
