<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record('table', 'wp_option_keys', 'wp_option_keys', 3, 'CREATE TABLE wp_option_keys(blog_id INTEGER NOT NULL, option_name TEXT COLLATE NOCASE NOT NULL, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 2),
    $record('index', 'sqlite_autoindex_wp_option_keys_1', 'wp_option_keys', 4, null, 3),
    $record('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        meta_key TEXT,
        option_value TEXT,
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE CASCADE,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_keys(blog_id, option_name) ON UPDATE CASCADE ON DELETE SET NULL
    )", 4),
    $record('index', 'wp_postmeta_term_partial', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_term_partial ON wp_postmeta_import(term_id) WHERE meta_key IS NOT NULL', 5),
    $record('index', 'wp_postmeta_option_partial', 'wp_postmeta_import', 7, 'CREATE INDEX wp_postmeta_option_partial ON wp_postmeta_import(blog_id, option_name) WHERE option_value IS NOT NULL', 6),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $currentRecords[3],
    $currentRecords[4],
    $currentRecords[5],
    $record('index', 'wp_postmeta_term_action', 'wp_postmeta_import', 8, 'CREATE INDEX wp_postmeta_term_action ON wp_postmeta_import(term_id)', 7),
    $record('index', 'wp_postmeta_option_action', 'wp_postmeta_import', 9, 'CREATE INDEX wp_postmeta_option_action ON wp_postmeta_import(blog_id, option_name)', 8),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page212(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_postmeta_option_partial)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next212',
    'applicationUse' => 'Copied Application postmeta imports can distinguish partial child indexes from full child-action lookup indexes before applying ON DELETE/ON UPDATE foreign-key actions.',
    'status' => $page['status'],
    'current_action_rows' => $page['current']['foreign_key_child_action_lookup']['rows'],
    'current_partial_action_blockers' => $page['current']['foreign_key_child_action_lookup']['partial_child_action_index'],
    'next_action_blockers' => $page['next_counts']['foreign_key_child_action_lookup']['blocked'],
    'action_lookup_repaired' => $page['delta']['foreign_key_child_action_lookup_repaired'],
    'source' => $page['current_source']['foreign_key_child_action_lookup_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_action_rows'] !== 3
        || $summary['current_partial_action_blockers'] !== 3
        || $summary['next_action_blockers'] !== 0
        || $summary['action_lookup_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next212 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next212 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
