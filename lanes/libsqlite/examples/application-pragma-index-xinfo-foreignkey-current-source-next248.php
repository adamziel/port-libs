<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_term_taxonomy_stage', 'wp_term_taxonomy_stage', 2, 'CREATE TABLE wp_term_taxonomy_stage(term_taxonomy_id INTEGER PRIMARY KEY, taxonomy TEXT NOT NULL, slug TEXT NOT NULL)', 1),
    $record('index', 'wp_term_taxonomy_stage_taxonomy_slug_unique', 'wp_term_taxonomy_stage', 3, 'CREATE UNIQUE INDEX wp_term_taxonomy_stage_taxonomy_slug_unique ON wp_term_taxonomy_stage(taxonomy, slug)', 2),
    $record('table', 'wp_term_relationships_stage', 'wp_term_relationships_stage', 4, 'CREATE TABLE wp_term_relationships_stage(object_id INTEGER NOT NULL, taxonomy TEXT NOT NULL, slug TEXT NOT NULL, FOREIGN KEY(taxonomy, slug) REFERENCES wp_term_taxonomy_stage(taxonomy, slug) ON DELETE CASCADE)', 3),
];

$next = [
    $record('table', 'wp_term_taxonomy_stage', 'wp_term_taxonomy_stage', 2, 'CREATE TABLE wp_term_taxonomy_stage(term_taxonomy_id INTEGER PRIMARY KEY, taxonomy TEXT NOT NULL, slug TEXT NOT NULL, UNIQUE(taxonomy, slug))', 1),
    $record('index', 'sqlite_autoindex_wp_term_taxonomy_stage_1', 'wp_term_taxonomy_stage', 3, null, 2),
    $record('table', 'wp_term_relationships_stage', 'wp_term_relationships_stage', 4, 'CREATE TABLE wp_term_relationships_stage(object_id INTEGER NOT NULL, taxonomy TEXT NOT NULL, slug TEXT NOT NULL, FOREIGN KEY(taxonomy, slug) REFERENCES wp_term_taxonomy_stage(taxonomy, slug) ON DELETE CASCADE)', 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page248(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_term_taxonomy_stage_taxonomy_slug_unique)',
    'PRAGMA main.foreign_key_list(wp_term_relationships_stage)',
    0,
    320,
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next248',
    'applicationUse' => 'Copied Application taxonomy imports can distinguish FK parent keys backed by external UNIQUE indexes from inline UNIQUE constraints before dropping staging indexes.',
    'current_external_rows' => $page['current']['foreign_key_parent_external_unique']['rows'],
    'current_drop_index_mismatch_risks' => $page['current']['foreign_key_parent_external_unique']['drop_index_mismatch_risks'],
    'next_inline_rows' => $page['next_counts']['foreign_key_parent_external_unique']['inline_unique_parent_key'],
    'external_parent_key_repaired' => $page['delta']['foreign_key_parent_external_unique_repaired'],
    'source' => $page['current_source']['foreign_key_parent_external_unique_source'],
    'pragmas' => [
        'PRAGMA main.index_list(wp_term_taxonomy_stage)',
        'PRAGMA main.index_xinfo(wp_term_taxonomy_stage_taxonomy_slug_unique)',
        'PRAGMA main.foreign_key_list(wp_term_relationships_stage)',
    ],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (
        $summary['current_external_rows'] !== 1
        || $summary['current_drop_index_mismatch_risks'] !== 1
        || $summary['next_inline_rows'] !== 1
        || $summary['external_parent_key_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next248 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next248 self-test passed\n");
}

return $summary;
