<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeParentPrunePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 7), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 96, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options (option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize);
$parentPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(4, 20),
    SQLiteTableInteriorCell::encode(5, 40),
], 6, $pageSize);
$leftLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(10, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
], $pageSize);
$middleLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(30, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
], $pageSize);
$payload = SQLiteRecord::encode([null, '_transient_parent_prune', str_repeat('cached:', 90), 'no']);
$localLength = SQLiteTableLeafCell::localPayloadLength(strlen($payload), $pageSize);
$overflowPage = SQLiteOverflowPage::encodeChainAtPages(substr($payload, $localLength), [7], $pageSize)[7];
$rightLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(50, $payload, $pageSize, 7),
], $pageSize);

$delete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
    $rightLeaf,
    50,
    static fn (int $_firstOverflowPage, int $_byteCount): array => [7],
    secureDelete: true,
);
$database = SQLiteDatabase::fromBytes($firstPage . $schemaPage . $parentPage . $leftLeaf . $middleLeaf . $rightLeaf . $overflowPage);
$plan = SQLiteBTreeParentPrunePlan::tableChild($database, 3, 6, $delete, true);
$parentAfter = SQLiteBTreePageHeader::parsePage($plan->pageImages[3], $pageSize);

echo json_encode([
    'applicationUse' => 'Delete the last transient row from a copied wp_options leaf, remove the now-empty child pointer from its table-interior parent, and return the empty child plus obsolete overflow page to the freelist without ext/sqlite.',
    'plan' => $plan->toArray(),
    'parentAfterCellCount' => $parentAfter->cellCount,
    'parentAfterRightMostPointer' => $parentAfter->rightMostPointer,
    'firstFreelistTrunkPage' => SQLiteHeader::parse($plan->pageImages[1])->firstFreelistTrunkPage,
    'freelistPageCount' => SQLiteHeader::parse($plan->pageImages[1])->freelistPageCount,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
