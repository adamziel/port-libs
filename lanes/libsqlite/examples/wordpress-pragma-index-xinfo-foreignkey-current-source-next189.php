<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE, active INTEGER)', 1),
    $record('table', 'wp_termmeta', 'wp_termmeta', 5, 'CREATE TABLE wp_termmeta(meta_id INTEGER PRIMARY KEY, term_slug TEXT COLLATE NOCASE, meta_key TEXT, FOREIGN KEY(term_slug) REFERENCES wp_terms(slug))', 2),
    $record('index', 'wp_terms_slug_active_unique', 'wp_terms', 6, 'CREATE UNIQUE INDEX wp_terms_slug_active_unique ON wp_terms(slug COLLATE NOCASE) WHERE active = 1', 3),
    $record('index', 'wp_terms_slug_lower_unique', 'wp_terms', 7, 'CREATE UNIQUE INDEX wp_terms_slug_lower_unique ON wp_terms(lower(slug))', 4),
    $record('index', 'wp_termmeta_slug_lookup', 'wp_termmeta', 8, 'CREATE INDEX wp_termmeta_slug_lookup ON wp_termmeta(term_slug COLLATE NOCASE)', 5),
];
$nextRecords = [
    $records[0],
    $records[1],
    $record('index', 'wp_terms_slug_unique', 'wp_terms', 9, 'CREATE UNIQUE INDEX wp_terms_slug_unique ON wp_terms(slug COLLATE NOCASE)', 6),
    $records[4],
];
$tables = [
    'wp_terms' => [
        ['rowid' => 1, 'term_id' => 1, 'slug' => 'news', 'active' => 1],
    ],
    'wp_termmeta' => [
        ['rowid' => 1, 'meta_id' => 1, 'term_slug' => 'news', 'meta_key' => '_wp_attachment_metadata'],
    ],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog189(
    $records,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_terms_slug_unique)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_rejected_parent_unique_indexes']['rows'] !== 2
        || $page['next_counts']['foreign_key_rejected_parent_unique_indexes']['rows'] !== 0
        || $page['delta']['foreign_key_rejected_parent_unique_cleared'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next189 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next189 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next189',
    'wordpressUse' => 'During term/meta import preflight, show why partial or expression UNIQUE indexes visible through PRAGMA index_xinfo do not satisfy a foreign-key parent key, and confirm the repaired full UNIQUE index.',
    'status' => $page['status'],
    'current_rejected_parent_unique' => $page['current']['foreign_key_rejected_parent_unique_indexes'],
    'next_rejected_parent_unique' => $page['next_counts']['foreign_key_rejected_parent_unique_indexes'],
    'delta' => $page['delta'],
    'rejected_rows' => array_values(array_filter($page['rows'], static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_rejected_parent_unique')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
