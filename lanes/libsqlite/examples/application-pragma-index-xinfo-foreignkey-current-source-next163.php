<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_blogs', 'wp_blogs', 4, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record('table', 'wp_option_scope', 'wp_option_scope', 5, 'CREATE TABLE wp_option_scope(site_key TEXT, locale TEXT, PRIMARY KEY(site_key, locale)) WITHOUT ROWID', 2),
    $record('index', 'sqlite_autoindex_wp_option_scope_1', 'wp_option_scope', 6, null, 3),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER REFERENCES wp_blogs, site_key TEXT, locale TEXT, option_name TEXT, FOREIGN KEY(site_key, locale) REFERENCES wp_option_scope)', 4),
    $record('index', 'wp_options_name', 'wp_options', 8, 'CREATE INDEX wp_options_name ON wp_options(option_name, blog_id)', 5),
];

$currentTables = [
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'main.example'],
    ],
    'wp_option_scope' => [
        ['rowid' => 'main-en', 'site_key' => 'main', 'locale' => 'en_US'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => 1, 'site_key' => 'main', 'locale' => 'en_US', 'option_name' => 'siteurl'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => 99, 'site_key' => 'main', 'locale' => 'fr_FR', 'option_name' => 'home'],
    ],
];
$nextTables = [
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'main.example'],
        ['rowid' => 99, 'blog_id' => 99, 'domain' => 'archive.example'],
    ],
    'wp_option_scope' => [
        ['rowid' => 'main-en', 'site_key' => 'main', 'locale' => 'en_US'],
        ['rowid' => 'main-fr', 'site_key' => 'main', 'locale' => 'fr_FR'],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog163(
    $records,
    $currentTables,
    $records,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_name)',
);

if (
    $page['status'] !== 'ok'
    || $page['current_source']['implicit_parent_keys'] !== 3
    || $page['current']['foreign_key_violations'] !== 2
    || $page['next_counts']['foreign_key_violations'] !== 0
) {
    fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next163 self-test failed\n");
    exit(1);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next163',
    'status' => $page['status'],
    'implicit_parent_keys' => $page['current_source']['implicit_parent_keys'],
    'current_foreign_key_violations' => $page['current']['foreign_key_violations'],
    'next_ready' => $page['next_state']['ready'],
    'delta_total_blockers' => $page['delta']['total_blockers'],
    'applicationUse' => 'Copied multisite wp_options imports can resume only after PRAGMA index_xinfo and foreign_key_check agree that implicit REFERENCES parent primary-key columns resolve against the current catalog image.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
