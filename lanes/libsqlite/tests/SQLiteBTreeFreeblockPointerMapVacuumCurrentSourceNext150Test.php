<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext150Plan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage150 = static function (int $pageSize, int $pageCount): string {
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

$makeLeafPage150 = static function (string $pageType = "\x0d"): string {
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

$putPointerMapEntry150 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$fixture150 = static function (
    int $maxTruncatedPages = 8,
    bool $secureDelete = true,
    bool $clearCoalescedFragments = true,
    string $pageType = "\x0d",
) use ($makeFirstPage150, $makeLeafPage150, $putPointerMapEntry150): array {
    $pageSize = 512;
    $pageCount = 106;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage150($pageSize, $pageCount);
    $pages[3] = $makeLeafPage150($pageType);
    $pages[104] = pack('N', 106) . str_repeat('O', $pageSize - 4);
    $pages[106] = pack('N', 0) . str_repeat('P', $pageSize - 4);

    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry150($pages, $pageNumber, $type, $parent, $pageSize);
    }
    $putPointerMapEntry150($pages, 104, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 42, $pageSize);
    $putPointerMapEntry150($pages, 106, SQLitePointerMapEntry::OVERFLOW_PAGE, 104, $pageSize);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext150Plan::fromDatabaseDeleteResults(
        $database,
        3,
        [[
            'source' => 'wp_options-transient-tail-overflow-delete-next150',
            'obsolete_overflow_page_numbers' => [104, 106],
            'rowids' => [15001],
        ]],
        $maxTruncatedPages,
        $secureDelete,
        $clearCoalescedFragments,
    );

    return [$database, $plan];
};

$message150 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases150 = [
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'leaf page' => static fn (array $fx): mixed => $fx[1]->toArray()['leaf_page'],
    'leaf page type' => static fn (array $fx): mixed => $fx[1]->toArray()['leaf_page_type'],
    'released overflow pages' => static fn (array $fx): mixed => $fx[1]->toArray()['released_overflow_pages'],
    'truncated pages' => static fn (array $fx): mixed => $fx[1]->toArray()['truncated_page_numbers'],
    'truncated pointer map pages' => static fn (array $fx): mixed => $fx[1]->truncatedPointerMapPages(),
    'surviving freelist pages' => static fn (array $fx): mixed => $fx[1]->toArray()['surviving_freelist_page_numbers'],
    'materialized page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['materialized_page_numbers'],
    'truncated row page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['truncated_row_page_numbers'],
    'final page count' => static fn (array $fx): mixed => $fx[1]->toArray()['final_database_page_count'],
    'updated page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_page_numbers'],
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
    'leaf freeblock offsets' => static fn (array $fx): mixed => $fx[1]->rows[0]['freeblock_offsets'],
    'leaf freeblock sizes' => static fn (array $fx): mixed => $fx[1]->rows[0]['freeblock_sizes'],
    'leaf fragmented before' => static fn (array $fx): mixed => $fx[1]->rows[0]['fragmented_bytes_before'],
    'leaf fragmented after' => static fn (array $fx): mixed => $fx[1]->rows[0]['fragmented_bytes_after'],
    'leaf coalesced bytes' => static fn (array $fx): mixed => $fx[1]->rows[0]['coalesced_fragment_bytes'],
    'leaf freeblock status' => static fn (array $fx): mixed => $fx[1]->rows[0]['freeblock_status'],
    'leaf current-next fragments' => static fn (array $fx): mixed => $fx[1]->rows[0]['current_next_fragment_bytes'],
    'materialized row count' => static fn (array $fx): mixed => count($fx[1]->materializedRows()),
    'truncated row count' => static fn (array $fx): mixed => count($fx[1]->truncatedRows()),
    'base action' => static fn (array $fx): mixed => $fx[1]->toArray()['base_plan']['action'],
    'base byte length' => static fn (array $fx): mixed => $fx[1]->basePlan->materializedApplySummary()['byte_length'],
    'next page 103 length' => static fn (array $fx): mixed => strlen($fx[1]->basePlan->nextDatabase->page(103)),
    'next page 105 missing' => static function (array $fx): string {
        try {
            $fx[1]->basePlan->nextDatabase->page(105);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'leaf hash changed' => static fn (array $fx): mixed => $fx[1]->rows[0]['source_page_hash'] !== $fx[1]->rows[0]['next_page_hash'],
    'released page hashes truncated' => static fn (array $fx): mixed => array_slice(array_column($fx[1]->rows, 'next_page_hash'), 2),
    'bounded survivor pages' => static fn (): mixed => $fixture150(2)[1]->toArray()['surviving_freelist_page_numbers'],
    'bounded materialized pages' => static fn (): mixed => $fixture150(2)[1]->toArray()['materialized_page_numbers'],
    'bounded truncated pointer map pages' => static fn (): mixed => $fixture150(2)[1]->truncatedPointerMapPages(),
    'bounded final page count' => static fn (): mixed => $fixture150(2)[1]->toArray()['final_database_page_count'],
    'index leaf accepted' => static fn (): mixed => $fixture150(8, true, true, "\x0a")[1]->toArray()['leaf_page_type'],
    'without clear fragments remain' => static fn (): mixed => $fixture150(8, true, false)[1]->rows[0]['current_next_fragment_bytes'],
    'zero truncation rejected' => static fn (array $fx): mixed => $message150(static fn () => $fixture150(0)),
    'pointer map freelist release rejected' => static function (array $fx) use ($fixture150, $message150): string {
        [$database] = $fixture150();
        return $message150(static fn () => SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext150Plan::fromDatabaseDeleteResults(
            $database,
            3,
            [['obsolete_overflow_page_numbers' => [105]]],
            1,
        ));
    },
];

$expected150 = [
    'action label' => 'btree-freeblock-pointermap-vacuum-current-source-next150',
    'leaf page' => 3,
    'leaf page type' => 'table-leaf',
    'released overflow pages' => [104, 106],
    'truncated pages' => [106, 105, 104],
    'truncated pointer map pages' => [105],
    'surviving freelist pages' => [],
    'materialized page numbers' => [3],
    'truncated row page numbers' => [104, 105, 106],
    'final page count' => 103,
    'updated page numbers' => [1, 2, 3],
    'row kinds' => ['deleted-leaf-freeblock', 'released-overflow-page', 'truncated-pointer-map-page', 'released-overflow-page'],
    'row pages' => [3, 104, 105, 106],
    'row materialized flags' => [true, false, false, false],
    'row vacuum statuses' => ['materialized-leaf-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'row pointer map pages' => [2, 2, 105, 105],
    'row source pointer types' => ['root-page', 'free-page', null, 'free-page'],
    'row free pointer types' => ['root-page', null, null, null],
    'row truncated pointer types' => [null, 'free-page', 'pointer-map-page', 'free-page'],
    'row sources' => ['coalesced-delete-leaf', 'wp_options-transient-tail-overflow-delete-next150', null, 'wp_options-transient-tail-overflow-delete-next150'],
    'overflow source next pages' => [106, 0],
    'overflow next next pages' => [],
    'freelist roles' => [null, null, null, null],
    'leaf freeblock offsets' => [360, 376, 392],
    'leaf freeblock sizes' => [12, 10, 28],
    'leaf fragmented before' => 7,
    'leaf fragmented after' => 5,
    'leaf coalesced bytes' => 2,
    'leaf freeblock status' => 'ok',
    'leaf current-next fragments' => 0,
    'materialized row count' => 1,
    'truncated row count' => 3,
    'base action' => 'btree-freeblock-pointermap-vacuum-current-source-next93',
    'base byte length' => 103 * 512,
    'next page 103 length' => 512,
    'next page 105 missing' => 'SQLite page 105 is not present in the database image',
    'leaf hash changed' => true,
    'released page hashes truncated' => [null, null],
    'bounded survivor pages' => [104],
    'bounded materialized pages' => [3, 104],
    'bounded truncated pointer map pages' => [105],
    'bounded final page count' => 104,
    'index leaf accepted' => 'index-leaf',
    'without clear fragments remain' => 0,
    'zero truncation rejected' => 'SQLite overflow vacuum truncate plan requires a positive truncation limit',
    'pointer map freelist release rejected' => 'SQLite auto-vacuum pointer-map pages cannot be placed on the freelist',
];

$tests = [];
foreach ($cases150 as $name => $callback) {
    $tests['btree freeblock pointermap vacuum current source next150 ' . $name] = static function (TestRunner $t) use ($fixture150, $callback, $expected150, $name): void {
        $t->same($expected150[$name], $callback($fixture150()));
    };
}

foreach (range(1, 40) as $index) {
    $tests['btree freeblock pointermap vacuum current source next150 invariant ' . $index] = static function (TestRunner $t) use ($fixture150, $index): void {
        [, $plan] = $fixture150($index % 2 === 0 ? 8 : 2, $index % 3 !== 0, $index % 4 !== 0, $index % 5 === 0 ? "\x0a" : "\x0d");
        $database = $plan->basePlan->nextDatabase;
        $leafHeader = SQLiteBTreePageHeader::parsePage($database->page(3), 512);

        $t->same('ok', $plan->rows[0]['freeblock_status']);
        $t->same('ok', $leafHeader->freeblockIntegrityReport($database->page(3))['status']);
        $t->same([105], $plan->truncatedPointerMapPages());
        $t->same($database->pageCount(), $plan->toArray()['final_database_page_count']);
        $t->same($plan->basePlan->updatedPageNumbers(), $plan->toArray()['updated_page_numbers']);
        $t->same('truncated-pointer-map-page', $plan->rows[2]['kind']);
        $t->same('pointer-map-page', $plan->rows[2]['truncated_pointer_map_type']);
        $t->same(['root-page', 'free-page', null, 'free-page'], array_column($plan->rows, 'source_pointer_map_type'));
    };
}

return $tests;
