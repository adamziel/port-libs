<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaAutoindexForeignKeyPreflight;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$records = [
    $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_sites_1', 'wp_sites', 3, null, 2),
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT UNIQUE)', 3),
    $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 5, null, 4),
    $record('table', 'wp_options', 'wp_options', 6, "CREATE TABLE wp_options(
        option_id INTEGER PRIMARY KEY,
        blog_id INTEGER REFERENCES wp_sites(blog_id),
        option_name TEXT NOT NULL,
        locale TEXT NOT NULL DEFAULT 'en_US',
        option_value TEXT,
        UNIQUE(option_name, locale),
        FOREIGN KEY(option_name) REFERENCES wp_option_names(name)
    )", 5),
    $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 7, null, 6),
];

$plan = SQLitePragmaAutoindexForeignKeyPreflight::plan($records, [
    ['table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']],
    ['table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
]);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'ready' || $plan['foreign_keys'][1]['required_autoindex'] !== 'sqlite_autoindex_wp_option_names_1') {
        fwrite(STDERR, "application-pragma-autoindex-foreignkey-current-next53 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-autoindex-foreignkey-current-next53 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'copied wp_options pragma autoindex foreign-key current next53',
    'applicationUse' => 'Preflight copied Application SQLite schema metadata so PRAGMA foreign_key_check parent keys are backed by current sqlite_autoindex rows before the next import write.',
    'status' => $plan['status'],
    'autoindexes' => array_column($plan['autoindexes'], 'name'),
    'foreign_key_parent_autoindex' => $plan['foreign_keys'][1]['required_autoindex'],
    'next' => $plan['next'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
