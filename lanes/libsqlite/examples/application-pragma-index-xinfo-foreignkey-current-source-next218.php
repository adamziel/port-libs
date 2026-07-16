<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL)', 1),
    $record('table', 'wp_posts', 'wp_posts', 3, 'CREATE TABLE wp_posts(blog_id INTEGER NOT NULL, post_id INTEGER NOT NULL, PRIMARY KEY(blog_id, post_id)) WITHOUT ROWID', 2),
    $record('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 4, null, 3),
    $record('table', 'wp_import_edges', 'wp_import_edges', 5, "CREATE TABLE wp_import_edges(
        edge_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
        FOREIGN KEY(blog_id, post_id) REFERENCES wp_posts(blog_id, post_id) ON UPDATE RESTRICT DEFERRABLE INITIALLY DEFERRED
    )", 4),
    $record('index', 'wp_import_edges_term', 'wp_import_edges', 6, 'CREATE INDEX wp_import_edges_term ON wp_import_edges(term_id)', 5),
    $record('index', 'wp_import_edges_post', 'wp_import_edges', 7, 'CREATE INDEX wp_import_edges_post ON wp_import_edges(blog_id, post_id)', 6),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('table', 'wp_import_edges', 'wp_import_edges', 5, "CREATE TABLE wp_import_edges(
        edge_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE NO ACTION DEFERRABLE INITIALLY DEFERRED,
        FOREIGN KEY(blog_id, post_id) REFERENCES wp_posts(blog_id, post_id) ON UPDATE NO ACTION DEFERRABLE INITIALLY DEFERRED
    )", 4),
    $currentRecords[4],
    $currentRecords[5],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page218(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_import_edges_post)',
    'PRAGMA main.foreign_key_list(wp_import_edges)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next218',
    'applicationUse' => 'Copied Application import schemas can flag DEFERRABLE foreign keys whose RESTRICT actions still block parent repairs immediately before commit.',
    'status' => $page['status'],
    'current_restrict_rows' => $page['current']['foreign_key_restrict_timing']['rows'],
    'current_immediate_restrict_rows' => $page['current']['foreign_key_restrict_timing']['restrict_immediate'],
    'next_immediate_restrict_rows' => $page['next_counts']['foreign_key_restrict_timing']['restrict_immediate'],
    'restrict_timing_repaired' => $page['delta']['foreign_key_restrict_timing_repaired'],
    'source' => $page['current_source']['foreign_key_restrict_timing_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_restrict_rows'] !== 3
        || $summary['current_immediate_restrict_rows'] !== 3
        || $summary['next_immediate_restrict_rows'] !== 0
        || $summary['restrict_timing_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next218 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next218 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
