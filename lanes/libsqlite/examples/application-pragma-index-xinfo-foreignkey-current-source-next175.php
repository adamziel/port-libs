<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites ON UPDATE CASCADE ON DELETE RESTRICT, option_name TEXT, blog_id TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id) ON UPDATE SET DEFAULT ON DELETE CASCADE MATCH custom)', 3),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 4),
    $record('index', 'wp_options_lookup', 'wp_options', 8, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 5),
];
$currentTables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'blog_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => '404', 'option_name' => 'home', 'blog_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'site_id' => '1', 'option_name' => 'plugin_missing', 'blog_id' => '2', 'autoload' => 'no'],
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
        ['name' => 'plugin_missing', 'blog_id' => 2],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog175(
    $records,
    $currentTables,
    $records,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);
$foreignKeyListRows = array_values(array_filter($page['rows'], static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_list'));

if (($argv[1] ?? null) === '--self-test') {
    if ($page['status'] !== 'ok' || $page['current']['foreign_key_violations'] !== 3 || $page['next_counts']['foreign_key_violations'] !== 0 || $page['current']['foreign_key_list_rows'] !== 3 || $foreignKeyListRows[1]['from'] !== 'option_name' || $foreignKeyListRows[2]['seq'] !== 1) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next175 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next175 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next175',
    'applicationUse' => 'Preserve PRAGMA foreign_key_list id/seq column order while admitting copied multisite wp_options rows after parent tables are repaired.',
    'status' => $page['status'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
    'foreign_key_list_rows' => $foreignKeyListRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
