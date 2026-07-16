<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, locale TEXT, PRIMARY KEY(name, locale)) WITHOUT ROWID', 2),
    $record('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record('table', 'wp_options', 'wp_options', 7, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT CONSTRAINT 'fk site''blog' REFERENCES wp_sites(blog_id) DEFERRABLE INITIALLY DEFERRED, option_name TEXT, locale TEXT, fallback_name TEXT, CONSTRAINT 'fk option locale' FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) NOT DEFERRABLE)", 4),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale)', 5),
    $record('index', 'wp_options_lookup', 'wp_options', 9, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 6),
    $record('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 10, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 7),
];
$nextRecords = [
    $records[0],
    $records[1],
    $records[2],
    $record('table', 'wp_options', 'wp_options', 7, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT CONSTRAINT 'fk blog site next' REFERENCES wp_sites(blog_id) DEFERRABLE INITIALLY IMMEDIATE, option_name TEXT, locale TEXT, fallback_name TEXT, CONSTRAINT 'fk option locale next' FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) DEFERRABLE INITIALLY DEFERRED, CONSTRAINT [fk fallback default] FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) NOT DEFERRABLE)", 4),
    $records[4],
    $records[5],
    $records[6],
];
$currentTables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'locale' => 'en_US']],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => '1', 'option_name' => 'siteurl', 'locale' => 'en_US', 'fallback_name' => 'siteurl'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => '404', 'option_name' => 'home', 'locale' => 'en_US', 'fallback_name' => 'missing_default'],
        ['rowid' => 3, 'option_id' => 3, 'blog_id' => '1', 'option_name' => 'missing', 'locale' => 'fr_FR', 'fallback_name' => 'siteurl'],
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
        ['name' => 'missing', 'locale' => 'fr_FR'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog179(
    $records,
    $currentTables,
    $nextRecords,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_constraints']['named'] !== 2
        || $page['next_counts']['foreign_key_constraints']['named'] !== 3
        || $page['delta']['foreign_key_constraint_changed'] !== true
        || $page['next_counts']['foreign_key_violations'] !== 0
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next179 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next179 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next179',
    'applicationUse' => 'Carry single-quoted named foreign-key constraints through copied wp_options PRAGMA index_xinfo/foreign_key_check diagnostics so migration tools can report the schema name that changed or blocked an import.',
    'status' => $page['status'],
    'current_constraints' => $page['current_source']['foreign_key_constraints'],
    'next_constraints' => $page['next_source']['foreign_key_constraints'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
