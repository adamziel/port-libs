<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms_255', 'wp_terms_255', 2, 'CREATE TABLE wp_terms_255(slug TEXT COLLATE NOCASE)', 1),
    $record('index', 'wp_terms_255_slug_binary', 'wp_terms_255', 3, 'CREATE UNIQUE INDEX wp_terms_255_slug_binary ON wp_terms_255(slug COLLATE BINARY)', 2),
    $record('table', 'wp_termmeta_255', 'wp_termmeta_255', 4, 'CREATE TABLE wp_termmeta_255(slug TEXT, FOREIGN KEY(slug) REFERENCES wp_terms_255(slug))', 3),
];

$next = [
    $record('table', 'wp_terms_258', 'wp_terms_258', 5, 'CREATE TABLE wp_terms_258(slug TEXT NOT NULL)', 4),
    $record('index', 'wp_terms_258_slug_desc', 'wp_terms_258', 6, 'CREATE UNIQUE INDEX wp_terms_258_slug_desc ON wp_terms_258(slug DESC)', 5),
    $record('table', 'wp_termmeta_258', 'wp_termmeta_258', 7, 'CREATE TABLE wp_termmeta_258(slug TEXT, FOREIGN KEY(slug) REFERENCES wp_terms_258(slug))', 6),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page258(
    $current,
    $next,
    'PRAGMA index_xinfo(wp_terms_258_slug_desc)',
    'PRAGMA main.foreign_key_list(wp_termmeta_258)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next255-258',
    'status' => $page['operation'],
    'current_collation_blockers' => $page['current']['foreign_key_parent_key_collation']['blocked'],
    'next_descending_rows' => $page['next_counts']['foreign_key_descending_parent_key']['rows'],
    'next_descending_blockers' => $page['next_counts']['foreign_key_descending_parent_key']['blocked'],
    'dependencies' => $page['dependencies'],
    'total' => $page['total'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'pragma-index-xinfo-foreignkey-current-source-next258'
        || $summary['current_collation_blockers'] !== 1
        || $summary['next_descending_rows'] !== 1
        || $summary['next_descending_blockers'] !== 0
        || !in_array('sqlite-pragma-foreign-key-parent-key-collation', $summary['dependencies'], true)
        || !in_array('sqlite-pragma-foreign-key-descending-parent-key', $summary['dependencies'], true)
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next255-258 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next255-258 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
