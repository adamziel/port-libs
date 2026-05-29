<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL, taxonomy TEXT NOT NULL)', 1),
    $record('index', 'wp_terms_slug_taxonomy_unique', 'wp_terms', 3, 'CREATE UNIQUE INDEX wp_terms_slug_taxonomy_unique ON wp_terms(slug, taxonomy)', 2),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        taxonomy TEXT NOT NULL,
        FOREIGN KEY(slug) REFERENCES wp_terms(slug),
        FOREIGN KEY(slug, taxonomy) REFERENCES wp_terms(slug, taxonomy),
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id)
    )", 3),
];

$nextRecords = [
    $currentRecords[0],
    $record('index', 'wp_terms_slug_unique', 'wp_terms', 5, 'CREATE UNIQUE INDEX wp_terms_slug_unique ON wp_terms(slug)', 2),
    $currentRecords[1],
    $currentRecords[2],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page229(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_terms_slug_taxonomy_unique)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next229',
    'wordpressUse' => 'Copied taxonomy option imports can reject a child FK that references only the left prefix of a wider UNIQUE parent index before enabling foreign_key_check.',
    'status' => $page['status'],
    'current_exact_arity_rows' => $page['current']['foreign_key_parent_key_exact_arity']['rows'],
    'current_exact_arity_blockers' => $page['current']['foreign_key_parent_key_exact_arity']['blocked'],
    'current_wider_unique_blockers' => $page['current']['foreign_key_parent_key_exact_arity']['wider_parent_unique_index'],
    'next_exact_arity_blockers' => $page['next_counts']['foreign_key_parent_key_exact_arity']['blocked'],
    'exact_arity_repaired' => $page['delta']['foreign_key_parent_key_exact_arity_repaired'],
    'source' => $page['current_source']['foreign_key_parent_key_exact_arity_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_exact_arity_rows'] !== 4
        || $summary['current_exact_arity_blockers'] !== 1
        || $summary['current_wider_unique_blockers'] !== 1
        || $summary['next_exact_arity_blockers'] !== 0
        || $summary['exact_arity_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next229 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next229 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
