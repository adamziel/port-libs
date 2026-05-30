<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(blog_id INTEGER NOT NULL, slug TEXT NOT NULL, name TEXT)', 1),
    $record('index', 'wp_terms_slug_blog_unique', 'wp_terms', 3, 'CREATE UNIQUE INDEX wp_terms_slug_blog_unique ON wp_terms(slug, blog_id)', 2),
    $record('table', 'wp_termmeta_stage', 'wp_termmeta_stage', 4, "CREATE TABLE wp_termmeta_stage(
        meta_id INTEGER PRIMARY KEY,
        blog_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        meta_key TEXT NOT NULL,
        FOREIGN KEY(blog_id, slug) REFERENCES wp_terms(blog_id, slug) ON UPDATE CASCADE
    )", 3),
];

$next = [
    $current[0],
    $record('index', 'wp_terms_blog_slug_unique', 'wp_terms', 5, 'CREATE UNIQUE INDEX wp_terms_blog_slug_unique ON wp_terms(blog_id, slug)', 4),
    $current[2],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page219(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_terms_slug_blog_unique)',
    'PRAGMA main.foreign_key_list(wp_termmeta_stage)',
    0,
    80,
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next219',
    'applicationUse' => 'Copied taxonomy/termmeta imports can reject a parent-key repair when PRAGMA index_xinfo shows a UNIQUE parent index with the same columns in the wrong order for SQLite foreign-key parent-key admission.',
    'current_permuted_parent_rows' => $page['current']['foreign_key_parent_key_permutation']['rows'],
    'current_permuted_parent_blockers' => $page['current']['foreign_key_parent_key_permutation']['permuted_parent_unique_index'],
    'next_permuted_parent_blockers' => $page['next_counts']['foreign_key_parent_key_permutation']['permuted_parent_unique_index'],
    'permutation_repaired' => $page['delta']['foreign_key_parent_key_permutation_repaired'],
    'pragmas' => [
        'PRAGMA index_xinfo(wp_terms_slug_blog_unique)',
        'PRAGMA foreign_key_list(wp_termmeta_stage)',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $summary['current_permuted_parent_rows'] !== 2
        || $summary['current_permuted_parent_blockers'] !== 2
        || $summary['next_permuted_parent_blockers'] !== 0
        || $summary['permutation_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next219 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next219 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
