<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage160 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 0), 32, 4);
    $page = substr_replace($page, pack('N', 0), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry160 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$database160 = static function () use ($makeFirstPage160, $putPointerMapEntry160): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage160(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next160', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(64 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry160($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan160 = static function (
    int $maxTruncatedPages = 2,
    ?string $payload = null,
) use ($database160): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database160();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafOverflowAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next160-multisite-transient-rewrite-', 42),
        3,
        true,
    );
};

$message160 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases160 = [
    'action label' => static fn (): mixed => $plan160()->toArray()['action'],
    'replacement pages' => static fn (): mixed => $plan160()->replacementOverflowPages(),
    'replacement next pages' => static fn (): mixed => $plan160()->replacementOverflowNextPages(),
    'replacement pointer parents' => static fn (): mixed => $plan160()->replacementPointerMapParents(),
    'reused current source pages' => static fn (): mixed => $plan160()->reusedCurrentSourceFreePages(),
    'truncated pages rejected' => static fn (): mixed => $plan160()->truncatedCurrentSourcePagesRejected(),
    'leaf freeblock pages' => static fn (): mixed => $plan160()->leafFreeblockPages(),
    'final page count' => static fn (): mixed => $plan160()->toArray()['final_database_page_count'],
    'final freelist pages' => static fn (): mixed => $plan160()->toArray()['final_freelist_page_numbers'],
    'row page numbers' => static fn (): mixed => array_column($plan160()->chainRows, 'page_number'),
    'row positions' => static fn (): mixed => array_column($plan160()->chainRows, 'chain_position'),
    'row pointer types' => static fn (): mixed => array_column($plan160()->chainRows, 'pointer_map_type'),
    'row expected parents' => static fn (): mixed => array_column($plan160()->chainRows, 'expected_pointer_map_parent'),
    'row pointer valid flags' => static fn (): mixed => array_column($plan160()->chainRows, 'pointer_map_matches_chain'),
    'row next valid flags' => static fn (): mixed => array_column($plan160()->chainRows, 'next_pointer_matches_chain'),
    'row reused flags' => static fn (): mixed => array_column($plan160()->chainRows, 'reused_current_source_free_page'),
    'row truncated reused flags' => static fn (): mixed => array_column($plan160()->chainRows, 'truncated_current_source_page_reused'),
    'row post vacuum status' => static fn (): mixed => array_column($plan160()->chainRows, 'post_vacuum_status'),
    'base surviving pages' => static fn (): mixed => $plan160()->basePlan->basePlan->basePlan->survivingReleasedOverflowPages(),
    'base truncated pages' => static fn (): mixed => $plan160()->basePlan->basePlan->basePlan->truncatedReleasedOverflowPages(),
    'base allocation sources' => static fn (): mixed => array_column($plan160()->basePlan->allocationPlan->allocationSteps(), 'source'),
    'base allocation trunks' => static fn (): mixed => array_column($plan160()->basePlan->allocationPlan->allocationSteps(), 'trunk_page'),
    'base allocated pointer types' => static fn (): mixed => array_column($plan160()->basePlan->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'base allocated pointer parents' => static fn (): mixed => array_column($plan160()->basePlan->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'base updated pages' => static fn (): mixed => array_keys($plan160()->basePlan->pageImages()),
    'leaf freeblock status' => static fn (): mixed => SQLiteBTreePageHeader::parsePage($plan160()->basePlan->basePlan->basePlan->basePlan->deletePlan->leafPageImage, 512)->freeblockIntegrityReport($plan160()->basePlan->basePlan->basePlan->basePlan->deletePlan->leafPageImage)['status'],
    'single surviving page rejected for three page payload' => static fn (): mixed => $message160(static fn () => $plan160(4)),
    'empty payload rejected' => static fn (): mixed => $message160(static fn () => $plan160(2, '')),
];

$expected160 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next160',
    'replacement pages' => [107, 108, 106],
    'replacement next pages' => [108, 106, 0],
    'replacement pointer parents' => [3, 107, 108],
    'reused current source pages' => [107, 108, 106],
    'truncated pages rejected' => [109, 110],
    'leaf freeblock pages' => [3],
    'final page count' => 108,
    'final freelist pages' => [],
    'row page numbers' => [107, 108, 106],
    'row positions' => [0, 1, 2],
    'row pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'row expected parents' => [3, 107, 108],
    'row pointer valid flags' => [true, true, true],
    'row next valid flags' => [true, true, true],
    'row reused flags' => [true, true, true],
    'row truncated reused flags' => [false, false, false],
    'row post vacuum status' => ['survives-as-free-page', 'survives-as-free-page', 'survives-as-free-page'],
    'base surviving pages' => [106, 107, 108],
    'base truncated pages' => [109, 110],
    'base allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-trunk'],
    'base allocation trunks' => [106, 106, 106],
    'base allocated pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'base allocated pointer parents' => [3, 107, 108],
    'base updated pages' => [1, 3, 105, 106, 107, 108],
    'leaf freeblock status' => 'ok',
    'single surviving page rejected for three page payload' => 'SQLite freelist does not contain enough pages for this allocation',
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases160 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next160 ' . $name] = static function (TestRunner $t) use ($callback, $expected160, $name): void {
        $t->same($expected160[$name], $callback());
    };
}

foreach (range(1, 42) as $index) {
    $tests['btree vacuum pointermap freeblock current source next160 invariant ' . $index] = static function (TestRunner $t) use ($plan160): void {
        $plan = $plan160();

        $t->same([107, 108, 106], $plan->replacementOverflowPages());
        $t->same([108, 106, 0], $plan->replacementOverflowNextPages());
        $t->same([3, 107, 108], $plan->replacementPointerMapParents());
        $t->same([107, 108, 106], $plan->reusedCurrentSourceFreePages());
        $t->same([109, 110], $plan->truncatedCurrentSourcePagesRejected());
        $t->same([false, false, false], array_column($plan->chainRows, 'truncated_current_source_page_reused'));
        $t->same([true, true, true], array_column($plan->chainRows, 'pointer_map_matches_chain'));
        $t->same([true, true, true], array_column($plan->chainRows, 'next_pointer_matches_chain'));
        $t->same('ok', SQLiteBTreePageHeader::parsePage($plan->basePlan->basePlan->basePlan->basePlan->deletePlan->leafPageImage, 512)->freeblockIntegrityReport($plan->basePlan->basePlan->basePlan->basePlan->deletePlan->leafPageImage)['status']);
    };
}

return $tests;
