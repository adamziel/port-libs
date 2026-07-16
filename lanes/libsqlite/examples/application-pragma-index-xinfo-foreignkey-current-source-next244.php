<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_parent_terms', 'wp_parent_terms', 2, 'CREATE TABLE wp_parent_terms(site_id INTEGER NOT NULL, slug TEXT NOT NULL, locale TEXT NOT NULL, name TEXT NOT NULL)', 1),
    $record('index', 'wp_parent_terms_expr_unique', 'wp_parent_terms', 3, 'CREATE UNIQUE INDEX wp_parent_terms_expr_unique ON wp_parent_terms(site_id, lower(slug))', 2),
    $record('index', 'wp_parent_terms_name_expr_unique', 'wp_parent_terms', 4, 'CREATE UNIQUE INDEX wp_parent_terms_name_expr_unique ON wp_parent_terms(lower(name))', 3),
    $record('table', 'wp_term_import_edges', 'wp_term_import_edges', 5, "CREATE TABLE wp_term_import_edges(
        edge_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        FOREIGN KEY(site_id, slug) REFERENCES wp_parent_terms(site_id, slug)
    )", 4),
];

$nextRecords = [
    $currentRecords[0],
    $record('index', 'wp_parent_terms_exact_unique', 'wp_parent_terms', 3, 'CREATE UNIQUE INDEX wp_parent_terms_exact_unique ON wp_parent_terms(site_id, slug)', 2),
    $currentRecords[2],
    $currentRecords[3],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page244(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_parent_terms_expr_unique)',
    'PRAGMA main.foreign_key_list(wp_term_import_edges)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next244',
    'applicationUse' => 'Copied Application taxonomy import schemas can avoid accepting a UNIQUE expression index as a foreign-key parent key when PRAGMA index_xinfo reports expression key rows.',
    'status' => $page['status'],
    'current_expression_parent_indexes' => $page['current']['foreign_key_parent_expression_indexes']['rows'],
    'current_blockers' => $page['current']['foreign_key_parent_expression_indexes']['blocked'],
    'next_blockers' => $page['next_counts']['foreign_key_parent_expression_indexes']['blocked'],
    'expression_parent_index_repaired' => $page['delta']['foreign_key_parent_expression_index_repaired'],
    'source' => $page['current_source']['foreign_key_parent_expression_index_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_expression_parent_indexes'] !== 2
        || $summary['current_blockers'] !== 1
        || $summary['next_blockers'] !== 0
        || $summary['expression_parent_index_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next244 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next244 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
