<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$next252Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingChildColumnRows252([
    $record('table', 'wp_terms_252', 'wp_terms_252', 2, 'CREATE TABLE wp_terms_252(slug TEXT PRIMARY KEY, taxonomy TEXT UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_252_1', 'wp_terms_252', 3, null, 2),
    $record('index', 'sqlite_autoindex_wp_terms_252_2', 'wp_terms_252', 4, null, 3),
    $record('table', 'wp_termmeta_252', 'wp_termmeta_252', 5, 'CREATE TABLE wp_termmeta_252(slug_ref TEXT REFERENCES wp_terms_252(slug), FOREIGN KEY(taxonomy_ref) REFERENCES wp_terms_252(taxonomy))', 4),
]);

$next253Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::generatedChildActionRows253([
    $record('table', 'wp_terms_253', 'wp_terms_253', 6, 'CREATE TABLE wp_terms_253(slug TEXT PRIMARY KEY, term_id INTEGER UNIQUE)', 5),
    $record('index', 'sqlite_autoindex_wp_terms_253_1', 'wp_terms_253', 7, null, 6),
    $record('index', 'sqlite_autoindex_wp_terms_253_2', 'wp_terms_253', 8, null, 7),
    $record('table', 'wp_termmeta_253', 'wp_termmeta_253', 9, 'CREATE TABLE wp_termmeta_253(raw_slug TEXT, raw_id INTEGER, slug_ref TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL NOT NULL REFERENCES wp_terms_253(slug) ON DELETE SET NULL, term_ref INTEGER GENERATED ALWAYS AS (raw_id) STORED NOT NULL, FOREIGN KEY(term_ref) REFERENCES wp_terms_253(term_id) ON UPDATE SET DEFAULT)', 8),
]);

$next254Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullableParentKeyRows254([
    $record('table', 'wp_terms_254', 'wp_terms_254', 10, 'CREATE TABLE wp_terms_254(slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM, UNIQUE(slug, taxonomy))', 9),
    $record('index', 'sqlite_autoindex_wp_terms_254_1', 'wp_terms_254', 11, null, 10),
    $record('table', 'wp_term_relationships_254', 'wp_term_relationships_254', 12, 'CREATE TABLE wp_term_relationships_254(slug TEXT, taxonomy TEXT, FOREIGN KEY(slug, taxonomy) REFERENCES wp_terms_254(slug, taxonomy) ON DELETE CASCADE)', 11),
]);

$methods = get_class_methods(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next252-255',
    'wordpressUse' => 'WordPress taxonomy import checks can keep missing child columns, generated child SET actions, and nullable parent UNIQUE keys visible while avoiding unrelated next255 clusters.',
    'next252_status' => $next252Rows[0]['status'],
    'next252_missing_from' => $next252Rows[0]['from'],
    'next253_blocked_rows' => count(array_filter($next253Rows, static fn (array $row): bool => ($row['blocked'] ?? false) === true)),
    'next253_statuses' => array_values(array_unique(array_column($next253Rows, 'status'))),
    'next254_nullable_rows' => count(array_filter($next254Rows, static fn (array $row): bool => ($row['status'] ?? null) === 'nullable_parent_key')),
    'next254_parent_index' => $next254Rows[0]['parent_unique_index'],
    'pragma_next255_present' => in_array('page255', $methods, true),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['next252_status'] !== 'missing_child_column'
        || $summary['next252_missing_from'] !== 'taxonomy_ref'
        || $summary['next253_blocked_rows'] !== 2
        || $summary['next253_statuses'] !== ['set_null_generated_notnull_child', 'set_default_generated_null_child']
        || $summary['next254_nullable_rows'] !== 2
        || $summary['next254_parent_index'] !== 'sqlite_autoindex_wp_terms_254_1'
        || $summary['pragma_next255_present'] !== false
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next252-255 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next252-255 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
