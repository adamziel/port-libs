<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
$firstPage = substr_replace($firstPage, pack('N', 8), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        3,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize, 100, $firstPage);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    6 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    7 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    8 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace(
        $pointerMapPage,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
}

$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
], $pageSize);
$childPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
], $pageSize);
$tailPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'blogname', 'Ported SQLite', 'yes'])),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $pointerMapPage
    . $tablePage
    . $childPage
    . SQLiteFreelistTrunkPage::assemble(null, [6, 7], $pageSize)
    . str_repeat("\0", $pageSize)
    . str_repeat("\0", $pageSize)
    . $tailPage,
);

$plan = $database->planBtreePageAllocation(2, 3, false);
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

echo json_encode([
    'applicationUse' => 'Preview auto-vacuum pointer-map rewrites when deleted wp_options pages are reused as B-tree child pages during repair or import planning.',
    'plan' => $plan->toArray(),
    'allocatedPointerMapEntries' => array_map(
        static fn (int $pageNumber): array => $postDatabase->pointerMapEntryForPage($pageNumber)->toArray(),
        $plan->allocatedPageNumbers,
    ),
    'freelistPagesAfterReuse' => $postDatabase->freelistPageNumbers(),
    'reachableOptions' => array_map(
        static fn ($option): string => $option->optionName,
        $postDatabase->optionRows(),
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
