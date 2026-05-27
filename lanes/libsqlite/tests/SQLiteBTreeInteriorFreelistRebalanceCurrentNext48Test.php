<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorFreelistRebalancePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;

$makeFirstPage = static function (int $pageSize, int $pageCount, bool $existingFreelist): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $existingFreelist ? 9 : 0), 32, 4);
    $page = substr_replace($page, pack('N', $existingFreelist ? 2 : 0), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);

    return $page;
};

$setPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parentPageNumber): string {
    return substr_replace($page, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$interiorPage = static function (string $pageType, int $pageSize, int $freeblockSize): string {
    if ($pageType === 'table-interior') {
        $page = SQLiteTableInteriorPage::assemble([
            SQLiteTableInteriorCell::encode(4, 50),
            SQLiteTableInteriorCell::encode(5, 100),
        ], 6, $pageSize);
    } else {
        $page = SQLiteIndexInteriorPage::assemble([
            pack('N', 4) . SQLiteRecord::encode(['autoload', 1]),
            pack('N', 5) . SQLiteRecord::encode(['siteurl', 2]),
        ], 6, $pageSize);
    }

    $freeblockOffset = 480;
    $page = substr_replace($page, pack('n', $freeblockOffset), 1, 2);
    $page = substr_replace($page, pack('n', $freeblockOffset), 5, 2);
    $page = substr_replace($page, pack('n', 0) . pack('n', $freeblockSize), $freeblockOffset, 4);
    $page = substr_replace($page, str_repeat("\0", $freeblockSize - 4), $freeblockOffset + 4, $freeblockSize - 4);

    return $page;
};

$makeDatabase = static function (string $pageType, bool $existingFreelist, array $releasedPages) use (
    $makeFirstPage,
    $setPointerMapEntry,
    $interiorPage,
): SQLiteDatabase {
    $pageSize = 512;
    $pageCount = 30;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, $existingFreelist);
    $pages[2] = str_repeat("\0", $pageSize);
    $pages[3] = $interiorPage($pageType, $pageSize, 16);
    if ($existingFreelist) {
        $pages[9] = SQLiteFreelistTrunkPage::assemble(null, [10], $pageSize);
        $pages[2] = $setPointerMapEntry($pages[2], 9, SQLitePointerMapEntry::FREE_PAGE, 0);
        $pages[2] = $setPointerMapEntry($pages[2], 10, SQLitePointerMapEntry::FREE_PAGE, 0);
    }

    $pages[2] = $setPointerMapEntry($pages[2], 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pages[2] = $setPointerMapEntry($pages[2], 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $pages[2] = $setPointerMapEntry($pages[2], 5, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $pages[2] = $setPointerMapEntry($pages[2], 6, SQLitePointerMapEntry::BTREE_PAGE, 3);
    foreach ($releasedPages as $pageNumber) {
        $pages[2] = $setPointerMapEntry(
            $pages[2],
            $pageNumber,
            $pageNumber >= 20 ? SQLitePointerMapEntry::OVERFLOW_PAGE : SQLitePointerMapEntry::BTREE_PAGE,
            $pageNumber >= 20 ? $pageNumber - 1 : 3,
        );
    }

    ksort($pages);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tests = [];
foreach (['table-interior', 'index-interior'] as $pageType) {
    foreach ([false, true] as $secureDelete) {
        foreach ([false, true] as $existingFreelist) {
            foreach ([1, 2, 3] as $childCount) {
                foreach ([false, true] as $withOverflow) {
                    $name = sprintf(
                        'plans %s current-next48 freelist rebalance child%d overflow%s secure%s existing%s',
                        $pageType,
                        $childCount,
                        $withOverflow ? 'yes' : 'no',
                        $secureDelete ? 'yes' : 'no',
                        $existingFreelist ? 'yes' : 'no',
                    );
                    $tests[$name] = static function (TestRunner $t) use (
                        $makeDatabase,
                        $pageType,
                        $secureDelete,
                        $existingFreelist,
                        $childCount,
                        $withOverflow,
                    ): void {
                        $childPages = range(11, 10 + $childCount);
                        $overflowPages = $withOverflow ? [20, 21] : [];
                        $releasedPages = array_merge($childPages, $overflowPages);
                        $database = $makeDatabase($pageType, $existingFreelist, $releasedPages);
                        $page = $database->page(3);
                        $deleteResult = [
                            'page' => $page,
                            'obsolete_child_page_numbers' => $childPages,
                            'obsolete_overflow_page_numbers' => $overflowPages,
                        ];

                        $plan = $pageType === 'table-interior'
                            ? SQLiteBTreeInteriorFreelistRebalancePlan::tableInteriorFromDeleteResult($database, 3, $deleteResult, $secureDelete)
                            : SQLiteBTreeInteriorFreelistRebalancePlan::indexInteriorFromDeleteResult($database, 3, $deleteResult, $secureDelete);
                        $summary = $plan->toArray();

                        $pages = [];
                        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
                            $pages[$pageNumber] = $plan->pageImages[$pageNumber] ?? $database->page($pageNumber);
                        }
                        $postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
                        $header = SQLiteBTreePageHeader::parsePage($plan->pageImages[3], 512);
                        $freeblocks = $header->freeblocks($plan->pageImages[3]);

                        $t->same('btree-interior-freelist-rebalance', $summary['action']);
                        $t->same(3, $plan->interiorPageNumber);
                        $t->same($pageType, $plan->interiorPageType);
                        $t->same(6, $plan->rightMostPointer);
                        $t->same(2, $plan->interiorCellCount);
                        $t->same(1, count($plan->interiorFreeblocks));
                        $t->same(480, $plan->interiorFreeblocks[0]['offset']);
                        $t->same(16, $plan->interiorFreeblockBytes);
                        $t->same(16, $freeblocks[0]->size);
                        $t->same($childPages, $plan->obsoleteChildPageNumbers);
                        $t->same($overflowPages, $plan->obsoleteOverflowPageNumbers);
                        $t->same($releasedPages, $plan->releasedPageNumbers());
                        $t->same($releasedPages, $plan->freePlan->freedPageNumbers);
                        $t->same(count($releasedPages) + ($existingFreelist ? 2 : 0), $plan->freePlan->freelistPageCount);
                        $t->same($existingFreelist ? 9 : $releasedPages[0], $plan->freePlan->firstFreelistTrunkPage);
                        $t->same($releasedPages, array_slice($postDatabase->freelistPageNumbers(), $existingFreelist ? 2 : 0));
                        $t->same(count($releasedPages) + ($existingFreelist ? 2 : 0), $postDatabase->header->freelistPageCount);
                        $t->same([2], array_keys($plan->freePlan->updatedPointerMapPages));
                        foreach ($releasedPages as $pageNumber) {
                            $t->same('free-page', $postDatabase->pointerMapEntryForPage($pageNumber)->typeName());
                            $t->same(0, $postDatabase->pointerMapEntryForPage($pageNumber)->parentPageNumber);
                        }
                        $expectedCleared = $secureDelete
                            ? ($existingFreelist ? $releasedPages : array_slice($releasedPages, 1))
                            : [];
                        $t->same($expectedCleared, $plan->freePlan->clearedPageNumbers);
                    };
                }
            }
        }
    }
}

$tests['rejects duplicate interior current-next48 released pages'] = static function (TestRunner $t) use ($makeDatabase): void {
    $database = $makeDatabase('table-interior', false, [11]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorFreelistRebalancePlan::tableInteriorFromDeleteResult($database, 3, [
        'page' => $database->page(3),
        'obsolete_child_page_numbers' => [11, 11],
    ]));
};

$tests['rejects leaf page images for interior current-next48 rebalance'] = static function (TestRunner $t): void {
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
    $firstPage = substr_replace($firstPage, pack('N', 4), 28, 4);
    $firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
    $leaf = str_repeat("\0", $pageSize);
    $leaf[0] = "\x0d";
    $leaf = substr_replace($leaf, pack('n', 0), 1, 2);
    $leaf = substr_replace($leaf, pack('n', 1), 3, 2);
    $leaf = substr_replace($leaf, pack('n', 500), 5, 2);
    $leaf[7] = "\x00";
    $leaf = substr_replace($leaf, pack('n', 500), 8, 2);
    $leaf = substr_replace($leaf, "\x01\x01", 500, 2);
    $database = SQLiteDatabase::fromBytes($firstPage . str_repeat("\0", $pageSize) . $leaf . str_repeat("\0", $pageSize));

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorFreelistRebalancePlan::tableInteriorFromDeleteResult($database, 3, [
        'page' => $leaf,
        'obsolete_child_page_numbers' => [4],
    ]));
};

return $tests;
