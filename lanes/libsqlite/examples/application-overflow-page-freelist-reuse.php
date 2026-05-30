<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionValue = $argv[1] ?? str_repeat('wp-reused-cache-page:', 80);
$pageSize = 512;
$reservedBytes = 12;
$usableSize = $pageSize - $reservedBytes;

$optionPayload = SQLiteRecord::encode([
    null,
    'freelist_reused_cache',
    $optionValue,
    'yes',
]);
$localPayloadLength = SQLiteTableLeafCell::localPayloadLength(strlen($optionPayload), $usableSize);
$overflowPayload = substr($optionPayload, $localPayloadLength);
$requiredOverflowPages = SQLiteOverflowPage::requiredPageCount(strlen($overflowPayload), $pageSize, $usableSize);

$reusePool = [5, 3, 7, 4, 8, 6, 9, 10, 11, 12];
while (count($reusePool) < $requiredOverflowPages) {
    $reusePool[] = max($reusePool) + 1;
}
$overflowPageNumbers = array_slice($reusePool, 0, $requiredOverflowPages);
$firstOverflowPage = $overflowPageNumbers[0] ?? null;
$optionCell = SQLiteTableLeafCell::encode(1, $optionPayload, $usableSize, $firstOverflowPage);
$overflowPages = SQLiteOverflowPage::encodeChainAtPages(
    $overflowPayload,
    $overflowPageNumbers,
    $pageSize,
    $usableSize,
);

$databasePageCount = max(2, $overflowPageNumbers === [] ? 2 : max($overflowPageNumbers));
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = chr($reservedBytes);
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', $databasePageCount), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize, 100, $firstPage, $usableSize);
$tablePage = SQLiteTableLeafPage::assemble([$optionCell], $pageSize, 0, null, $usableSize);

$databasePages = [];
for ($pageNumber = 1; $pageNumber <= $databasePageCount; $pageNumber++) {
    $databasePages[$pageNumber] = str_repeat("\0", $pageSize);
}
$databasePages[1] = $schemaPage;
$databasePages[2] = $tablePage;
foreach ($overflowPages as $pageNumber => $page) {
    $databasePages[$pageNumber] = $page;
}

$database = SQLiteDatabase::fromBytes(implode('', $databasePages));
$option = $database->keyValueRows()[0] ?? null;
$overflowNextPointers = [];
foreach ($overflowPages as $pageNumber => $page) {
    $overflowNextPointers[$pageNumber] = unpack('N', substr($page, 0, 4))[1];
}

echo json_encode([
    'applicationUse' => 'Assemble a wp_options overflow value on non-contiguous reusable pages while respecting SQLite reserved bytes, then parse it back without the SQLite extension.',
    'pageSize' => $database->header->pageSize,
    'reservedBytes' => $database->header->reservedSpace,
    'usablePageSize' => $database->usablePageSize(),
    'databasePages' => $database->pageCount(),
    'localPayloadLength' => $localPayloadLength,
    'overflowPageNumbers' => $overflowPageNumbers,
    'overflowNextPointers' => $overflowNextPointers,
    'reservedTailHexByOverflowPage' => array_map(
        static fn (string $page): string => bin2hex(substr($page, $usableSize)),
        $overflowPages,
    ),
    'lookup' => $option?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
