<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_slug_parent', 'wp_slug_parent', 2, 'CREATE TABLE wp_slug_parent(site_id INTEGER NOT NULL, slug TEXT NOT NULL)', 1),
    $record('index', 'wp_slug_parent_site_lower_slug_unique', 'wp_slug_parent', 3, 'CREATE UNIQUE INDEX wp_slug_parent_site_lower_slug_unique ON wp_slug_parent(site_id, lower(slug))', 2),
    $record('table', 'wp_import_slugmeta', 'wp_import_slugmeta', 4, "CREATE TABLE wp_import_slugmeta(
        meta_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        meta_key TEXT NOT NULL,
        FOREIGN KEY(site_id, slug) REFERENCES wp_slug_parent(site_id, slug) ON DELETE CASCADE
    )", 3),
];

$next = [
    $current[0],
    $record('index', 'wp_slug_parent_site_slug_unique', 'wp_slug_parent', 5, 'CREATE UNIQUE INDEX wp_slug_parent_site_slug_unique ON wp_slug_parent(site_id, slug)', 4),
    $current[2],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page234(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_slug_parent_site_lower_slug_unique)',
    'PRAGMA main.foreign_key_list(wp_import_slugmeta)',
    0,
    160,
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next234',
    'applicationUse' => 'Copied Application import schemas can reject an expression-backed UNIQUE parent index exposed by PRAGMA index_xinfo(cid=-2,name=NULL) before trusting PRAGMA foreign_key_list parent-key repair diagnostics.',
    'current_expression_parent_key_rows' => $page['current']['foreign_key_expression_parent_key']['rows'],
    'current_expression_parent_key_blockers' => $page['current']['foreign_key_expression_parent_key']['expression_parent_unique_index'],
    'current_expression_terms' => $page['current']['foreign_key_expression_parent_key']['expression_terms'],
    'next_expression_parent_key_ok' => $page['next_counts']['foreign_key_expression_parent_key']['ok'],
    'expression_parent_key_repaired' => $page['delta']['foreign_key_expression_parent_key_repaired'],
    'source' => $page['current_source']['foreign_key_expression_parent_key_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(wp_slug_parent_site_lower_slug_unique)',
        'PRAGMA main.foreign_key_list(wp_import_slugmeta)',
    ],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (
        $summary['current_expression_parent_key_rows'] !== 2
        || $summary['current_expression_parent_key_blockers'] !== 2
        || $summary['current_expression_terms'] !== 1
        || $summary['next_expression_parent_key_ok'] !== 2
        || $summary['expression_parent_key_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next234 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next234 self-test passed\n");
}

return $summary;
