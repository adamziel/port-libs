<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE NOT NULL, locale TEXT NOT NULL, label TEXT)', 1),
    $record('table', 'wp_options_import', 'wp_options_import', 5, 'CREATE TABLE wp_options_import(import_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE NOT NULL, locale TEXT NOT NULL, option_value TEXT, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale))', 2),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 6, 'CREATE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale)', 3),
    $record('index', 'wp_options_import_fk_lookup', 'wp_options_import', 7, 'CREATE INDEX wp_options_import_fk_lookup ON wp_options_import(option_name COLLATE NOCASE, locale)', 4),
];
$nextRecords = [
    $records[0],
    $records[1],
    $record('index', 'wp_option_names_unique', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_unique ON wp_option_names(name COLLATE NOCASE, locale)', 5),
    $records[3],
];
$tables = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'active_plugins', 'locale' => 'en_US', 'label' => 'plugins'],
        ['rowid' => 2, 'name' => 'stylesheet', 'locale' => 'en_US', 'label' => 'theme'],
    ],
    'wp_options_import' => [
        ['rowid' => 10, 'import_id' => 10, 'option_name' => 'active_plugins', 'locale' => 'en_US', 'option_value' => 'a:0:{}'],
    ],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog197(
    $records,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_options_import_fk_lookup)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next197',
    'applicationUse' => 'Copied wp_options imports must not treat a matching non-UNIQUE parent index as satisfying SQLite foreign-key parent-key admission, even when PRAGMA index_xinfo columns match.',
    'status' => $page['status'],
    'current_non_unique_parent_rows' => $page['current']['foreign_key_parent_non_unique_rows'],
    'current_non_unique_parent_blockers' => $page['current']['foreign_key_parent_non_unique']['non_unique_matching_parent'],
    'next_non_unique_parent_blockers' => $page['next_counts']['foreign_key_parent_non_unique']['non_unique_matching_parent'],
    'non_unique_parent_repaired' => $page['delta']['foreign_key_parent_non_unique_repaired'],
    'next_ready' => $page['next_state']['ready'],
    'pragmas' => [
        'PRAGMA index_xinfo(wp_options_import_fk_lookup)',
        'PRAGMA foreign_key_list(wp_options_import)',
        'PRAGMA foreign_key_check',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_non_unique_parent_rows'] !== 1
        || $summary['current_non_unique_parent_blockers'] !== 1
        || $summary['next_non_unique_parent_blockers'] !== 0
        || $summary['non_unique_parent_repaired'] !== true
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next197 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next197 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
