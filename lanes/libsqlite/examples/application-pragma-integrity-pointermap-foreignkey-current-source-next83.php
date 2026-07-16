<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegritySourceCursor;

$pageSize = 512;
$pageCount = 70;

$putPointerMapEntry = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);

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
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl'],
            ],
            'wp_options' => [
                ['rowid' => 'option-1', 'option_name' => 'missing_1'],
                ['rowid' => 'option-2', 'option_name' => 'missing_2'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 13, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

$first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($database, $schemas, 'PRAGMA main.foreign_key_check(wp_options)', 0, 40);
$second = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($database, $schemas, 'PRAGMA main.foreign_key_check(wp_options)', 40, 40, 'PRAGMA integrity_check', [
    'source_id' => $first['source_id'],
    'next_offset' => $first['next_offset'],
]);

if (($argv[1] ?? null) === '--self-test') {
    if ($first['next']['offset'] !== 40 || $second['source_id'] !== $first['source_id'] || $second['rows'][0]['source'] !== 'pointer_map') {
        fwrite(STDERR, "application-pragma-integrity-pointermap-foreignkey-current-source-next83 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-integrity-pointermap-foreignkey-current-source-next83 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'copied wp_options pragma integrity pointer-map foreign-key current source next83',
    'applicationUse' => 'Resume copied Application SQLite integrity and foreign_key_check pagination only when the database image, pragma SQL, and staged wp_options FK rows still match the original source.',
    'first' => [
        'source_id' => $first['source_id'],
        'count' => $first['count'],
        'total' => $first['total'],
        'next' => $first['next'],
        'current' => $first['current'],
    ],
    'second' => [
        'source_id' => $second['source_id'],
        'offset' => $second['offset'],
        'count' => $second['count'],
        'first_source' => $second['rows'][0]['source'] ?? null,
        'complete' => $second['complete'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
