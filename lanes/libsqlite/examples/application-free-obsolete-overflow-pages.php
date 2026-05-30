<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

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

$oldPayload = SQLiteRecord::encode([
    null,
    'obsolete_large_cache',
    str_repeat('large-cache-value:', 80),
    'yes',
]);
$oldLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($oldPayload), $pageSize);
$oldOverflowPayload = substr($oldPayload, $oldLocalLength);
$oldOverflowPages = SQLiteOverflowPage::encodeChainAtPages($oldOverflowPayload, [3, 4], $pageSize);
$oldCell = SQLiteTableLeafCell::encode(1, $oldPayload, $pageSize, 3);
$oldTablePage = SQLiteTableLeafPage::assemble([$oldCell], $pageSize);

$preDatabase = SQLiteDatabase::fromBytes(
    $schemaPage
    . $oldTablePage
    . $oldOverflowPages[3]
    . $oldOverflowPages[4],
);

$freePlan = $preDatabase->planPageFreeList([3, 4]);

$newPayload = SQLiteRecord::encode([
    null,
    'obsolete_large_cache',
    'small-cache-value',
    'yes',
]);
$newTablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, $newPayload),
], $pageSize);

$postPages = [
    1 => $freePlan->firstPage,
    2 => $newTablePage,
    3 => $oldOverflowPages[3],
    4 => $oldOverflowPages[4],
];
foreach ($freePlan->updatedFreelistPages as $pageNumber => $page) {
    $postPages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
$option = $postDatabase->optionRows()[0] ?? null;

echo json_encode([
    'applicationUse' => 'After rewriting a large wp_options row to a small inline value, return its obsolete overflow pages to SQLite freelist metadata without the SQLite extension.',
    'freePlan' => $freePlan->toArray(),
    'freelistAfter' => array_map(
        static fn ($trunkPage): array => $trunkPage->toArray(),
        $postDatabase->freelistTrunkPages(),
    ),
    'nextAllocationOrder' => $postDatabase->freelistAllocationOrder(),
    'lookup' => $option?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
