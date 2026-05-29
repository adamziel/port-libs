<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites, option_name TEXT, blog_id TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id))', 3),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name, blog_id)', 4),
    $record('index', 'wp_options_lookup', 'wp_options', 8, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 5),
];
$nextRecords = $currentRecords;
$nextRecords[3] = $record('index', 'wp_option_names_lookup', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 4);

$tables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'blog_id' => '1'],
    ],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog182(
    $currentRecords,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next182',
    'wordpressUse' => 'Copied wp_options imports can block PRAGMA foreign_key_check repair pages when a parent UNIQUE index has the right columns but the wrong parent-column collation.',
    'status' => $page['status'],
    'current_parent_collation_mismatches' => $page['current']['foreign_key_parent_collations']['mismatch'],
    'next_parent_collation_mismatches' => $page['next_counts']['foreign_key_parent_collations']['mismatch'],
    'collation_repaired' => $page['delta']['foreign_key_parent_collation_repaired'],
    'next_ready' => $page['next_state']['ready'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_parent_collation_mismatches'] !== 1
        || $summary['next_parent_collation_mismatches'] !== 0
        || $summary['collation_repaired'] !== true
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next182 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next182 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
