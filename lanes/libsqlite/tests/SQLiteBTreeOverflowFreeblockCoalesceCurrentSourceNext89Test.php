<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockCoalesceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage89 = static function (int $pageSize, int $pageCount): string {
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
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$makeLeafPage89 = static function (string $pageType = "\x0d"): string {
    $page = str_repeat("\xcc", 512);
    $page[0] = $pageType;
    $page = substr_replace($page, pack('n', 400), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 384), 5, 2);
    $page[7] = chr(6);
    $page = substr_replace($page, pack('n', 500), 8, 2);
    $page = substr_replace($page, str_repeat('W', 8), 500, 8);
    $page = substr_replace($page, pack('n', 413) . pack('n', 12), 400, 4);
    $page = substr_replace($page, pack('n', 428) . pack('n', 12), 413, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', 16), 428, 4);

    return $page;
};

$putPointerMapEntry89 = static function (string &$pointerMapPage, int $pageNumber, int $type, int $parentPageNumber): void {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$fixture89 = static function (bool $secureDelete = true, bool $clearCoalescedFragments = true, string $pageType = "\x0d") use ($makeFirstPage89, $makeLeafPage89, $putPointerMapEntry89): array {
    $pageSize = 512;
    $pointerMapPage = str_repeat("\0", $pageSize);
    $putPointerMapEntry89($pointerMapPage, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry89($pointerMapPage, 5, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry89($pointerMapPage, 6, SQLitePointerMapEntry::OVERFLOW_PAGE, 5);

    $database = SQLiteDatabase::fromBytes(
        $makeFirstPage89($pageSize, 6)
        . $pointerMapPage
        . $makeLeafPage89($pageType)
        . str_repeat("\0", $pageSize)
        . pack('N', 6) . str_repeat('O', $pageSize - 4)
        . pack('N', 0) . str_repeat('P', $pageSize - 4),
    );

    $plan = SQLiteBTreeOverflowFreeblockCoalesceCurrentSourceNextPlan::fromDatabaseDeleteResults(
        $database,
        3,
        [
            [
                'source' => 'wp_options-transient-overflow-delete',
                'obsolete_overflow_page_numbers' => [5, 6],
                'rowids' => [901],
            ],
        ],
        $secureDelete,
        $clearCoalescedFragments,
    );

    return [$database, $plan];
};

$afterHeader89 = static fn (array $fx): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($fx[1]->database->page(3), 512);

$cases = [
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'leaf page number' => static fn (array $fx): mixed => $fx[1]->coalescePlan->pageNumber,
    'leaf page type' => static fn (array $fx): mixed => $fx[1]->coalescePlan->pageType,
    'fragmented bytes before' => static fn (array $fx): mixed => $fx[1]->coalescePlan->fragmentedBytesBefore,
    'fragmented bytes after' => static fn (array $fx): mixed => $fx[1]->coalescePlan->fragmentedBytesAfter,
    'coalesced bytes' => static fn (array $fx): mixed => $fx[1]->coalescePlan->coalescedFragmentBytes,
    'coalesced fragment count' => static fn (array $fx): mixed => count($fx[1]->coalescePlan->coalescedFragments),
    'coalesced fragment byte list' => static fn (array $fx): mixed => array_column($fx[1]->coalescePlan->coalescedFragments, 'fragment_bytes'),
    'freeblock count before' => static fn (array $fx): mixed => count($fx[1]->coalescePlan->beforeFreeblocks),
    'freeblock count after' => static fn (array $fx): mixed => count($fx[1]->coalescePlan->afterFreeblocks),
    'before freeblock offsets' => static fn (array $fx): mixed => array_column($fx[1]->coalescePlan->beforeFreeblocks, 'offset'),
    'before freeblock ends' => static fn (array $fx): mixed => array_column($fx[1]->coalescePlan->beforeFreeblocks, 'end_offset'),
    'after freeblock offset' => static fn (array $fx): mixed => $fx[1]->coalescePlan->afterFreeblocks[0]['offset'],
    'after freeblock size' => static fn (array $fx): mixed => $fx[1]->coalescePlan->afterFreeblocks[0]['size'],
    'after freeblock end' => static fn (array $fx): mixed => $fx[1]->coalescePlan->afterFreeblocks[0]['end_offset'],
    'after freeblock next null' => static fn (array $fx): mixed => $fx[1]->coalescePlan->afterFreeblocks[0]['next_offset'],
    'release source label' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[0]['source'],
    'release source pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[0]['pages'],
    'release source count' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[0]['count'],
    'released overflow pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->releasedOverflowPages,
    'freed page numbers' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->freedPageNumbers,
    'freelist leaf pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->leafPageNumbers,
    'new trunk page numbers' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->newTrunkPageNumbers,
    'first freelist trunk page' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->firstFreelistTrunkPage,
    'freelist page count' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->freelistPageCount,
    'cleared page numbers' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->clearedPageNumbers,
    'updated pointer map pages' => static fn (array $fx): mixed => array_keys($fx[1]->releasePlan->freePlan->updatedPointerMapPages),
    'freed pointer map pages' => static fn (array $fx): mixed => array_column($fx[1]->releasePlan->freePlan->freedPointerMapEntries, 'page_number'),
    'freed pointer map types' => static fn (array $fx): mixed => array_column($fx[1]->releasePlan->freePlan->freedPointerMapEntries, 'type_name'),
    'updated page numbers' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers,
    'toArray updated page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_page_numbers'],
    'toArray coalesce nested action' => static fn (array $fx): mixed => $fx[1]->toArray()['coalesce']['action'],
    'toArray release nested count' => static fn (array $fx): mixed => $fx[1]->toArray()['release']['released_overflow_page_count'],
    'materialized database page count' => static fn (array $fx): mixed => $fx[1]->database->pageCount(),
    'materialized header freelist count' => static fn (array $fx): mixed => $fx[1]->database->header->freelistPageCount,
    'materialized header first trunk' => static fn (array $fx): mixed => $fx[1]->database->header->firstFreelistTrunkPage,
    'materialized leaf fragment report ok' => static fn (array $fx): mixed => $afterHeader89($fx)->freeblockCurrentNextFragmentReport($fx[1]->database->page(3))['status'],
    'materialized leaf has no current next fragments' => static fn (array $fx): mixed => $afterHeader89($fx)->freeblockCurrentNextFragmentReport($fx[1]->database->page(3))['current_next_fragment_bytes'],
    'materialized leaf integrity ok' => static fn (array $fx): mixed => $afterHeader89($fx)->freeblockIntegrityReport($fx[1]->database->page(3))['status'],
    'materialized leaf secure-delete zeroed' => static fn (array $fx): mixed => $afterHeader89($fx)->freeblockSecureDeleteReport($fx[1]->database->page(3))['secure_delete_payload_zeroed'],
    'materialized overflow page 5 is freelist trunk' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->database->page(5), 4, 4))[1],
    'materialized overflow page 6 zeroed' => static fn (array $fx): mixed => trim(substr($fx[1]->database->page(6), 4), "\0") === '',
    'materialized page 5 pointer map type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(5)->typeName(),
    'materialized page 6 pointer map type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(6)->typeName(),
    'materialized page 5 pointer map parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(5)->parentPageNumber,
    'materialized page 6 pointer map parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(6)->parentPageNumber,
    'materialized freelist order' => static fn (array $fx): mixed => $fx[1]->database->freelistPageNumbers(),
    'materialized allocation order' => static fn (array $fx): mixed => $fx[1]->database->freelistAllocationOrder(),
    'page image leaf matches database' => static fn (array $fx): mixed => $fx[1]->pageImages[3] === $fx[1]->database->page(3),
    'page image first page matches header' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->pageImages[1])->freelistPageCount,
    'index leaf page type accepted' => static fn (): mixed => $fixture89(true, true, "\x0a")[1]->coalescePlan->pageType,
    'index leaf releases same pages' => static fn (): mixed => $fixture89(true, true, "\x0a")[1]->releasePlan->releasedOverflowPages,
    'without clear leaves coalesced payload bytes' => static fn (): mixed => strpos($fixture89(true, false)[1]->database->page(3), str_repeat("\xcc", 4)) !== false,
    'without secure delete keeps overflow leaf payload' => static fn (): mixed => substr($fixture89(false, true)[1]->database->page(6), 4, 1),
    'throws on empty delete results' => static function () use ($fixture89): string {
        [$database] = $fixture89();
        try {
            SQLiteBTreeOverflowFreeblockCoalesceCurrentSourceNextPlan::fromDatabaseDeleteResults($database, 3, []);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'throws on invalid leaf page' => static function () use ($fixture89): string {
        [$database] = $fixture89();
        try {
            SQLiteBTreeOverflowFreeblockCoalesceCurrentSourceNextPlan::fromDatabaseDeleteResults($database, 9, [['obsolete_overflow_page_numbers' => [5]]]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
];

$expected = [
    'action label' => 'btree-overflow-freeblock-coalesce-current-source-next89',
    'leaf page number' => 3,
    'leaf page type' => 'table-leaf',
    'fragmented bytes before' => 6,
    'fragmented bytes after' => 2,
    'coalesced bytes' => 4,
    'coalesced fragment count' => 2,
    'coalesced fragment byte list' => [1, 3],
    'freeblock count before' => 3,
    'freeblock count after' => 1,
    'before freeblock offsets' => [400, 413, 428],
    'before freeblock ends' => [412, 425, 444],
    'after freeblock offset' => 400,
    'after freeblock size' => 44,
    'after freeblock end' => 444,
    'after freeblock next null' => null,
    'release source label' => 'wp_options-transient-overflow-delete',
    'release source pages' => [5, 6],
    'release source count' => 2,
    'released overflow pages' => [5, 6],
    'freed page numbers' => [5, 6],
    'freelist leaf pages' => [6],
    'new trunk page numbers' => [5],
    'first freelist trunk page' => 5,
    'freelist page count' => 2,
    'cleared page numbers' => [6],
    'updated pointer map pages' => [2],
    'freed pointer map pages' => [5, 6],
    'freed pointer map types' => ['free-page', 'free-page'],
    'updated page numbers' => [1, 2, 3, 5, 6],
    'toArray updated page numbers' => [1, 2, 3, 5, 6],
    'toArray coalesce nested action' => 'btree-freeblock-coalesce-current-next',
    'toArray release nested count' => 2,
    'materialized database page count' => 6,
    'materialized header freelist count' => 2,
    'materialized header first trunk' => 5,
    'materialized leaf fragment report ok' => 'ok',
    'materialized leaf has no current next fragments' => 0,
    'materialized leaf integrity ok' => 'ok',
    'materialized leaf secure-delete zeroed' => true,
    'materialized overflow page 5 is freelist trunk' => 1,
    'materialized overflow page 6 zeroed' => true,
    'materialized page 5 pointer map type' => 'free-page',
    'materialized page 6 pointer map type' => 'free-page',
    'materialized page 5 pointer map parent' => 0,
    'materialized page 6 pointer map parent' => 0,
    'materialized freelist order' => [5, 6],
    'materialized allocation order' => [6, 5],
    'page image leaf matches database' => true,
    'page image first page matches header' => 2,
    'index leaf page type accepted' => 'index-leaf',
    'index leaf releases same pages' => [5, 6],
    'without clear leaves coalesced payload bytes' => true,
    'without secure delete keeps overflow leaf payload' => 'P',
    'throws on empty delete results' => 'SQLite overflow freelist release requires at least one delete result',
    'throws on invalid leaf page' => 'SQLite freeblock coalesce page is outside the database image',
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['btree overflow freeblock coalesce current source next89 ' . $name] = static function (TestRunner $t) use ($fixture89, $read, $expected, $name): void {
        $t->same($expected[$name], $read($fixture89()));
    };
}

foreach (range(1, 16) as $index) {
    $tests['btree overflow freeblock coalesce current source next89 invariant ' . $index] = static function (TestRunner $t) use ($fixture89, $index): void {
        [, $plan] = $fixture89($index % 2 === 0, $index % 3 !== 0, $index % 4 === 0 ? "\x0a" : "\x0d");
        $header = SQLiteBTreePageHeader::parsePage($plan->database->page(3), 512);

        $t->same('ok', $header->freeblockIntegrityReport($plan->database->page(3))['status']);
        $t->same(0, $header->freeblockCurrentNextFragmentReport($plan->database->page(3))['current_next_fragment_bytes']);
        $t->same([5, 6], $plan->releasePlan->releasedOverflowPages);
        $t->same([5, 6], $plan->database->freelistPageNumbers());
        $t->same('free-page', $plan->database->pointerMapEntryForPage(5)->typeName());
        $t->same('free-page', $plan->database->pointerMapEntryForPage(6)->typeName());
    };
}

return $tests;
