<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_posts_parent', 'wp_posts_parent', 2, 'CREATE TABLE wp_posts_parent(site_id INTEGER NOT NULL, post_name TEXT COLLATE NOCASE NOT NULL)', 1),
    $record('index', 'wp_posts_parent_site_name_desc_unique', 'wp_posts_parent', 3, 'CREATE UNIQUE INDEX wp_posts_parent_site_name_desc_unique ON wp_posts_parent(site_id DESC, post_name COLLATE NOCASE DESC)', 2),
    $record('table', 'wp_import_postmeta', 'wp_import_postmeta', 4, "CREATE TABLE wp_import_postmeta(
        meta_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        post_name TEXT NOT NULL,
        meta_key TEXT NOT NULL,
        FOREIGN KEY(site_id, post_name) REFERENCES wp_posts_parent(site_id, post_name) ON DELETE CASCADE
    )", 3),
];

$next = [
    $current[0],
    $record('index', 'wp_posts_parent_site_name_asc_unique', 'wp_posts_parent', 5, 'CREATE UNIQUE INDEX wp_posts_parent_site_name_asc_unique ON wp_posts_parent(site_id ASC, post_name COLLATE NOCASE ASC)', 4),
    $current[2],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page228(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_posts_parent_site_name_desc_unique)',
    'PRAGMA main.foreign_key_list(wp_import_postmeta)',
    0,
    120,
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next228',
    'applicationUse' => 'Copied Application import schemas can trust PRAGMA foreign_key_list parent-key validation even when PRAGMA index_xinfo exposes DESC sort order on the matching UNIQUE parent index.',
    'current_desc_parent_key_columns' => $page['current']['foreign_key_parent_key_sort_order']['desc_columns'],
    'current_parent_key_rows' => $page['current']['foreign_key_parent_key_sort_order']['rows'],
    'current_missing_parent_unique_index' => $page['current']['foreign_key_parent_key_sort_order']['missing_parent_unique_index'],
    'next_desc_parent_key_columns' => $page['next_counts']['foreign_key_parent_key_sort_order']['desc_columns'],
    'sort_order_changed' => $page['delta']['foreign_key_parent_key_sort_order_changed'],
    'source' => $page['current_source']['foreign_key_parent_key_sort_order_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(wp_posts_parent_site_name_desc_unique)',
        'PRAGMA main.foreign_key_list(wp_import_postmeta)',
    ],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (
        $summary['current_desc_parent_key_columns'] !== 2
        || $summary['current_parent_key_rows'] !== 2
        || $summary['current_missing_parent_unique_index'] !== 0
        || $summary['next_desc_parent_key_columns'] !== 0
        || $summary['sort_order_changed'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next228 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next228 self-test passed\n");
}

return $summary;
