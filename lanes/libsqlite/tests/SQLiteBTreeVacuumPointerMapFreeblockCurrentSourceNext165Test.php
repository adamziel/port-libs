<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage165 = static function (int $pageCount): string {
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

$putPointerMapEntry165 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database165 = static function () use ($makeFirstPage165, $putPointerMapEntry165): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage165(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next165', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(70 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry165($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan165 = static function (int $maxTruncatedPages = 2, ?string $payload = null): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database165;

    $database = $database165();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafWritableDiffFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next165-multisite-transient-rewrite-', 42),
        3,
        true,
    );
};

$message165 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases165 = [
    'action label' => static fn (): mixed => $plan165()->toArray()['action'],
    'changed writable pages' => static fn (): mixed => $plan165()->changedWritablePages(),
    'unchanged writable pages' => static fn (): mixed => $plan165()->unchangedWritablePages(),
    'rejected current source pages' => static fn (): mixed => $plan165()->rejectedCurrentSourcePages(),
    'row pages' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'page_number'),
    'row write kinds' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'write_kind'),
    'row write allowed' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'write_allowed'),
    'row current materialized' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'current_materialized'),
    'row next materialized' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'next_materialized'),
    'row page changed' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'page_changed'),
    'row current overflow next' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'current_overflow_next_page'),
    'row next overflow next' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'next_overflow_next_page'),
    'row current pointer types' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'current_pointer_map_type'),
    'row current pointer parents' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'current_pointer_map_parent'),
    'row next pointer types' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'next_pointer_map_type'),
    'row next pointer parents' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'next_pointer_map_parent'),
    'row pointer map changed' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'pointer_map_changed'),
    'row pointer map offsets' => static fn (): mixed => array_column($plan165()->sourceNextRows(), 'pointer_map_cell_offsets'),
    'base writable pages' => static fn (): mixed => $plan165()->basePlan->writablePageNumbers(),
    'base rejected pages' => static fn (): mixed => $plan165()->basePlan->rejectedTruncatedPages(),
    'base replacement pages' => static fn (): mixed => $plan165()->toArray()['replacement_overflow_pages'],
    'base replacement next pages' => static fn (): mixed => $plan165()->toArray()['replacement_overflow_next_pages'],
    'base replacement parents' => static fn (): mixed => $plan165()->toArray()['replacement_pointer_map_parents'],
    'next row current hash length' => static fn (): mixed => strlen($plan165()->sourceNextRows()[1]['current_page_hash']),
    'next row next hash length' => static fn (): mixed => strlen($plan165()->sourceNextRows()[1]['next_page_hash']),
    'rejected next hashes' => static fn (): mixed => array_slice(array_column($plan165()->sourceNextRows(), 'next_page_hash'), -2),
    'wide vacuum rejected allocation' => static fn (): mixed => $message165(static fn () => $plan165(4)),
    'empty payload rejected' => static fn (): mixed => $message165(static fn () => $plan165(2, '')),
];

$expected165 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next165',
    'changed writable pages' => [1, 3, 105, 106, 107, 108],
    'unchanged writable pages' => [],
    'rejected current source pages' => [109, 110],
    'row pages' => [1, 3, 105, 106, 107, 108, 109, 110],
    'row write kinds' => ['database-header', 'leaf-freeblock-page', 'pointer-map-page', 'replacement-overflow-page', 'replacement-overflow-page', 'replacement-overflow-page', 'rejected-truncated-current-source-page', 'rejected-truncated-current-source-page'],
    'row write allowed' => [true, true, true, true, true, true, false, false],
    'row current materialized' => [true, true, true, true, true, true, true, true],
    'row next materialized' => [true, true, true, true, true, true, false, false],
    'row page changed' => [true, true, true, true, true, true, false, false],
    'row current overflow next' => [null, null, null, 107, 108, 109, null, null],
    'row next overflow next' => [null, null, null, 0, 108, 106, null, null],
    'row current pointer types' => [null, 'root-page', null, 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'row current pointer parents' => [null, 0, null, 3, 106, 107, 108, 109],
    'row next pointer types' => [null, 'root-page', null, 'overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'row next pointer parents' => [null, 0, null, 108, 3, 107, null, null],
    'row pointer map changed' => [false, false, false, true, true, false, true, true],
    'row pointer map offsets' => [[], [], [5, 10, 0], [], [], [], [], []],
    'base writable pages' => [1, 3, 105, 106, 107, 108],
    'base rejected pages' => [109, 110],
    'base replacement pages' => [107, 108, 106],
    'base replacement next pages' => [108, 106, 0],
    'base replacement parents' => [3, 107, 108],
    'next row current hash length' => 64,
    'next row next hash length' => 64,
    'rejected next hashes' => [null, null],
    'wide vacuum rejected allocation' => 'SQLite freelist does not contain enough pages for this allocation',
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases165 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next165 ' . $name] = static function (TestRunner $t) use ($callback, $expected165, $name): void {
        $t->same($expected165[$name], $callback());
    };
}

foreach (range(1, 50) as $index) {
    $tests['btree vacuum pointermap freeblock current source next165 source-next invariant ' . $index] = static function (TestRunner $t) use ($plan165): void {
        $plan = $plan165();
        $rows = $plan->sourceNextRows();

        $t->same([1, 3, 105, 106, 107, 108], $plan->changedWritablePages());
        $t->same([], $plan->unchangedWritablePages());
        $t->same([109, 110], $plan->rejectedCurrentSourcePages());
        $t->same([true, true, true, true, true, true, false, false], array_column($rows, 'write_allowed'));
        $t->same([true, true, true, true, true, true, false, false], array_column($rows, 'next_materialized'));
        $t->same([107, 108, 109], array_slice(array_column($rows, 'current_overflow_next_page'), 3, 3));
        $t->same([0, 108, 106], array_slice(array_column($rows, 'next_overflow_next_page'), 3, 3));
        $t->same(['overflow-page', 'first-overflow-page', 'overflow-page'], array_slice(array_column($rows, 'next_pointer_map_type'), 3, 3));
        $t->same([108, 3, 107], array_slice(array_column($rows, 'next_pointer_map_parent'), 3, 3));
    };
}

return $tests;
