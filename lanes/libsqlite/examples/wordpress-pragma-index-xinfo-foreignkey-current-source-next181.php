<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_slug_registry', 'wp_slug_registry', 4, 'CREATE TABLE wp_slug_registry(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, title TEXT)', 1),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, slug TEXT, locale TEXT, option_value TEXT, FOREIGN KEY(slug, locale) REFERENCES wp_slug_registry(slug, locale))', 2),
    $record('index', 'wp_slug_registry_lookup', 'wp_slug_registry', 6, 'CREATE UNIQUE INDEX wp_slug_registry_lookup ON wp_slug_registry(slug, locale COLLATE RTRIM)', 3),
    $record('index', 'wp_options_lookup', 'wp_options', 7, 'CREATE INDEX wp_options_lookup ON wp_options(slug, locale)', 4),
];
$nextRecords = $currentRecords;
$nextRecords[2] = $record('index', 'wp_slug_registry_lookup', 'wp_slug_registry', 6, 'CREATE UNIQUE INDEX wp_slug_registry_lookup ON wp_slug_registry(slug COLLATE NOCASE, locale COLLATE RTRIM)', 3);

$currentTables = [
    'wp_slug_registry' => [['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'title' => 'Home']],
    'wp_options' => [['rowid' => 1, 'option_id' => 1, 'slug' => 'home', 'locale' => 'en_US', 'option_value' => 'https://example.test']],
];
$nextTables = $currentTables;

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog181(
    $currentRecords,
    $currentTables,
    $nextRecords,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next181',
    'wordpressUse' => 'Copied wp_options imports can block a parent-key repair when PRAGMA index_xinfo exposes a UNIQUE parent index whose key collation differs from the parent table column collation.',
    'status' => $page['status'],
    'current_parent_collation_rows' => $page['current']['foreign_key_parent_collation_rows'],
    'current_mismatched_collations' => $page['current']['foreign_key_parent_collations']['mismatched'],
    'next_mismatched_collations' => $page['next_counts']['foreign_key_parent_collations']['mismatched'],
    'collation_changed' => $page['delta']['foreign_key_parent_collation_changed'],
    'next_ready' => $page['next_state']['ready'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_parent_collation_rows'] !== 2
        || $summary['current_mismatched_collations'] !== 1
        || $summary['next_mismatched_collations'] !== 0
        || $summary['collation_changed'] !== true
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next181 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next181 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
