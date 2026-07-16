<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT, blog_id INTEGER)', 1),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id INTEGER, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id))', 2),
    $record('index', 'wp_option_names_reversed_unique', 'wp_option_names', 6, 'CREATE UNIQUE INDEX wp_option_names_reversed_unique ON wp_option_names(blog_id, name)', 3),
    $record('index', 'wp_options_lookup', 'wp_options', 7, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 4),
];
$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('index', 'wp_option_names_exact_unique', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_exact_unique ON wp_option_names(name, blog_id)', 5),
    $currentRecords[3],
];

$tables = [
    'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1]],
    'wp_options' => [['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'blog_id' => 1]],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog195(
    $currentRecords,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_parent_permuted']['permuted_unique_only'] !== 2
        || $page['next_counts']['foreign_key_parent_permuted']['permuted_unique_only'] !== 0
        || $page['delta']['foreign_key_parent_permuted_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next195 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next195 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next195',
    'applicationUse' => 'Copied wp_options diagnostics must reject a parent UNIQUE index that contains the referenced columns in a different order; PRAGMA index_xinfo proves whether the next schema adds the exact ordered FK parent key.',
    'current_permuted_blockers' => $page['current']['foreign_key_parent_permuted']['permuted_unique_only'],
    'next_permuted_blockers' => $page['next_counts']['foreign_key_parent_permuted']['permuted_unique_only'],
    'repaired' => $page['delta']['foreign_key_parent_permuted_repaired'],
    'ready' => $page['next_state']['ready'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
