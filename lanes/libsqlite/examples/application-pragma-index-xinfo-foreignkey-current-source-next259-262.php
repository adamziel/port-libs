<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_terms_259', 'wp_terms_259', 2, 'CREATE TABLE wp_terms_259(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_259_1', 'wp_terms_259', 3, null, 2),
    $record('table', 'wp_termmeta_259', 'wp_termmeta_259', 4, 'CREATE TABLE wp_termmeta_259(meta_id INTEGER PRIMARY KEY, term_id INTEGER REFERENCES wp_terms_259(term_id), active INTEGER)', 3),
    $record('table', 'wp_termmeta_260', 'wp_termmeta_260', 5, 'CREATE TABLE wp_termmeta_260(meta_id INTEGER PRIMARY KEY, term_id INTEGER REFERENCES wp_terms_259(term_id), active INTEGER)', 4),
    $record('index', 'wp_termmeta_260_partial', 'wp_termmeta_260', 6, 'CREATE INDEX wp_termmeta_260_partial ON wp_termmeta_260(term_id) WHERE active = 1', 5),
    $record('table', 'wp_termmeta_261', 'wp_termmeta_261', 7, 'CREATE TABLE wp_termmeta_261(slug TEXT REFERENCES wp_terms_259(slug))', 6),
    $record('index', 'wp_termmeta_261_expr', 'wp_termmeta_261', 8, 'CREATE INDEX wp_termmeta_261_expr ON wp_termmeta_261(lower(slug))', 7),
    $record('table', 'wp_pairs_262', 'wp_pairs_262', 9, 'CREATE TABLE wp_pairs_262(a INTEGER, b INTEGER, UNIQUE(a, b))', 8),
    $record('index', 'sqlite_autoindex_wp_pairs_262_1', 'wp_pairs_262', 10, null, 9),
    $record('table', 'wp_pairmeta_262', 'wp_pairmeta_262', 11, 'CREATE TABLE wp_pairmeta_262(a INTEGER, b INTEGER, FOREIGN KEY(a, b) REFERENCES wp_pairs_262(a, b))', 10),
    $record('index', 'wp_pairmeta_262_b_a', 'wp_pairmeta_262', 12, 'CREATE INDEX wp_pairmeta_262_b_a ON wp_pairmeta_262(b, a)', 11),
];

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next259-262',
    'applicationUse' => 'Copied Application taxonomy tables can distinguish absent, partial, expression, and misordered child-side lookup indexes before cascading foreign-key actions scan large child tables.',
    'next259_missing' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childLookupIndexRows259($records, 'next', 'missing_child_lookup_index')),
    'next260_partial' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childLookupIndexRows259($records, 'next', 'partial_child_lookup_index')),
    'next261_expression' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childLookupIndexRows259($records, 'next', 'expression_child_lookup_index')),
    'next262_misordered' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childLookupIndexRows259($records, 'next', 'misordered_child_lookup_index')),
    'implemented_pages' => array_values(array_filter(
        range(259, 262),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['next259_missing'] !== 1
        || $summary['next260_partial'] !== 1
        || $summary['next261_expression'] !== 1
        || $summary['next262_misordered'] !== 1
        || $summary['implemented_pages'] !== [259, 260, 261, 262]
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next259-262 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next259-262 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
