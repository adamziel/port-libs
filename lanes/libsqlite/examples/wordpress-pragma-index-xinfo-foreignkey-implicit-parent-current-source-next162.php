<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 1),
    $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 5, null, 2),
    $record('table', 'wp_sites', 'wp_sites', 6, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 3),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names, FOREIGN KEY(site_id) REFERENCES wp_sites)', 4),
    $record('index', 'wp_options_fk_lookup', 'wp_options', 8, 'CREATE INDEX wp_options_fk_lookup ON wp_options(site_id, option_name)', 5),
];

$currentTables = [
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
    ],
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'blog_id' => '1', 'site_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'plugin_missing', 'blog_id' => '2', 'site_id' => '99', 'autoload' => 'no'],
    ],
];
$nextTables = [
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'plugin_missing', 'blog_id' => 2],
    ],
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 99, 'blog_id' => 99, 'domain' => 'network.example.test'],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog(
    $records,
    $currentTables,
    $records,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_fk_lookup)',
);

$payload = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-implicit-parent-current-source-next162',
    'status' => $page['status'],
    'foreign_key_source' => $page['current_source']['foreign_key_source'],
    'derived_foreign_keys' => $page['current_source']['derived_foreign_keys'],
    'implicit_parent_indexes' => $page['current']['parent_indexes'],
    'current_foreign_key_violations' => $page['current']['foreign_key_violations'],
    'next_foreign_key_violations' => $page['next_counts']['foreign_key_violations'],
    'delta_total_blockers' => $page['delta']['total_blockers'],
    'next_ready' => $page['next_state']['ready'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $payload['status'] !== 'ok'
        || $payload['derived_foreign_keys'] !== 2
        || $payload['implicit_parent_indexes'] !== ['sqlite_autoindex_wp_option_names_1', 'rowid-primary-key']
        || $payload['current_foreign_key_violations'] !== 2
        || $payload['next_foreign_key_violations'] !== 0
        || $payload['delta_total_blockers'] !== -2
        || $payload['next_ready'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-implicit-parent-current-source-next162 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-implicit-parent-current-source-next162 self-test passed\n");
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
