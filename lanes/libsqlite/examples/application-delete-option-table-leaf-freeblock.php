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
    '_transient_timeout_cache',
    '1700000000',
    'no',
]));
$settingsCell = SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([
    null,
    'home',
    'https://example.test/blog',
    'yes',
]));

$tablePage = SQLiteTableLeafPage::assemble([$siteurlCell, $transientCell, $homeCell, $settingsCell]);
$beforeHeader = SQLiteBTreePageHeader::parsePage($tablePage, 512);
$beforeCells = SQLiteTableLeafCell::parsePageCells($tablePage, $beforeHeader);

$deletedPage = SQLiteTableLeafPage::deleteCellsByRowIds($tablePage, [2, 3], secureDelete: true);
$afterHeader = SQLiteBTreePageHeader::parsePage($deletedPage, 512);
$afterCells = SQLiteTableLeafCell::parsePageCells($deletedPage, $afterHeader);
$deletedBytes = $beforeCells[1]->bytesRead + $beforeCells[2]->bytesRead;
$reinsertPayload = SQLiteRecord::encode([
    null,
    '_transient_cache_refilled',
    'fresh payload',
    'no',
]);
$reinsertedPage = SQLiteTableLeafPage::insertCellByRowIdReusingFreeblock($deletedPage, 3, $reinsertPayload);
$reinsertedHeader = SQLiteBTreePageHeader::parsePage($reinsertedPage, 512);
$reinsertedCells = SQLiteTableLeafCell::parsePageCells($reinsertedPage, $reinsertedHeader);

echo json_encode([
    'applicationUse' => 'Bulk delete obsolete wp_options transient table rows locally and reuse the coalesced table leaf freeblock for a refreshed transient row.',
    'deletedRowIds' => [2, 3],
    'beforeRowIds' => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $beforeCells),
    'afterRowIds' => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $afterCells),
    'afterCellCount' => $afterHeader->cellCount,
    'freeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $afterHeader->freeblocks($deletedPage)),
    'reinsertedRowIds' => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $reinsertedCells),
    'reinsertedOptionNames' => array_map(static fn (SQLiteTableLeafCell $cell): string => SQLiteRecord::parse($cell->payload)->values[1], $reinsertedCells),
    'reinsertedCellCount' => $reinsertedHeader->cellCount,
    'reusedFreeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $reinsertedHeader->freeblocks($reinsertedPage)),
    'secureDeletedBytes' => bin2hex(substr($deletedPage, $beforeCells[2]->offset + 4, max(0, $deletedBytes - 4))),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
