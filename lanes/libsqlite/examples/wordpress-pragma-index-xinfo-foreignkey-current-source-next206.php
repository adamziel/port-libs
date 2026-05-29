<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record('table', 'wp_plugins', 'wp_plugins', 3, 'CREATE TABLE wp_plugins(plugin_slug TEXT, locale TEXT, active INTEGER)', 2),
    $record('table', 'wp_option_import', 'wp_option_import', 4, "CREATE TABLE wp_option_import(
        option_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL REFERENCES wp_terms(term_id),
        plugin_slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        FOREIGN KEY(plugin_slug, locale) REFERENCES wp_plugins(plugin_slug, locale)
    )", 3),
    $record('index', 'wp_option_import_lookup', 'wp_option_import', 5, 'CREATE INDEX wp_option_import_lookup ON wp_option_import(term_id, plugin_slug, locale)', 4),
];
$nextRecords = $records;
$nextRecords[] = $record('index', 'wp_plugins_slug_locale_unique', 'wp_plugins', 6, 'CREATE UNIQUE INDEX wp_plugins_slug_locale_unique ON wp_plugins(plugin_slug, locale)', 5);

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page206(
    $records,
    $nextRecords,
    'PRAGMA index_xinfo(wp_option_import_lookup)',
    'PRAGMA foreign_key_list(wp_option_import)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next206',
    'wordpressUse' => 'Copied wp_options-style term imports can keep INTEGER PRIMARY KEY parent references countable as valid FK parent keys even though PRAGMA index_list has no parent index row for the rowid alias.',
    'status' => $page['status'],
    'current_rowid_alias_parent_keys' => $page['current']['foreign_key_parent_rowid_alias']['rowid_alias_parent_key'],
    'current_missing_rowid_alias_parent_keys' => $page['current']['foreign_key_parent_rowid_alias']['missing_parent_key'],
    'next_missing_parent_unique' => $page['next_counts']['foreign_key_parent_coverage']['missing_parent_unique'],
    'rowid_alias_summary' => $page['current_source']['foreign_key_parent_rowid_alias'],
    'pragmas' => [
        'PRAGMA index_xinfo(wp_option_import_lookup)',
        'PRAGMA foreign_key_list(wp_option_import)',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_rowid_alias_parent_keys'] !== 1
        || $summary['current_missing_rowid_alias_parent_keys'] !== 1
        || $summary['next_missing_parent_unique'] !== 1
        || !str_contains($summary['rowid_alias_summary'][0], 'rowid-alias=term_id:rowid_alias_parent_key')
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next206 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next206 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
