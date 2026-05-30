<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegritySourceCursor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $name, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name,
    $root,
);

$catalog = static fn (bool $tempOptions): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog(
    [
        $record('wp_options', 2),
        $record('wp_option_names', 3),
    ],
    $tempOptions ? [
        $record('wp_options', 4),
        $record('wp_option_names', 5),
    ] : [],
);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 11, 'option_name' => 'siteurl'],
                ['rowid' => 12, 'option_name' => 'main_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase']]],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_name' => 'siteurl'],
                ['rowid' => 'temp-2', 'option_name' => 'temp_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase']]],
        ],
    ],
];

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$sql = "SELECT * FROM pragma_foreign_key_check('wp_options')";
$first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
    $database,
    $schemas,
    $sql,
    0,
    1,
    'PRAGMA quick_check',
    null,
    $catalog(false),
);
$shadowed = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
    $database,
    $schemas,
    $sql,
    0,
    1,
    'PRAGMA quick_check',
    null,
    $catalog(true),
);

$staleRejected = false;
try {
    SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
        $database,
        $schemas,
        $sql,
        1,
        1,
        'PRAGMA quick_check',
        ['source_id' => $first['source_id'], 'next_offset' => 1],
        $catalog(true),
    );
} catch (InvalidArgumentException) {
    $staleRejected = true;
}

$summary = [
    'scenario' => 'copied wp_options PRAGMA foreign_key_check pagination current source next97',
    'initial' => [
        'source_id' => $first['source_id'],
        'catalog_hash' => $first['current_source']['catalog_hash'],
        'schema' => $first['rows'][0]['schema'] ?? null,
        'rowid' => $first['rows'][0]['rowid'] ?? null,
    ],
    'after_temp_shadow' => [
        'source_id' => $shadowed['source_id'],
        'catalog_hash' => $shadowed['current_source']['catalog_hash'],
        'schema' => $shadowed['rows'][0]['schema'] ?? null,
        'rowid' => $shadowed['rows'][0]['rowid'] ?? null,
    ],
    'stale_cursor_rejected' => $staleRejected,
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['initial']['schema'] === 'main');
    assert($summary['after_temp_shadow']['schema'] === 'temp');
    assert($summary['initial']['source_id'] !== $summary['after_temp_shadow']['source_id']);
    assert($summary['stale_cursor_rejected'] === true);
    echo "application-pragma-fk-integrity-pagination-current-source-next97 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
