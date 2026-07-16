<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowVacuumTruncatePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage66 = static function (int $pageSize, int $pageCount): string {
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

$putPointerMapEntry66 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$fixture66 = static function (int $maxTruncatedPages = 3) use ($makeFirstPage66, $putPointerMapEntry66): SQLiteOverflowVacuumTruncatePlan {
    $pageSize = 512;
    $pageCount = 310;
    $releasedPages = [306, 307, 308, 309, 310];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage66($pageSize, $pageCount);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry66($pages, $pageNumber, $type, $parent, $pageSize);
    }

    foreach ($releasedPages as $index => $pageNumber) {
        $isFirst = $index === 0 || $index === 3;
        $parent = $isFirst ? ($index === 0 ? 42 : 5) : $releasedPages[$index - 1];
        $putPointerMapEntry66(
            $pages,
            $pageNumber,
            $isFirst ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );

        $next = in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    return SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-autoload-table-tail-overflow',
                'obsolete_overflow_page_numbers' => [306, 307, 308],
                'rowids' => [9001],
            ],
            [
                'source' => 'wp_options-option-name-index-tail-overflow',
                'obsolete_overflow_page_numbers' => [309, 310],
                'record_values' => [['_transient_doing_cron', 9001]],
            ],
        ],
        $maxTruncatedPages,
        true,
    );
};

$tests = [];

$cases = [
    'materialized database page count matches truncation boundary' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->pageCount(),
    'materialized byte length reflects truncated tail' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => strlen($plan->materializedBytes()),
    'materialized header database size is rewritten' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->header->databaseSizePages,
    'materialized header first freelist trunk is surviving tail page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->header->firstFreelistTrunkPage,
    'materialized header freelist count excludes truncated pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->header->freelistPageCount,
    'materialized freelist pages include only surviving freed overflow pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->freelistPageNumbers(),
    'materialized apply summary page count' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['database_page_count'],
    'materialized apply summary byte length' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['byte_length'],
    'materialized apply summary first trunk' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['first_freelist_trunk_page'],
    'materialized apply summary freelist count' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['freelist_page_count'],
    'materialized apply summary freelist pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['freelist_page_numbers'],
    'materialized apply summary omitted tail pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['omitted_truncated_page_numbers'],
    'materialized apply summary appears in toArray' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->toArray()['materialized_apply']['omitted_truncated_page_numbers'],
    'materialized page images stop at final page count' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_key_last($plan->materializedPageImages()),
    'materialized page image count matches final page count' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => count($plan->materializedPageImages()),
    'surviving trunk page is zeroed by secure delete before trunk rewrite' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => substr($plan->materializedDatabase()->page(306), 8, 16),
    'surviving leaf page is secure delete cleared' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => substr($plan->materializedDatabase()->page(307), 4, 16),
    'surviving trunk next pointer is zero' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => unpack('N', substr($plan->materializedDatabase()->page(306), 0, 4))[1],
    'surviving trunk leaf count is one' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => unpack('N', substr($plan->materializedDatabase()->page(306), 4, 4))[1],
    'surviving trunk leaf points at surviving page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => unpack('N', substr($plan->materializedDatabase()->page(306), 8, 4))[1],
    'surviving pointer map entry remains free page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->pointerMapEntryForPage(306)->typeName(),
    'surviving leaf pointer map entry remains free page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->pointerMapEntryForPage(307)->typeName(),
    'surviving pointer map parent is cleared' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->pointerMapEntryForPage(306)->parentPageNumber,
    'surviving leaf pointer map parent is cleared' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->pointerMapEntryForPage(307)->parentPageNumber,
];

$expected = [
    'materialized database page count matches truncation boundary' => 307,
    'materialized byte length reflects truncated tail' => 307 * 512,
    'materialized header database size is rewritten' => 307,
    'materialized header first freelist trunk is surviving tail page' => 306,
    'materialized header freelist count excludes truncated pages' => 2,
    'materialized freelist pages include only surviving freed overflow pages' => [306, 307],
    'materialized apply summary page count' => 307,
    'materialized apply summary byte length' => 307 * 512,
    'materialized apply summary first trunk' => 306,
    'materialized apply summary freelist count' => 2,
    'materialized apply summary freelist pages' => [306, 307],
    'materialized apply summary omitted tail pages' => [310, 309, 308],
    'materialized apply summary appears in toArray' => [310, 309, 308],
    'materialized page images stop at final page count' => 307,
    'materialized page image count matches final page count' => 307,
    'surviving trunk page is zeroed by secure delete before trunk rewrite' => pack('N', 307) . str_repeat("\0", 12),
    'surviving leaf page is secure delete cleared' => str_repeat("\0", 16),
    'surviving trunk next pointer is zero' => 0,
    'surviving trunk leaf count is one' => 1,
    'surviving trunk leaf points at surviving page' => 307,
    'surviving pointer map entry remains free page' => 'free-page',
    'surviving leaf pointer map entry remains free page' => 'free-page',
    'surviving pointer map parent is cleared' => 0,
    'surviving leaf pointer map parent is cleared' => 0,
];

foreach ($cases as $name => $callback) {
    $tests['btree pointermap vacuum apply current next66 ' . $name] = static function (TestRunner $t) use ($fixture66, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture66()));
    };
}

foreach (range(1, 31) as $index) {
    $tests['btree pointermap vacuum apply current next66 materialized invariant ' . $index] = static function (TestRunner $t) use ($fixture66, $index): void {
        $limit = $index % 2 === 0 ? 3 : 4;
        $plan = $fixture66($limit);
        $database = $plan->materializedDatabase();
        $summary = $plan->materializedApplySummary();

        $t->same($plan->finalDatabasePageCount(), $database->pageCount());
        $t->same($database->pageCount() * $database->header->pageSize, strlen($plan->materializedBytes()));
        $t->same($database->header->databaseSizePages, $summary['database_page_count']);
        $t->same($database->header->firstFreelistTrunkPage, $summary['first_freelist_trunk_page']);
        $t->same($database->header->freelistPageCount, $summary['freelist_page_count']);
        $t->same($database->freelistPageNumbers(), $summary['freelist_page_numbers']);
        $t->same($plan->truncatedPageNumbers(), $summary['omitted_truncated_page_numbers']);
        $t->same(max($plan->survivingFreedPointerMapPages()), $database->pageCount());
    };
}

$tests['btree pointermap vacuum apply current next66 full tail removal materializes shorter database'] = static function (TestRunner $t) use ($fixture66): void {
    $plan = $fixture66(8);
    $database = $plan->materializedDatabase();

    $t->same(305, $database->pageCount());
    $t->same(305 * 512, strlen($plan->materializedBytes()));
    $t->same(0, $database->header->firstFreelistTrunkPage);
    $t->same(0, $database->header->freelistPageCount);
    $t->same([], $database->freelistPageNumbers());
    $t->same([310, 309, 308, 307, 306], $plan->materializedApplySummary()['omitted_truncated_page_numbers']);
};

$tests['btree pointermap vacuum apply current next66 rejects reading truncated materialized page'] = static function (TestRunner $t) use ($fixture66): void {
    $t->throws(InvalidArgumentException::class, static function () use ($fixture66): void {
        $fixture66()->materializedDatabase()->page(308);
    });
};

return $tests;
