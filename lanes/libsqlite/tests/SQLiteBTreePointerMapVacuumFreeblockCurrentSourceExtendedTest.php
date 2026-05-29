<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage144 = static function (int $pageCount): string {
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

$putPointerMapEntry144 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage144 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database144 = static function () use ($makeFirstPage144, $putPointerMapEntry144, $overflowPage144): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage144(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_timeout_next144', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 24)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage144(107, 'A');
    $pages[107] = $overflowPage144(108, 'B');
    $pages[108] = $overflowPage144(109, 'C');
    $pages[109] = $overflowPage144(110, 'D');
    $pages[110] = $overflowPage144(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry144($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan144 = static function (int $maxTruncatedPages = 4) use ($database144): SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan {
    $database = $database144();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::extendedTableLeafFromDeleteResult(
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

$message144 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases144 = [
    'action label' => static fn (): mixed => $plan144()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan144()->toArray()['released_overflow_pages'],
    'surviving released pages' => static fn (): mixed => $plan144()->toArray()['surviving_released_overflow_pages'],
    'truncated released pages' => static fn (): mixed => $plan144()->toArray()['truncated_released_overflow_pages'],
    'materialized pages include leaf and survivor' => static fn (): mixed => $plan144()->toArray()['materialized_page_numbers'],
    'truncated pages omit materialized survivor' => static fn (): mixed => $plan144()->toArray()['truncated_page_numbers'],
    'final page count' => static fn (): mixed => $plan144()->toArray()['final_database_page_count'],
    'final freelist pages' => static fn (): mixed => $plan144()->toArray()['final_freelist_page_numbers'],
    'updated pages' => static fn (): mixed => $plan144()->toArray()['updated_page_numbers'],
    'row kinds' => static fn (): mixed => array_column($plan144()->rows, 'kind'),
    'row page numbers' => static fn (): mixed => array_column($plan144()->rows, 'page_number'),
    'row materialized flags' => static fn (): mixed => array_column($plan144()->rows, 'materialized'),
    'row vacuum statuses' => static fn (): mixed => array_column($plan144()->rows, 'vacuum_status'),
    'row freelist roles' => static fn (): mixed => array_column($plan144()->rows, 'freelist_role'),
    'row source pointer pages' => static fn (): mixed => array_column($plan144()->rows, 'source_pointer_map_page'),
    'row next pointer pages' => static fn (): mixed => array_column($plan144()->rows, 'next_pointer_map_page'),
    'row source pointer types' => static fn (): mixed => array_column($plan144()->rows, 'source_pointer_map_type'),
    'row next pointer types' => static fn (): mixed => array_column($plan144()->rows, 'next_pointer_map_type'),
    'row source pointer parents' => static fn (): mixed => array_column($plan144()->rows, 'source_pointer_map_parent'),
    'row next pointer parents' => static fn (): mixed => array_column($plan144()->rows, 'next_pointer_map_parent'),
    'overflow source next pages' => static fn (): mixed => array_values(array_filter(array_column($plan144()->rows, 'source_overflow_next_page'), static fn (mixed $value): bool => $value !== null)),
    'overflow next next pages' => static fn (): mixed => array_values(array_filter(array_column($plan144()->rows, 'next_overflow_next_page'), static fn (mixed $value): bool => $value !== null)),
    'leaf freeblock count' => static fn (): mixed => $plan144()->rows[0]['freeblock_count'],
    'leaf freeblock status' => static fn (): mixed => $plan144()->rows[0]['freeblock_status'],
    'leaf freeblock bytes' => static fn (): mixed => $plan144()->rows[0]['freeblock_bytes'],
    'materialized row count' => static fn (): mixed => count($plan144()->materializedRows()),
    'truncated row count' => static fn (): mixed => count($plan144()->truncatedRows()),
    'base materialized byte length' => static fn (): mixed => $plan144()->basePlan->materializedApplySummary()['byte_length'],
    'wide vacuum materialized pages only leaf' => static fn (): mixed => $plan144(6)->toArray()['materialized_page_numbers'],
    'wide vacuum truncates pointer map page' => static fn (): mixed => $plan144(6)->basePlan->truncatedPointerMapPages(),
    'zero truncation rejected' => static fn (): mixed => $message144(static fn () => $plan144(0)),
];

$expected144 = [
    'action label' => 'btree-pointermap-vacuum-freeblock-current-source-next144',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'surviving released pages' => [106],
    'truncated released pages' => [107, 108, 109, 110],
    'materialized pages include leaf and survivor' => [3, 106],
    'truncated pages omit materialized survivor' => [107, 108, 109, 110],
    'final page count' => 106,
    'final freelist pages' => [106],
    'updated pages' => [1, 3, 105, 106],
    'row kinds' => ['deleted-leaf-freeblock', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page'],
    'row page numbers' => [3, 106, 107, 108, 109, 110],
    'row materialized flags' => [true, true, false, false, false, false],
    'row vacuum statuses' => ['materialized-leaf-page', 'survives-as-free-page', 'truncated', 'truncated', 'truncated', 'truncated'],
    'row freelist roles' => [null, 'freelist-trunk', null, null, null, null],
    'row source pointer pages' => [2, 105, 105, 105, 105, 105],
    'row next pointer pages' => [2, 105, null, null, null, null],
    'row source pointer types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'row next pointer types' => ['root-page', 'free-page', null, null, null, null],
    'row source pointer parents' => [0, 3, 106, 107, 108, 109],
    'row next pointer parents' => [0, 0, null, null, null, null],
    'overflow source next pages' => [107, 108, 109, 110, 0],
    'overflow next next pages' => [0],
    'leaf freeblock count' => 0,
    'leaf freeblock status' => 'ok',
    'leaf freeblock bytes' => 0,
    'materialized row count' => 2,
    'truncated row count' => 4,
    'base materialized byte length' => 54272,
    'wide vacuum materialized pages only leaf' => [3],
    'wide vacuum truncates pointer map page' => [105],
    'zero truncation rejected' => 'SQLite pointer-map vacuum freeblock next127 requires a positive truncation limit',
];

foreach ($cases144 as $name => $callback) {
    $tests['btree pointermap vacuum freeblock current source next144 ' . $name] = static function (TestRunner $t) use ($callback, $expected144, $name): void {
        $t->same($expected144[$name], $callback());
    };
}

foreach (range(1, 32) as $index) {
    $tests['btree pointermap vacuum freeblock current source next144 invariant ' . $index] = static function (TestRunner $t) use ($plan144): void {
        $plan = $plan144();
        $leafRow = $plan->rows[0];
        $survivorRow = $plan->rows[1];

        $t->same('ok', $leafRow['freeblock_status']);
        $t->same('freelist-trunk', $survivorRow['freelist_role']);
        $t->same([3, 106], array_column($plan->materializedRows(), 'page_number'));
        $t->same([107, 108, 109, 110], array_column($plan->truncatedRows(), 'page_number'));
        $t->same($leafRow['next_page_hash'], hash('sha256', $plan->basePlan->basePlan->deletePlan->leafPageImage));
        $t->same('free-page', $plan->basePlan->basePlan->nextDatabase->pointerMapEntryForPage(106)->typeName());
        $t->same('ok', SQLiteBTreePageHeader::parsePage($plan->basePlan->basePlan->deletePlan->leafPageImage, 512)->freeblockIntegrityReport($plan->basePlan->basePlan->deletePlan->leafPageImage)['status']);
    };
}

return $tests;
