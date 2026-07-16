<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, guid TEXT COLLATE NOCASE NOT NULL, import_score NUMERIC)', 1),
    $record('index', 'wp_posts_guid_unique', 'wp_posts', 3, 'CREATE UNIQUE INDEX wp_posts_guid_unique ON wp_posts(guid COLLATE NOCASE)', 2),
    $record('table', 'wp_comment_import', 'wp_comment_import', 4, "CREATE TABLE wp_comment_import(
        comment_id INTEGER PRIMARY KEY,
        owner_id TEXT REFERENCES wp_posts,
        explicit_guid BLOB REFERENCES wp_posts(guid),
        score_text TEXT REFERENCES wp_posts(import_score)
    )", 3),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $record('table', 'wp_comment_import', 'wp_comment_import', 4, "CREATE TABLE wp_comment_import(
        comment_id INTEGER PRIMARY KEY,
        owner_id INTEGER REFERENCES wp_posts(ID),
        explicit_guid TEXT REFERENCES wp_posts(guid),
        score_text NUMERIC REFERENCES wp_posts(import_score)
    )", 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page243(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_posts_guid_unique)',
    'PRAGMA main.foreign_key_list(wp_comment_import)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next243',
    'applicationUse' => 'Copied Application import schemas can compare PRAGMA foreign_key_list child columns with parent table_info affinities before replaying FK-sensitive rows.',
    'status' => $page['status'],
    'current_affinity_mismatches' => $page['current']['foreign_key_affinity']['affinity_mismatch'],
    'next_affinity_mismatches' => $page['next_counts']['foreign_key_affinity']['affinity_mismatch'],
    'affinity_repaired' => $page['delta']['foreign_key_affinity_repaired'],
    'source' => $page['current_source']['foreign_key_affinity_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_affinity_mismatches'] !== 3
        || $summary['next_affinity_mismatches'] !== 0
        || $summary['affinity_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next243 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next243 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
