<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE PRIMARY KEY, autoload TEXT)', 2),
    $record('table', 'wp_option_scope', 'wp_option_scope', 6, 'CREATE TABLE wp_option_scope(site_key TEXT COLLATE NOCASE, locale TEXT, PRIMARY KEY(site_key, locale)) WITHOUT ROWID', 3),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER, option_name TEXT, site_key TEXT, locale TEXT, fallback_name TEXT REFERENCES wp_option_names(name) DEFERRABLE INITIALLY DEFERRED, option_value TEXT, FOREIGN KEY(blog_id) REFERENCES wp_sites(blog_id) NOT DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(site_key, locale) REFERENCES wp_option_scope(site_key, locale) DEFERRABLE INITIALLY IMMEDIATE)', 4),
    $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 8, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_option_names_1 ON wp_option_names(name COLLATE NOCASE)', 5),
    $record('index', 'wp_option_scope_lookup', 'wp_option_scope', 9, 'CREATE UNIQUE INDEX wp_option_scope_lookup ON wp_option_scope(site_key COLLATE NOCASE, locale)', 6),
    $record('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 7),
];

$currentTables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl', 'autoload' => 'yes']],
    'wp_option_scope' => [['site_key' => 'main', 'locale' => 'en_US']],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'site_key' => 'main', 'locale' => 'en_US', 'fallback_name' => 'siteurl'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => 404, 'option_name' => 'home', 'site_key' => 'main', 'locale' => 'fr_FR', 'fallback_name' => 'missing_home'],
    ],
];
$nextTables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'archive.example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 2, 'name' => 'missing_home', 'autoload' => 'no'],
    ],
    'wp_option_scope' => [
        ['site_key' => 'main', 'locale' => 'en_US'],
        ['site_key' => 'main', 'locale' => 'fr_FR'],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog169(
    $records,
    $currentTables,
    $records,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

if (($argv[1] ?? null) === '--self-test') {
    if ($page['status'] !== 'ok' || ($page['rows'][3]['deferral_summary'] ?? null) !== 'deferrable_deferred' || ($page['current_source']['deferral_summary']['deferrable_deferred'] ?? null) !== 1) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next169 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next169 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next169',
    'wordpressUse' => 'Classify copied wp_options foreign-key repair rows by immediate, DEFERRABLE INITIALLY IMMEDIATE, and DEFERRABLE INITIALLY DEFERRED DDL before resuming imports.',
    'status' => $page['status'],
    'deferral_source' => $page['current_source']['deferral_source'],
    'deferral_summary' => $page['current_source']['deferral_summary'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
