<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id))', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites(blog_id), option_name TEXT, blog_id TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id))', 3),
    $record('index', 'wp_option_names_nonunique', 'wp_option_names', 7, 'CREATE INDEX wp_option_names_nonunique ON wp_option_names(name COLLATE NOCASE, blog_id)', 4),
    $record('index', 'wp_option_names_partial_unique', 'wp_option_names', 8, "CREATE UNIQUE INDEX wp_option_names_partial_unique ON wp_option_names(name COLLATE NOCASE, blog_id) WHERE blog_id > 0", 5),
    $record('index', 'wp_option_names_binary_unique', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_binary_unique ON wp_option_names(name COLLATE BINARY, blog_id)', 6),
    $record('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 7),
];
$nextRecords = [
    $records[0],
    $records[1],
    $records[2],
    $records[3],
    $records[4],
    $records[5],
    $record('index', 'wp_option_names_lookup_unique', 'wp_option_names', 11, 'CREATE UNIQUE INDEX wp_option_names_lookup_unique ON wp_option_names(name COLLATE NOCASE, blog_id)', 8),
    $records[6],
];
$currentTables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'blog_id' => '1'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => '99', 'option_name' => 'missing', 'blog_id' => '2'],
    ],
];
$nextTables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 99, 'blog_id' => 99, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1],
        ['rowid' => 2, 'name' => 'missing', 'blog_id' => 2],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog180(
    $records,
    $currentTables,
    $nextRecords,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_parent_indexes']['blocked'] !== 1
        || $page['next_counts']['foreign_key_parent_indexes']['blocked'] !== 0
        || $page['next_counts']['foreign_key_violations'] !== 0
        || $page['delta']['foreign_key_parent_index_changed'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next180 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next180 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next180',
    'wordpressUse' => 'Explain why copied wp_options foreign-key checks are blocked by parent-key index candidates before a migration adds the matching UNIQUE index.',
    'status' => $page['status'],
    'current_parent_indexes' => $page['current']['foreign_key_parent_indexes'],
    'next_parent_indexes' => $page['next_counts']['foreign_key_parent_indexes'],
    'current_source' => $page['current_source']['foreign_key_parent_indexes'],
    'next_source' => $page['next_source']['foreign_key_parent_indexes'],
    'delta' => $page['delta'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
