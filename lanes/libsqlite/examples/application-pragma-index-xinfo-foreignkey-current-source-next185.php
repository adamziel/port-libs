<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, PRIMARY KEY(name, locale)) WITHOUT ROWID', 1),
    $record('table', 'wp_defaults', 'wp_defaults', 5, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, locale TEXT, fallback_name TEXT, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name))', 3),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale COLLATE RTRIM)', 4),
    $record('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 8, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 5),
    $record('index', 'wp_options_lookup', 'wp_options', 9, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, locale, fallback_name)', 6),
];

$currentTables = [
    'wp_option_names' => [
        ['name' => 'siteurl', 'locale' => 'en_US'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'locale' => 'en_US', 'fallback_name' => 'siteurl'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing-plugin', 'locale' => null, 'fallback_name' => null],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'siteurl', 'locale' => 'en_US', 'fallback_name' => 'missing_default'],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_defaults'][] = ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog185(
    $records,
    $currentTables,
    $records,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next185',
    'applicationUse' => 'Copied wp_options imports can repair concrete foreign_key_check violations while preserving SQLite parity that child rows with NULL foreign-key columns are omitted from PRAGMA foreign_key_check.',
    'status' => $page['status'],
    'current_fk_violations' => $page['current']['foreign_key_violations'],
    'next_fk_violations' => $page['next_counts']['foreign_key_violations'],
    'null_child_rows' => $page['current']['foreign_key_null_child_keys']['rows'],
    'next_null_child_rows' => $page['next_counts']['foreign_key_null_child_keys']['rows'],
    'next_ready' => $page['next_state']['ready'],
    'pragmas' => [
        'PRAGMA index_xinfo(wp_options_lookup)',
        'PRAGMA foreign_key_check',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_fk_violations'] !== 1
        || $summary['next_fk_violations'] !== 0
        || $summary['null_child_rows'] !== 2
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next185 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next185 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
