<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCurrentNextYield;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 75;
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

$schemas = [
    'main' => [
        'tables' => [
            'wp_posts' => [['rowid' => 1, 'ID' => 1]],
            'wp_postmeta' => array_map(static fn (int $i): array => ['rowid' => $i, 'post_id' => 100 + $i], range(1, 8)),
        ],
        'foreignKeys' => [
            ['id' => 9, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
        ],
    ],
];

$page = SQLitePragmaIntegrityCurrentNextYield::page(implode('', $pages), $schemas, 64, 64);

echo json_encode([
    'scenario' => 'copied wp_posts/wp_postmeta PRAGMA integrity_check current/next64',
    'status' => $page['status'],
    'offset' => $page['offset'],
    'count' => $page['count'],
    'total' => $page['total'],
    'complete' => $page['complete'],
    'current' => $page['current'],
    'first_row' => $page['rows'][0] ?? null,
    'last_row' => $page['rows'][$page['count'] - 1] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
