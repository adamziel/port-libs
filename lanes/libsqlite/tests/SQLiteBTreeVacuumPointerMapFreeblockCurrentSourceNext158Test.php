<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage158 = static function (int $pageCount): string {
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

$putPointerMapEntry158 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage158 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database158 = static function () use ($makeFirstPage158, $putPointerMapEntry158, $overflowPage158): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage158(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_timeout_next158', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 24)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage158(107, 'A');
    $pages[107] = $overflowPage158(108, 'B');
    $pages[108] = $overflowPage158(109, 'C');
    $pages[109] = $overflowPage158(110, 'D');
    $pages[110] = $overflowPage158(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry158($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan158 = static function (?string $payload = null) use ($database158): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database158();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext158(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        4,
        3,
        $payload ?? str_repeat('R', 500),
        true,
    );
};

$throws158 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases158 = [
    'action label' => static fn (): mixed => $plan158()->toArray()['action'],
    'released pages' => static fn (): mixed => $plan158()->toArray()['released_overflow_pages'],
    'surviving pages' => static fn (): mixed => $plan158()->toArray()['surviving_released_overflow_pages'],
    'truncated pages' => static fn (): mixed => $plan158()->toArray()['truncated_page_numbers'],
    'allocated pages' => static fn (): mixed => $plan158()->allocatedOverflowPages(),
    'reused vacuum freelist pages' => static fn (): mixed => $plan158()->reusedVacuumFreelistPages(),
    'truncated pages not reused' => static fn (): mixed => $plan158()->truncatedPagesNotReused(),
    'final page count' => static fn (): mixed => $plan158()->toArray()['final_database_page_count'],
    'final first freelist trunk' => static fn (): mixed => $plan158()->toArray()['final_first_freelist_trunk_page'],
    'final freelist count' => static fn (): mixed => $plan158()->toArray()['final_freelist_page_count'],
    'final freelist pages' => static fn (): mixed => $plan158()->toArray()['final_freelist_page_numbers'],
    'updated pages' => static fn (): mixed => $plan158()->toArray()['updated_page_numbers'],
    'row pages' => static fn (): mixed => array_column($plan158()->rows(), 'page_number'),
    'row allocation sources' => static fn (): mixed => array_column($plan158()->rows(), 'allocation_source'),
    'row allocation trunks' => static fn (): mixed => array_column($plan158()->rows(), 'allocation_trunk_page'),
    'row vacuum statuses' => static fn (): mixed => array_column($plan158()->rows(), 'vacuum_status'),
    'row vacuum freelist roles' => static fn (): mixed => array_column($plan158()->rows(), 'vacuum_freelist_role'),
    'row before pointer types' => static fn (): mixed => array_column($plan158()->rows(), 'before_pointer_map_type'),
    'row before pointer parents' => static fn (): mixed => array_column($plan158()->rows(), 'before_pointer_map_parent'),
    'row next pointer types' => static fn (): mixed => array_column($plan158()->rows(), 'next_pointer_map_type'),
    'row next pointer parents' => static fn (): mixed => array_column($plan158()->rows(), 'next_pointer_map_parent'),
    'row overflow next pages' => static fn (): mixed => array_column($plan158()->rows(), 'next_overflow_next_page'),
    'row tail flags' => static fn (): mixed => array_column($plan158()->rows(), 'next_overflow_is_tail'),
    'row payload prefixes' => static fn (): mixed => array_column($plan158()->rows(), 'payload_prefix'),
    'allocation pointer entries' => static fn (): mixed => array_column($plan158()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer parents' => static fn (): mixed => array_column($plan158()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'summary allocated pages' => static fn (): mixed => $plan158()->toArray()['allocated_overflow_pages'],
    'summary vacuum allocated pages agree' => static fn (): mixed => $plan158()->toArray()['vacuum']['final_freelist_page_numbers'],
    'summary allocation page count' => static fn (): mixed => $plan158()->toArray()['allocation']['database_page_count'],
    'overflow image pages' => static fn (): mixed => array_keys($plan158()->overflowPageImages()),
    'overflow image next pointer' => static fn (): mixed => unpack('N', substr($plan158()->overflowPageImages()[106], 0, 4))[1],
    'overflow image payload byte' => static fn (): mixed => substr($plan158()->overflowPageImages()[106], 4, 1),
    'final pointer map type' => static fn (): mixed => $plan158()->databaseAfterAllocation->pointerMapEntryForPage(106)->typeName(),
    'final pointer map parent' => static fn (): mixed => $plan158()->databaseAfterAllocation->pointerMapEntryForPage(106)->parentPageNumber,
    'final allocated page bytes' => static fn (): mixed => substr($plan158()->databaseAfterAllocation->page(106), 4, 3),
    'empty payload rejected' => static fn (): mixed => $throws158(static fn () => $plan158('')),
    'two page allocation without append rejected' => static fn (): mixed => $throws158(static fn () => $plan158(str_repeat('Q', 900))),
];

$expected158 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next158',
    'released pages' => [106, 107, 108, 109, 110],
    'surviving pages' => [106],
    'truncated pages' => [107, 108, 109, 110],
    'allocated pages' => [106],
    'reused vacuum freelist pages' => [106],
    'truncated pages not reused' => [107, 108, 109, 110],
    'final page count' => 106,
    'final first freelist trunk' => 0,
    'final freelist count' => 0,
    'final freelist pages' => [],
    'updated pages' => [1, 3, 105, 106],
    'row pages' => [106],
    'row allocation sources' => ['freelist-trunk'],
    'row allocation trunks' => [106],
    'row vacuum statuses' => ['survives-as-free-page'],
    'row vacuum freelist roles' => ['freelist-trunk'],
    'row before pointer types' => ['free-page'],
    'row before pointer parents' => [0],
    'row next pointer types' => ['first-overflow-page'],
    'row next pointer parents' => [3],
    'row overflow next pages' => [0],
    'row tail flags' => [true],
    'row payload prefixes' => [str_repeat('R', 12)],
    'allocation pointer entries' => ['first-overflow-page'],
    'allocation pointer parents' => [3],
    'summary allocated pages' => [106],
    'summary vacuum allocated pages agree' => [106],
    'summary allocation page count' => 106,
    'overflow image pages' => [106],
    'overflow image next pointer' => 0,
    'overflow image payload byte' => 'R',
    'final pointer map type' => 'first-overflow-page',
    'final pointer map parent' => 3,
    'final allocated page bytes' => 'RRR',
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next158 requires replacement overflow payload bytes',
    'two page allocation without append rejected' => 'SQLite freelist does not contain enough pages for this allocation',
];

foreach ($cases158 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next158 ' . $name] = static function (TestRunner $t) use ($callback, $expected158, $name): void {
        $t->same($expected158[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree vacuum pointermap freeblock current source next158 invariant ' . $index] = static function (TestRunner $t) use ($plan158): void {
        $plan = $plan158();
        $row = $plan->rows()[0];
        $t->same([106], $plan->allocatedOverflowPages());
        $t->same([106], $plan->reusedVacuumFreelistPages());
        $t->same([107, 108, 109, 110], $plan->truncatedPagesNotReused());
        $t->same('free-page', $row['before_pointer_map_type']);
        $t->same('first-overflow-page', $row['next_pointer_map_type']);
        $t->same(0, $row['next_overflow_next_page']);
        $t->same(106, $plan->databaseAfterAllocation->pageCount());
    };
}

return $tests;
