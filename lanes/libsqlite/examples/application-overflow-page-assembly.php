<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionValue = $argv[1] ?? str_repeat('wp-cache-fragment:', 40) . 'end';

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$optionPayload = SQLiteRecord::encode([
    null,
    'large_autoloaded_cache',
    $optionValue,
    'yes',
]);
$optionAllocation = SQLiteTableLeafCell::encodeWithOverflowPages(1, $optionPayload, 3);
$databasePageCount = 2 + count($optionAllocation['overflowPages']);
$firstPage = substr_replace($firstPage, pack('N', $databasePageCount), 28, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], 512, 100, $firstPage);
$tablePage = SQLiteTableLeafPage::assemble([$optionAllocation['cell']]);

$database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . implode('', $optionAllocation['overflowPages']));
$options = $database->optionRows();
$option = $options[0] ?? null;

echo json_encode([
    'applicationUse' => 'Assemble a wp_options row whose option_value spills to SQLite overflow pages, then parse it back without the SQLite extension.',
    'pageSize' => $database->header->pageSize,
    'databasePages' => $database->pageCount(),
    'wpOptionsRootPage' => $database->tableRootPage('wp_options'),
    'localPayloadLength' => $optionAllocation['localPayloadLength'],
    'overflowPageCount' => count($optionAllocation['overflowPages']),
    'overflowNextPointers' => array_map(
        static fn (string $page): int => unpack('N', substr($page, 0, 4))[1],
        $optionAllocation['overflowPages'],
    ),
    'optionValueBytes' => strlen($option?->optionValue ?? ''),
    'lookup' => $option?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
