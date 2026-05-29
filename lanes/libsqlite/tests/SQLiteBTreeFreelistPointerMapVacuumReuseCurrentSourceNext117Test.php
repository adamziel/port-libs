<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage117 = static function (int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
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

$putPointerMapEntry117 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 1) {
        return;
    }

    $stride = intdiv(512, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$databaseFixture117 = static function () use ($makeFirstPage117, $putPointerMapEntry117): SQLiteDatabase {
    $pages = array_fill(1, 310, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage117(310, 0, 0);

    $overflowChains = [
        203 => [204, 'a'],
        204 => [0, 'b'],
        306 => [307, 'c'],
        307 => [308, 'd'],
        308 => [0, 'e'],
        309 => [310, 'f'],
        310 => [0, 'g'],
    ];
    foreach ($overflowChains as $pageNumber => [$nextPage, $fill]) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat($fill, 508);
    }

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4], 88 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry117($pages, $pageNumber, $type, $parent);
    }
    foreach ([203, 204] as $index => $pageNumber) {
        $putPointerMapEntry117(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 42 : 203,
        );
    }
    foreach ([306, 307, 308] as $index => $pageNumber) {
        $putPointerMapEntry117(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 88 : $pageNumber - 1,
        );
    }
    foreach ([309, 310] as $index => $pageNumber) {
        $putPointerMapEntry117(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 4 : 309,
        );
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$deleteResults117 = static fn (): array => [
    [
        'source' => 'wp_options-autoload-middle-overflow-next117',
        'obsolete_overflow_page_numbers' => [203, 204],
        'rowids' => [11701],
    ],
    [
        'source' => 'wp_options-option-value-tail-overflow-next117',
        'obsolete_overflow_page_numbers' => [306, 307, 308],
        'rowids' => [11702],
    ],
    [
        'source' => 'wp_options-index-tail-overflow-next117',
        'obsolete_overflow_page_numbers' => [309, 310],
        'record_values' => [['_transient_next117', 11702]],
    ],
];

$allocatedImages117 = static fn (): array => [
    204 => SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(11711, SQLiteRecord::encode([null, '_site_transient_pm_reused_next117', 'payload', 'no'])),
    ]),
    307 => SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(11710, SQLiteRecord::encode([null, '_transient_pm_reused_next117', 'payload', 'no'])),
    ]),
    306 => SQLiteIndexLeafPage::assemble([
        SQLiteRecord::encode(['_transient_pm_reused_next117', 11710]),
    ]),
    203 => SQLiteIndexLeafPage::assemble([
        SQLiteRecord::encode(['_site_transient_pm_reused_next117', 11711]),
    ]),
];

$fixture117 = static function (int $allocationCount = 4, ?int $parentPage = 42) use ($databaseFixture117, $deleteResults117, $allocatedImages117): SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNextPlan {
    return SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNextPlan::fromOverflowDeleteResults(
        $databaseFixture117(),
        $deleteResults117(),
        3,
        $allocationCount,
        $parentPage,
        array_slice($allocatedImages117(), 0, $allocationCount, true),
        true,
    );
};

$throwsMessage117 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows117 = static fn (SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNextPlan $plan): array => $plan->pointerMapVacuumReuseRows();
$mapRows117 = static fn (SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNextPlan $plan): array => $plan->touchedPointerMapPageRows();

