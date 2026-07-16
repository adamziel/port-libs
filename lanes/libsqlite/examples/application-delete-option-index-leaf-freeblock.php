<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblock;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$siteurlCell = SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 1]));
$transientCell = SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_cache', 2]));
$transientTimeoutCell = SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_timeout_cache', 3]));
$homeCell = SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 4]));

$indexPage = SQLiteIndexLeafPage::assemble([$siteurlCell, $transientCell, $transientTimeoutCell, $homeCell]);
$beforeHeader = SQLiteBTreePageHeader::parsePage($indexPage, 512);
$beforeCells = SQLiteIndexCell::parsePageCells($indexPage, $beforeHeader);

$deletedRecords = [
    ['_transient_cache', 2],
    ['_transient_timeout_cache', 3],
];
$deletedPage = SQLiteIndexLeafPage::deleteCellsByRecordValues($indexPage, $deletedRecords, secureDelete: true);
$afterHeader = SQLiteBTreePageHeader::parsePage($deletedPage, 512);
$afterCells = SQLiteIndexCell::parsePageCells($deletedPage, $afterHeader);
$deletedBytes = $beforeCells[1]->bytesRead + $beforeCells[2]->bytesRead;

$largeKey = str_repeat('_transient_large_cache_', 30);
$largeRecord = SQLiteRecord::encode([$largeKey, 9]);
$overflowAllocation = SQLiteIndexCell::encodeWithOverflowPages($largeRecord, 3);
$overflowIndexPage = SQLiteIndexLeafPage::assemble([$overflowAllocation['cell']]);
$overflowHeader = SQLiteBTreePageHeader::parsePage($overflowIndexPage, 512);
$overflowPages = array_combine(
    range(3, 2 + count($overflowAllocation['overflowPages'])),
    $overflowAllocation['overflowPages'],
);
$overflowReader = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): string {
    $payload = '';
    $pageNumber = $firstOverflowPage;
    while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
        $page = $overflowPages[$pageNumber] ?? null;
        if ($page === null) {
            throw new InvalidArgumentException('Fixture overflow page is missing');
        }
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $payload .= substr($page, 4);
    }

    return substr($payload, 0, $byteCount);
};
$overflowCell = SQLiteIndexCell::parsePageCells($overflowIndexPage, $overflowHeader, 512, $overflowReader)[0];

echo json_encode([
    'applicationUse' => 'Bulk delete obsolete wp_options option_name index entries locally and expose the coalesced page freeblock that a later writer can reuse.',
    'deletedIndexRecords' => $deletedRecords,
    'beforeRecords' => array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, $beforeCells),
    'afterRecords' => array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, $afterCells),
    'afterCellCount' => $afterHeader->cellCount,
    'freeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $afterHeader->freeblocks($deletedPage)),
    'secureDeletedBytes' => bin2hex(substr($deletedPage, $beforeCells[2]->offset + 4, max(0, $deletedBytes - 4))),
    'overflowIndexDeletePrerequisite' => [
        'firstOverflowPage' => $overflowCell->firstOverflowPage,
        'localPayloadLength' => $overflowCell->localPayloadLength,
        'payloadLength' => $overflowCell->payloadLength,
        'overflowPageCount' => count($overflowAllocation['overflowPages']),
        'recordPrefix' => substr($overflowCell->record()->values[0], 0, 22),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
