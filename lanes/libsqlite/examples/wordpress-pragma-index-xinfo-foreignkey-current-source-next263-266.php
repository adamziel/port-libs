<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_terms_263', 'wp_terms_263', 2, 'CREATE TABLE wp_terms_263(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_263_1', 'wp_terms_263', 3, null, 2),
    $record('table', 'wp_termmeta_263', 'wp_termmeta_263', 4, 'CREATE TABLE wp_termmeta_263(term_id INTEGER, FOREIGN KEY(term_id) REFERENCES wp_terms_263(term_id) ON DELETE CASCADE)', 3),
    $record('table', 'wp_termmeta_264', 'wp_termmeta_264', 5, 'CREATE TABLE wp_termmeta_264(term_id INTEGER, active INTEGER, FOREIGN KEY(term_id) REFERENCES wp_terms_263(term_id) ON UPDATE SET NULL)', 4),
    $record('index', 'wp_termmeta_264_partial', 'wp_termmeta_264', 6, 'CREATE INDEX wp_termmeta_264_partial ON wp_termmeta_264(term_id) WHERE active = 1', 5),
    $record('table', 'wp_termmeta_265', 'wp_termmeta_265', 7, "CREATE TABLE wp_termmeta_265(slug TEXT DEFAULT 'uncategorized', FOREIGN KEY(slug) REFERENCES wp_terms_263(slug) ON DELETE SET DEFAULT)", 6),
    $record('index', 'wp_termmeta_265_expr', 'wp_termmeta_265', 8, 'CREATE INDEX wp_termmeta_265_expr ON wp_termmeta_265(lower(slug))', 7),
    $record('table', 'wp_pairs_266', 'wp_pairs_266', 9, 'CREATE TABLE wp_pairs_266(a INTEGER, b INTEGER, UNIQUE(a, b))', 8),
    $record('index', 'sqlite_autoindex_wp_pairs_266_1', 'wp_pairs_266', 10, null, 9),
    $record('table', 'wp_pairmeta_266', 'wp_pairmeta_266', 11, 'CREATE TABLE wp_pairmeta_266(a INTEGER, b INTEGER, FOREIGN KEY(a, b) REFERENCES wp_pairs_266(a, b) ON UPDATE RESTRICT)', 10),
    $record('index', 'wp_pairmeta_266_b_a', 'wp_pairmeta_266', 12, 'CREATE INDEX wp_pairmeta_266_b_a ON wp_pairmeta_266(b, a)', 11),
];

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next263-266',
    'wordpressUse' => 'Copied WordPress taxonomy and metadata tables can bind foreign-key CASCADE, SET NULL, SET DEFAULT, and RESTRICT actions to child-side lookup index diagnostics before admitting the next schema image.',
    'next263_cascade_blocked' => count(array_filter(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows263($records, 'next', 'CASCADE'), static fn (array $row): bool => $row['blocked'] === true)),
    'next264_set_null_blocked' => count(array_filter(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows263($records, 'next', 'SET NULL'), static fn (array $row): bool => $row['blocked'] === true)),
    'next265_set_default_blocked' => count(array_filter(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows263($records, 'next', 'SET DEFAULT'), static fn (array $row): bool => $row['blocked'] === true)),
    'next266_restrict_blocked' => count(array_filter(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows263($records, 'next', 'RESTRICT'), static fn (array $row): bool => $row['blocked'] === true)),
    'implemented_pages' => array_values(array_filter(
        range(263, 266),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['next263_cascade_blocked'] !== 1
        || $summary['next264_set_null_blocked'] !== 1
        || $summary['next265_set_default_blocked'] !== 1
        || $summary['next266_restrict_blocked'] !== 1
        || $summary['implemented_pages'] !== [263, 264, 265, 266]
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next263-266 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next263-266 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
