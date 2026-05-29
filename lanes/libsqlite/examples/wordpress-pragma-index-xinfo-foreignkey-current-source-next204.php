<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext204;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext196.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext203.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext204.php';

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT NOT NULL, site_id INTEGER NOT NULL, UNIQUE(site_id, post_name))', 1),
    $record('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 3, null, 2),
    $record('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, UNIQUE(slug, locale))', 3),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 5, null, 4),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 6, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_slug TEXT COLLATE NOCASE NOT NULL,
        locale TEXT COLLATE RTRIM NOT NULL,
        site_id INTEGER NOT NULL,
        post_name TEXT NOT NULL,
        meta_key TEXT,
        FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale),
        FOREIGN KEY(site_id, post_name) REFERENCES wp_posts(site_id, post_name)
    )", 5),
    $record('index', 'wp_termmeta_lookup', 'wp_termmeta_import', 7, 'CREATE INDEX wp_termmeta_lookup ON wp_termmeta_import(site_id DESC, post_name, meta_key)', 6),
];

$next = [
    ...$current,
    $record('index', 'wp_termmeta_fk_terms_lookup', 'wp_termmeta_import', 8, 'CREATE INDEX wp_termmeta_fk_terms_lookup ON wp_termmeta_import(term_slug COLLATE NOCASE, locale COLLATE RTRIM, meta_id)', 7),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext204::page(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_termmeta_lookup)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
);

if (($argv[1] ?? null) === '--self-test') {
    if ($page['status'] !== 'ok' || $page['current']['foreign_key_child_index']['missing_child_index'] !== 1 || $page['next_counts']['foreign_key_child_index']['missing_child_index'] !== 0) {
        fwrite(STDERR, "unexpected next204 PRAGMA child FK index coverage\n");
        exit(1);
    }
}

echo json_encode([
    'scenario' => 'copied wp_termmeta import PRAGMA index_xinfo plus foreign_key_list child-index current-source next204',
    'wordpressUse' => 'Copied WordPress termmeta imports can decide whether cascade/delete repair should first build a child-side FK lookup index by paging PRAGMA index_xinfo and foreign_key_list current-source diagnostics.',
    'pragma' => [
        'PRAGMA main.index_xinfo(wp_termmeta_lookup)',
        'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    ],
    'current_missing_child_indexes' => $page['current']['foreign_key_child_index']['missing_child_index'],
    'next_missing_child_indexes' => $page['next_counts']['foreign_key_child_index']['missing_child_index'],
    'child_index_repaired' => $page['delta']['foreign_key_child_index_repaired'],
    'dependencyClosure' => 'no new support component needed; reuses native sqlite_schema PRAGMA catalog, index_xinfo, index_list, and foreign_key_list parsing',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
