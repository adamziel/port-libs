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

echo json_encode([
    'wordpressUse' => 'Bulk delete obsolete wp_options option_name index entries locally and expose the coalesced page freeblock that a later writer can reuse.',
    'deletedIndexRecords' => $deletedRecords,
    'beforeRecords' => array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, $beforeCells),
    'afterRecords' => array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, $afterCells),
    'afterCellCount' => $afterHeader->cellCount,
    'freeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $afterHeader->freeblocks($deletedPage)),
    'secureDeletedBytes' => bin2hex(substr($deletedPage, $beforeCells[2]->offset + 4, max(0, $deletedBytes - 4))),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
