<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowAutoVacuumPointerMapPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowVacuumTruncatePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage68 = static function (int $pageSize, int $pageCount): string {
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

$putPointerMapEntry68 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$fixture68 = static function (int $maxTruncatedPages = 8, string $payload = '') use ($makeFirstPage68, $putPointerMapEntry68): array {
    $pageSize = 512;
    $pageCount = 212;
    $releasedPages = [209, 210, 211, 212];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage68($pageSize, $pageCount);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry68($pages, $pageNumber, $type, $parent, $pageSize);
    }

    foreach ($releasedPages as $index => $pageNumber) {
        $parent = $index === 0 ? 42 : $releasedPages[$index - 1];
        $putPointerMapEntry68(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );

        $next = $index < count($releasedPages) - 1 ? $releasedPages[$index + 1] : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    $vacuum = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [[
            'source' => 'wp_options-autoload-tail-overflow-before-next-insert',
            'obsolete_overflow_page_numbers' => $releasedPages,
            'rowids' => [441],
        ]],
        $maxTruncatedPages,
        true,
    );

    $payload = $payload === '' ? str_repeat('n', 1100) : $payload;
    $append = SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain(
        $vacuum->materializedDatabase(),
        42,
        $payload,
        true,
    );

    return [$vacuum, $append, $append->database];
};

$tests = [];

$cases = [
    'vacuum final page count stops before new pointer-map page' => static fn (array $fx): mixed => $fx[0]->finalDatabasePageCount(),
    'vacuum truncated old pointer-map boundary' => static fn (array $fx): mixed => $fx[0]->truncatedPageNumbers(),
    'vacuum removed old tail freelist pages' => static fn (array $fx): mixed => $fx[0]->nextFreelistPageNumbers(),
    'vacuum summary omits all obsolete overflow pages' => static fn (array $fx): mixed => $fx[0]->materializedApplySummary()['omitted_truncated_page_numbers'],
    'append allocation skips pointer-map page 208' => static fn (array $fx): mixed => $fx[1]->allocationPlan->allocatedPageNumbers,
    'append allocation records appended overflow pages' => static fn (array $fx): mixed => $fx[1]->allocationPlan->appendedPageNumbers,
    'append allocation final database count includes pointer-map gap' => static fn (array $fx): mixed => $fx[1]->allocationPlan->databasePageCount,
    'append updated pointer-map page is recreated' => static fn (array $fx): mixed => $fx[1]->updatedPointerMapPageNumbers(),
    'append updated pages include pointer-map then overflow pages' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers(),
    'append first chain link starts after pointer-map page' => static fn (array $fx): mixed => $fx[1]->chainLinks[0]['current_page'],
    'append first chain link points to second overflow page' => static fn (array $fx): mixed => $fx[1]->chainLinks[0]['next_page'],
    'append middle chain link points to third overflow page' => static fn (array $fx): mixed => $fx[1]->chainLinks[1]['next_page'],
    'append terminal chain link is marked terminal' => static fn (array $fx): mixed => $fx[1]->chainLinks[2]['terminal'],
    'append pointer-map entries are reported for each overflow page' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapEntries, 'page_number'),
    'append pointer-map entries all live on recreated map page' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[1]->pointerMapEntries, 'pointer_map_page'))),
    'append first overflow type is first-overflow-page' => static fn (array $fx): mixed => $fx[1]->pointerMapEntries[0]['type_name'],
    'append second overflow type is overflow-page' => static fn (array $fx): mixed => $fx[1]->pointerMapEntries[1]['type_name'],
    'append third overflow type is overflow-page' => static fn (array $fx): mixed => $fx[1]->pointerMapEntries[2]['type_name'],
    'append first overflow parent is owning btree page' => static fn (array $fx): mixed => $fx[1]->pointerMapEntries[0]['parent_page_number'],
    'append second overflow parent is first overflow page' => static fn (array $fx): mixed => $fx[1]->pointerMapEntries[1]['parent_page_number'],
    'append third overflow parent is second overflow page' => static fn (array $fx): mixed => $fx[1]->pointerMapEntries[2]['parent_page_number'],
    'post database page count includes appended chain' => static fn (array $fx): mixed => $fx[2]->pageCount(),
    'post database sees page 208 as pointer-map page' => static fn (array $fx): mixed => $fx[2]->isPointerMapPage(208),
    'post database page 209 pointer-map type' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(209)->typeName(),
    'post database page 210 pointer-map parent' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(210)->parentPageNumber,
    'post database page 211 pointer-map parent' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(211)->parentPageNumber,
    'post database first overflow next pointer' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->page(209), 0, 4))[1],
    'post database terminal overflow next pointer' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->page(211), 0, 4))[1],
    'toArray allocated pages match append plan' => static fn (array $fx): mixed => $fx[1]->toArray()['allocated_overflow_pages'],
    'toArray pointer-map pages match append plan' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'toArray freelist count remains empty after append' => static fn (array $fx): mixed => $fx[1]->toArray()['freelist_page_count'],
];

