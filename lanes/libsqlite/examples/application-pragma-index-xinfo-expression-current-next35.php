<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowId,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('index', 'wp_options_expr_main', 'wp_options', 3, "CREATE INDEX wp_options_expr_main ON wp_options(lower(option_name) COLLATE nocase DESC, json_extract(option_value, '$.enabled'), autoload)", 2),
]);

$cursor = $catalog->executeSchemaPragmaCursor('PRAGMA index_xinfo(wp_options_expr_main)');
$first = $cursor->current();
$second = $cursor->next();
$third = $cursor->next();

echo json_encode([
    'applicationUse' => 'Walk PRAGMA index_xinfo current/next rows for copied wp_options expression indexes so schema diagnostics can stream expression terms, ordinary columns, and rowid auxiliary rows without ext/sqlite.',
    'index' => 'wp_options_expr_main',
    'metadata' => $cursor->metadata(),
    'firstExpression' => $first,
    'secondExpression' => $second,
    'firstColumn' => $third,
    'remainingRows' => $cursor->remainingRows(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
