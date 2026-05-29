<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, locale TEXT, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites, option_name TEXT, blog_id TEXT, fallback_name TEXT, orphan_name TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name), FOREIGN KEY(orphan_name) REFERENCES wp_missing_defaults(default_name))', 4),
    $record('table', 'wp_missing_defaults', 'wp_missing_defaults', 8, 'CREATE TABLE wp_missing_defaults(default_name TEXT, enabled INTEGER)', 5),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 6),
    $record('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 10, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 7),
    $record('index', 'wp_options_lookup', 'wp_options', 11, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id, fallback_name)', 8),
];
$nextRecords = $currentRecords;
$nextRecords[4] = $record('table', 'wp_missing_defaults', 'wp_missing_defaults', 8, 'CREATE TABLE wp_missing_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 5);
$nextRecords[] = $record('index', 'sqlite_autoindex_wp_missing_defaults_1', 'wp_missing_defaults', 12, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_missing_defaults_1 ON wp_missing_defaults(default_name)', 9);

$currentTables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US']],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_missing_defaults' => [['rowid' => 1, 'default_name' => 'core_missing', 'enabled' => 0]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'blog_id' => '1', 'fallback_name' => 'siteurl', 'orphan_name' => 'core_missing', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => '404', 'option_name' => 'home', 'blog_id' => '1', 'fallback_name' => 'missing_default', 'orphan_name' => 'plugin_missing', 'autoload' => 'yes'],
    ],
];
$nextTables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US'],
        ['name' => 'home', 'blog_id' => 1, 'locale' => 'en_US'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_missing_defaults' => [
        ['rowid' => 1, 'default_name' => 'core_missing', 'enabled' => 0],
        ['rowid' => 2, 'default_name' => 'plugin_missing', 'enabled' => 0],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog178(
    $currentRecords,
    $currentTables,
    $nextRecords,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next178',
    'wordpressUse' => 'Copied wp_options imports can verify that PRAGMA foreign_key_list parent columns map to PRAGMA index_xinfo key columns while ignoring auxiliary covering columns before resuming FK repair pages.',
    'status' => $page['status'],
    'current_parent_key_rows' => $page['current']['foreign_key_parent_key_rows'],
    'current_missing_parent_key' => $page['current']['foreign_key_parent_key_columns']['missing_parent_key'],
    'next_missing_parent_key' => $page['next_counts']['foreign_key_parent_key_columns']['missing_parent_key'],
    'auxiliary_columns_ignored' => $page['current']['foreign_key_parent_key_columns']['auxiliary_columns_ignored'],
    'next_ready' => $page['next_state']['ready'],
    'parent_key_changed' => $page['delta']['foreign_key_parent_key_changed'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_parent_key_rows'] !== 5
        || $summary['current_missing_parent_key'] !== 1
        || $summary['next_missing_parent_key'] !== 0
        || $summary['auxiliary_columns_ignored'] !== 1
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next178 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next178 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
