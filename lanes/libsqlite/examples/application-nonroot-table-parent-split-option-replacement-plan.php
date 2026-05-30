<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$largeRowIdBase = 72057594037927936;
$optionName = $argv[1] ?? 'blogname';
$replacementValue = $argv[2] ?? str_repeat('x', 450);

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 38), 28, 4);
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

$lowerParentCells = [];
$leafPages = [];
for ($index = 0; $index < 33; $index++) {
    $rowId = $largeRowIdBase + ($index * 10);
    $pageNumber = 4 + $index;
    $lowerParentCells[] = SQLiteTableInteriorCell::encode($pageNumber, $rowId);
    $leafPages[$pageNumber] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode([null, 'filler_' . $index, 'value_' . $index, 'no'])),
    ], $pageSize);
}

$targetRowId = $largeRowIdBase + 330;
$nextRowId = $largeRowIdBase + 340;
$rightRootRowId = $largeRowIdBase + 350;
$targetLeafPage = 37;
$rightRootLeafPage = 38;
$tableRootPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(3, $nextRowId),
], $rightRootLeafPage, $pageSize);
$lowerInteriorPage = SQLiteTableInteriorPage::assemble($lowerParentCells, $targetLeafPage, $pageSize);
$leafPages[$targetLeafPage] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode($targetRowId, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
    SQLiteTableLeafCell::encode($nextRowId, SQLiteRecord::encode([null, 'template', 'twentytwentyfive', 'yes'])),
], $pageSize);
$leafPages[$rightRootLeafPage] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode($rightRootRowId, SQLiteRecord::encode([null, 'stylesheet', 'twentytwentysix', 'yes'])),
], $pageSize);
ksort($leafPages);

$database = SQLiteDatabase::fromBytes($schemaPage . $tableRootPage . $lowerInteriorPage . implode('', $leafPages));
$plan = $database->planOptionRowReplace($optionName, $replacementValue, 'no');

$pages = [];
for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $pageNumber <= $database->pageCount()
        ? $database->page($pageNumber)
        : str_repeat("\0", $pageSize);
}
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$rootCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(2), $postDatabase->pageHeader(2));
$leftParentCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(3), $postDatabase->pageHeader(3));
$rightParentCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(40), $postDatabase->pageHeader(40));
$targetOptions = $postDatabase->optionRowsByRowIdRange($targetRowId, $targetRowId, 1, true);

echo json_encode([
    'applicationUse' => 'Plan a wp_options replacement that splits a full non-root table-interior parent and promotes the divider into the root, without the SQLite extension.',
    'plan' => $plan->toArray(),
    'rootSeparators' => array_map(
        static fn (SQLiteTableInteriorCell $cell): array => [
            'leftChildPage' => $cell->leftChildPage,
            'maxRowid' => $cell->key,
        ],
        $rootCells,
    ),
    'rootRightMostPage' => $postDatabase->pageHeader(2)->rightMostPointer,
    'splitParents' => [
        3 => [
            'cellCount' => $postDatabase->pageHeader(3)->cellCount,
            'rightMostPage' => $postDatabase->pageHeader(3)->rightMostPointer,
            'firstSeparator' => $leftParentCells[0]->key,
        ],
        40 => [
            'cellCount' => $postDatabase->pageHeader(40)->cellCount,
            'rightMostPage' => $postDatabase->pageHeader(40)->rightMostPointer,
            'firstSeparator' => $rightParentCells[0]->key,
        ],
    ],
    'splitLeafPages' => [
        37 => $postDatabase->pageHeader(37)->cellCount,
        39 => $postDatabase->pageHeader(39)->cellCount,
    ],
    'updatedOption' => $targetOptions[0]?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
