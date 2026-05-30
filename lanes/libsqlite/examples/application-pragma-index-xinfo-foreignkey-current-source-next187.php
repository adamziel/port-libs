<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_slug_registry', 'wp_slug_registry', 4, 'CREATE TABLE wp_slug_registry(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, active INTEGER)', 1),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, slug TEXT, locale TEXT, option_value TEXT, FOREIGN KEY(slug, locale) REFERENCES wp_slug_registry(slug, locale))', 2),
    $record('index', 'wp_slug_registry_active_unique', 'wp_slug_registry', 6, 'CREATE UNIQUE INDEX wp_slug_registry_active_unique ON wp_slug_registry(slug COLLATE NOCASE, locale COLLATE RTRIM) WHERE active = 1', 3),
    $record('index', 'wp_options_lookup', 'wp_options', 7, 'CREATE INDEX wp_options_lookup ON wp_options(slug, locale)', 4),
];
$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('index', 'wp_slug_registry_full_unique', 'wp_slug_registry', 8, 'CREATE UNIQUE INDEX wp_slug_registry_full_unique ON wp_slug_registry(slug COLLATE NOCASE, locale COLLATE RTRIM)', 5),
    $currentRecords[3],
];

$tables = [
    'wp_slug_registry' => [['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'active' => 1]],
    'wp_options' => [['rowid' => 1, 'option_id' => 1, 'slug' => 'home', 'locale' => 'en_US', 'option_value' => 'https://example.test']],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog187(
    $currentRecords,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next187',
    'applicationUse' => 'Copied wp_options imports must not treat a partial UNIQUE parent index as satisfying SQLite foreign-key parent-key admission, even when PRAGMA index_xinfo columns match.',
    'status' => $page['status'],
    'current_partial_parent_rows' => $page['current']['foreign_key_partial_parent_index_rows'],
    'current_partial_parent_blockers' => $page['current']['foreign_key_partial_parent_indexes']['partial_unique_candidates'],
    'next_partial_parent_blockers' => $page['next_counts']['foreign_key_partial_parent_indexes']['partial_unique_candidates'],
    'partial_parent_repaired' => $page['delta']['foreign_key_partial_parent_index_repaired'],
    'next_ready' => $page['next_state']['ready'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_partial_parent_rows'] !== 2
        || $summary['current_partial_parent_blockers'] !== 2
        || $summary['next_partial_parent_blockers'] !== 0
        || $summary['partial_parent_repaired'] !== true
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next187 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next187 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
