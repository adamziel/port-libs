<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(blog_id INTEGER NOT NULL, post_id INTEGER NOT NULL, PRIMARY KEY(blog_id, post_id)) WITHOUT ROWID', 1),
    $record('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 3, null, 2),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        blog_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        option_id INTEGER NOT NULL,
        parent_option INTEGER,
        FOREIGN KEY(blog_id, post_id) REFERENCES wp_posts(blog_id, post_id) ON DELETE CASCADE,
        FOREIGN KEY(parent_option) REFERENCES wp_termmeta_import(option_id) ON UPDATE SET NULL
    )", 3),
    $record('index', 'wp_termmeta_import_post_reversed', 'wp_termmeta_import', 5, 'CREATE INDEX wp_termmeta_import_post_reversed ON wp_termmeta_import(post_id, blog_id)', 4),
    $record('index', 'wp_termmeta_import_parent_option', 'wp_termmeta_import', 6, 'CREATE INDEX wp_termmeta_import_parent_option ON wp_termmeta_import(parent_option)', 5),
    $record('index', 'wp_termmeta_import_option_id', 'wp_termmeta_import', 7, 'CREATE UNIQUE INDEX wp_termmeta_import_option_id ON wp_termmeta_import(option_id)', 6),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('index', 'wp_termmeta_import_post_prefix', 'wp_termmeta_import', 8, 'CREATE INDEX wp_termmeta_import_post_prefix ON wp_termmeta_import(blog_id, post_id, meta_id)', 4),
    $currentRecords[4],
    $currentRecords[5],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page232(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_termmeta_import_post_reversed)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next232',
    'wordpressUse' => 'Copied WordPress import schemas can identify child FK action indexes whose columns are present but reversed, then require a leftmost-prefix repair before relying on parent delete/update probes.',
    'status' => $page['status'],
    'current_prefix_rows' => $page['current']['foreign_key_child_action_prefix']['rows'],
    'current_misordered_rows' => $page['current']['foreign_key_child_action_prefix']['misordered_child_action_index'],
    'next_misordered_rows' => $page['next_counts']['foreign_key_child_action_prefix']['misordered_child_action_index'],
    'prefix_repaired' => $page['delta']['foreign_key_child_action_prefix_repaired'],
    'source' => $page['current_source']['foreign_key_child_action_prefix_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_prefix_rows'] !== 3
        || $summary['current_misordered_rows'] !== 2
        || $summary['next_misordered_rows'] !== 0
        || $summary['prefix_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next232 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next232 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
