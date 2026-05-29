<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms_stage', 'wp_terms_stage', 2, 'CREATE TABLE wp_terms_stage(slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM, UNIQUE(slug, taxonomy))', 1),
    $record('index', 'sqlite_autoindex_wp_terms_stage_1', 'wp_terms_stage', 3, null, 2),
    $record('table', 'wp_term_relationships_stage', 'wp_term_relationships_stage', 4, 'CREATE TABLE wp_term_relationships_stage(object_id INTEGER, term_slug TEXT NOT NULL, taxonomy TEXT NOT NULL, FOREIGN KEY(term_slug, taxonomy) REFERENCES wp_terms_stage(slug, taxonomy) ON DELETE CASCADE)', 3),
];

$next = [
    $record('table', 'wp_terms_stage', 'wp_terms_stage', 2, 'CREATE TABLE wp_terms_stage(slug TEXT COLLATE NOCASE NOT NULL, taxonomy TEXT COLLATE RTRIM NOT NULL, UNIQUE(slug, taxonomy))', 1),
    $current[1],
    $current[2],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page254(
    $current,
    $next,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_stage_1)',
    'PRAGMA main.foreign_key_list(wp_term_relationships_stage)',
    0,
    80,
);

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv ?? [], true)) {
    if (
        $page['operation'] !== 'pragma-index-xinfo-foreignkey-current-source-next254'
        || $page['current']['foreign_key_nullable_parent_key']['nullable_parent_key'] !== 2
        || $page['next_counts']['foreign_key_nullable_parent_key']['nullable_parent_key'] !== 0
        || $page['delta']['foreign_key_nullable_parent_key_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress pragma nullable parent-key next254 self-test failed\n");
        exit(1);
    }

    echo "wordpress-pragma-index-xinfo-foreignkey-nullable-parent-key-current-source-next254 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied WordPress taxonomy PRAGMA index_xinfo foreign-key nullable parent key current-source next254',
    'wordpressUse' => 'Copied taxonomy imports can hold PRAGMA index_xinfo and foreign_key_list on the same current source while detecting nullable parent key columns behind exact UNIQUE parent indexes before admitting the repaired next schema.',
    'current_nullable_parent_keys' => $page['current']['foreign_key_nullable_parent_key']['nullable_parent_key'],
    'next_nullable_parent_keys' => $page['next_counts']['foreign_key_nullable_parent_key']['nullable_parent_key'],
    'repaired' => $page['delta']['foreign_key_nullable_parent_key_repaired'],
    'source' => $page['current_source']['foreign_key_nullable_parent_key_source'],
    'pragma' => [
        'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_stage_1)',
        'PRAGMA main.foreign_key_list(wp_term_relationships_stage)',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
