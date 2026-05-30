<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$pageSize = 512;
$pageCount = 8;
$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', $pageCount), 28, 4);
$header = substr_replace($header, pack('N', 3), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$pointerMap = str_repeat("\0", $pageSize);
$putPointerMap = static function (int $pageNumber, int $type, int $parent) use (&$pointerMap): void {
    $pointerMap = substr_replace($pointerMap, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};
$putPointerMap(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMap(4, SQLitePointerMapEntry::BTREE_PAGE, 0);
$putPointerMap(5, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 0);
$putPointerMap(6, SQLitePointerMapEntry::OVERFLOW_PAGE, 5);
$putPointerMap(7, SQLitePointerMapEntry::BTREE_PAGE, 3);
$putPointerMap(8, SQLitePointerMapEntry::BTREE_PAGE, 3);

$pages = [1 => $header, 2 => $pointerMap];
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[$pageNumber] = str_repeat("\0", $pageSize);
}
ksort($pages);
$database = implode('', $pages);

$schemas = [
    'archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
            'wp_options' => [
                ['rowid' => 'archive-siteurl', 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-missing', 'option_name' => 'missing_archive'],
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
$catalog = new SQLiteAttachedSchemaCatalog([]);
$catalog->attach('archive', '/srv/wp/archive.sqlite', [
    $record('wp_options', 3),
    $record('wp_option_names', 4),
]);

$summary = SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield::page(
    $database,
    $schemas,
    "SELECT * FROM pragma_foreign_key_check('archive.wp_options')",
    0,
    5,
    'PRAGMA integrity_check',
    null,
    $catalog,
    true,
);

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'blocked'
        || $summary['current']['pointer_map'] !== 2
        || $summary['current']['foreign_key'] !== 1
        || $summary['next_state']['blocking'] !== ['integrity_pointer_map', 'foreign_key_check']
    ) {
        fwrite(STDERR, "application-pragma-foreign-key-integrity-pointermap-current-source-next104 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-foreign-key-integrity-pointermap-current-source-next104 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-foreign-key-integrity-pointermap-current-source-next104',
    'applicationUse' => 'Page copied Application archive foreign_key_check rows together with auto-vacuum pointer-map integrity blockers using a current-source cursor that rejects stale database/schema/catalog resumes without ext/sqlite.',
    'status' => $summary['status'],
    'source_id' => $summary['source_id'],
    'pointer_map_errors' => $summary['current']['pointer_map'],
    'foreign_key_rows' => $summary['current']['foreign_key'],
    'blocking' => $summary['next_state']['blocking'],
    'next_offset' => $summary['next_offset'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
