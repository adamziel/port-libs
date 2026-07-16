<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$emptyPage = str_repeat("\0", $pageSize);

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 4), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize, 100, $firstPage);

$oldValue = str_repeat('old-cache-fragment:', 70) . 'done';
$oldPayload = SQLiteRecord::encode([null, 'rewrite_large_cache', $oldValue, 'yes']);
$oldLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($oldPayload), $pageSize);
$oldOverflowPayload = substr($oldPayload, $oldLocalLength);
$oldOverflowPages = SQLiteOverflowPage::encodeChainAtPages($oldOverflowPayload, [3, 4], $pageSize);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(2, $oldPayload, $pageSize, 3),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . $oldOverflowPages[3]
    . $oldOverflowPages[4],
);

$newValue = $argv[1] ?? str_repeat('new-cache-fragment:', 78) . 'done';
$plan = $database->planKeyValueRowReplace('rewrite_large_cache', $newValue, 'no');

$pages = [];
for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $pageNumber <= $database->pageCount() ? $database->page($pageNumber) : $emptyPage;
}
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $postDatabase->keyValueRows(),
);

echo json_encode([
    'applicationUse' => 'Rewrite a large wp_options row to a larger overflow value, allocating the new overflow chain before freeing obsolete overflow pages, without the SQLite extension.',
    'plan' => $plan->toArray(),
    'updatedPageNumbers' => array_keys($plan->pageImages()),
    'freelistAfter' => array_map(
        static fn ($trunkPage): array => $trunkPage->toArray(),
        $postDatabase->freelistTrunkPages(),
    ),
    'nextAllocationOrder' => $postDatabase->freelistAllocationOrder(),
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
