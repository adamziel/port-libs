<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_blogs', 'wp_blogs', 4, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_scope', 'wp_option_scope', 5, 'CREATE TABLE wp_option_scope(site_key TEXT COLLATE NOCASE, locale TEXT, PRIMARY KEY(site_key, locale)) WITHOUT ROWID', 2),
    $record('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT, site_key TEXT, locale TEXT, fallback_name TEXT, option_name TEXT, FOREIGN KEY(blog_id) REFERENCES wp_blogs(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT MATCH simple, FOREIGN KEY(site_key, locale) REFERENCES wp_option_scope(site_key, locale) ON UPDATE SET DEFAULT ON DELETE CASCADE MATCH custom, FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) ON DELETE SET NULL)', 4),
    $record('index', 'wp_option_scope_lookup', 'wp_option_scope', 8, 'CREATE UNIQUE INDEX wp_option_scope_lookup ON wp_option_scope(site_key COLLATE NOCASE, locale)', 5),
    $record('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 9, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 6),
    $record('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 7),
];

$currentTables = [
    'wp_blogs' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_scope' => [['site_key' => 'main', 'locale' => 'en_US']],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => '1', 'site_key' => 'main', 'locale' => 'en_US', 'fallback_name' => 'siteurl', 'option_name' => 'siteurl'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => '404', 'site_key' => 'main', 'locale' => 'fr_FR', 'fallback_name' => 'missing_default', 'option_name' => 'home'],
    ],
];
$nextTables = [
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'archive.example.test'],
    ],
    'wp_option_scope' => [
        ['site_key' => 'main', 'locale' => 'en_US'],
        ['site_key' => 'main', 'locale' => 'fr_FR'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog165(
    $records,
    $currentTables,
    $records,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

if (($argv[1] ?? null) === '--self-test') {
    if ($page['status'] !== 'ok' || $page['current']['foreign_key_violations'] !== 3 || $page['next_counts']['foreign_key_violations'] !== 0 || ($page['rows'][4]['action_summary'] ?? null) !== 'SET DEFAULT/CASCADE/CUSTOM') {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next165 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next165 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next165',
    'wordpressUse' => 'Resume copied multisite wp_options imports only after PRAGMA index_xinfo and foreign_key_check preserve the action/match metadata that decides repair policy.',
    'status' => $page['status'],
    'action_source' => $page['current_source']['action_source'],
    'action_summary' => $page['current_source']['action_summary'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
