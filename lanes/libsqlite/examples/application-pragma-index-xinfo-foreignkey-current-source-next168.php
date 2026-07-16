<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, UNIQUE(name, blog_id))', 1),
    $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 5, null, 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id))', 3),
];
$currentTables = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1],
        ['rowid' => 2, 'name' => 'home', 'blog_id' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'transient_missing', 'blog_id' => '2', 'autoload' => 'no'],
    ],
];
$nextTables = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1],
        ['rowid' => 2, 'name' => 'home', 'blog_id' => 1],
        ['rowid' => 3, 'name' => 'TRANSIENT_MISSING', 'blog_id' => 2],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$catalog = new SQLitePragmaSchemaCatalog($records);
$xinfo = $catalog->execute('PRAGMA index_xinfo(sqlite_autoindex_wp_option_names_1)');
$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog164(
    $records,
    $currentTables,
    $records,
    $nextTables,
    'PRAGMA index_xinfo(sqlite_autoindex_wp_option_names_1)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['index_blockers'] !== 0
        || $page['current']['foreign_key_violations'] !== 1
        || $page['next_counts']['foreign_key_violations'] !== 0
        || ($xinfo['rows'][0]['coll'] ?? null) !== 'NOCASE'
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next168 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next168 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next168',
    'applicationUse' => 'Resume copied wp_options foreign-key repair only when PRAGMA index_xinfo preserves automatic UNIQUE-index NOCASE collation metadata used by parent-key admission.',
    'autoindexXinfo' => $xinfo['rows'],
    'status' => $page['status'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
