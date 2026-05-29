<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_slug_parent', 'wp_slug_parent', 2, 'CREATE TABLE wp_slug_parent(site_id INTEGER NOT NULL, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record('index', 'wp_slug_parent_site_slug_desc_unique', 'wp_slug_parent', 3, 'CREATE UNIQUE INDEX wp_slug_parent_site_slug_desc_unique ON wp_slug_parent(site_id DESC, slug COLLATE NOCASE DESC)', 2),
    $record('table', 'wp_import_slugmeta', 'wp_import_slugmeta', 4, "CREATE TABLE wp_import_slugmeta(
        meta_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        slug TEXT COLLATE NOCASE NOT NULL,
        meta_key TEXT NOT NULL,
        FOREIGN KEY(site_id, slug) REFERENCES wp_slug_parent(site_id, slug) ON DELETE CASCADE
    )", 3),
];

$next = [
    $current[0],
    $record('index', 'wp_slug_parent_site_slug_unique', 'wp_slug_parent', 5, 'CREATE UNIQUE INDEX wp_slug_parent_site_slug_unique ON wp_slug_parent(site_id, slug COLLATE NOCASE)', 4),
    $current[2],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page238(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_slug_parent_site_slug_desc_unique)',
    'PRAGMA main.foreign_key_list(wp_import_slugmeta)',
    0,
    180,
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next238',
    'wordpressUse' => 'Copied WordPress import schemas can admit descending UNIQUE parent indexes from PRAGMA index_xinfo(desc=1) as valid foreign-key parent keys instead of requiring a false repair before schema replay.',
    'current_descending_parent_key_rows' => $page['current']['foreign_key_parent_descending_key']['rows'],
    'current_descending_parent_key_ok' => $page['current']['foreign_key_parent_descending_key']['ok_desc_parent_unique_index'],
    'current_blockers' => $page['current']['foreign_key_parent_descending_key']['blocked'],
    'next_ascending_parent_key_ok' => $page['next_counts']['foreign_key_parent_descending_key']['ok'],
    'descending_key_changed' => $page['delta']['foreign_key_parent_descending_key_changed'],
    'source' => $page['current_source']['foreign_key_parent_descending_key_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(wp_slug_parent_site_slug_desc_unique)',
        'PRAGMA main.foreign_key_list(wp_import_slugmeta)',
    ],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (
        $summary['current_descending_parent_key_rows'] !== 2
        || $summary['current_descending_parent_key_ok'] !== 2
        || $summary['current_blockers'] !== 0
        || $summary['next_ascending_parent_key_ok'] !== 2
        || $summary['descending_key_changed'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next238 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next238 self-test passed\n");
}

return $summary;
