<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage162 = static function (int $pageCount): string {
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

$putPointerMapEntry162 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database162 = static function () use ($makeFirstPage162, $putPointerMapEntry162): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage162(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next162', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry162($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan162 = static function (int $maxTruncatedPages = 2, ?string $payload = null): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database162;

    $database = $database162();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafWriteAdmissionFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next162-multisite-transient-rewrite-', 42),
        3,
        true,
    );
};

$message162 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases162 = [
    'action label' => static fn (): mixed => $plan162()->toArray()['action'],
    'writable pages' => static fn (): mixed => $plan162()->writablePageNumbers(),
    'pointer map write pages' => static fn (): mixed => $plan162()->pointerMapWritePages(),
    'rejected truncated pages' => static fn (): mixed => $plan162()->rejectedTruncatedPages(),
    'replacement pages' => static fn (): mixed => $plan162()->toArray()['replacement_overflow_pages'],
    'replacement next pages' => static fn (): mixed => $plan162()->toArray()['replacement_overflow_next_pages'],
    'replacement parents' => static fn (): mixed => $plan162()->toArray()['replacement_pointer_map_parents'],
    'final database page count' => static fn (): mixed => $plan162()->toArray()['final_database_page_count'],
    'write row pages' => static fn (): mixed => array_column($plan162()->writeRows(), 'page_number'),
    'write row kinds' => static fn (): mixed => array_column($plan162()->writeRows(), 'write_kind'),
    'write allowed flags' => static fn (): mixed => array_column($plan162()->writeRows(), 'write_allowed'),
    'write page sizes' => static fn (): mixed => array_column($plan162()->writeRows(), 'page_size'),
    'pointer map flags' => static fn (): mixed => array_column($plan162()->writeRows(), 'is_pointer_map_page'),
    'replacement flags' => static fn (): mixed => array_column($plan162()->writeRows(), 'is_replacement_overflow_page'),
    'overflow next pages' => static fn (): mixed => array_column($plan162()->writeRows(), 'overflow_next_page'),
    'pointer map offsets' => static fn (): mixed => array_column($plan162()->writeRows(), 'pointer_map_cell_offsets'),
    'pointer map page hash is sha256' => static fn (): mixed => strlen($plan162()->writeRows()[2]['page_hash']),
    'leaf page hash is sha256' => static fn (): mixed => strlen($plan162()->writeRows()[1]['page_hash']),
    'rejected hash is null' => static fn (): mixed => array_slice(array_column($plan162()->writeRows(), 'page_hash'), -2),
    'base reused pages' => static fn (): mixed => $plan162()->basePlan->reusedCurrentSourceFreePages(),
    'base truncated rejected' => static fn (): mixed => $plan162()->basePlan->truncatedCurrentSourcePagesRejected(),
    'base final freelist pages' => static fn (): mixed => $plan162()->basePlan->toArray()['final_freelist_page_numbers'],
    'wide vacuum rejected allocation' => static fn (): mixed => $message162(static fn () => $plan162(4)),
    'empty payload rejected' => static fn (): mixed => $message162(static fn () => $plan162(2, '')),
];

$expected162 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next162',
    'writable pages' => [1, 3, 105, 106, 107, 108],
    'pointer map write pages' => [105],
    'rejected truncated pages' => [109, 110],
    'replacement pages' => [107, 108, 106],
    'replacement next pages' => [108, 106, 0],
    'replacement parents' => [3, 107, 108],
    'final database page count' => 108,
    'write row pages' => [1, 3, 105, 106, 107, 108, 109, 110],
    'write row kinds' => ['database-header', 'leaf-freeblock-page', 'pointer-map-page', 'replacement-overflow-page', 'replacement-overflow-page', 'replacement-overflow-page', 'rejected-truncated-current-source-page', 'rejected-truncated-current-source-page'],
    'write allowed flags' => [true, true, true, true, true, true, false, false],
    'write page sizes' => [512, 512, 512, 512, 512, 512, 0, 0],
    'pointer map flags' => [false, false, true, false, false, false, false, false],
    'replacement flags' => [false, false, false, true, true, true, false, false],
    'overflow next pages' => [null, null, null, 0, 108, 106, null, null],
    'pointer map offsets' => [[], [], [5, 10, 0], [], [], [], [], []],
    'pointer map page hash is sha256' => 64,
    'leaf page hash is sha256' => 64,
    'rejected hash is null' => [null, null],
    'base reused pages' => [107, 108, 106],
    'base truncated rejected' => [109, 110],
    'base final freelist pages' => [],
    'wide vacuum rejected allocation' => 'SQLite freelist does not contain enough pages for this allocation',
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases162 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next162 ' . $name] = static function (TestRunner $t) use ($callback, $expected162, $name): void {
        $t->same($expected162[$name], $callback());
    };
}

foreach (range(1, 48) as $index) {
    $tests['btree vacuum pointermap freeblock current source next162 write invariant ' . $index] = static function (TestRunner $t) use ($plan162): void {
        $plan = $plan162();

        $t->same([1, 3, 105, 106, 107, 108], $plan->writablePageNumbers());
        $t->same([109, 110], $plan->rejectedTruncatedPages());
        $t->same([105], $plan->pointerMapWritePages());
        $t->same([107, 108, 106], $plan->basePlan->replacementOverflowPages());
        $t->same([108, 106, 0], $plan->basePlan->replacementOverflowNextPages());
        $t->same([3, 107, 108], $plan->basePlan->replacementPointerMapParents());
        $t->same([true, true, true, true, true, true, false, false], array_column($plan->writeRows(), 'write_allowed'));
        $t->same([[], [], [5, 10, 0], [], [], [], [], []], array_column($plan->writeRows(), 'pointer_map_cell_offsets'));
        $t->same([], array_values(array_intersect($plan->writablePageNumbers(), $plan->rejectedTruncatedPages())));
    };
}

return $tests;
