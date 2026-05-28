<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
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
    $page = substr_replace($page, pack('N', $firstTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    if ($pageNumber === 1) {
        return;
    }

    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$fixture = static function (int $maxTruncatedPages = 8) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 106;
    $firstTrunkPage = 104;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, $firstTrunkPage, 2);
    $pages[$firstTrunkPage] = SQLiteFreelistTrunkPage::assemble(null, [106], $pageSize);
    $pages[106] = str_repeat('Z', $pageSize);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    $putPointerMapEntry($pages, 104, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    $putPointerMapEntry($pages, 106, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = $database->planFreelistTailTruncation($maxTruncatedPages);
    $pageImages = $plan->pageImages();
    $nextPages = [];
    for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
        $nextPages[$pageNumber] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
    }
    $nextDatabase = SQLiteDatabase::fromBytes(implode('', $nextPages));

    return [$database, $plan, $nextDatabase, $pageImages];
};

$tests = [];

$cases = [
    'current page count includes pointer-map tail page' => static fn (array $fx): mixed => $fx[0]->pageCount(),
    'current header page count includes pointer-map tail page' => static fn (array $fx): mixed => $fx[0]->header->databaseSizePages,
    'current first freelist trunk is below pointer-map page' => static fn (array $fx): mixed => $fx[0]->header->firstFreelistTrunkPage,
    'current freelist count excludes pointer-map page' => static fn (array $fx): mixed => $fx[0]->header->freelistPageCount,
    'current pointer-map stride places second map at 105' => static fn (array $fx): mixed => $fx[0]->pointerMapPageFor(106),
    'current page 105 is pointer-map page' => static fn (array $fx): mixed => $fx[0]->isPointerMapPage(105),
    'current trunk page 104 is ordinary page' => static fn (array $fx): mixed => $fx[0]->isPointerMapPage(104),
    'current page 106 pointer-map entry is free' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(106)->typeName(),
    'current page 106 pointer-map parent is zero' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(106)->parentPageNumber,
    'truncated pages include free leaf then pointer-map then trunk' => static fn (array $fx): mixed => $fx[1]->truncatedPageNumbers,
    'truncated page count includes pointer-map page' => static fn (array $fx): mixed => count($fx[1]->truncatedPageNumbers),
    'final database page count drops below pointer-map page' => static fn (array $fx): mixed => $fx[1]->databasePageCount,
    'final first trunk cleared' => static fn (array $fx): mixed => $fx[1]->firstFreelistTrunkPage,
    'final freelist count subtracts only free pages' => static fn (array $fx): mixed => $fx[1]->freelistPageCount,
    'updated freelist pages are removed with trunk' => static fn (array $fx): mixed => array_keys($fx[1]->updatedFreelistPages),
    'page images only need database header' => static fn (array $fx): mixed => array_keys($fx[1]->pageImages()),
    'next page count excludes obsolete pointer-map page' => static fn (array $fx): mixed => $fx[2]->pageCount(),
    'next header page count excludes obsolete pointer-map page' => static fn (array $fx): mixed => $fx[2]->header->databaseSizePages,
    'next first freelist trunk cleared' => static fn (array $fx): mixed => $fx[2]->header->firstFreelistTrunkPage,
    'next freelist count cleared' => static fn (array $fx): mixed => $fx[2]->header->freelistPageCount,
    'next freelist has no pages' => static fn (array $fx): mixed => $fx[2]->freelistPageNumbers(),
    'next page 42 pointer map page remains first map' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(42)->pointerMapPageNumber,
    'next page 42 remains btree child' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(42)->typeName(),
    'next page 42 parent remains root' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(42)->parentPageNumber,
    'summary truncated pages include pointer-map page' => static fn (array $fx): mixed => $fx[1]->toArray()['truncated_page_numbers'],
    'summary final database page count' => static fn (array $fx): mixed => $fx[1]->toArray()['database_page_count'],
    'summary final first trunk' => static fn (array $fx): mixed => $fx[1]->toArray()['first_freelist_trunk_page'],
    'summary final freelist count' => static fn (array $fx): mixed => $fx[1]->toArray()['freelist_page_count'],
    'summary updated freelist pages empty' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_freelist_page_numbers'],
    'page image header database count' => static fn (array $fx): mixed => unpack('N', substr($fx[3][1], 28, 4))[1],
    'page image header first trunk' => static fn (array $fx): mixed => unpack('N', substr($fx[3][1], 32, 4))[1],
    'page image header freelist count' => static fn (array $fx): mixed => unpack('N', substr($fx[3][1], 36, 4))[1],
];

$expected = [
    'current page count includes pointer-map tail page' => 106,
    'current header page count includes pointer-map tail page' => 106,
    'current first freelist trunk is below pointer-map page' => 104,
    'current freelist count excludes pointer-map page' => 2,
    'current pointer-map stride places second map at 105' => 105,
    'current page 105 is pointer-map page' => true,
    'current trunk page 104 is ordinary page' => false,
    'current page 106 pointer-map entry is free' => 'free-page',
    'current page 106 pointer-map parent is zero' => 0,
    'truncated pages include free leaf then pointer-map then trunk' => [106, 105, 104],
    'truncated page count includes pointer-map page' => 3,
    'final database page count drops below pointer-map page' => 103,
    'final first trunk cleared' => 0,
    'final freelist count subtracts only free pages' => 0,
    'updated freelist pages are removed with trunk' => [],
    'page images only need database header' => [1],
    'next page count excludes obsolete pointer-map page' => 103,
    'next header page count excludes obsolete pointer-map page' => 103,
    'next first freelist trunk cleared' => 0,
    'next freelist count cleared' => 0,
    'next freelist has no pages' => [],
    'next page 42 pointer map page remains first map' => 2,
    'next page 42 remains btree child' => 'btree-page',
    'next page 42 parent remains root' => 4,
    'summary truncated pages include pointer-map page' => [106, 105, 104],
    'summary final database page count' => 103,
    'summary final first trunk' => 0,
    'summary final freelist count' => 0,
    'summary updated freelist pages empty' => [],
    'page image header database count' => 103,
    'page image header first trunk' => 0,
    'page image header freelist count' => 0,
];

foreach ($cases as $name => $callback) {
    $tests['btree pointermap vacuum current next65 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree pointermap vacuum current next65 bounded pass stops before pointer-map page'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan, $nextDatabase] = $fixture(1);

    $t->same([106], $plan->truncatedPageNumbers);
    $t->same(105, $plan->databasePageCount);
    $t->same(104, $plan->firstFreelistTrunkPage);
    $t->same(1, $plan->freelistPageCount);
    $t->same([104], $nextDatabase->freelistPageNumbers());
    $t->same(true, $nextDatabase->isPointerMapPage(105));
};

$tests['btree pointermap vacuum current next65 bounded pass can leave trunk below dropped pointer-map'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan, $nextDatabase] = $fixture(2);

    $t->same([106, 105], $plan->truncatedPageNumbers);
    $t->same(104, $plan->databasePageCount);
    $t->same(104, $plan->firstFreelistTrunkPage);
    $t->same(1, $plan->freelistPageCount);
    $t->same([104], $nextDatabase->freelistPageNumbers());
    $t->same(false, $nextDatabase->isPointerMapPage(104));
};

$tests['btree pointermap vacuum current next65 rejects pointer-map page freelist insertion'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    try {
        $database->planPageFreeList([105]);
    } catch (InvalidArgumentException $exception) {
        $t->contains('pointer-map pages cannot be placed on the freelist', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected pointer-map freelist insertion rejection');
};

return $tests;
