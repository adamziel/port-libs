<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';

$pragmaChain = glob(__DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext*.php') ?: [];
sort($pragmaChain, SORT_NATURAL);
foreach ($pragmaChain as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_term_relationships_stage', 'wp_term_relationships_stage', 2, "CREATE TABLE wp_term_relationships_stage(
        object_id INTEGER NOT NULL,
        term_taxonomy_id INTEGER NOT NULL,
        site_id INTEGER NOT NULL,
        FOREIGN KEY(term_taxonomy_id) REFERENCES wp_term_taxonomy(term_taxonomy_id) ON DELETE CASCADE,
        FOREIGN KEY(site_id, term_taxonomy_id) REFERENCES wp_network_terms(site_id, term_taxonomy_id) ON UPDATE CASCADE
    )", 1),
    $record('index', 'wp_term_relationships_stage_fk', 'wp_term_relationships_stage', 3, 'CREATE INDEX wp_term_relationships_stage_fk ON wp_term_relationships_stage(term_taxonomy_id)', 2),
];

$next = [
    $record('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 4, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, taxonomy TEXT NOT NULL)', 3),
    $record('table', 'wp_network_terms', 'wp_network_terms', 5, 'CREATE TABLE wp_network_terms(site_id INTEGER NOT NULL, term_taxonomy_id INTEGER NOT NULL, PRIMARY KEY(site_id, term_taxonomy_id))', 4),
    ...$current,
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page226(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_term_relationships_stage_fk)',
    'PRAGMA main.foreign_key_list(wp_term_relationships_stage)',
    0,
    100,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_missing_parent_table']['missing_parent_table'] !== 3
        || $page['next_counts']['foreign_key_missing_parent_table']['missing_parent_table'] !== 0
        || $page['delta']['foreign_key_missing_parent_table_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next226 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next226 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next226',
    'wordpressUse' => 'Copied taxonomy relationship imports must not admit PRAGMA foreign_key_list/index_xinfo repair pages when the child table references parent taxonomy tables that are absent from the current copied catalog.',
    'current_missing_parent_table_rows' => $page['current']['foreign_key_missing_parent_table']['rows'],
    'current_missing_parent_tables' => $page['current']['foreign_key_missing_parent_table']['parent_tables'],
    'next_missing_parent_table_rows' => $page['next_counts']['foreign_key_missing_parent_table']['rows'],
    'repaired' => $page['delta']['foreign_key_missing_parent_table_repaired'],
    'pragmas' => [
        'PRAGMA index_xinfo(wp_term_relationships_stage_fk)',
        'PRAGMA foreign_key_list(wp_term_relationships_stage)',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
