<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext198;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_option_scope', 'wp_option_scope', 4, 'CREATE TABLE wp_option_scope(blog_id INTEGER, option_name TEXT, value TEXT, PRIMARY KEY(blog_id, option_name))', 1),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER, option_name TEXT, FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_scope(blog_id, option_name))', 2),
    $record('index', 'wp_options_lookup', 'wp_options', 6, 'CREATE INDEX wp_options_lookup ON wp_options(blog_id, option_name)', 3),
];
$nextRecords = [
    $record('table', 'wp_option_scope', 'wp_option_scope', 4, 'CREATE TABLE wp_option_scope(blog_id INTEGER, option_name TEXT, value TEXT, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 1),
    $currentRecords[1],
    $currentRecords[2],
];

$tables = [
    'wp_option_scope' => [
        ['rowid' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'value' => 'https://example.test'],
    ],
    'wp_options' => [
        ['rowid' => 10, 'option_id' => 10, 'blog_id' => 1, 'option_name' => 'siteurl'],
    ],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext198::currentNextPageFromCatalog(
    $currentRecords,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['next_counts']['foreign_key_without_rowid_parent']['covered_foreign_keys'] !== 1
        || $page['delta']['foreign_key_without_rowid_parent_repaired'] !== true
        || $page['next_state']['ready'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next198 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next198 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next198',
    'wordpressUse' => 'Copied wp_options import diagnostics must accept a WITHOUT ROWID composite parent primary key even when no separate sqlite_schema index row exists for PRAGMA index_xinfo.',
    'current_without_rowid_parent_keys' => $page['current']['foreign_key_without_rowid_parent']['covered_foreign_keys'],
    'next_without_rowid_parent_keys' => $page['next_counts']['foreign_key_without_rowid_parent']['covered_foreign_keys'],
    'repaired' => $page['delta']['foreign_key_without_rowid_parent_repaired'],
    'ready' => $page['next_state']['ready'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
