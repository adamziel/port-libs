<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage121 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry121 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$makeOverflowPage121 = static fn (?int $nextPage, string $payload): string => substr_replace(
    str_pad(pack('N', $nextPage ?? 0) . $payload, 512, "\0"),
    pack('N', $nextPage ?? 0),
    0,
    4,
);

$databaseFixture121 = static function () use ($makeFirstPage121, $putPointerMapEntry121, $makeOverflowPage121): SQLiteDatabase {
    $pages = array_fill(1, 8, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage121(8, 8, 1);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[4] = SQLiteTableLeafPage::assemble([]);
    $pages[5] = SQLiteTableLeafPage::assemble([]);
    $pages[6] = $makeOverflowPage121(7, str_repeat('A', 508));
    $pages[7] = $makeOverflowPage121(null, str_repeat('B', 192));
    $pages[8] = SQLiteFreelistTrunkPage::assemble(null, [], 512);

    $putPointerMapEntry121($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry121($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $putPointerMapEntry121($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $putPointerMapEntry121($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry121($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry121($pages, 8, SQLitePointerMapEntry::FREE_PAGE, 0);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$allocatedImages121 = static fn (): array => [
    6 => SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(12102, SQLiteRecord::encode([null, '_transient_reused_overflow_page_next121', 'reused table leaf', 'yes'])),
    ]),
    7 => SQLiteIndexLeafPage::assemble([
        SQLiteRecord::encode(['_transient_reused_overflow_page_next121', 12102]),
    ]),
];

$planFixture121 = static function (?int $parentPage = 3, bool $secureDelete = false) use ($databaseFixture121, $allocatedImages121): SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan {
    return SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowChains(
        $databaseFixture121(),
        [[
            'source' => 'wp-option-delete-overflow-chain',
            'first_page' => 6,
            'overflow_payload_bytes' => 700,
            'rowids' => [12101],
        ]],
        2,
        $parentPage,
        $allocatedImages121(),
        $secureDelete,
    );
};

$throwsMessage121 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows121 = static fn (SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan $plan): array => $plan->reuseRows;

$cases121 = [
    'action label' => static fn (): mixed => $planFixture121()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $planFixture121()->releasedOverflowPages(),
    'reused page numbers' => static fn (): mixed => $planFixture121()->reusedPageNumbers(),
    'release source count' => static fn (): mixed => $planFixture121()->releasePlan->sources[0]['count'],
    'release free plan pages' => static fn (): mixed => $planFixture121()->releasePlan->freePlan->freedPageNumbers,
    'release leaf pages' => static fn (): mixed => $planFixture121()->releasePlan->freePlan->leafPageNumbers,
    'release new trunk pages empty' => static fn (): mixed => $planFixture121()->releasePlan->freePlan->newTrunkPageNumbers,
    'release updated freelist pages' => static fn (): mixed => array_keys($planFixture121()->releasePlan->freePlan->updatedFreelistPages),
    'release updated pointer-map pages' => static fn (): mixed => array_keys($planFixture121()->releasePlan->freePlan->updatedPointerMapPages),
    'released pointer-map types are free' => static fn (): mixed => array_column($planFixture121()->releasePlan->freePlan->freedPointerMapEntries, 'type_name'),
    'released pointer-map parents are zero' => static fn (): mixed => array_column($planFixture121()->releasePlan->freePlan->freedPointerMapEntries, 'parent_page_number'),
    'database after release first trunk' => static fn (): mixed => $planFixture121()->databaseAfterRelease->header->firstFreelistTrunkPage,
    'database after release freelist count' => static fn (): mixed => $planFixture121()->databaseAfterRelease->header->freelistPageCount,
    'database after release freelist pages' => static fn (): mixed => $planFixture121()->databaseAfterRelease->freelistPageNumbers(),
    'database after release page six pointer is free' => static fn (): mixed => $planFixture121()->databaseAfterRelease->pointerMapEntryForPage(6)->typeName(),
    'database after release page seven pointer is free' => static fn (): mixed => $planFixture121()->databaseAfterRelease->pointerMapEntryForPage(7)->typeName(),
    'allocation pages consume just released overflow pages' => static fn (): mixed => $planFixture121()->allocationPlan->allocatedPageNumbers,
    'allocation has no appended pages' => static fn (): mixed => $planFixture121()->allocationPlan->appendedPageNumbers,
    'allocation sources are freelist leaves' => static fn (): mixed => array_column($planFixture121()->allocationPlan->allocationSteps(), 'source'),
    'allocation trunk pages' => static fn (): mixed => array_column($planFixture121()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation leaf count before' => static fn (): mixed => array_column($planFixture121()->allocationPlan->allocationSteps(), 'leaf_count_before'),
    'allocation leaf count after' => static fn (): mixed => array_column($planFixture121()->allocationPlan->allocationSteps(), 'leaf_count_after'),
    'allocation freelist count after' => static fn (): mixed => array_column($planFixture121()->allocationPlan->allocationSteps(), 'freelist_page_count_after'),
    'allocation pointer-map pages' => static fn (): mixed => array_keys($planFixture121()->allocationPlan->updatedPointerMapPages),
    'allocation pointer-map types' => static fn (): mixed => array_column($planFixture121()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer-map parents' => static fn (): mixed => array_column($planFixture121()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'final first trunk remains original trunk' => static fn (): mixed => $planFixture121()->databaseAfterReuse->header->firstFreelistTrunkPage,
    'final freelist count returns to original trunk only' => static fn (): mixed => $planFixture121()->databaseAfterReuse->header->freelistPageCount,
    'final freelist pages contain only trunk' => static fn (): mixed => $planFixture121()->databaseAfterReuse->freelistPageNumbers(),
    'final page six pointer-map type is btree' => static fn (): mixed => $planFixture121()->databaseAfterReuse->pointerMapEntryForPage(6)->typeName(),
    'final page seven pointer-map type is btree' => static fn (): mixed => $planFixture121()->databaseAfterReuse->pointerMapEntryForPage(7)->typeName(),
    'final page six pointer-map parent' => static fn (): mixed => $planFixture121()->databaseAfterReuse->pointerMapEntryForPage(6)->parentPageNumber,
    'final page seven pointer-map parent' => static fn (): mixed => $planFixture121()->databaseAfterReuse->pointerMapEntryForPage(7)->parentPageNumber,
    'final page six type byte' => static fn (): mixed => ord($planFixture121()->databaseAfterReuse->page(6)[0]),
    'final page seven type byte' => static fn (): mixed => ord($planFixture121()->databaseAfterReuse->page(7)[0]),
    'final page six cell count' => static fn (): mixed => $planFixture121()->databaseAfterReuse->pageHeader(6)->cellCount,
    'final page seven cell count' => static fn (): mixed => $planFixture121()->databaseAfterReuse->pageHeader(7)->cellCount,
    'reuse row pages' => static fn (): mixed => array_column($rows121($planFixture121()), 'page_number'),
    'reuse row release source' => static fn (): mixed => array_column($rows121($planFixture121()), 'release_source'),
    'reuse row allocation source' => static fn (): mixed => array_column($rows121($planFixture121()), 'allocation_source'),
    'reuse row before pointer-map type' => static fn (): mixed => array_column($rows121($planFixture121()), 'before_pointer_map_type'),
    'reuse row before pointer-map parent' => static fn (): mixed => array_column($rows121($planFixture121()), 'before_pointer_map_parent'),
    'reuse row free pointer-map type' => static fn (): mixed => array_column($rows121($planFixture121()), 'free_pointer_map_type'),
    'reuse row reuse pointer-map type' => static fn (): mixed => array_column($rows121($planFixture121()), 'reuse_pointer_map_type'),
    'reuse row reuse pointer-map parent' => static fn (): mixed => array_column($rows121($planFixture121()), 'reuse_pointer_map_parent'),
    'reuse row supplied image flag' => static fn (): mixed => array_column($rows121($planFixture121()), 'materialized_with_supplied_image'),
    'reuse row page type bytes' => static fn (): mixed => array_column($rows121($planFixture121()), 'next_page_type_byte'),
    'page images include first pointer freelist and reused pages' => static fn (): mixed => array_keys($planFixture121()->pageImages()),
    'summary updated page numbers' => static fn (): mixed => $planFixture121()->toArray()['updated_page_numbers'],
    'summary final freelist pages' => static fn (): mixed => $planFixture121()->toArray()['final_freelist_page_numbers'],
    'summary embeds reuse row pages' => static fn (): mixed => array_column($planFixture121()->toArray()['btree_overflow_freelist_vacuum_reuse_current_source_next121'], 'page_number'),
    'root reuse pointer-map type' => static fn (): mixed => array_column($rows121($planFixture121(null)), 'reuse_pointer_map_type'),
    'root reuse pointer-map parent' => static fn (): mixed => array_column($rows121($planFixture121(null)), 'reuse_pointer_map_parent'),
    'secure delete records cleared pages before reuse' => static fn (): mixed => $planFixture121(3, true)->releasePlan->freePlan->clearedPageNumbers,
    'secure delete cleared page image length' => static fn (): mixed => strlen($planFixture121(3, true)->releasePlan->freePlan->clearedPageImages[6]),
    'final integrity check reports ok' => static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $planFixture121()->databaseAfterReuse)['rows'],
    'zero allocation rejected' => static function () use ($databaseFixture121, $throwsMessage121): mixed {
        return $throwsMessage121(static fn () => SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowChains($databaseFixture121(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 0, 3));
    },
    'duplicate chain pages rejected' => static function () use ($databaseFixture121, $throwsMessage121): mixed {
        return $throwsMessage121(static fn () => SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowChains($databaseFixture121(), [['first_page' => 6, 'overflow_payload_bytes' => 700], ['first_page' => 6, 'overflow_payload_bytes' => 700]], 2, 3));
    },
    'non allocated supplied image rejected' => static function () use ($databaseFixture121, $allocatedImages121, $throwsMessage121): mixed {
        return $throwsMessage121(static fn () => SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowChains($databaseFixture121(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 2, 3, $allocatedImages121() + [9 => str_repeat("\0", 512)]));
    },
];

$expected121 = [
    'action label' => 'btree-overflow-freelist-vacuum-reuse-current-source-next121',
    'released overflow pages' => [6, 7],
    'reused page numbers' => [6, 7],
    'release source count' => 2,
    'release free plan pages' => [6, 7],
    'release leaf pages' => [6, 7],
    'release new trunk pages empty' => [],
    'release updated freelist pages' => [8],
    'release updated pointer-map pages' => [2],
    'released pointer-map types are free' => ['free-page', 'free-page'],
    'released pointer-map parents are zero' => [0, 0],
    'database after release first trunk' => 8,
    'database after release freelist count' => 3,
    'database after release freelist pages' => [8, 6, 7],
    'database after release page six pointer is free' => 'free-page',
    'database after release page seven pointer is free' => 'free-page',
    'allocation pages consume just released overflow pages' => [6, 7],
    'allocation has no appended pages' => [],
    'allocation sources are freelist leaves' => ['freelist-leaf', 'freelist-leaf'],
    'allocation trunk pages' => [8, 8],
    'allocation leaf count before' => [2, 1],
    'allocation leaf count after' => [1, 0],
    'allocation freelist count after' => [2, 1],
    'allocation pointer-map pages' => [2],
    'allocation pointer-map types' => ['btree-page', 'btree-page'],
    'allocation pointer-map parents' => [3, 3],
    'final first trunk remains original trunk' => 8,
    'final freelist count returns to original trunk only' => 1,
    'final freelist pages contain only trunk' => [8],
    'final page six pointer-map type is btree' => 'btree-page',
    'final page seven pointer-map type is btree' => 'btree-page',
    'final page six pointer-map parent' => 3,
    'final page seven pointer-map parent' => 3,
    'final page six type byte' => 13,
    'final page seven type byte' => 10,
    'final page six cell count' => 1,
    'final page seven cell count' => 1,
    'reuse row pages' => [6, 7],
    'reuse row release source' => ['wp-option-delete-overflow-chain', 'wp-option-delete-overflow-chain'],
    'reuse row allocation source' => ['freelist-leaf', 'freelist-leaf'],
    'reuse row before pointer-map type' => ['first-overflow-page', 'overflow-page'],
    'reuse row before pointer-map parent' => [3, 6],
    'reuse row free pointer-map type' => ['free-page', 'free-page'],
    'reuse row reuse pointer-map type' => ['btree-page', 'btree-page'],
    'reuse row reuse pointer-map parent' => [3, 3],
    'reuse row supplied image flag' => [true, true],
    'reuse row page type bytes' => [13, 10],
    'page images include first pointer freelist and reused pages' => [1, 2, 6, 7, 8],
    'summary updated page numbers' => [1, 2, 6, 7, 8],
    'summary final freelist pages' => [8],
    'summary embeds reuse row pages' => [6, 7],
    'root reuse pointer-map type' => ['root-page', 'root-page'],
    'root reuse pointer-map parent' => [0, 0],
    'secure delete records cleared pages before reuse' => [6, 7],
    'secure delete cleared page image length' => 512,
    'final integrity check reports ok' => [['integrity_check' => 'ok']],
    'zero allocation rejected' => 'SQLite overflow freelist vacuum reuse allocation count must be positive',
    'duplicate chain pages rejected' => 'SQLite overflow freelist release page 6 appears more than once',
    'non allocated supplied image rejected' => 'SQLite allocated page image was not part of the allocation plan',
];

$tests = [];

foreach ($cases121 as $name => $callback) {
    $tests['btree pointermap freelist vacuum current source next121 ' . $name] = static function (TestRunner $t) use ($callback, $expected121, $name): void {
        $t->same($expected121[$name], $callback());
    };
}

foreach (range(1, 20) as $index) {
    $tests['btree pointermap freelist vacuum current source next121 repeated reuse invariant ' . $index] = static function (TestRunner $t) use ($planFixture121, $rows121, $index): void {
        $parentPage = $index % 4 === 0 ? null : 3;
        $plan = $planFixture121($parentPage);
        $rows = $rows121($plan);

        $t->same([6, 7], $plan->releasedOverflowPages());
        $t->same([6, 7], $plan->reusedPageNumbers());
        $t->same([8], $plan->databaseAfterReuse->freelistPageNumbers());
        $t->same(['free-page', 'free-page'], array_column($rows, 'free_pointer_map_type'));
        $t->same([$parentPage === null ? 'root-page' : 'btree-page', $parentPage === null ? 'root-page' : 'btree-page'], array_column($rows, 'reuse_pointer_map_type'));
        $t->same([$parentPage ?? 0, $parentPage ?? 0], array_column($rows, 'reuse_pointer_map_parent'));
        $t->same([13, 10], array_column($rows, 'next_page_type_byte'));
    };
}

return $tests;