$expected = [
    'vacuum final page count stops before new pointer-map page' => 207,
    'vacuum truncated old pointer-map boundary' => [212, 211, 210, 209, 208],
    'vacuum removed old tail freelist pages' => [],
    'vacuum summary omits all obsolete overflow pages' => [212, 211, 210, 209, 208],
    'append allocation skips pointer-map page 208' => [209, 210, 211],
    'append allocation records appended overflow pages' => [209, 210, 211],
    'append allocation final database count includes pointer-map gap' => 211,
    'append updated pointer-map page is recreated' => [208],
    'append updated pages include pointer-map then overflow pages' => [1, 208, 209, 210, 211],
    'append first chain link starts after pointer-map page' => 209,
    'append first chain link points to second overflow page' => 210,
    'append middle chain link points to third overflow page' => 211,
    'append terminal chain link is marked terminal' => true,
    'append pointer-map entries are reported for each overflow page' => [209, 210, 211],
    'append pointer-map entries all live on recreated map page' => [208],
    'append first overflow type is first-overflow-page' => 'first-overflow-page',
    'append second overflow type is overflow-page' => 'overflow-page',
    'append third overflow type is overflow-page' => 'overflow-page',
    'append first overflow parent is owning btree page' => 42,
    'append second overflow parent is first overflow page' => 209,
    'append third overflow parent is second overflow page' => 210,
    'post database page count includes appended chain' => 211,
    'post database sees page 208 as pointer-map page' => true,
    'post database page 209 pointer-map type' => 'first-overflow-page',
    'post database page 210 pointer-map parent' => 209,
    'post database page 211 pointer-map parent' => 210,
    'post database first overflow next pointer' => 210,
    'post database terminal overflow next pointer' => 0,
    'toArray allocated pages match append plan' => [209, 210, 211],
    'toArray pointer-map pages match append plan' => [208],
    'toArray freelist count remains empty after append' => 0,
];

foreach ($cases as $name => $callback) {
    $tests['btree pointermap vacuum append current next68 ' . $name] = static function (TestRunner $t) use ($fixture68, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture68()));
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree pointermap vacuum append current next68 invariant ' . $index] = static function (TestRunner $t) use ($fixture68, $index): void {
        [, $append, $database] = $fixture68(8, str_repeat(chr(96 + ($index % 26)), 1020 + $index));

        $t->same(207, $append->allocationPlan->allocationSteps()[0]['allocated_page'] - 2);
        $t->same([208], $append->updatedPointerMapPageNumbers());
        $t->same(true, $database->isPointerMapPage(208));
        $t->same($append->allocationPlan->allocatedPageNumbers, array_column($append->chainLinks, 'current_page'));
        $t->same($append->allocationPlan->allocatedPageNumbers, array_column($append->pointerMapEntries, 'page_number'));
        $t->same('first-overflow-page', $database->pointerMapEntryForPage(209)->typeName());
        $t->same(42, $database->pointerMapEntryForPage(209)->parentPageNumber);
        $t->same(0, $append->allocationPlan->freelistPageCount);
    };
}

$tests['btree pointermap vacuum append current next68 keeps old no-append guard'] = static function (TestRunner $t) use ($fixture68): void {
    [$vacuum] = $fixture68();

    $t->throws(InvalidArgumentException::class, static function () use ($vacuum): void {
        SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain(
            $vacuum->materializedDatabase(),
            42,
            str_repeat('x', 1100),
            false,
        );
    });
};

return $tests;
