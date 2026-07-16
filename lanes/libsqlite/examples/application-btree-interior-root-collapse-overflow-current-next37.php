<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeRootCollapsePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 22;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = str_repeat("\0", $pageSize);
$pages[1] = substr_replace($pages[1], "SQLite format 3\0", 0, 16);
$pages[1] = substr_replace($pages[1], pack('n', $pageSize), 16, 2);
$pages[1][18] = "\x01";
$pages[1][19] = "\x01";
$pages[1][20] = "\x00";
$pages[1][21] = "\x40";
$pages[1][22] = "\x20";
$pages[1][23] = "\x20";
$pages[1] = substr_replace($pages[1], pack('N', $pageCount), 28, 4);
$pages[1] = substr_replace($pages[1], pack('N', 4), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);

$payload = SQLiteRecord::encode(['_transient_timeout_plugin_payload', str_repeat('plugin-state:', 85), 91]);
$encoded = SQLiteIndexCell::encodeWithOverflowPages($payload, 20, $pageSize, $pageSize, 5);
$pages[3] = SQLiteIndexInteriorPage::assemble([], 4, $pageSize);
$pages[4] = SQLiteIndexInteriorPage::assemble([$encoded['cell']], 6, $pageSize);
foreach (SQLiteOverflowPage::encodeChainAtPages(substr($payload, $encoded['localPayloadLength']), [20, 21, 22], $pageSize) as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$putPointerMapEntry(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry(4, SQLitePointerMapEntry::BTREE_PAGE, 3);
$putPointerMapEntry(5, SQLitePointerMapEntry::BTREE_PAGE, 4);
$putPointerMapEntry(6, SQLitePointerMapEntry::BTREE_PAGE, 4);
$putPointerMapEntry(20, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
$putPointerMapEntry(21, SQLitePointerMapEntry::OVERFLOW_PAGE, 20);
$putPointerMapEntry(22, SQLitePointerMapEntry::OVERFLOW_PAGE, 21);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreeRootCollapsePlan::collapseOnlyChild($database, 3, true);
foreach ($plan->pageImages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$rootHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(3), $pageSize);
$rootCell = SQLiteIndexCell::parsePageCells(
    $postDatabase->page(3),
    $rootHeader,
    $pageSize,
    static fn (int $firstOverflowPage, int $byteCount): string => $postDatabase->readOverflowPayloadForBtreePlan($firstOverflowPage, $byteCount),
)[0];

echo json_encode([
    'applicationUse' => 'Apply a copied wp_options autoload index root-collapse where an index-interior child with an overflow-backed separator cell is copied into the root and the first-overflow pointer-map owner changes from the obsolete child to the root.',
    'summary' => $plan->toArray(),
    'rootCell' => [
        'leftChildPage' => $rootCell->leftChildPage,
        'rightMostPage' => $rootHeader->rightMostPointer,
        'firstOverflowPage' => $rootCell->firstOverflowPage,
        'payloadLength' => $rootCell->payloadLength,
        'localPayloadLength' => $rootCell->localPayloadLength,
    ],
    'pointerMap' => [
        'obsoleteChild' => $postDatabase->pointerMapEntryForPage(4)->toArray(),
        'leftChild' => $postDatabase->pointerMapEntryForPage(5)->toArray(),
        'rightChild' => $postDatabase->pointerMapEntryForPage(6)->toArray(),
        'firstOverflow' => $postDatabase->pointerMapEntryForPage(20)->toArray(),
        'nextOverflow' => $postDatabase->pointerMapEntryForPage(21)->toArray(),
        'terminalOverflow' => $postDatabase->pointerMapEntryForPage(22)->toArray(),
    ],
    'overflowChain' => $postDatabase->overflowPageChainNumbers(20, $rootCell->payloadLength - $rootCell->localPayloadLength),
    'freelistPages' => $postDatabase->freelistPageNumbers(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
