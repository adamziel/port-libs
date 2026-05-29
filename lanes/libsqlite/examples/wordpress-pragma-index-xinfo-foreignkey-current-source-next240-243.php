<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$next240Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentPrimaryKeyRows240([
    $record('table', 'wp_fk_parent_240', 'wp_fk_parent_240', 2, 'CREATE TABLE wp_fk_parent_240(site_id INTEGER NOT NULL, term_id INTEGER NOT NULL, PRIMARY KEY(site_id, term_id))', 1),
    $record('table', 'wp_fk_child_240', 'wp_fk_child_240', 3, 'CREATE TABLE wp_fk_child_240(legacy_parent INTEGER NOT NULL, FOREIGN KEY(legacy_parent) REFERENCES wp_fk_parent_240)', 2),
]);

$next241Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentReferenceRows241([
    $record('table', 'wp_fk_parent_241', 'wp_fk_parent_241', 4, 'CREATE TABLE wp_fk_parent_241(a INTEGER, b INTEGER, PRIMARY KEY(b, a))', 3),
    $record('table', 'wp_fk_child_241', 'wp_fk_child_241', 5, 'CREATE TABLE wp_fk_child_241(x INTEGER, y INTEGER, FOREIGN KEY(x, y) REFERENCES wp_fk_parent_241)', 4),
]);

$next242Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidParentKeyRows242([
    $record('table', 'wp_fk_parent_242', 'wp_fk_parent_242', 6, 'CREATE TABLE wp_fk_parent_242(slug TEXT NOT NULL UNIQUE)', 5),
    $record('index', 'sqlite_autoindex_wp_fk_parent_242_1', 'wp_fk_parent_242', 7, null, 6),
    $record('table', 'wp_fk_child_242', 'wp_fk_child_242', 8, 'CREATE TABLE wp_fk_child_242(parent_row INTEGER, FOREIGN KEY(parent_row) REFERENCES wp_fk_parent_242(rowid))', 7),
]);

$next243Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeyAffinityRows243([
    $record('table', 'wp_fk_parent_243', 'wp_fk_parent_243', 9, 'CREATE TABLE wp_fk_parent_243(id INTEGER PRIMARY KEY, score NUMERIC)', 8),
    $record('table', 'wp_fk_child_243', 'wp_fk_child_243', 10, 'CREATE TABLE wp_fk_child_243(parent_id TEXT REFERENCES wp_fk_parent_243(id), score_text TEXT REFERENCES wp_fk_parent_243(score))', 9),
]);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next240-243',
    'wordpressUse' => 'Bulk WordPress schema import checks can keep implicit parent primary keys, implicit parent reference resolution, rowid alias rejection, and FK affinity mismatches visible together.',
    'next240_status' => $next240Rows[0]['status'],
    'next240_parent_primary_key' => $next240Rows[0]['parent_primary_key_columns'],
    'next241_status' => $next241Rows[0]['status'],
    'next241_resolved_parent_columns' => array_column($next241Rows, 'resolved_to'),
    'next242_status' => $next242Rows[0]['status'],
    'next242_rowid_alias' => $next242Rows[0]['rowid_alias'],
    'next243_statuses' => array_column($next243Rows, 'status'),
    'next243_parent_affinities' => array_column($next243Rows, 'parent_affinity'),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['next240_status'] !== 'parent_primary_key_arity_mismatch'
        || $summary['next240_parent_primary_key'] !== ['site_id', 'term_id']
        || $summary['next241_status'] !== 'ok_implicit_parent_primary_key'
        || $summary['next241_resolved_parent_columns'] !== ['b', 'a']
        || $summary['next242_status'] !== 'rowid_alias_parent_key'
        || $summary['next242_rowid_alias'] !== 'rowid'
        || $summary['next243_statuses'] !== ['affinity_mismatch', 'affinity_mismatch']
        || $summary['next243_parent_affinities'] !== ['INTEGER', 'NUMERIC']
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next240-243 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next240-243 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
