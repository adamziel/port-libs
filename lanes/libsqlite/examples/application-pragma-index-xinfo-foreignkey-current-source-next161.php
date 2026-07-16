<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, autoload TEXT, FOREIGN KEY(site_id) REFERENCES wp_sites, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names)', 3),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 4),
];
$currentTables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'home', 'blog_id' => '1', 'site_id' => '404', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '1', 'autoload' => 'no'],
    ],
];
$nextTables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'home', 'blog_id' => 1],
        ['name' => 'missing', 'blog_id' => 2],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog161(
    $records,
    $currentTables,
    $records,
    $nextTables,
    'PRAGMA index_xinfo(wp_option_names_lookup)',
);

if (($argv[1] ?? null) === '--self-test') {
    if ($page['status'] !== 'ok' || $page['current']['foreign_key_violations'] !== 3 || $page['next_counts']['foreign_key_violations'] !== 0 || $page['delta']['cleared'] !== true) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next161 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next161 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next161',
    'applicationUse' => 'Admit wp_options foreign_key_check diagnostics when PRAGMA foreign_key_list reports implicit parent primary-key columns for REFERENCES parent declarations.',
    'status' => $page['status'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
