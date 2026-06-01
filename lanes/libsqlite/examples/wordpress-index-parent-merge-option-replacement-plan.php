<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$nameLength = 64;
$name = static fn (string $prefix): string => str_repeat($prefix, $nameLength);
$optionName = $argv[1] ?? $name('x');
$replacementValue = $argv[2] ?? 'fixed-cache';

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 13), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
        'index',
        'wp_options_autoload_name',
        'wp_options',
        3,
        'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
    ])),
], $pageSize, 100, $firstPage);

$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
], $pageSize);

$indexRootPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('y'), 900]), $pageSize, null, 4),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('{'), 950]), $pageSize, null, 8),
], 11, $pageSize);
$lowerInteriorPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('g'), 100]), $pageSize, null, 5),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('m'), 101]), $pageSize, null, 6),
], 7, $pageSize);
$rightInteriorPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 911]), $pageSize, null, 9),
], 10, $pageSize);
$farRightInteriorPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('~'), 961]), $pageSize, null, 12),
], 13, $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . $indexRootPage
    . $lowerInteriorPage
    . SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['no', $name('a'), 50])),
    ], $pageSize)
    . SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('h'), 60])),
    ], $pageSize)
    . SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('w'), 63])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 4])),
    ], $pageSize)
    . $rightInteriorPage
    . SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 910])),
    ], $pageSize)
    . SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 912])),
    ], $pageSize)
    . $farRightInteriorPage
    . SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('|'), 951])),
    ], $pageSize)
    . SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('~'), 962])),
    ], $pageSize),
);

$plan = $database->planWordPressOptionReplace($optionName, $replacementValue, 'no');

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
$header = SQLiteHeader::parse($plan->pageImages()[1]);
$rootCells = SQLiteIndexCell::parsePageCells(
    $postDatabase->page(3),
    $postDatabase->pageHeader(3),
    $pageSize,
);
$mergedParentCells = SQLiteIndexCell::parsePageCells(
    $postDatabase->page(4),
    $postDatabase->pageHeader(4),
    $pageSize,
);

echo json_encode([
    'wordpressUse' => 'Plan a wp_options autoload rewrite that merges an underfilled non-root composite-index parent under a multi-child root, keeping the index reachable without the SQLite extension.',
    'plan' => $plan->toArray(),
    'indexRoot' => [
        'page' => 3,
        'cellCount' => $postDatabase->pageHeader(3)->cellCount,
        'leftChildren' => array_map(static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage, $rootCells),
        'rightMostPointer' => $postDatabase->pageHeader(3)->rightMostPointer,
    ],
    'mergedIndexParent' => [
        'page' => 4,
        'cellCount' => $postDatabase->pageHeader(4)->cellCount,
        'leftChildren' => array_map(static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage, $mergedParentCells),
        'rightMostPointer' => $postDatabase->pageHeader(4)->rightMostPointer,
    ],
    'freelist' => [
        'firstTrunkPage' => $header->firstFreelistTrunkPage,
        'freelistPageCount' => $header->freelistPageCount,
        'pages' => $postDatabase->freelistPageNumbers(),
    ],
    'replacedOption' => $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', $optionName)?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
