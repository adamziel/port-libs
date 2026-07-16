<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegritySourceCursor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$pageSize = 512;
$pageCount = 76;

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', $pageCount), 28, 4);
$header = substr_replace($header, pack('N', 3), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$pointerMap = str_repeat("\0", $pageSize);
$pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
    $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 0);
}

$pages = [$header, $pointerMap];
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[] = str_repeat("\0", $pageSize);
}
$database = implode('', $pages);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 2, 'option_name' => 'main_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
            'wp_options' => [
                ['rowid' => 'archive-siteurl', 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-missing-1', 'option_name' => 'missing_1'],
                ['rowid' => 'archive-missing-2', 'option_name' => 'missing_2'],
                ['rowid' => 'archive-missing-3', 'option_name' => 'missing_3'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 9, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

$record = static fn (string $name, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name,
    $root,
);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('wp_options', 3),
    $record('wp_option_names', 4),
]);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', [
    $record('wp_options', 5),
    $record('wp_option_names', 6),
]);

$sql = "SELECT * FROM pragma_foreign_key_check('archive.wp_options')";
$first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database, $schemas, $sql, 0, 37, 'PRAGMA integrity_check', null, $catalog);
$second = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database, $schemas, $sql, 37, 37, 'PRAGMA integrity_check', $first['next'], $catalog);

$wrongOffsetRejected = false;
try {
    SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database, $schemas, $sql, 38, 37, 'PRAGMA integrity_check', $first['next'], $catalog);
} catch (InvalidArgumentException) {
    $wrongOffsetRejected = true;
}

$summary = [
    'scenario' => 'copied wp_options integrity pointer-map plus foreign_key_check current-source next94',
    'applicationUse' => 'Repair and migration preflights can resume paged pointer-map integrity and attached foreign-key diagnostics only at the exact emitted next offset.',
    'sourceId' => $first['source_id'],
    'counts' => $first['current'],
    'firstPage' => ['offset' => $first['offset'], 'count' => $first['count'], 'next' => $first['next']],
    'secondPage' => ['offset' => $second['offset'], 'count' => $second['count'], 'firstRow' => $second['rows'][0]],
    'wrongOffsetRejected' => $wrongOffsetRejected,
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['counts']['pointer_map'] !== 73
        || $summary['counts']['foreign_key'] !== 3
        || $summary['firstPage']['next']['offset'] !== 37
        || $summary['secondPage']['firstRow']['page'] !== 41
        || $summary['wrongOffsetRejected'] !== true
    ) {
        fwrite(STDERR, "application-pragma-integrity-pointermap-foreignkey-current-source-next94 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-integrity-pointermap-foreignkey-current-source-next94 self-test passed\n");
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
