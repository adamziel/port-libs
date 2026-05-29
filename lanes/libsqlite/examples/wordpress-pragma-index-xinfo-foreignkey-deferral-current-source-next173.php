<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT, locale TEXT, PRIMARY KEY(name, locale)) WITHOUT ROWID', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT, option_name TEXT, locale TEXT, autoload TEXT, FOREIGN KEY(blog_id) REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE)', 3),
    $record('index', 'wp_options_lookup', 'wp_options', 7, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 4),
    $record('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name, locale)', 5),
];
$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT, option_name TEXT, locale TEXT, autoload TEXT, FOREIGN KEY(blog_id) REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) ON UPDATE NO ACTION ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED)', 3),
    $currentRecords[3],
    $currentRecords[4],
];

$currentTables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'locale' => 'en_US'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => '1', 'option_name' => 'siteurl', 'locale' => 'en_US', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => '404', 'option_name' => 'home', 'locale' => 'en_US', 'autoload' => 'yes'],
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

$plan = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog173(
    $currentRecords,
    $currentTables,
    $nextRecords,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'ok');
    assert($plan['current']['foreign_key_deferrals']['initially_deferred'] === 1);
    assert($plan['next_counts']['foreign_key_deferrals']['initially_deferred'] === 1);
    assert($plan['delta']['foreign_key_deferral_changed'] === true);
    assert($plan['rows'][3]['deferral_summary'] === 'DEFERRABLE/INITIALLY DEFERRED');

    echo "wordpress-pragma-index-xinfo-foreignkey-deferral-current-source-next173 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA index_xinfo foreign-key deferral current-source next173',
    'status' => $plan['status'],
    'index_xinfo_rows' => $plan['current']['index_xinfo'],
    'current_deferrals' => $plan['current_source']['foreign_key_deferrals'],
    'next_deferrals' => $plan['next_source']['foreign_key_deferrals'],
    'deferral_changed' => $plan['delta']['foreign_key_deferral_changed'],
    'violations_cleared' => $plan['delta']['cleared'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
