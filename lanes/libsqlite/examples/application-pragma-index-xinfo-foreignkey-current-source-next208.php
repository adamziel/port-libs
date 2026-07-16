<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_blogs', 'wp_blogs', 2, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT NOT NULL)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 3, 'CREATE TABLE wp_option_names(default_name TEXT PRIMARY KEY, label TEXT)', 2),
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER REFERENCES wp_blogs, option_name TEXT REFERENCES wp_option_names)', 3),
    $record('index', 'wp_options_lookup', 'wp_options', 5, 'CREATE INDEX wp_options_lookup ON wp_options(blog_id, option_name)', 4),
];

$nextRecords = [
    $currentRecords[0],
    $record('table', 'wp_option_names', 'wp_option_names', 3, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY, label TEXT)', 2),
    $currentRecords[2],
    $currentRecords[3],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page208(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_options_lookup)',
    'PRAGMA main.foreign_key_list(wp_options)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next208',
    'applicationUse' => 'Copied multisite wp_options imports that omit parent-column lists can verify which parent primary-key columns PRAGMA foreign_key_list resolves before applying schema repairs.',
    'status' => $page['status'],
    'operation' => $page['operation'],
    'current_implicit_rows' => $page['current']['foreign_key_implicit_parent_keys']['rows'],
    'current_resolved' => $page['current']['foreign_key_implicit_parent_keys']['implicit_parent_key_resolved'],
    'next_resolved' => $page['next_counts']['foreign_key_implicit_parent_keys']['implicit_parent_key_resolved'],
    'implicit_changed' => $page['delta']['foreign_key_implicit_parent_key_changed'],
    'current_option_name_parent' => $page['current_source']['foreign_key_implicit_parent_keys'][1],
    'next_option_name_parent' => $page['next_source']['foreign_key_implicit_parent_keys'][1],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['operation'] !== 'pragma-index-xinfo-foreignkey-current-source-next208'
        || $summary['current_implicit_rows'] !== 2
        || $summary['current_resolved'] !== 2
        || $summary['next_resolved'] !== 2
        || $summary['implicit_changed'] !== true
        || !str_contains($summary['current_option_name_parent'], 'parent_pk=default_name')
        || !str_contains($summary['next_option_name_parent'], 'parent_pk=name')
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next208 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next208 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
