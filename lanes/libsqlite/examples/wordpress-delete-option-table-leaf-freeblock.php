<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblock;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$siteurlCell = SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
    null,
    'siteurl',
    'https://example.test',
    'yes',
]));
$transientCell = SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
    null,
    '_transient_cache',
    'stale payload',
    'no',
]));
$homeCell = SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([
    null,
    'home',
    'https://example.test/blog',
    'yes',
]));

$tablePage = SQLiteTableLeafPage::assemble([$siteurlCell, $transientCell, $homeCell]);
$beforeHeader = SQLiteBTreePageHeader::parsePage($tablePage, 512);
$beforeCells = SQLiteTableLeafCell::parsePageCells($tablePage, $beforeHeader);

$deletedPage = SQLiteTableLeafPage::deleteCellByRowId($tablePage, 2, secureDelete: true);
$afterHeader = SQLiteBTreePageHeader::parsePage($deletedPage, 512);
$afterCells = SQLiteTableLeafCell::parsePageCells($deletedPage, $afterHeader);

echo json_encode([
    'wordpressUse' => 'Delete an obsolete wp_options table row locally and expose the table leaf freeblock that a later writer can reuse.',
    'deletedRowId' => 2,
    'beforeRowIds' => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $beforeCells),
    'afterRowIds' => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $afterCells),
    'afterCellCount' => $afterHeader->cellCount,
    'freeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $afterHeader->freeblocks($deletedPage)),
    'secureDeletedBytes' => bin2hex(substr($deletedPage, $beforeCells[1]->offset + 4, max(0, $beforeCells[1]->bytesRead - 4))),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
