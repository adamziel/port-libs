<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record('table', 'wp_posts', 'wp_posts', 6, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT, FOREIGN KEY(post_name) REFERENCES wp_option_names(name))', 3),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(site_id) REFERENCES wp_sites(blog_id))', 4),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
];

$currentTables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wp_posts' => [['rowid' => 1, 'ID' => 1, 'post_name' => 'orphan-post']],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '404'],
    ],
];
$nextTables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'missing', 'blog_id' => 2],
    ],
    'wp_posts' => $currentTables['wp_posts'],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog172(
    $records,
    $currentTables,
    $records,
    $nextTables,
    'PRAGMA temp.index_xinfo(wp_option_names_lookup)',
    'PRAGMA foreign_key_check(wp_options)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current_source']['foreign_key_target'] !== 'wp_options'
        || $page['current']['foreign_key_violations'] !== 2
        || $page['next_counts']['foreign_key_violations'] !== 0
        || $page['current']['foreign_key_tables'] !== ['wp_options']
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next172 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next172 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next172',
    'applicationUse' => 'Copied multisite wp_options imports can resume PRAGMA index_xinfo plus targeted foreign_key_check(table) pages without being blocked by unrelated Application tables in the same catalog image.',
    'status' => $page['status'],
    'foreign_key_target' => $page['current_source']['foreign_key_target'],
    'target_tables' => $page['current']['foreign_key_tables'],
    'current_foreign_key_violations' => $page['current']['foreign_key_violations'],
    'next_foreign_key_violations' => $page['next_counts']['foreign_key_violations'],
    'delta_total_blockers' => $page['delta']['total_blockers'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
