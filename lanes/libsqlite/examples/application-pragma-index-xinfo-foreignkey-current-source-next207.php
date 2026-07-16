<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT)', 1),
    $record('table', 'wp_plugins', 'wp_plugins', 3, 'CREATE TABLE wp_plugins(plugin_slug TEXT, locale TEXT, UNIQUE(plugin_slug, locale))', 2),
    $record('index', 'sqlite_autoindex_wp_plugins_1', 'wp_plugins', 4, null, 3),
    $record('table', 'wp_option_import', 'wp_option_import', 5, 'CREATE TABLE wp_option_import(option_id INTEGER PRIMARY KEY, term_id INTEGER REFERENCES wp_terms(term_id), plugin_slug TEXT, locale TEXT, FOREIGN KEY(plugin_slug, locale) REFERENCES wp_plugins(plugin_slug, locale))', 4),
    $record('index', 'wp_option_import_term_lookup', 'wp_option_import', 6, 'CREATE INDEX wp_option_import_term_lookup ON wp_option_import(term_id)', 5),
];
$nextRecords = [
    ...$records,
    $record('index', 'wp_option_import_plugin_fk', 'wp_option_import', 7, 'CREATE INDEX wp_option_import_plugin_fk ON wp_option_import(plugin_slug, locale)', 6),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page207(
    $records,
    $nextRecords,
    'PRAGMA index_xinfo(wp_option_import_term_lookup)',
    'PRAGMA foreign_key_list(wp_option_import)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next207',
    'applicationUse' => 'Copied wp_options imports can preflight whether child foreign-key columns have a non-partial PRAGMA index_xinfo prefix before cascade/delete checks scan the staging table.',
    'status' => $page['status'],
    'current_child_index_missing' => $page['current']['foreign_key_child_indexes']['missing_child_index'],
    'next_child_index_missing' => $page['next_counts']['foreign_key_child_indexes']['missing_child_index'],
    'child_index_repaired' => $page['delta']['foreign_key_child_index_repaired'],
    'next_child_index_covered' => $page['next_counts']['foreign_key_child_indexes']['covered'],
    'pragmas' => [
        'PRAGMA index_xinfo(wp_option_import_term_lookup)',
        'PRAGMA foreign_key_list(wp_option_import)',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_child_index_missing'] !== 1
        || $summary['next_child_index_missing'] !== 0
        || $summary['child_index_repaired'] !== true
        || $summary['next_child_index_covered'] !== 2
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next207 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next207 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