$cases117 = [
    'action label' => static fn (): mixed => $fixture117()->toArray()['action'],
    'vacuum final page count' => static fn (): mixed => $fixture117()->reusePlan->vacuumPlan->finalDatabasePageCount(),
    'vacuum survivors span pointer map pages' => static fn (): mixed => $fixture117()->reusePlan->vacuumPlan->survivingFreedPointerMapPages(),
    'vacuum truncated tail pages' => static fn (): mixed => $fixture117()->reusePlan->vacuumPlan->truncatedFreedPointerMapPages(),
    'allocated pages prefer newest trunk leaf order' => static fn (): mixed => $fixture117()->allocatedPageNumbers(),
    'allocation step sources' => static fn (): mixed => array_column($fixture117()->reusePlan->allocationPlan->allocationSteps(), 'source'),
    'allocation step trunks' => static fn (): mixed => array_column($fixture117()->reusePlan->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation step counts' => static fn (): mixed => array_column($fixture117()->reusePlan->allocationPlan->allocationSteps(), 'freelist_page_count_after'),
    'final freelist empty' => static fn (): mixed => $fixture117()->reusePlan->databaseAfterReuse->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $fixture117()->reusePlan->databaseAfterReuse->header->freelistPageCount,
    'updated pointer map pages' => static fn (): mixed => array_keys($fixture117()->reusePlan->allocationPlan->updatedPointerMapPages),
    'row page numbers' => static fn (): mixed => array_column($rows117($fixture117()), 'page_number'),
    'row allocation positions' => static fn (): mixed => array_column($rows117($fixture117()), 'allocation_position'),
    'row sources' => static fn (): mixed => array_column($rows117($fixture117()), 'allocation_source'),
    'row reused survivors' => static fn (): mixed => array_column($rows117($fixture117()), 'reused_vacuum_survivor'),
    'row before pointer map pages' => static fn (): mixed => array_column($rows117($fixture117()), 'before_pointer_map_page'),
    'row after pointer map pages' => static fn (): mixed => array_column($rows117($fixture117()), 'after_pointer_map_page'),
    'row before offsets' => static fn (): mixed => array_column($rows117($fixture117()), 'before_pointer_map_offset'),
    'row after offsets' => static fn (): mixed => array_column($rows117($fixture117()), 'after_pointer_map_offset'),
    'row before types' => static fn (): mixed => array_column($rows117($fixture117()), 'before_pointer_map_type'),
    'row before parents' => static fn (): mixed => array_column($rows117($fixture117()), 'before_pointer_map_parent'),
    'row after types' => static fn (): mixed => array_column($rows117($fixture117()), 'after_pointer_map_type'),
    'row after parents' => static fn (): mixed => array_column($rows117($fixture117()), 'after_pointer_map_parent'),
    'row map rewrite flags' => static fn (): mixed => array_column($rows117($fixture117()), 'pointer_map_page_rewritten'),
    'row page type bytes' => static fn (): mixed => array_column($rows117($fixture117()), 'materialized_page_type_byte'),
    'pointer map page rows pages' => static fn (): mixed => array_column($mapRows117($fixture117()), 'pointer_map_page'),
    'pointer map page rows allocated pages' => static fn (): mixed => array_column($mapRows117($fixture117()), 'allocated_pages'),
    'pointer map page rows slot offsets' => static fn (): mixed => array_column($mapRows117($fixture117()), 'slot_offsets'),
    'pointer map page rows rewritten' => static fn (): mixed => array_column($mapRows117($fixture117()), 'rewritten'),
    'pointer map page rows before types' => static fn (): mixed => array_column($mapRows117($fixture117()), 'before_types'),
    'pointer map page rows after types' => static fn (): mixed => array_column($mapRows117($fixture117()), 'after_types'),
    'root allocation after types' => static fn (): mixed => array_column($rows117($fixture117(4, null)), 'after_pointer_map_type'),
    'root allocation after parents' => static fn (): mixed => array_column($rows117($fixture117(4, null)), 'after_pointer_map_parent'),
    'one allocation leaves survivors not reused' => static fn (): mixed => $fixture117(1)->reusePlan->survivorPagesNotReused(),
    'two allocations leave older pointer map survivor group' => static fn (): mixed => $fixture117(2)->reusePlan->survivorPagesNotReused(),
    'summary updated pointer map pages' => static fn (): mixed => $fixture117()->toArray()['updated_pointer_map_page_numbers'],
    'summary allocated page numbers' => static fn (): mixed => $fixture117()->toArray()['allocated_page_numbers'],
    'summary row page numbers' => static fn (): mixed => array_column($fixture117()->toArray()['btree_freelist_pointermap_vacuum_reuse_current_source_next117'], 'page_number'),
    'summary map page rows' => static fn (): mixed => array_column($fixture117()->toArray()['pointer_map_page_rewrites_current_source_next117'], 'pointer_map_page'),
    'page 308 is truncated' => static fn (): mixed => $throwsMessage117(static fn () => $fixture117()->reusePlan->databaseAfterReuse->page(308)),
    'allocation beyond survivors rejected' => static fn (): mixed => $throwsMessage117(static fn () => $fixture117(5)),
    'invalid parent rejected' => static fn (): mixed => $throwsMessage117(static fn () => $fixture117(1, 1)),
    'non allocated image rejected' => static fn (): mixed => $throwsMessage117(static function () use ($databaseFixture117, $deleteResults117, $allocatedImages117): void {
        $images = $allocatedImages117();
        $images[205] = str_repeat("\0", 512);
        SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNextPlan::fromOverflowDeleteResults(
            $databaseFixture117(),
            $deleteResults117(),
            3,
            4,
            42,
            $images,
            true,
        );
    }),
];

$expected117 = [
    'action label' => 'btree-freelist-pointermap-vacuum-reuse-current-source-next117',
    'vacuum final page count' => 307,
    'vacuum survivors span pointer map pages' => [203, 204, 306, 307],
    'vacuum truncated tail pages' => [308, 309, 310],
    'allocated pages prefer newest trunk leaf order' => [204, 307, 306, 203],
    'allocation step sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-trunk'],
    'allocation step trunks' => [203, 203, 203, 203],
    'allocation step counts' => [3, 2, 1, 0],
    'final freelist empty' => [],
    'final freelist count' => 0,
    'updated pointer map pages' => [105, 208],
    'row page numbers' => [204, 307, 306, 203],
    'row allocation positions' => [0, 1, 2, 3],
    'row sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-trunk'],
    'row reused survivors' => [true, true, true, true],
    'row before pointer map pages' => [105, 208, 208, 105],
    'row after pointer map pages' => [105, 208, 208, 105],
    'row before offsets' => [490, 490, 485, 485],
    'row after offsets' => [490, 490, 485, 485],
    'row before types' => ['free-page', 'free-page', 'free-page', 'free-page'],
    'row before parents' => [0, 0, 0, 0],
    'row after types' => ['btree-page', 'btree-page', 'btree-page', 'btree-page'],
    'row after parents' => [42, 42, 42, 42],
    'row map rewrite flags' => [true, true, true, true],
    'row page type bytes' => [13, 13, 10, 10],
    'pointer map page rows pages' => [105, 208],
    'pointer map page rows allocated pages' => [[204, 203], [307, 306]],
    'pointer map page rows slot offsets' => [[490, 485], [490, 485]],
    'pointer map page rows rewritten' => [true, true],
    'pointer map page rows before types' => [['free-page', 'free-page'], ['free-page', 'free-page']],
    'pointer map page rows after types' => [['btree-page', 'btree-page'], ['btree-page', 'btree-page']],
    'root allocation after types' => ['root-page', 'root-page', 'root-page', 'root-page'],
    'root allocation after parents' => [0, 0, 0, 0],
    'one allocation leaves survivors not reused' => [203, 306, 307],
    'two allocations leave older pointer map survivor group' => [203, 306],
    'summary updated pointer map pages' => [105, 208],
    'summary allocated page numbers' => [204, 307, 306, 203],
    'summary row page numbers' => [204, 307, 306, 203],
    'summary map page rows' => [105, 208],
    'page 308 is truncated' => 'SQLite page 308 is not present in the database image',
    'allocation beyond survivors rejected' => 'SQLite freelist does not contain enough pages for this allocation',
    'invalid parent rejected' => 'SQLite b-tree allocation parent page must be null or at page 2 or later',
    'non allocated image rejected' => 'SQLite allocated page image was not part of the allocation plan',
];

