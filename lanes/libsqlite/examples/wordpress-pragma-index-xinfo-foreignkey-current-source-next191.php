<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT, blog_id INTEGER, network_id INTEGER)', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id INTEGER REFERENCES wp_sites(blog_id), option_name TEXT, blog_id INTEGER, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id))', 3),
    $record('index', 'wp_option_names_network_unique', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_network_unique ON wp_option_names(name, blog_id, network_id)', 4),
    $record('index', 'wp_options_lookup', 'wp_options', 8, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 5),
];
$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $currentRecords[3],
    $record('index', 'wp_option_names_exact_unique', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_exact_unique ON wp_option_names(name, blog_id)', 6),
    $currentRecords[4],
];

$tables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1, 'network_id' => 1]],
    'wp_options' => [['rowid' => 1, 'option_id' => 1, 'site_id' => 1, 'option_name' => 'siteurl', 'blog_id' => 1]],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog191(
    $currentRecords,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_parent_superset']['superset_unique_only'] !== 2
        || $page['next_counts']['foreign_key_parent_superset']['superset_unique_only'] !== 0
        || $page['delta']['foreign_key_parent_superset_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next191 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next191 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next191',
    'wordpressUse' => 'Copied wp_options diagnostics must reject a parent UNIQUE index that merely prefixes the referenced columns with an extra network/site key; PRAGMA index_xinfo proves whether the next schema adds the exact FK parent key.',
    'current_superset_blockers' => $page['current']['foreign_key_parent_superset']['superset_unique_only'],
    'next_superset_blockers' => $page['next_counts']['foreign_key_parent_superset']['superset_unique_only'],
    'repaired' => $page['delta']['foreign_key_parent_superset_repaired'],
    'ready' => $page['next_state']['ready'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
