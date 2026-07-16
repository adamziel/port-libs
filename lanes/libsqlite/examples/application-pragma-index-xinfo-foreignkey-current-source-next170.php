<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record('table', 'wp_option_groups', 'wp_option_groups', 6, 'CREATE TABLE wp_option_groups(group_id INTEGER PRIMARY KEY, label TEXT)', 3),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, group_id INTEGER, autoload TEXT, FOREIGN KEY(site_id) REFERENCES wp_sites ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names ON UPDATE NO ACTION ON DELETE SET NULL MATCH simple, FOREIGN KEY(group_id) REFERENCES wp_option_groups(group_id) ON DELETE SET DEFAULT NOT DEFERRABLE)', 4),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
];
$nextRecords = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record('table', 'wp_option_groups', 'wp_option_groups', 6, 'CREATE TABLE wp_option_groups(group_id INTEGER PRIMARY KEY, label TEXT)', 3),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, group_id INTEGER, autoload TEXT, FOREIGN KEY(site_id) REFERENCES wp_sites ON UPDATE SET NULL ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names ON UPDATE CASCADE ON DELETE NO ACTION MATCH partial, FOREIGN KEY(group_id) REFERENCES wp_option_groups(group_id) ON UPDATE RESTRICT ON DELETE SET DEFAULT)', 4),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
];
$currentTables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wp_option_groups' => [['rowid' => 10, 'group_id' => 10, 'label' => 'core']],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'group_id' => 10, 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'home', 'blog_id' => '1', 'site_id' => '404', 'group_id' => 10, 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '1', 'group_id' => 99, 'autoload' => 'no'],
    ],
];
$nextTables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'home', 'blog_id' => 1],
        ['name' => 'missing', 'blog_id' => 2],
    ],
    'wp_option_groups' => [
        ['rowid' => 10, 'group_id' => 10, 'label' => 'core'],
        ['rowid' => 99, 'group_id' => 99, 'label' => 'plugins'],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog170(
    $currentRecords,
    $currentTables,
    $nextRecords,
    $nextTables,
    'PRAGMA index_xinfo(wp_option_names_lookup)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next170',
    'applicationUse' => 'Copied multisite wp_options imports can distinguish immediate FK blockers from deferrable violations that survive until transaction commit while resuming PRAGMA index_xinfo diagnostics.',
    'status' => $page['status'],
    'current_immediate_foreign_key_violations' => $page['current']['immediate_foreign_key_violations'],
    'current_deferred_foreign_key_violations' => $page['current']['deferred_foreign_key_violations'],
    'current_commit_blocking_foreign_key_violations' => $page['current']['commit_blocking_foreign_key_violations'],
    'next_commit_blocking_foreign_key_violations' => $page['next_counts']['commit_blocking_foreign_key_violations'],
    'next_ready' => $page['next_state']['ready'],
    'deferred_cleared' => $page['delta']['deferred_cleared'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_immediate_foreign_key_violations'] !== 3
        || $summary['current_deferred_foreign_key_violations'] !== 1
        || $summary['next_commit_blocking_foreign_key_violations'] !== 0
        || $summary['deferred_cleared'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next170 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next170 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
