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

$recordsBySchema = [
    'temp' => [
        $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT UNIQUE)', 1),
        $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 6, null, 2),
        $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT, UNIQUE(option_name, autoload), FOREIGN KEY(option_name) REFERENCES wp_option_names(name))', 3),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 8, null, 4),
    ],
    'main' => [
        $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)', 1),
        $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER REFERENCES wp_posts(ID), meta_key TEXT)', 2),
    ],
    'archive' => [
        $record('table', 'wp_option_names', 'wp_option_names', 10, 'CREATE TABLE wp_option_names(name TEXT UNIQUE)', 1),
        $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 11, null, 2),
        $record('table', 'wp_options', 'wp_options', 12, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, locale TEXT, UNIQUE(option_name, locale), FOREIGN KEY(option_name) REFERENCES wp_option_names(name))', 3),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 13, null, 4),
    ],
];

$foreignKeysBySchema = [
    'temp' => [
        ['table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
    ],
    'main' => [
        ['table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
    ],
    'archive' => [
        ['table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
    ],
];

$plan = SQLitePragmaAutoindexForeignKeyPreflight::planCurrentSource(
    $recordsBySchema,
    $foreignKeysBySchema,
    'cff6baf6d405b3aa7ae1f6fca752e50506b70f0e',
    'pragma-integrity-autoindex-fk-current-source-next92',
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'ready' || $plan['foreign_keys'][0]['schema'] !== 'temp' || $plan['foreign_keys'][2]['schema'] !== 'archive') {
        fwrite(STDERR, "application-pragma-integrity-autoindex-fk-current-source-next92 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-integrity-autoindex-fk-current-source-next92 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA integrity autoindex/FK current-source next92',
    'applicationUse' => 'Preflight copied temp/main/archive Application SQLite schemas so autoindex catalog rows and foreign-key parent coverage remain schema-current before import writes.',
    'status' => $plan['status'],
    'schemas' => $plan['schemas'],
    'autoindex_count' => count($plan['autoindexes']),
    'foreign_key_count' => count($plan['foreign_keys']),
    'temp_parent_autoindex' => $plan['foreign_keys'][0]['required_autoindex'],
    'archive_parent_autoindex' => $plan['foreign_keys'][2]['required_autoindex'],
    'next' => $plan['next'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