$tests = [];

foreach ($cases117 as $name => $callback) {
    $tests['btree freelist pointermap vacuum reuse current source next117 ' . $name] = static function (TestRunner $t) use ($callback, $expected117, $name): void {
        $t->same($expected117[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree freelist pointermap vacuum reuse current source next117 invariant ' . $index] = static function (TestRunner $t) use ($fixture117, $rows117, $mapRows117, $index): void {
        $allocationCount = ($index % 4) + 1;
        $parentPage = $index % 6 === 0 ? null : 42;
        $plan = $fixture117($allocationCount, $parentPage);
        $rows = $rows117($plan);

        $t->same(array_slice([204, 307, 306, 203], 0, $allocationCount), $plan->allocatedPageNumbers());
        $t->same([], $plan->reusePlan->allocationPlan->appendedPageNumbers);
        $t->same(array_fill(0, $allocationCount, true), array_column($rows, 'reused_vacuum_survivor'));
        $t->same(array_fill(0, $allocationCount, 'free-page'), array_column($rows, 'before_pointer_map_type'));
        $t->same(array_fill(0, $allocationCount, $parentPage === null ? 'root-page' : 'btree-page'), array_column($rows, 'after_pointer_map_type'));
        $t->same(array_fill(0, $allocationCount, $parentPage ?? 0), array_column($rows, 'after_pointer_map_parent'));
        $t->same(array_fill(0, $allocationCount, true), array_column($rows, 'pointer_map_page_rewritten'));
        $t->same([308, 309, 310], $plan->reusePlan->truncatedPagesNotReused());
        $t->same($allocationCount === 1 ? [105] : [105, 208], array_column($mapRows117($plan), 'pointer_map_page'));
    };
}

return $tests;
