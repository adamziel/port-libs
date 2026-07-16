<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage135 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
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

$putPointerMapEntry135 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage135 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database135 = static function () use ($makeFirstPage135, $putPointerMapEntry135, $overflowPage135): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage135(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_timeout_next135', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 24)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage135(107, 'A');
    $pages[107] = $overflowPage135(108, 'B');
    $pages[108] = $overflowPage135(109, 'C');
    $pages[109] = $overflowPage135(110, 'D');
    $pages[110] = $overflowPage135(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry135($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan135 = static function (int $maxTruncatedPages = 4) use ($database135): SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan {
    $database = $database135();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan::tableLeafFromDeleteResult(
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

$message135 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases135 = [
    'action label' => static fn (): mixed => $plan135()->toArray()['action'],
    'leaf page type' => static fn (): mixed => $plan135()->toArray()['leaf_page_type'],
    'released overflow pages' => static fn (): mixed => $plan135()->toArray()['released_overflow_pages'],
    'surviving released pages' => static fn (): mixed => $plan135()->survivingReleasedOverflowPages(),
    'truncated released pages' => static fn (): mixed => $plan135()->truncatedReleasedOverflowPages(),
    'truncated pointer map pages empty for partial vacuum' => static fn (): mixed => $plan135()->truncatedPointerMapPages(),
    'final page count after partial vacuum' => static fn (): mixed => $plan135()->toArray()['final_database_page_count'],
    'final first freelist trunk page' => static fn (): mixed => $plan135()->toArray()['final_first_freelist_trunk_page'],
    'final freelist page count' => static fn (): mixed => $plan135()->toArray()['final_freelist_page_count'],
    'final freelist page numbers' => static fn (): mixed => $plan135()->toArray()['final_freelist_page_numbers'],
    'updated page numbers' => static fn (): mixed => $plan135()->toArray()['updated_page_numbers'],
    'row page numbers' => static fn (): mixed => array_column($plan135()->rows, 'page_number'),
    'row pointer map pages' => static fn (): mixed => array_column($plan135()->rows, 'pointer_map_page'),
    'row current pointer types' => static fn (): mixed => array_column($plan135()->rows, 'current_pointer_map_type'),
    'row current pointer parents' => static fn (): mixed => array_column($plan135()->rows, 'current_pointer_map_parent'),
    'row next pointer types' => static fn (): mixed => array_column($plan135()->rows, 'next_pointer_map_type'),
    'row next pointer parents' => static fn (): mixed => array_column($plan135()->rows, 'next_pointer_map_parent'),
    'row freelist roles' => static fn (): mixed => array_column($plan135()->rows, 'freelist_role'),
    'row vacuum statuses' => static fn (): mixed => array_column($plan135()->rows, 'vacuum_status'),
    'materialized byte length' => static fn (): mixed => $plan135()->materializedApplySummary()['byte_length'],
    'materialized omitted tail pages' => static fn (): mixed => $plan135()->materializedApplySummary()['omitted_truncated_page_numbers'],
    'materialized freeblock status' => static fn (): mixed => $plan135()->materializedApplySummary()['freeblock_integrity_status'],
    'leaf freeblock after delete is valid' => static fn (): mixed => SQLiteBTreePageHeader::parsePage($plan135()->basePlan->deletePlan->leafPageImage, 512)->freeblockIntegrityReport($plan135()->basePlan->deletePlan->leafPageImage)['status'],
    'leaf rowids after delete' => static fn (): mixed => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, SQLiteTableLeafCell::parsePageCells($plan135()->basePlan->deletePlan->leafPageImage, SQLiteBTreePageHeader::parsePage($plan135()->basePlan->deletePlan->leafPageImage, 512))),
    'surviving page 106 pointer type' => static fn (): mixed => $plan135()->basePlan->nextDatabase->pointerMapEntryForPage(106)->typeName(),
    'surviving page 106 parent' => static fn (): mixed => $plan135()->basePlan->nextDatabase->pointerMapEntryForPage(106)->parentPageNumber,
    'truncated page 109 unavailable' => static function () use ($plan135): string {
        try {
            $plan135()->basePlan->nextDatabase->page(109);
        } catch (Throwable) {
            return 'unavailable';
        }

        return 'available';
    },
    'wide vacuum truncates pointer map page' => static fn (): mixed => $plan135(6)->truncatedPointerMapPages(),
    'wide vacuum final page count' => static fn (): mixed => $plan135(6)->toArray()['final_database_page_count'],
    'wide vacuum surviving pages' => static fn (): mixed => $plan135(6)->survivingReleasedOverflowPages(),
    'wide vacuum final freelist pages' => static fn (): mixed => $plan135(6)->toArray()['final_freelist_page_numbers'],
    'zero truncation rejected' => static fn (): mixed => $message135(static fn () => $plan135(0)),
];

$expected135 = [
    'action label' => 'btree-pointermap-freeblock-vacuum-current-source-next135',
    'leaf page type' => 'table-leaf',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'surviving released pages' => [106],
    'truncated released pages' => [107, 108, 109, 110],
    'truncated pointer map pages empty for partial vacuum' => [],
    'final page count after partial vacuum' => 106,
    'final first freelist trunk page' => 106,
    'final freelist page count' => 1,
    'final freelist page numbers' => [106],
    'updated page numbers' => [1, 3, 105, 106],
    'row page numbers' => [106, 107, 108, 109, 110],
    'row pointer map pages' => [105, 105, 105, 105, 105],
    'row current pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'row current pointer parents' => [3, 106, 107, 108, 109],
    'row next pointer types' => ['free-page', null, null, null, null],
    'row next pointer parents' => [0, null, null, null, null],
    'row freelist roles' => ['freelist-trunk', null, null, null, null],
    'row vacuum statuses' => ['survives-as-free-page', 'truncated', 'truncated', 'truncated', 'truncated'],
    'materialized byte length' => 54272,
    'materialized omitted tail pages' => [110, 109, 108, 107],
    'materialized freeblock status' => 'ok',
    'leaf freeblock after delete is valid' => 'ok',
    'leaf rowids after delete' => [1, 3],
    'surviving page 106 pointer type' => 'free-page',
    'surviving page 106 parent' => 0,
    'truncated page 109 unavailable' => 'unavailable',
    'wide vacuum truncates pointer map page' => [105],
    'wide vacuum final page count' => 104,
    'wide vacuum surviving pages' => [],
    'wide vacuum final freelist pages' => [],
    'zero truncation rejected' => 'SQLite pointer-map vacuum freeblock next127 requires a positive truncation limit',
];

foreach ($cases135 as $name => $callback) {
    $tests['btree pointermap freeblock vacuum current source next135 ' . $name] = static function (TestRunner $t) use ($callback, $expected135, $name): void {
        $t->same($expected135[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree pointermap freeblock vacuum current source next135 invariant ' . $index] = static function (TestRunner $t) use ($plan135): void {
        $plan = $plan135();

        $t->same([106, 107, 108, 109, 110], $plan->basePlan->releasedOverflowPages());
        $t->same([106], $plan->survivingReleasedOverflowPages());
        $t->same([107, 108, 109, 110], $plan->truncatedReleasedOverflowPages());
        $t->same(['freelist-trunk', null, null, null, null], array_column($plan->rows, 'freelist_role'));
        $t->same(['free-page', null, null, null, null], array_column($plan->rows, 'next_pointer_map_type'));
        $t->same('ok', $plan->materializedApplySummary()['freeblock_integrity_status']);
    };
}

return $tests;
