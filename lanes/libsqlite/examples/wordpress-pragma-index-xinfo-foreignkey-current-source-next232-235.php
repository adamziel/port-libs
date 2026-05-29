<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$next232Records = [
    $record('table', 'wp_fk_parent_232', 'wp_fk_parent_232', 2, 'CREATE TABLE wp_fk_parent_232(a INTEGER, b INTEGER, PRIMARY KEY(a, b)) WITHOUT ROWID', 1),
    $record('index', 'sqlite_autoindex_wp_fk_parent_232_1', 'wp_fk_parent_232', 3, null, 2),
    $record('table', 'wp_fk_child_232', 'wp_fk_child_232', 4, 'CREATE TABLE wp_fk_child_232(a INTEGER, b INTEGER, payload TEXT, FOREIGN KEY(a, b) REFERENCES wp_fk_parent_232(a, b) ON DELETE CASCADE)', 3),
    $record('index', 'wp_fk_child_232_payload_ab', 'wp_fk_child_232', 5, 'CREATE INDEX wp_fk_child_232_payload_ab ON wp_fk_child_232(payload, a, b)', 4),
];

$next233Records = [
    $record('table', 'wp_fk_parent_233', 'wp_fk_parent_233', 6, 'CREATE TABLE wp_fk_parent_233(code TEXT PRIMARY KEY)', 5),
    $record('index', 'sqlite_autoindex_wp_fk_parent_233_1', 'wp_fk_parent_233', 7, null, 6),
    $record('table', 'wp_fk_child_233', 'wp_fk_child_233', 8, 'CREATE TABLE wp_fk_child_233(code TEXT, meta_key TEXT, FOREIGN KEY(code) REFERENCES wp_fk_parent_233(code))', 7),
    $record('index', 'wp_fk_child_233_expr_code', 'wp_fk_child_233', 9, 'CREATE INDEX wp_fk_child_233_expr_code ON wp_fk_child_233(lower(meta_key), code)', 8),
];

$next234Records = [
    $record('table', 'wp_fk_parent_234', 'wp_fk_parent_234', 10, 'CREATE TABLE wp_fk_parent_234(slug TEXT NOT NULL)', 9),
    $record('index', 'wp_fk_parent_234_lower_slug_unique', 'wp_fk_parent_234', 11, 'CREATE UNIQUE INDEX wp_fk_parent_234_lower_slug_unique ON wp_fk_parent_234(lower(slug))', 10),
    $record('table', 'wp_fk_child_234', 'wp_fk_child_234', 12, 'CREATE TABLE wp_fk_child_234(slug TEXT, FOREIGN KEY(slug) REFERENCES wp_fk_parent_234(slug))', 11),
];

$next235Records = [
    $record('table', 'wp_fk_parent_235', 'wp_fk_parent_235', 13, 'CREATE TABLE wp_fk_parent_235(slug TEXT NOT NULL)', 12),
    $record('index', 'wp_fk_parent_235_slug_desc_unique', 'wp_fk_parent_235', 14, 'CREATE UNIQUE INDEX wp_fk_parent_235_slug_desc_unique ON wp_fk_parent_235(slug DESC)', 13),
    $record('table', 'wp_fk_child_235', 'wp_fk_child_235', 15, 'CREATE TABLE wp_fk_child_235(slug TEXT, FOREIGN KEY(slug) REFERENCES wp_fk_parent_235(slug))', 14),
];

$next232Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionPrefixRows232($next232Records);
$next233Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childExpressionPrefixRows233($next233Records);
$next234Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows234($next234Records);
$next235Rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235($next235Records);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next232-235',
    'wordpressUse' => 'Bulk WordPress schema import checks can separate child-action prefix blockers, expression-prefixed child indexes, expression parent UNIQUE indexes, and DESC parent UNIQUE metadata.',
    'next232_status' => $next232Rows[0]['status'],
    'next232_misordered_index' => $next232Rows[0]['misordered_child_index'],
    'next233_status' => $next233Rows[0]['status'],
    'next233_expression_terms' => $next233Rows[0]['expression_terms'],
    'next234_status' => $next234Rows[0]['status'],
    'next234_expression_unique_index' => $next234Rows[0]['expression_unique_index'],
    'next235_status' => $next235Rows[0]['status'],
    'next235_desc_columns' => $next235Rows[0]['descending_key_columns'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['next232_status'] !== 'misordered_child_action_index'
        || $summary['next233_status'] !== 'expression_prefix_child_index'
        || $summary['next233_expression_terms'] !== ['lower(meta_key)']
        || $summary['next234_status'] !== 'expression_parent_unique_index'
        || $summary['next235_status'] !== 'ok'
        || $summary['next235_desc_columns'] !== ['slug']
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next232-235 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next232-235 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
