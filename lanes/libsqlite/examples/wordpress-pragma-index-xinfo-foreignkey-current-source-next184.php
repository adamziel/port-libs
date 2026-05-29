<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 1),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, option_value TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id))', 2),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 6, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE DESC, blog_id)', 3),
    $record('index', 'wp_options_lookup', 'wp_options', 7, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 4),
];
$nextRecords = $currentRecords;
$nextRecords[2] = $record('index', 'wp_option_names_lookup', 'wp_option_names', 6, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 3);

$tables = [
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wp_options' => [['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'blog_id' => '1', 'option_value' => 'https://example.test']],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog184(
    $currentRecords,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next184',
    'wordpressUse' => 'Copied wp_options imports can preserve PRAGMA index_xinfo parent-key ASC/DESC metadata in current-source FK diagnostics while a parent UNIQUE index is rebuilt.',
    'status' => $page['status'],
    'current_parent_sort_rows' => $page['current']['foreign_key_parent_sort_rows'],
    'current_desc_parent_keys' => $page['current']['foreign_key_parent_sort']['desc'],
    'next_desc_parent_keys' => $page['next_counts']['foreign_key_parent_sort']['desc'],
    'sort_changed' => $page['delta']['foreign_key_parent_sort_changed'],
    'desc_delta' => $page['delta']['foreign_key_parent_desc_delta'],
    'next_ready' => $page['next_state']['ready'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_parent_sort_rows'] !== 2
        || $summary['current_desc_parent_keys'] !== 1
        || $summary['next_desc_parent_keys'] !== 0
        || $summary['sort_changed'] !== true
        || $summary['desc_delta'] !== -1
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next184 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next184 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
