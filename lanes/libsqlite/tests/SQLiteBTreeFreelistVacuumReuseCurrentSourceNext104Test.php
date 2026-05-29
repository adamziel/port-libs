<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage104 = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
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

$putPointerMapEntry104 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$databaseFixture104 = static function () use ($makeFirstPage104, $putPointerMapEntry104): SQLiteDatabase {
    $pageSize = 512;
    $pageCount = 310;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage104($pageSize, $pageCount, 0, 0);
    $pages[306] = pack('N', 307) . str_repeat('t', $pageSize - 4);
    $pages[307] = pack('N', 308) . str_repeat('u', $pageSize - 4);
    $pages[308] = pack('N', 0) . str_repeat('v', $pageSize - 4);
    $pages[309] = pack('N', 310) . str_repeat('w', $pageSize - 4);
    $pages[310] = pack('N', 0) . str_repeat('x', $pageSize - 4);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry104($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ([306, 307, 308] as $index => $pageNumber) {
        $putPointerMapEntry104(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 42 : $pageNumber - 1,
            $pageSize,
        );
    }
    foreach ([309, 310] as $index => $pageNumber) {
        $putPointerMapEntry104(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 4 : 309,
            $pageSize,
        );
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$deleteResults104 = static fn (): array => [
    [
        'source' => 'wp_options-autoload-tail-overflow',
        'obsolete_overflow_page_numbers' => [306, 307, 308],
        'rowids' => [10401],
    ],
    [
        'source' => 'wp_options-option-name-index-tail-overflow',
        'obsolete_overflow_page_numbers' => [309, 310],
        'record_values' => [['_transient_next104', 10401]],
    ],
];

$allocatedImages104 = static fn (): array => [
    307 => SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(10410, SQLiteRecord::encode([null, '_transient_reused_after_vacuum', 'fresh', 'no'])),
    ]),
    306 => SQLiteIndexLeafPage::assemble([
        SQLiteRecord::encode(['_transient_reused_after_vacuum', 10410]),
    ]),
];

$fixture104 = static function (int $allocationCount = 2) use ($databaseFixture104, $deleteResults104, $allocatedImages104): SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan {
    return SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowDeleteResults(
        $databaseFixture104(),
        $deleteResults104(),
        3,
        $allocationCount,
        42,
        array_slice($allocatedImages104(), 0, $allocationCount, true),
        true,
    );
};

$throwsMessage104 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows104 = static fn (SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan $plan): array => $plan->btreeFreelistVacuumReuseRows();

$cases104 = [
    'action label' => static fn (): mixed => $fixture104()->toArray()['action'],
    'vacuum final page count' => static fn (): mixed => $fixture104()->vacuumPlan->finalDatabasePageCount(),
    'vacuum survivors' => static fn (): mixed => $fixture104()->vacuumPlan->survivingFreedPointerMapPages(),
    'vacuum truncated pages' => static fn (): mixed => $fixture104()->vacuumPlan->truncatedFreedPointerMapPages(),
    'allocated pages reuse survivors in allocation order' => static fn (): mixed => $fixture104()->allocatedPageNumbers(),
    'no append pages' => static fn (): mixed => $fixture104()->allocationPlan->appendedPageNumbers,
    'all survivor pages reused' => static fn (): mixed => $fixture104()->survivorPagesNotReused(),
    'all truncated pages remain not reused' => static fn (): mixed => $fixture104()->truncatedPagesNotReused(),
    'final page count remains truncated boundary' => static fn (): mixed => $fixture104()->databaseAfterReuse->pageCount(),
    'final freelist count' => static fn (): mixed => $fixture104()->databaseAfterReuse->header->freelistPageCount,
    'final first trunk' => static fn (): mixed => $fixture104()->databaseAfterReuse->header->firstFreelistTrunkPage,
    'final freelist is empty' => static fn (): mixed => $fixture104()->databaseAfterReuse->freelistPageNumbers(),
    'allocation step sources' => static fn (): mixed => array_column($fixture104()->allocationPlan->allocationSteps(), 'source'),
    'allocation step pages' => static fn (): mixed => array_column($fixture104()->allocationPlan->allocationSteps(), 'allocated_page'),
    'allocation step counts' => static fn (): mixed => array_column($fixture104()->allocationPlan->allocationSteps(), 'freelist_page_count_after'),
    'allocation step trunks' => static fn (): mixed => array_column($fixture104()->allocationPlan->allocationSteps(), 'trunk_page'),
    'row page numbers' => static fn (): mixed => array_column($rows104($fixture104()), 'page_number'),
    'row allocation positions' => static fn (): mixed => array_column($rows104($fixture104()), 'allocation_position'),
    'row sources' => static fn (): mixed => array_column($rows104($fixture104()), 'source'),
    'row reused survivor flags' => static fn (): mixed => array_column($rows104($fixture104()), 'reused_vacuum_survivor'),
    'row truncated flags' => static fn (): mixed => array_column($rows104($fixture104()), 'was_truncated_tail_page'),
    'row before pointer map types' => static fn (): mixed => array_column($rows104($fixture104()), 'before_pointer_map_type'),
    'row before parents' => static fn (): mixed => array_column($rows104($fixture104()), 'before_parent_page_number'),
    'row after pointer map types' => static fn (): mixed => array_column($rows104($fixture104()), 'after_pointer_map_type'),
    'row after parents' => static fn (): mixed => array_column($rows104($fixture104()), 'after_parent_page_number'),
    'row materialized flags' => static fn (): mixed => array_column($rows104($fixture104()), 'materialized_as_btree_page'),
    'allocated pointer map pages' => static fn (): mixed => array_column($fixture104()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocated pointer map type names' => static fn (): mixed => array_column($fixture104()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocated pointer map parents' => static fn (): mixed => array_column($fixture104()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'updated pointer map page list' => static fn (): mixed => array_keys($fixture104()->allocationPlan->updatedPointerMapPages),
    'updated page images include allocated pages' => static fn (): mixed => array_keys($fixture104()->pageImages()),
    'table leaf materialized cell count' => static fn (): mixed => $fixture104()->databaseAfterReuse->pageHeader(307)->cellCount,
    'index leaf materialized cell count' => static fn (): mixed => $fixture104()->databaseAfterReuse->pageHeader(306)->cellCount,
    'table leaf page type byte' => static fn (): mixed => ord($fixture104()->databaseAfterReuse->page(307)[0]),
    'index leaf page type byte' => static fn (): mixed => ord($fixture104()->databaseAfterReuse->page(306)[0]),
    'page 308 is no longer readable' => static fn (): mixed => $throwsMessage104(static fn () => $fixture104()->databaseAfterReuse->page(308)),
    'page 309 pointer map is no longer readable' => static fn (): mixed => $throwsMessage104(static fn () => $fixture104()->databaseAfterReuse->pointerMapEntryForPage(309)),
    'summary allocated pages' => static fn (): mixed => $fixture104()->toArray()['allocated_page_numbers'],
    'summary truncated pages' => static fn (): mixed => $fixture104()->toArray()['vacuum_truncated_freed_pages'],
    'summary updated page numbers' => static fn (): mixed => $fixture104()->toArray()['updated_page_numbers'],
];

$expected104 = [
    'action label' => 'btree-freelist-pointermap-vacuum-reuse',
    'vacuum final page count' => 307,
    'vacuum survivors' => [306, 307],
    'vacuum truncated pages' => [308, 309, 310],
    'allocated pages reuse survivors in allocation order' => [307, 306],
    'no append pages' => [],
    'all survivor pages reused' => [],
    'all truncated pages remain not reused' => [308, 309, 310],
    'final page count remains truncated boundary' => 307,
    'final freelist count' => 0,
    'final first trunk' => 0,
    'final freelist is empty' => [],
    'allocation step sources' => ['freelist-leaf', 'freelist-trunk'],
    'allocation step pages' => [307, 306],
    'allocation step counts' => [1, 0],
    'allocation step trunks' => [306, 306],
    'row page numbers' => [307, 306],
    'row allocation positions' => [0, 1],
    'row sources' => ['freelist-leaf', 'freelist-trunk'],
    'row reused survivor flags' => [true, true],
    'row truncated flags' => [false, false],
    'row before pointer map types' => ['free-page', 'free-page'],
    'row before parents' => [0, 0],
    'row after pointer map types' => ['btree-page', 'btree-page'],
    'row after parents' => [42, 42],
    'row materialized flags' => [true, true],
    'allocated pointer map pages' => [307, 306],
    'allocated pointer map type names' => ['btree-page', 'btree-page'],
    'allocated pointer map parents' => [42, 42],
    'updated pointer map page list' => [208],
    'updated page images include allocated pages' => [1, 208, 306, 307],
    'table leaf materialized cell count' => 1,
    'index leaf materialized cell count' => 1,
    'table leaf page type byte' => 13,
    'index leaf page type byte' => 10,
    'page 308 is no longer readable' => 'SQLite page 308 is not present in the database image',
    'page 309 pointer map is no longer readable' => 'SQLite page 309 is not present in the database image',
    'summary allocated pages' => [307, 306],
    'summary truncated pages' => [308, 309, 310],
    'summary updated page numbers' => [1, 208, 306, 307],
];

$tests = [];

foreach ($cases104 as $name => $callback) {
    $tests['btree freelist pointermap vacuum reuse current source next104 ' . $name] = static function (TestRunner $t) use ($callback, $expected104, $name): void {
        $t->same($expected104[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree freelist pointermap vacuum reuse current source next104 invariant ' . $index] = static function (TestRunner $t) use ($fixture104, $rows104, $index): void {
        $allocationCount = $index % 2 === 0 ? 1 : 2;
        $plan = $fixture104($allocationCount);
        $rows = $rows104($plan);

        $t->same(array_slice([307, 306], 0, $allocationCount), $plan->allocatedPageNumbers());
        $t->same([], $plan->allocationPlan->appendedPageNumbers);
        $t->same(307, $plan->databaseAfterReuse->pageCount());
        $t->same(array_fill(0, $allocationCount, true), array_column($rows, 'reused_vacuum_survivor'));
        $t->same(array_fill(0, $allocationCount, false), array_column($rows, 'was_truncated_tail_page'));
        $t->same(array_fill(0, $allocationCount, 'free-page'), array_column($rows, 'before_pointer_map_type'));
        $t->same(array_fill(0, $allocationCount, 'btree-page'), array_column($rows, 'after_pointer_map_type'));
        $t->same(array_fill(0, $allocationCount, 42), array_column($rows, 'after_parent_page_number'));
        $t->same([308, 309, 310], $plan->truncatedPagesNotReused());
    };
}

$tests['btree freelist pointermap vacuum reuse current source next104 leaves one survivor when one page allocated'] = static function (TestRunner $t) use ($fixture104): void {
    $plan = $fixture104(1);

    $t->same([307], $plan->allocatedPageNumbers());
    $t->same([306], $plan->survivorPagesNotReused());
    $t->same([306], $plan->databaseAfterReuse->freelistPageNumbers());
    $t->same('free-page', $plan->databaseAfterReuse->pointerMapEntryForPage(306)->typeName());
};

$tests['btree freelist pointermap vacuum reuse current source next104 rejects allocation beyond vacuum survivors without append'] = static function (TestRunner $t) use ($databaseFixture104, $deleteResults104, $allocatedImages104): void {
    $t->throws(InvalidArgumentException::class, static function () use ($databaseFixture104, $deleteResults104, $allocatedImages104): void {
        SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowDeleteResults(
            $databaseFixture104(),
            $deleteResults104(),
            3,
            3,
            42,
            $allocatedImages104(),
            true,
        );
    });
};

$tests['btree freelist pointermap vacuum reuse current source next104 rejects non allocated image'] = static function (TestRunner $t) use ($databaseFixture104, $deleteResults104, $allocatedImages104): void {
    $images = $allocatedImages104();
    $images[305] = SQLiteTableLeafPage::assemble([]);

    $t->throws(InvalidArgumentException::class, static function () use ($databaseFixture104, $deleteResults104, $images): void {
        SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowDeleteResults(
            $databaseFixture104(),
            $deleteResults104(),
            3,
            2,
            42,
            $images,
            true,
        );
    });
};

return $tests;
