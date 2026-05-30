<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyPointerMapIntegrityYield;

$pageSize = 512;
$pageCount = 56;
$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', $pageCount), 28, 4);
$header = substr_replace($header, pack('N', 3), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$pointerMap = str_repeat("\0", $pageSize);
$put = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$pointerMap = $put($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
    $pointerMap = $put($pointerMap, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 0);
}

$pages = [$header, $pointerMap];
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[] = str_repeat("\0", $pageSize);
}

$schemas = [
    'main' => [
        'tables' => [
            'wp_posts' => [['rowid' => 1, 'ID' => 1]],
            'wp_postmeta' => [
                ['rowid' => 10, 'post_id' => 1],
                ['rowid' => 11, 'post_id' => 404],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'siteurl', 'option_name' => 'siteurl'],
                ['rowid' => 'plugin-cache', 'option_name' => 'missing_plugin'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
        ],
    ],
];

$page = SQLitePragmaForeignKeyPointerMapIntegrityYield::page(implode('', $pages), $schemas, 0, 52);
$allRows = SQLitePragmaForeignKeyPointerMapIntegrityYield::collect(implode('', $pages), $schemas);

echo json_encode([
    'status' => $page['status'],
    'count' => $page['count'],
    'total' => $page['total'],
    'next_offset' => $page['next_offset'],
    'current' => $page['current'],
    'first_source' => $page['rows'][0]['source'] ?? null,
    'first_pointer_page' => $page['rows'][0]['page'] ?? null,
    'first_foreign_key' => array_values(array_filter($allRows, static fn (array $row): bool => $row['source'] === 'foreign_key'))[0]['message'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
