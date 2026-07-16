<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record('table', 'wp_option_keys', 'wp_option_keys', 3, 'CREATE TABLE wp_option_keys(blog_id INTEGER NOT NULL, option_name TEXT COLLATE NOCASE NOT NULL, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 2),
    $record('index', 'sqlite_autoindex_wp_option_keys_1', 'wp_option_keys', 4, null, 3),
    $record('table', 'wp_option_import', 'wp_option_import', 5, "CREATE TABLE wp_option_import(
        option_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL REFERENCES wp_terms,
        option_name TEXT NOT NULL REFERENCES wp_option_keys,
        blog_id INTEGER NOT NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_keys
    )", 4),
    $record('index', 'wp_option_import_lookup', 'wp_option_import', 6, 'CREATE INDEX wp_option_import_lookup ON wp_option_import(term_id, blog_id, option_name)', 5),
];

$next = [
    $current[0],
    $current[1],
    $current[2],
    $record('table', 'wp_option_import', 'wp_option_import', 5, "CREATE TABLE wp_option_import(
        option_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL REFERENCES wp_terms,
        option_name TEXT NOT NULL,
        blog_id INTEGER NOT NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_keys
    )", 4),
    $current[4],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page209(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_option_import_lookup)',
    'PRAGMA main.foreign_key_list(wp_option_import)',
    25,
    5,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['operation'] !== 'pragma-index-xinfo-foreignkey-current-source-next209'
        || $page['current']['foreign_key_implicit_parent_primary_key']['arity_mismatch'] !== 1
        || $page['next_counts']['foreign_key_implicit_parent_primary_key']['arity_mismatch'] !== 0
        || $page['delta']['foreign_key_implicit_parent_primary_key_repaired'] !== true
        || $page['rows'][1]['status'] !== 'arity_mismatch'
        || $page['rows'][4]['status'] !== 'valid_implicit_parent_key'
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next209 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next209 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next209',
    'applicationUse' => 'Before applying copied wp_options taxonomy imports, verify that PRAGMA foreign_key_list omitted-parent-column rows have the same arity as the parent table primary key exposed through PRAGMA table_info/index_xinfo metadata.',
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'current_implicit_parent_primary_key' => $page['current']['foreign_key_implicit_parent_primary_key'],
    'next_implicit_parent_primary_key' => $page['next_counts']['foreign_key_implicit_parent_primary_key'],
    'delta' => [
        'rows' => $page['delta']['foreign_key_implicit_parent_primary_key_rows'],
        'mismatches' => $page['delta']['foreign_key_implicit_parent_primary_key_mismatches'],
        'repaired' => $page['delta']['foreign_key_implicit_parent_primary_key_repaired'],
    ],
    'implicit_parent_primary_key_rows' => $page['rows'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
