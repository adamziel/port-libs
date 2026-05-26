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
$homeCell = SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 3]));

$indexPage = SQLiteIndexLeafPage::assemble([$siteurlCell, $transientCell, $homeCell]);
$beforeHeader = SQLiteBTreePageHeader::parsePage($indexPage, 512);
$beforeCells = SQLiteIndexCell::parsePageCells($indexPage, $beforeHeader);

$deletedPage = SQLiteIndexLeafPage::deleteCellByRecordValues($indexPage, ['_transient_cache', 2], secureDelete: true);
$afterHeader = SQLiteBTreePageHeader::parsePage($deletedPage, 512);
$afterCells = SQLiteIndexCell::parsePageCells($deletedPage, $afterHeader);

echo json_encode([
    'wordpressUse' => 'Delete an obsolete wp_options option_name index entry locally and expose the page freeblock that a later writer can reuse.',
    'deletedIndexRecord' => ['_transient_cache', 2],
    'beforeRecords' => array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, $beforeCells),
    'afterRecords' => array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, $afterCells),
    'afterCellCount' => $afterHeader->cellCount,
    'freeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $afterHeader->freeblocks($deletedPage)),
    'secureDeletedBytes' => bin2hex(substr($deletedPage, $beforeCells[1]->offset + 4, max(0, $beforeCells[1]->bytesRead - 4))),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
