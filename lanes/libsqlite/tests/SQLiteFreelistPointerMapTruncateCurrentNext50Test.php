<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageSize, int $pageCount, int $firstTrunk, int $freelistCount, int $largestRoot = 3): string {
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
    $page = substr_replace($page, pack('N', $firstTrunk), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRoot), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

$fixture = static function () use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pages = array_fill(1, 12, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, 12, 5, 6);
    $pages[5] = SQLiteFreelistTrunkPage::assemble(null, [4, 9, 10, 11, 12], $pageSize);

    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::FREE_PAGE, 0], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ([4, 9, 10, 11, 12] as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = $database->planFreelistTailTruncation(4);
    $postPages = [];
    for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
        $postPages[$pageNumber] = $database->page($pageNumber);
    }
    foreach ($plan->pageImages() as $pageNumber => $page) {
        if ($pageNumber <= $plan->databasePageCount) {
            $postPages[$pageNumber] = $page;
        }
    }

    return [$database, $plan, SQLiteDatabase::fromBytes(implode('', $postPages))];
};

$cases = [
    'truncates current next tail pages' => static fn (array $fx): mixed => $fx[1]->truncatedPageNumbers,
    'database page count moves to surviving boundary' => static fn (array $fx): mixed => $fx[1]->databasePageCount,
    'first freelist trunk remains current trunk' => static fn (array $fx): mixed => $fx[1]->firstFreelistTrunkPage,
    'freelist page count subtracts truncated leaves' => static fn (array $fx): mixed => $fx[1]->freelistPageCount,
    'updates only current trunk image' => static fn (array $fx): mixed => array_keys($fx[1]->updatedFreelistPages),
    'page images include first page and trunk' => static fn (array $fx): mixed => array_keys($fx[1]->pageImages()),
    'header database size is truncated' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->firstPage)->databaseSizePages,
    'header first trunk remains five' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->firstPage)->firstFreelistTrunkPage,
    'header freelist count remains two' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->firstPage)->freelistPageCount,
    'post freelist pages keep trunk and non-tail leaf' => static fn (array $fx): mixed => $fx[2]->freelistPageNumbers(),
    'post allocation order keeps non-tail leaf before trunk' => static fn (array $fx): mixed => $fx[2]->freelistAllocationOrder(),
    'post current trunk leaf count' => static fn (array $fx): mixed => count(SQLiteFreelistTrunkPage::parse(5, $fx[1]->updatedFreelistPages[5], 512, 8)->leafPageNumbers),
    'post current trunk leaf number' => static fn (array $fx): mixed => SQLiteFreelistTrunkPage::parse(5, $fx[1]->updatedFreelistPages[5], 512, 8)->leafPageNumbers,
    'post current trunk next trunk remains null' => static fn (array $fx): mixed => SQLiteFreelistTrunkPage::parse(5, $fx[1]->updatedFreelistPages[5], 512, 8)->nextTrunkPage,
    'records four truncated pointer-map entries' => static fn (array $fx): mixed => count($fx[1]->truncatedPointerMapEntries),
    'truncated pointer-map pages are tail pages' => static fn (array $fx): mixed => array_column($fx[1]->truncatedPointerMapEntries, 'page_number'),
    'truncated pointer-map types are free page' => static fn (array $fx): mixed => array_column($fx[1]->truncatedPointerMapEntries, 'type_name'),
    'truncated pointer-map parents are zero' => static fn (array $fx): mixed => array_column($fx[1]->truncatedPointerMapEntries, 'parent_page_number'),
    'truncated pointer-map page numbers are current page two' => static fn (array $fx): mixed => array_unique(array_column($fx[1]->truncatedPointerMapEntries, 'pointer_map_page')),
    'truncated pointer-map offsets preserve current next order' => static fn (array $fx): mixed => array_column($fx[1]->truncatedPointerMapEntries, 'offset'),
    'boundary pointer-map entry exists' => static fn (array $fx): mixed => $fx[1]->boundaryPointerMapEntry !== null,
    'boundary pointer-map page number' => static fn (array $fx): mixed => $fx[1]->boundaryPointerMapEntry['page_number'],
    'boundary pointer-map type is btree page' => static fn (array $fx): mixed => $fx[1]->boundaryPointerMapEntry['type_name'],
    'boundary pointer-map parent is root page' => static fn (array $fx): mixed => $fx[1]->boundaryPointerMapEntry['parent_page_number'],
    'boundary pointer-map page is page two' => static fn (array $fx): mixed => $fx[1]->boundaryPointerMapEntry['pointer_map_page'],
    'boundary pointer-map offset' => static fn (array $fx): mixed => $fx[1]->boundaryPointerMapEntry['offset'],
    'post boundary pointer-map matches plan boundary' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(8)->toArray(),
    'post free leaf pointer-map remains free' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(4)->typeName(),
    'post current trunk pointer-map remains free' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(5)->typeName(),
    'post root pointer-map remains root' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(3)->typeName(),
    'post btree boundary remains parented to page three' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(8)->parentPageNumber,
    'post page count is eight' => static fn (array $fx): mixed => $fx[2]->pageCount(),
    'post header database page count is eight' => static fn (array $fx): mixed => $fx[2]->header->databaseSizePages,
    'post header freelist trunk is five' => static fn (array $fx): mixed => $fx[2]->header->firstFreelistTrunkPage,
    'post header freelist page count is two' => static fn (array $fx): mixed => $fx[2]->header->freelistPageCount,
    'toArray truncated pages' => static fn (array $fx): mixed => $fx[1]->toArray()['truncated_page_numbers'],
    'toArray database page count' => static fn (array $fx): mixed => $fx[1]->toArray()['database_page_count'],
    'toArray first freelist trunk' => static fn (array $fx): mixed => $fx[1]->toArray()['first_freelist_trunk_page'],
    'toArray freelist page count' => static fn (array $fx): mixed => $fx[1]->toArray()['freelist_page_count'],
    'toArray updated freelist page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_freelist_page_numbers'],
    'toArray pointer-map entry count' => static fn (array $fx): mixed => count($fx[1]->toArray()['truncated_pointer_map_entries']),
    'toArray boundary pointer-map entry page' => static fn (array $fx): mixed => $fx[1]->toArray()['boundary_pointer_map_entry']['page_number'],
    'single page truncation records only last pointer-map entry' => static fn (array $fx): mixed => array_column($fx[0]->planFreelistTailTruncation(1)->truncatedPointerMapEntries, 'page_number'),
    'single page truncation boundary is previous tail' => static fn (array $fx): mixed => $fx[0]->planFreelistTailTruncation(1)->boundaryPointerMapEntry['page_number'],
    'two page truncation records current next tail entries' => static fn (array $fx): mixed => array_column($fx[0]->planFreelistTailTruncation(2)->truncatedPointerMapEntries, 'page_number'),
    'three page truncation boundary is nine' => static fn (array $fx): mixed => $fx[0]->planFreelistTailTruncation(3)->boundaryPointerMapEntry['page_number'],
    'no truncation before non-free tail records no pointer-map entries' => static fn (array $fx): mixed => $fx[2]->planFreelistTailTruncation(2)->truncatedPointerMapEntries,
    'throws on zero truncation count' => static function (array $fx): mixed {
        try {
            $fx[0]->planFreelistTailTruncation(0);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return null;
    },
    'throws on negative truncation count' => static function (array $fx): mixed {
        try {
            $fx[0]->planFreelistTailTruncation(-1);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return null;
    },
    'post database rejects truncated page lookup' => static function (array $fx): mixed {
        try {
            $fx[2]->page(9);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return null;
    },
];

$expected = [
    'truncates current next tail pages' => [12, 11, 10, 9],
    'database page count moves to surviving boundary' => 8,
    'first freelist trunk remains current trunk' => 5,
    'freelist page count subtracts truncated leaves' => 2,
    'updates only current trunk image' => [5],
    'page images include first page and trunk' => [1, 5],
    'header database size is truncated' => 8,
    'header first trunk remains five' => 5,
    'header freelist count remains two' => 2,
    'post freelist pages keep trunk and non-tail leaf' => [5, 4],
    'post allocation order keeps non-tail leaf before trunk' => [4, 5],
    'post current trunk leaf count' => 1,
    'post current trunk leaf number' => [4],
    'post current trunk next trunk remains null' => null,
    'records four truncated pointer-map entries' => 4,
    'truncated pointer-map pages are tail pages' => [12, 11, 10, 9],
    'truncated pointer-map types are free page' => ['free-page', 'free-page', 'free-page', 'free-page'],
    'truncated pointer-map parents are zero' => [0, 0, 0, 0],
    'truncated pointer-map page numbers are current page two' => [2],
    'truncated pointer-map offsets preserve current next order' => [45, 40, 35, 30],
    'boundary pointer-map entry exists' => true,
    'boundary pointer-map page number' => 8,
    'boundary pointer-map type is btree page' => 'btree-page',
    'boundary pointer-map parent is root page' => 3,
    'boundary pointer-map page is page two' => 2,
    'boundary pointer-map offset' => 25,
    'post boundary pointer-map matches plan boundary' => ['page_number' => 8, 'pointer_map_page' => 2, 'offset' => 25, 'type' => SQLitePointerMapEntry::BTREE_PAGE, 'type_name' => 'btree-page', 'parent_page_number' => 3],
    'post free leaf pointer-map remains free' => 'free-page',
    'post current trunk pointer-map remains free' => 'free-page',
    'post root pointer-map remains root' => 'root-page',
    'post btree boundary remains parented to page three' => 3,
    'post page count is eight' => 8,
    'post header database page count is eight' => 8,
    'post header freelist trunk is five' => 5,
    'post header freelist page count is two' => 2,
    'toArray truncated pages' => [12, 11, 10, 9],
    'toArray database page count' => 8,
    'toArray first freelist trunk' => 5,
    'toArray freelist page count' => 2,
    'toArray updated freelist page numbers' => [5],
    'toArray pointer-map entry count' => 4,
    'toArray boundary pointer-map entry page' => 8,
    'single page truncation records only last pointer-map entry' => [12],
    'single page truncation boundary is previous tail' => 11,
    'two page truncation records current next tail entries' => [12, 11],
    'three page truncation boundary is nine' => 9,
    'no truncation before non-free tail records no pointer-map entries' => [],
    'throws on zero truncation count' => 'SQLite freelist tail truncation count must be positive',
    'throws on negative truncation count' => 'SQLite freelist tail truncation count must be positive',
    'post database rejects truncated page lookup' => 'SQLite page 9 is not present in the database image',
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['freelist pointer-map truncate current next50 ' . $name] = static function (TestRunner $t) use ($fixture, $read, $expected, $name): void {
        $t->same($expected[$name], $read($fixture()));
    };
}

return $tests;
