<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageSize, int $pageCount): string {
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

$makeLeafPage = static function (string $pageType = "\x0d"): string {
    $page = str_repeat("\xdd", 512);
    $page[0] = $pageType;
    $page = substr_replace($page, pack('n', 360), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 340), 5, 2);
    $page[7] = chr(7);
    $page = substr_replace($page, pack('n', 492), 8, 2);
    $page = substr_replace($page, str_repeat('L', 12), 492, 12);
    $page = substr_replace($page, pack('n', 376) . pack('n', 12), 360, 4);
    $page = substr_replace($page, pack('n', 392) . pack('n', 10), 376, 4);
    $page = substr_replace($page, pack('n', 406) . pack('n', 12), 392, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', 14), 406, 4);

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

$fixture = static function (
    int $maxTruncatedPages = 8,
    bool $secureDelete = true,
    bool $clearCoalescedFragments = true,
    string $pageType = "\x0d",
) use ($makeFirstPage, $makeLeafPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 106;
    $releasedPages = [104, 106];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount);
    $pages[3] = $makeLeafPage($pageType);
    $pages[104] = pack('N', 106) . str_repeat('O', $pageSize - 4);
    $pages[106] = pack('N', 0) . str_repeat('P', $pageSize - 4);

    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    $putPointerMapEntry($pages, 104, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 42, $pageSize);
    $putPointerMapEntry($pages, 106, SQLitePointerMapEntry::OVERFLOW_PAGE, 104, $pageSize);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNextPlan::fromDatabaseDeleteResults(
        $database,
        3,
        [[
            'source' => 'wp_options-transient-tail-overflow-delete',
            'obsolete_overflow_page_numbers' => $releasedPages,
            'rowids' => [1701],
        ]],
        $maxTruncatedPages,
        $secureDelete,
        $clearCoalescedFragments,
    );

    return [$database, $plan];
};

$afterHeader = static fn (array $fx): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($fx[1]->nextDatabase->page(3), 512);

$cases = [
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'leaf page type' => static fn (array $fx): mixed => $fx[1]->coalescePlan->pageType,
    'leaf page number' => static fn (array $fx): mixed => $fx[1]->coalescePlan->pageNumber,
    'fragmented bytes before' => static fn (array $fx): mixed => $fx[1]->coalescePlan->fragmentedBytesBefore,
    'fragmented bytes after' => static fn (array $fx): mixed => $fx[1]->coalescePlan->fragmentedBytesAfter,
    'coalesced bytes' => static fn (array $fx): mixed => $fx[1]->coalescePlan->coalescedFragmentBytes,
    'freeblock before count' => static fn (array $fx): mixed => count($fx[1]->coalescePlan->beforeFreeblocks),
    'freeblock after count' => static fn (array $fx): mixed => count($fx[1]->coalescePlan->afterFreeblocks),
    'before freeblock offsets' => static fn (array $fx): mixed => array_column($fx[1]->coalescePlan->beforeFreeblocks, 'offset'),
    'after freeblock offset' => static fn (array $fx): mixed => $fx[1]->coalescePlan->afterFreeblocks[0]['offset'],
    'after freeblock size' => static fn (array $fx): mixed => $fx[1]->coalescePlan->afterFreeblocks[0]['size'],
    'leaf transition phases' => static fn (array $fx): mixed => array_column($fx[1]->leafFreeblockTransitions, 'phase'),
    'leaf transition counts' => static fn (array $fx): mixed => array_column($fx[1]->leafFreeblockTransitions, 'freeblock_count'),
    'released overflow pages' => static fn (array $fx): mixed => $fx[1]->vacuumPlan->releasedOverflowPages(),
    'current freelist after release includes pointer-map gap pages' => static fn (array $fx): mixed => $fx[1]->vacuumPlan->currentFreelistPageNumbers(),
    'truncated pages cross pointer-map boundary' => static fn (array $fx): mixed => $fx[1]->truncatedPageNumbers(),
    'final database page count' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pageCount(),
    'final header database size' => static fn (array $fx): mixed => $fx[1]->nextDatabase->header->databaseSizePages,
    'final first freelist trunk' => static fn (array $fx): mixed => $fx[1]->nextDatabase->header->firstFreelistTrunkPage,
    'final freelist page count' => static fn (array $fx): mixed => $fx[1]->nextDatabase->header->freelistPageCount,
    'final freelist pages' => static fn (array $fx): mixed => $fx[1]->survivingFreelistPageNumbers(),
    'updated page numbers omit truncated tail' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers(),
    'materialized byte length' => static fn (array $fx): mixed => strlen($fx[1]->nextDatabase->toBytes()),
    'materialized apply page count' => static fn (array $fx): mixed => $fx[1]->materializedApplySummary()['database_page_count'],
    'materialized apply truncated pages' => static fn (array $fx): mixed => $fx[1]->materializedApplySummary()['truncated_page_numbers'],
    'pointer-map transition statuses' => static fn (array $fx): mixed => array_column($fx[1]->vacuumPlan->pointerMapVacuumTransitions(), 'status'),
    'pointer-map transition current types' => static fn (array $fx): mixed => array_column($fx[1]->vacuumPlan->pointerMapVacuumTransitions(), 'current_type_name'),
    'pointer-map transition truncated types' => static fn (array $fx): mixed => array_column($fx[1]->vacuumPlan->pointerMapVacuumTransitions(), 'truncated_type_name'),
    'toArray surviving freelist pages' => static fn (array $fx): mixed => $fx[1]->toArray()['surviving_freelist_page_numbers'],
    'toArray truncated pointer map pages' => static fn (array $fx): mixed => $fx[1]->toArray()['truncated_pointer_map_pages'],
    'toArray materialized page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['materialized_page_numbers'],
    'toArray truncated row page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['truncated_row_page_numbers'],
    'row kinds' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'kind'),
    'row pages' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'page_number'),
    'row materialized flags' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'materialized'),
    'row vacuum statuses' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'vacuum_status'),
    'row pointer map pages' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'pointer_map_page'),
    'row source pointer types' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'source_pointer_map_type'),
    'row free pointer types' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'free_pointer_map_type'),
    'row truncated pointer types' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'truncated_pointer_map_type'),
    'row sources' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'source'),
    'overflow source next pages' => static fn (array $fx): mixed => array_values(array_filter(array_column($fx[1]->rows, 'source_overflow_next_page'), static fn (mixed $value): bool => $value !== null)),
    'overflow next next pages' => static fn (array $fx): mixed => array_values(array_filter(array_column($fx[1]->rows, 'next_overflow_next_page'), static fn (mixed $value): bool => $value !== null)),
    'freelist roles' => static fn (array $fx): mixed => array_column($fx[1]->rows, 'freelist_role'),
    'leaf row freeblock offsets' => static fn (array $fx): mixed => $fx[1]->rows[0]['freeblock_offsets'],
    'leaf row freeblock sizes' => static fn (array $fx): mixed => $fx[1]->rows[0]['freeblock_sizes'],
    'leaf row current-next fragments' => static fn (array $fx): mixed => $fx[1]->rows[0]['current_next_fragment_bytes'],
    'materialized row count' => static fn (array $fx): mixed => count($fx[1]->materializedRows()),
    'truncated row count' => static fn (array $fx): mixed => count($fx[1]->truncatedRows()),
    'bounded materialized rows include survivor' => static fn (): mixed => array_column($fixture(2)[1]->materializedRows(), 'page_number'),
    'toArray nested vacuum final page count' => static fn (array $fx): mixed => $fx[1]->toArray()['vacuum']['final_database_page_count'],
    'toArray nested coalesce action' => static fn (array $fx): mixed => $fx[1]->toArray()['coalesce']['action'],
    'next page 103 exists' => static fn (array $fx): mixed => strlen($fx[1]->nextDatabase->page(103)),
    'next page 105 was truncated' => static function (array $fx): string {
        try {
            $fx[1]->nextDatabase->page(105);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'next page 42 pointer map survives' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pointerMapEntryForPage(42)->typeName(),
    'next page 42 pointer map parent survives' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pointerMapEntryForPage(42)->parentPageNumber,
    'leaf integrity ok' => static fn (array $fx): mixed => $afterHeader($fx)->freeblockIntegrityReport($fx[1]->nextDatabase->page(3))['status'],
    'leaf fragment report ok' => static fn (array $fx): mixed => $afterHeader($fx)->freeblockFragmentReport($fx[1]->nextDatabase->page(3))['status'],
    'leaf current-next fragments cleared' => static fn (array $fx): mixed => $afterHeader($fx)->freeblockFragmentReport($fx[1]->nextDatabase->page(3))['current_next_fragment_bytes'],
    'leaf secure-delete zeroed' => static fn (array $fx): mixed => $afterHeader($fx)->freeblockSecureDeleteReport($fx[1]->nextDatabase->page(3))['secure_delete_payload_zeroed'],
    'index leaf page type accepted' => static fn (): mixed => $fixture(8, true, true, "\x0a")[1]->coalescePlan->pageType,
    'bounded pass keeps trunk below pointer-map page' => static fn (): mixed => $fixture(2)[1]->survivingFreelistPageNumbers(),
    'bounded pass final page count is pointer-map predecessor' => static fn (): mixed => $fixture(2)[1]->nextDatabase->pageCount(),
    'bounded pass keeps first trunk' => static fn (): mixed => $fixture(2)[1]->nextDatabase->header->firstFreelistTrunkPage,
    'bounded pass leaves one free page' => static fn (): mixed => $fixture(2)[1]->nextDatabase->header->freelistPageCount,
    'without clear leaves coalesced bytes' => static fn (): mixed => strpos($fixture(8, true, false)[1]->nextDatabase->page(3), str_repeat("\xdd", 4)) !== false,
    'without secure delete keeps surviving payload before truncation boundary' => static fn (): mixed => $fixture(1, false)[1]->nextDatabase->page(104)[8],
    'throws on zero truncation limit' => static function () use ($fixture): string {
        [$database] = $fixture();
        try {
            SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNextPlan::fromDatabaseDeleteResults($database, 3, [['obsolete_overflow_page_numbers' => [104]]], 0);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'throws on pointer-map freelist release' => static function () use ($fixture): string {
        [$database] = $fixture();
        try {
            SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNextPlan::fromDatabaseDeleteResults($database, 3, [['obsolete_overflow_page_numbers' => [105]]], 1);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
];

$expected = [
    'action label' => 'btree-freeblock-pointermap-vacuum-current-source-next',
    'leaf page type' => 'table-leaf',
    'leaf page number' => 3,
    'fragmented bytes before' => 7,
    'fragmented bytes after' => 5,
    'coalesced bytes' => 2,
    'freeblock before count' => 4,
    'freeblock after count' => 3,
    'before freeblock offsets' => [360, 376, 392, 406],
    'after freeblock offset' => 360,
    'after freeblock size' => 12,
    'leaf transition phases' => ['current', 'next'],
    'leaf transition counts' => [4, 3],
    'released overflow pages' => [104, 106],
    'current freelist after release includes pointer-map gap pages' => [104, 106],
    'truncated pages cross pointer-map boundary' => [106, 105, 104],
    'final database page count' => 103,
    'final header database size' => 103,
    'final first freelist trunk' => 0,
    'final freelist page count' => 0,
    'final freelist pages' => [],
    'updated page numbers omit truncated tail' => [1, 2, 3],
    'materialized byte length' => 103 * 512,
    'materialized apply page count' => 103,
    'materialized apply truncated pages' => [106, 105, 104],
    'pointer-map transition statuses' => ['truncated-from-database', 'truncated-from-database'],
    'pointer-map transition current types' => ['free-page', 'free-page'],
    'pointer-map transition truncated types' => ['free-page', 'free-page'],
    'toArray surviving freelist pages' => [],
    'toArray truncated pointer map pages' => [105],
    'toArray materialized page numbers' => [3],
    'toArray truncated row page numbers' => [104, 105, 106],
    'row kinds' => ['deleted-leaf-freeblock', 'released-overflow-page', 'truncated-pointer-map-page', 'released-overflow-page'],
    'row pages' => [3, 104, 105, 106],
    'row materialized flags' => [true, false, false, false],
    'row vacuum statuses' => ['materialized-leaf-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'row pointer map pages' => [2, 2, 105, 105],
    'row source pointer types' => ['root-page', 'free-page', null, 'free-page'],
    'row free pointer types' => ['root-page', null, null, null],
    'row truncated pointer types' => [null, 'free-page', 'pointer-map-page', 'free-page'],
    'row sources' => ['coalesced-delete-leaf', 'wp_options-transient-tail-overflow-delete', null, 'wp_options-transient-tail-overflow-delete'],
    'overflow source next pages' => [106, 0],
    'overflow next next pages' => [],
    'freelist roles' => [null, null, null, null],
    'leaf row freeblock offsets' => [360, 376, 392],
    'leaf row freeblock sizes' => [12, 10, 28],
    'leaf row current-next fragments' => 0,
    'materialized row count' => 1,
    'truncated row count' => 3,
    'bounded materialized rows include survivor' => [3, 104],
    'toArray nested vacuum final page count' => 103,
    'toArray nested coalesce action' => 'btree-freeblock-coalesce-current-next',
    'next page 103 exists' => 512,
    'next page 105 was truncated' => 'SQLite page 105 is not present in the database image',
    'next page 42 pointer map survives' => 'btree-page',
    'next page 42 pointer map parent survives' => 3,
    'leaf integrity ok' => 'ok',
    'leaf fragment report ok' => 'ok',
    'leaf current-next fragments cleared' => 0,
    'leaf secure-delete zeroed' => true,
    'index leaf page type accepted' => 'index-leaf',
    'bounded pass keeps trunk below pointer-map page' => [104],
    'bounded pass final page count is pointer-map predecessor' => 104,
    'bounded pass keeps first trunk' => 104,
    'bounded pass leaves one free page' => 1,
    'without clear leaves coalesced bytes' => true,
    'without secure delete keeps surviving payload before truncation boundary' => "\0",
    'throws on zero truncation limit' => 'SQLite overflow vacuum truncate plan requires a positive truncation limit',
    'throws on pointer-map freelist release' => 'SQLite auto-vacuum pointer-map pages cannot be placed on the freelist',
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree freeblock pointermap vacuum current source next ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

foreach (range(1, 22) as $index) {
    $tests['btree freeblock pointermap vacuum current source next invariant ' . $index] = static function (TestRunner $t) use ($fixture, $index): void {
        [, $plan] = $fixture($index % 2 === 0 ? 8 : 2, $index % 3 !== 0, $index % 4 !== 0, $index % 5 === 0 ? "\x0a" : "\x0d");
        $database = $plan->nextDatabase;
        $header = SQLiteBTreePageHeader::parsePage($database->page(3), 512);

        $t->same($database->pageCount(), $plan->materializedApplySummary()['database_page_count']);
        $t->same(strlen($database->toBytes()), $plan->materializedApplySummary()['byte_length']);
        $t->same($database->freelistPageNumbers(), $plan->survivingFreelistPageNumbers());
        $t->same('ok', $header->freeblockIntegrityReport($database->page(3))['status']);
        $t->same(0, $header->freeblockFragmentReport($database->page(3))['current_next_fragment_bytes']);
        $t->same('btree-page', $database->pointerMapEntryForPage(42)->typeName());
        $t->same([104, 106], $plan->vacuumPlan->releasedOverflowPages());
        $t->same('ok', $plan->rows[0]['freeblock_status']);
        $t->same([105], $plan->truncatedPointerMapPages());
        $t->same($database->pageCount(), $plan->toArray()['final_database_page_count']);
    };
}

return $tests;
