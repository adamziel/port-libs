<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_parent_terms', 'wp_parent_terms', 2, 'CREATE TABLE wp_parent_terms(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL, taxonomy TEXT NOT NULL)', 1),
    $record('index', 'wp_parent_terms_lower_slug_unique', 'wp_parent_terms', 3, 'CREATE UNIQUE INDEX wp_parent_terms_lower_slug_unique ON wp_parent_terms(lower(slug))', 2),
    $record('index', 'wp_parent_terms_lower_slug_tax_unique', 'wp_parent_terms', 4, 'CREATE UNIQUE INDEX wp_parent_terms_lower_slug_tax_unique ON wp_parent_terms(lower(slug), taxonomy)', 3),
    $record('table', 'wp_term_import_edges', 'wp_term_import_edges', 5, "CREATE TABLE wp_term_import_edges(
        edge_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        taxonomy TEXT NOT NULL,
        FOREIGN KEY(slug) REFERENCES wp_parent_terms(slug),
        FOREIGN KEY(slug, taxonomy) REFERENCES wp_parent_terms(slug, taxonomy),
        FOREIGN KEY(term_id) REFERENCES wp_parent_terms(term_id)
    )", 4),
];

$nextRecords = [
    $currentRecords[0],
    $record('index', 'wp_parent_terms_slug_unique', 'wp_parent_terms', 6, 'CREATE UNIQUE INDEX wp_parent_terms_slug_unique ON wp_parent_terms(slug)', 2),
    $record('index', 'wp_parent_terms_slug_tax_unique', 'wp_parent_terms', 7, 'CREATE UNIQUE INDEX wp_parent_terms_slug_tax_unique ON wp_parent_terms(slug, taxonomy)', 3),
    $currentRecords[1],
    $currentRecords[2],
    $currentRecords[3],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page231(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_parent_terms_lower_slug_tax_unique)',
    'PRAGMA main.foreign_key_list(wp_term_import_edges)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next231',
    'wordpressUse' => 'Copied taxonomy imports must not treat lower(slug) UNIQUE expression indexes as parent-key coverage for foreign_key_check repair; PRAGMA index_xinfo exposes expression terms with NULL names.',
    'status' => $page['status'],
    'current_expression_rows' => $page['current']['foreign_key_parent_expression_unique']['rows'],
    'current_expression_blockers' => $page['current']['foreign_key_parent_expression_unique']['expression_unique_index'],
    'next_expression_blockers' => $page['next_counts']['foreign_key_parent_expression_unique']['expression_unique_index'],
    'expression_parent_repaired' => $page['delta']['foreign_key_parent_expression_unique_repaired'],
    'source' => $page['current_source']['foreign_key_parent_expression_unique_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_expression_rows'] !== 4
        || $summary['current_expression_blockers'] !== 3
        || $summary['next_expression_blockers'] !== 0
        || $summary['expression_parent_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next231 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next231 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
