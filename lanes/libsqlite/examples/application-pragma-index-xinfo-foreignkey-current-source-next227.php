<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(post_id INTEGER NOT NULL, meta_key TEXT NOT NULL, title TEXT, UNIQUE(post_id, meta_key))', 1),
    $record('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 3, null, 2),
    $record('table', 'wp_blogs', 'wp_blogs', 4, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT)', 3),
    $record('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        meta_value TEXT,
        post_id INTEGER NOT NULL,
        meta_key TEXT NOT NULL,
        site_id INTEGER NOT NULL,
        autoload TEXT,
        FOREIGN KEY(post_id, meta_key) REFERENCES wp_posts(post_id, meta_key) ON DELETE CASCADE,
        FOREIGN KEY(site_id) REFERENCES wp_blogs(blog_id) ON DELETE CASCADE
    )", 4),
    $record('index', 'wp_postmeta_import_value_post_key', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_import_value_post_key ON wp_postmeta_import(meta_value, post_id, meta_key)', 5),
    $record('index', 'wp_postmeta_import_autoload_site', 'wp_postmeta_import', 7, 'CREATE INDEX wp_postmeta_import_autoload_site ON wp_postmeta_import(autoload COLLATE NOCASE, site_id)', 6),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $currentRecords[3],
    $record('index', 'wp_postmeta_import_post_key_value', 'wp_postmeta_import', 8, 'CREATE INDEX wp_postmeta_import_post_key_value ON wp_postmeta_import(post_id, meta_key, meta_value)', 7),
    $record('index', 'wp_postmeta_import_site_autoload', 'wp_postmeta_import', 9, 'CREATE INDEX wp_postmeta_import_site_autoload ON wp_postmeta_import(site_id, autoload COLLATE NOCASE)', 8),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page227(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_postmeta_import_value_post_key)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next227',
    'applicationUse' => 'Copied Application postmeta import schemas can identify lookup indexes where FK child columns are present but not leftmost, then confirm the repaired next schema moves those columns to the prefix.',
    'status' => $page['status'],
    'current_suffix_rows' => $page['current']['foreign_key_child_suffix_indexes']['rows'],
    'current_suffix_foreign_keys' => $page['current']['foreign_key_child_suffix_indexes']['foreign_keys'],
    'next_suffix_rows' => $page['next_counts']['foreign_key_child_suffix_indexes']['rows'],
    'suffix_indexes_repaired' => $page['delta']['foreign_key_child_suffix_index_repaired'],
    'source' => $page['current_source']['foreign_key_child_suffix_index_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_suffix_rows'] !== 3
        || $summary['current_suffix_foreign_keys'] !== 2
        || $summary['next_suffix_rows'] !== 0
        || $summary['suffix_indexes_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next227 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next227 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
