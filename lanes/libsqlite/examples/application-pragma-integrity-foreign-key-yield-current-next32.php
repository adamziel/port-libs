<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIntegrityForeignKeyYield;

$pageSize = 512;
$database = str_repeat("\0", $pageSize);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', $pageSize), 16, 2);
$database[18] = "\x09";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 56, 4);

$children = [];
for ($i = 1; $i <= 34; $i++) {
    $children[] = ['rowid' => $i, 'post_id' => 1000 + $i];
}
$schemas = [
    'main' => [
        'tables' => [
            'wp_posts' => [['rowid' => 1, 'ID' => 1]],
            'wp_postmeta' => $children,
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
        ],
    ],
];

$first = SQLitePragmaIntegrityForeignKeyYield::page($database, $schemas, 0, 32);
$next = $first['next_offset'] === null ? null : SQLitePragmaIntegrityForeignKeyYield::page($database, $schemas, $first['next_offset'], 32);

echo json_encode([
    'scenario' => 'copied wp_options integrity and foreign-key streaming preflight',
    'firstPage' => [
        'count' => $first['count'],
        'total' => $first['total'],
        'nextOffset' => $first['next_offset'],
        'firstKind' => $first['rows'][0]['kind'] ?? null,
        'lastKind' => $first['rows'][$first['count'] - 1]['kind'] ?? null,
    ],
    'nextPage' => $next === null ? null : [
        'count' => $next['count'],
        'complete' => $next['complete'],
        'firstRowid' => $next['rows'][0]['rowid'] ?? null,
    ],
    'dependency' => 'native PHP PRAGMA integrity_check plus foreign_key_check current/next32 stream; no ext/sqlite required',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
