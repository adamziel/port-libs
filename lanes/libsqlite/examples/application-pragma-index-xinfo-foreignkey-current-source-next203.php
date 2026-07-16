<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, UNIQUE(slug))', 1),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT NOT NULL, site_id INTEGER NOT NULL, UNIQUE(site_id, post_name))', 3),
    $record('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 5, null, 4),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 6, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_slug TEXT COLLATE NOCASE NOT NULL,
        locale TEXT COLLATE RTRIM NOT NULL,
        site_id INTEGER NOT NULL,
        post_name TEXT NOT NULL,
        meta_key TEXT,
        FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale) ON UPDATE CASCADE ON DELETE RESTRICT,
        FOREIGN KEY(site_id, post_name) REFERENCES wp_posts(site_id, post_name) ON UPDATE CASCADE ON DELETE CASCADE
    )", 5),
    $record('index', 'wp_termmeta_lookup', 'wp_termmeta_import', 7, 'CREATE INDEX wp_termmeta_lookup ON wp_termmeta_import(term_slug COLLATE NOCASE, locale COLLATE RTRIM, site_id, post_name)', 6),
];

$next = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, UNIQUE(slug, locale))', 1),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $current[2],
    $current[3],
    $current[4],
    $current[5],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page203(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_termmeta_lookup)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    18,
    4,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['operation'] !== 'pragma-index-xinfo-foreignkey-current-source-next203'
        || $page['current']['foreign_key_parent_coverage']['missing_parent_unique'] !== 1
        || $page['next_counts']['foreign_key_parent_coverage']['missing_parent_unique'] !== 0
        || $page['delta']['foreign_key_parent_coverage_repaired'] !== true
        || $page['rows'][0]['status'] !== 'missing_parent_unique'
        || $page['rows'][2]['parent_index'] !== 'sqlite_autoindex_wp_terms_1'
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next203 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next203 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next203',
    'applicationUse' => 'Before applying copied taxonomy metadata schema changes, confirm that PRAGMA foreign_key_list parent groups and PRAGMA index_xinfo/index_list agree on usable UNIQUE parent-key coverage in the current and next schema snapshots.',
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'current_parent_coverage' => $page['current']['foreign_key_parent_coverage'],
    'next_parent_coverage' => $page['next_counts']['foreign_key_parent_coverage'],
    'delta' => [
        'missing' => $page['delta']['foreign_key_parent_coverage_missing'],
        'covered' => $page['delta']['foreign_key_parent_coverage_covered'],
        'repaired' => $page['delta']['foreign_key_parent_coverage_repaired'],
    ],
    'coverage_rows' => $page['rows'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
