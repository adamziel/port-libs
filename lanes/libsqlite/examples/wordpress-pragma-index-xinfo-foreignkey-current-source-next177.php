<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$currentRecords = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, locale TEXT, PRIMARY KEY(name, locale)) WITHOUT ROWID', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT CONSTRAINT fk_option_site REFERENCES wp_sites(blog_id) DEFERRABLE INITIALLY DEFERRED, option_name TEXT, locale TEXT, CONSTRAINT fk_option_name_locale FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale))', 3),
    $record('index', 'wp_options_lookup', 'wp_options', 7, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 4),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale)', 5),
];
$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT CONSTRAINT fk_option_site_next REFERENCES wp_sites(blog_id) DEFERRABLE INITIALLY IMMEDIATE, option_name TEXT, locale TEXT, CONSTRAINT fk_option_name_locale FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) DEFERRABLE INITIALLY DEFERRED)', 3),
    $currentRecords[3],
    $currentRecords[4],
];

$currentTables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'locale' => 'en_US']],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => '1', 'option_name' => 'siteurl', 'locale' => 'en_US'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => '404', 'option_name' => 'home', 'locale' => 'en_US'],
    ],
];
$nextTables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'archive.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'locale' => 'en_US'],
        ['name' => 'home', 'locale' => 'en_US'],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog177(
    $currentRecords,
    $currentTables,
    $nextRecords,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_constraints']['named'] !== 2
        || $page['current']['foreign_key_constraints']['table_origin'] !== 1
        || $page['next_counts']['foreign_key_violations'] !== 0
        || $page['delta']['foreign_key_constraint_changed'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next177 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next177 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next177',
    'wordpressUse' => 'Copied multisite wp_options imports can keep PRAGMA index_xinfo plus foreign_key_check pages tied to named FK constraints and column/table-level origins when schema DDL changes between current and next sources.',
    'status' => $page['status'],
    'constraint_source' => $page['current_source']['foreign_key_constraint_source'],
    'current_constraints' => $page['current_source']['foreign_key_constraints'],
    'next_constraints' => $page['next_source']['foreign_key_constraints'],
    'constraint_changes' => $page['delta']['foreign_key_constraint_changes'],
    'next_foreign_key_violations' => $page['next_counts']['foreign_key_violations'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
