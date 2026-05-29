<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'WpSites', 'WpSites', 4, 'CREATE TABLE WpSites(Blog_ID INTEGER PRIMARY KEY, Domain TEXT COLLATE NOCASE)', 1),
    $record('table', 'WpOptionNames', 'WpOptionNames', 5, 'CREATE TABLE WpOptionNames(Name TEXT COLLATE NOCASE, Blog_ID INTEGER, PRIMARY KEY(Name, Blog_ID)) WITHOUT ROWID', 2),
    $record('table', 'WpOptions', 'WpOptions', 6, 'CREATE TABLE WpOptions(Option_ID INTEGER PRIMARY KEY, Option_Name TEXT, Blog_ID TEXT, Site_ID TEXT, Autoload TEXT, FOREIGN KEY(Site_ID) REFERENCES WpSites ON UPDATE CASCADE ON DELETE SET NULL MATCH SIMPLE, FOREIGN KEY(Option_Name, Blog_ID) REFERENCES WpOptionNames ON UPDATE RESTRICT ON DELETE CASCADE)', 3),
    $record('index', 'WpOptionNamesLookup', 'WpOptionNames', 7, 'CREATE UNIQUE INDEX WpOptionNamesLookup ON WpOptionNames(Name COLLATE NOCASE, Blog_ID)', 4),
];
$nextRecords = [
    $records[0],
    $records[1],
    $record('table', 'WpOptions', 'WpOptions', 6, 'CREATE TABLE WpOptions(Option_ID INTEGER PRIMARY KEY, Option_Name TEXT, Blog_ID TEXT, Site_ID TEXT, Autoload TEXT, FOREIGN KEY(Site_ID) REFERENCES WpSites ON UPDATE CASCADE ON DELETE CASCADE MATCH SIMPLE, FOREIGN KEY(Option_Name, Blog_ID) REFERENCES WpOptionNames ON UPDATE NO ACTION ON DELETE CASCADE)', 3),
    $records[3],
];
$currentTables = [
    'wpsites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wpoptionnames' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wpoptions' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'home', 'blog_id' => '1', 'site_id' => '404', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '1', 'autoload' => 'no'],
    ],
];
$nextTables = [
    'WPSITES' => [
        ['ROWID' => 1, 'BLOG_ID' => 1, 'DOMAIN' => 'example.test'],
        ['ROWID' => 404, 'BLOG_ID' => 404, 'DOMAIN' => 'network.example.test'],
    ],
    'WPOPTIONNAMES' => [
        ['NAME' => 'siteurl', 'BLOG_ID' => 1],
        ['NAME' => 'home', 'BLOG_ID' => 1],
        ['NAME' => 'missing', 'BLOG_ID' => 2],
    ],
    'WPOPTIONS' => $currentTables['wpoptions'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog167(
    $records,
    $currentTables,
    $nextRecords,
    $nextTables,
    'PRAGMA index_xinfo(WpOptionNamesLookup)',
);

if (($argv[1] ?? null) === '--self-test') {
    if ($page['status'] !== 'ok' || $page['current']['foreign_key_violations'] !== 3 || $page['next_counts']['foreign_key_violations'] !== 0 || $page['delta']['foreign_key_action_changed'] !== true) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next167 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next167 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next167',
    'wordpressUse' => 'Carry PRAGMA foreign_key_list action metadata through copied wp_options PRAGMA index_xinfo/foreign_key_check diagnostics so migration tooling can distinguish CASCADE, SET NULL, RESTRICT, and NO ACTION repairs.',
    'status' => $page['status'],
    'current_action_summary' => $page['current_source']['foreign_key_actions'],
    'next_action_summary' => $page['next_source']['foreign_key_actions'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
