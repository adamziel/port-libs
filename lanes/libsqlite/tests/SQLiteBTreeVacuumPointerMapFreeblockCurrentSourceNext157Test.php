<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage157 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry157 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database157 = static function () use ($makeFirstPage157, $putPointerMapEntry157): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage157(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next157', str_repeat('cache:', 32)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', 'fresh'])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = pack('N', 107) . str_repeat('A', 508);
    $pages[107] = pack('N', 108) . str_repeat('B', 508);
    $pages[108] = pack('N', 109) . str_repeat('C', 508);
    $pages[109] = pack('N', 110) . str_repeat('D', 508);
    $pages[110] = pack('N', 0) . str_repeat('E', 508);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry157($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan157 = static function (int $maxTruncatedPages = 4) use ($database157): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    $database = $database157();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafTransitionFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        true,
    );
};

$message157 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases157 = [
    'action label' => static fn (): mixed => $plan157()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan157()->toArray()['released_overflow_pages'],
    'materialized freeblock pages' => static fn (): mixed => $plan157()->materializedFreeblockPages(),
    'surviving freelist cleared pages' => static fn (): mixed => $plan157()->survivingFreelistPagesWithClearedNext(),
    'severed source next pointers' => static fn (): mixed => $plan157()->severedCurrentSourceNextPointers(),
    'final page count' => static fn (): mixed => $plan157()->toArray()['final_database_page_count'],
    'final freelist pages' => static fn (): mixed => $plan157()->toArray()['final_freelist_page_numbers'],
    'row page numbers' => static fn (): mixed => array_column($plan157()->transitionRows(), 'page_number'),
    'row kinds' => static fn (): mixed => array_column($plan157()->transitionRows(), 'kind'),
    'current source next pages' => static fn (): mixed => array_column($plan157()->transitionRows(), 'current_source_next_page'),
    'next materialized next pages' => static fn (): mixed => array_column($plan157()->transitionRows(), 'next_materialized_next_page'),
    'transition statuses' => static fn (): mixed => array_column($plan157()->transitionRows(), 'transition_status'),
    'current pointer types' => static fn (): mixed => array_column($plan157()->transitionRows(), 'current_pointer_map_type'),
    'next pointer types' => static fn (): mixed => array_column($plan157()->transitionRows(), 'next_pointer_map_type'),
    'current pointer parents' => static fn (): mixed => array_column($plan157()->transitionRows(), 'current_pointer_map_parent'),
    'next pointer parents' => static fn (): mixed => array_column($plan157()->transitionRows(), 'next_pointer_map_parent'),
    'materialized flags' => static fn (): mixed => array_column($plan157()->transitionRows(), 'materialized'),
    'summary severed pointers' => static fn (): mixed => $plan157()->toArray()['severed_current_source_next_pointers'],
    'summary materialized freeblock' => static fn (): mixed => $plan157()->toArray()['materialized_freeblock_pages'],
    'summary surviving cleared' => static fn (): mixed => $plan157()->toArray()['surviving_freelist_pages_with_cleared_next'],
    'wide vacuum severed source next pointers' => static fn (): mixed => $plan157(6)->severedCurrentSourceNextPointers(),
    'wide vacuum materialized freeblock pages' => static fn (): mixed => $plan157(6)->materializedFreeblockPages(),
    'wide vacuum final page count' => static fn (): mixed => $plan157(6)->toArray()['final_database_page_count'],
    'zero truncation rejected' => static fn (): mixed => $message157(static fn () => $plan157(0)),
];

$expected157 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next157',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'materialized freeblock pages' => [3],
    'surviving freelist cleared pages' => [106],
    'severed source next pointers' => [107, 108, 109],
    'final page count' => 106,
    'final freelist pages' => [106],
    'row page numbers' => [3, 106, 107, 108, 109, 110],
    'row kinds' => ['deleted-leaf-freeblock', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page'],
    'current source next pages' => [null, 107, 108, 109, 110, 0],
    'next materialized next pages' => [null, 0, null, null, null, null],
    'transition statuses' => ['leaf-freeblock-preserved', 'surviving-free-page-cleared-next', 'truncated-current-next-pointer', 'truncated-current-next-pointer', 'truncated-current-next-pointer', 'truncated-terminal-overflow'],
    'current pointer types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'next pointer types' => ['root-page', 'free-page', null, null, null, null],
    'current pointer parents' => [0, 3, 106, 107, 108, 109],
    'next pointer parents' => [0, 0, null, null, null, null],
    'materialized flags' => [true, true, false, false, false, false],
    'summary severed pointers' => [107, 108, 109],
    'summary materialized freeblock' => [3],
    'summary surviving cleared' => [106],
    'wide vacuum severed source next pointers' => [106, 107, 108, 109],
    'wide vacuum materialized freeblock pages' => [3],
    'wide vacuum final page count' => 104,
    'zero truncation rejected' => 'SQLite pointer-map vacuum freeblock next127 requires a positive truncation limit',
];

$tests = [];

foreach ($cases157 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next157 ' . $name] = static function (TestRunner $t) use ($callback, $expected157, $name): void {
        $t->same($expected157[$name], $callback());
    };
}

foreach (range(1, 36) as $index) {
    $tests['btree vacuum pointermap freeblock current source next157 invariant ' . $index] = static function (TestRunner $t) use ($plan157): void {
        $plan = $plan157();
        $rows = $plan->transitionRows();

        $t->same([3], $plan->materializedFreeblockPages());
        $t->same([106], $plan->survivingFreelistPagesWithClearedNext());
        $t->same([107, 108, 109], $plan->severedCurrentSourceNextPointers());
        $t->same('leaf-freeblock-preserved', $rows[0]['transition_status']);
        $t->same('surviving-free-page-cleared-next', $rows[1]['transition_status']);
        $t->same('truncated-terminal-overflow', $rows[5]['transition_status']);
        $t->same($rows[0]['next_page_hash'], $plan->basePlan->basePlan->basePlan->deletePlan->nextLeafPageHash);
        $t->same([3, 106], array_column($plan->basePlan->materializedRows(), 'page_number'));
        $t->same([107, 108, 109, 110], array_column($plan->basePlan->truncatedRows(), 'page_number'));
    };
}

return $tests;
