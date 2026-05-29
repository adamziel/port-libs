<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreePageMoveFreelistRebalanceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

$makeFirstPage = static function (int $pageCount, int $firstTrunk, int $freelistCount): string {
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
    $page = substr_replace($page, pack('N', $firstTrunk), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$overflowReaderFor = static function (array $overflowPages): callable {
    return static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): string {
        $payload = '';
        $pageNumber = $firstOverflowPage;
        while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
            $page = $overflowPages[$pageNumber] ?? null;
            if ($page === null) {
                throw new InvalidArgumentException('Fixture overflow page is missing');
            }
            $pageNumber = unpack('N', substr($page, 0, 4))[1];
            $payload .= substr($page, 4);
        }

        return substr($payload, 0, $byteCount);
    };
};

$overflowNumbersFor = static function (array $overflowPages): callable {
    return static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
        $pages = [];
        $pageNumber = $firstOverflowPage;
        $remaining = $byteCount;
        while ($pageNumber !== 0 && $remaining > 0) {
            $page = $overflowPages[$pageNumber] ?? null;
            if ($page === null) {
                throw new InvalidArgumentException('Fixture overflow page is missing');
            }
            $pages[] = $pageNumber;
            $pageNumber = unpack('N', substr($page, 0, 4))[1];
            $remaining -= min($remaining, 508);
        }

        return $pages;
    };
};

$fixture = static function (bool $secureDelete = true) use ($makeFirstPage, $putPointerMapEntry, $overflowReaderFor, $overflowNumbersFor): array {
    $pageCount = 12;
    $pages = array_fill(1, $pageCount, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage($pageCount, 11, 1);
    $pages[2] = str_repeat("\0", 512);
    $pages[11] = SQLiteFreelistTrunkPage::assemble(null, [], 512);

    $deletedValues = ['no', '_transient_current_source_next85', str_repeat('deleted-index-overflow-next85:', 60), 10];
    $deletedPayload = SQLiteRecord::encode($deletedValues);
    $deleted = SQLiteIndexCell::encodeWithOverflowPages($deletedPayload, 7, 512);
    $leftLeaf = SQLiteIndexLeafPage::assemble([
        $deleted['cell'],
        SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_keep_next85', 20])),
    ]);
    $rightLeaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_beta_next85', 40])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_gamma_next85', 50])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_omega_next85', 60])),
    ]);
    $movedLeaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_moved_next85', 900])),
    ]);
    $parent = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_alpha_next85', 30]), leftChildPage: 4),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_divider_next85', 800]), leftChildPage: 5),
    ], 12);

    $pages[3] = $parent;
    $pages[4] = $leftLeaf;
    $pages[5] = $rightLeaf;
    $overflowPages = array_combine(range(7, 6 + count($deleted['overflowPages'])), $deleted['overflowPages']);
    foreach ($overflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    $pages[12] = $movedLeaf;

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
        10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
        11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        12 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    ] as $pageNumber => [$type, $parentPageNumber]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parentPageNumber);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreePageMoveFreelistRebalanceCurrentSourceNextPlan::deleteRebalanceFreeAndMoveLastIndexLeaf(
        $database,
        3,
        4,
        5,
        0,
        $deletedValues,
        $overflowNumbersFor($overflowPages),
        12,
        $secureDelete,
        $overflowReaderFor($overflowPages),
    );

    return [$database, $plan, $deletedValues];
};

$recordsFor = static function (SQLiteDatabase $database, int $pageNumber): array {
    $page = $database->page($pageNumber);
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header, $database->usablePageSize()),
    );
};

$parentInfo = static function (SQLiteDatabase $database): array {
    $page = $database->page(3);
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return [
        'rightMost' => $header->rightMostPointer,
        'cells' => array_map(
            static fn (SQLiteIndexCell $cell): array => ['leftChild' => $cell->leftChildPage, 'values' => $cell->record()->values],
            SQLiteIndexCell::parsePageCells($page, $header, $database->usablePageSize()),
        ),
    ];
};

$cases = [
    'plan class' => static fn (array $fx): mixed => get_class($fx[1]),
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'obsolete overflow pages' => static fn (array $fx): mixed => $fx[1]->rebalanceFreelistPlan->obsoleteOverflowPageNumbers,
    'freed pages before move' => static fn (array $fx): mixed => $fx[1]->toArray()['freed_pages_before_move'],
    'moved source page' => static fn (array $fx): mixed => $fx[1]->pageMovePlan->sourcePageNumber,
    'moved target page' => static fn (array $fx): mixed => $fx[1]->pageMovePlan->targetPageNumber,
    'database page count after move' => static fn (array $fx): mixed => $fx[1]->database->pageCount(),
    'header database size after move' => static fn (array $fx): mixed => $fx[1]->database->header->databaseSizePages,
    'header first trunk after move' => static fn (array $fx): mixed => $fx[1]->database->header->firstFreelistTrunkPage,
    'header freelist count after move' => static fn (array $fx): mixed => $fx[1]->database->header->freelistPageCount,
    'updated page numbers' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers(),
    'summary updated pages' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_page_numbers'],
    'summary pointer map pages' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'rebalance action' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance_freelist']['action'],
    'page move action' => static fn (array $fx): mixed => $fx[1]->toArray()['page_move']['action'],
    'page move allocation' => static fn (array $fx): mixed => $fx[1]->toArray()['page_move']['allocated_page_numbers'],
    'page move parent update' => static fn (array $fx): mixed => $fx[1]->toArray()['page_move']['parent_pointer_update'],
    'left records after all apply' => static fn (array $fx): mixed => $recordsFor($fx[1]->database, 4),
    'right records after all apply' => static fn (array $fx): mixed => $recordsFor($fx[1]->database, 5),
    'moved leaf records at target' => static fn (array $fx): mixed => $recordsFor($fx[1]->database, $fx[1]->pageMovePlan->targetPageNumber),
    'parent rightmost after move' => static fn (array $fx): mixed => $parentInfo($fx[1]->database)['rightMost'],
    'parent cells after move' => static fn (array $fx): mixed => $parentInfo($fx[1]->database)['cells'],
    'freelist pages after move' => static fn (array $fx): mixed => $fx[1]->database->freelistPageNumbers(),
    'freelist allocation order after move' => static fn (array $fx): mixed => $fx[1]->database->freelistAllocationOrder(),
    'pointer map target type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage($fx[1]->pageMovePlan->targetPageNumber)->typeName(),
    'pointer map target parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage($fx[1]->pageMovePlan->targetPageNumber)->parentPageNumber,
    'pointer map freed page 8' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(8)->typeName(),
    'pointer map freed page 9' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(9)->typeName(),
    'pointer map moved old source absent by count' => static fn (array $fx): mixed => $fx[1]->database->pageCount() < 12,
    'deleted key carried' => static fn (array $fx): mixed => $fx[2][1],
];

$expected = [
    'plan class' => SQLiteBTreePageMoveFreelistRebalanceCurrentSourceNextPlan::class,
    'action label' => 'btree-page-move-freelist-rebalance-current-source-next85',
    'obsolete overflow pages' => [7, 8, 9, 10],
    'freed pages before move' => [7, 8, 9, 10],
    'moved source page' => 12,
    'moved target page' => 7,
    'database page count after move' => 11,
    'header database size after move' => 11,
    'header first trunk after move' => 11,
    'header freelist count after move' => 4,
    'updated page numbers' => [1, 2, 3, 4, 5, 7, 8, 9, 10, 11],
    'summary updated pages' => [1, 2, 3, 4, 5, 7, 8, 9, 10, 11],
    'summary pointer map pages' => [2],
    'rebalance action' => 'btree-index-overflow-rebalance-freelist-current-source-next82',
    'page move action' => 'auto-vacuum-index-leaf-page-move',
    'page move allocation' => [7],
    'page move parent update' => ['kind' => 'right-most', 'page' => 3, 'before' => 12, 'after' => 7],
    'left records after all apply' => [
        ['no', '_transient_keep_next85', 20],
        ['yes', 'autoload_alpha_next85', 30],
    ],
    'right records after all apply' => [
        ['yes', 'autoload_gamma_next85', 50],
        ['yes', 'autoload_omega_next85', 60],
    ],
    'moved leaf records at target' => [
        ['z', 'tail_moved_next85', 900],
    ],
    'parent rightmost after move' => 7,
    'parent cells after move' => [
        ['leftChild' => 4, 'values' => ['yes', 'autoload_beta_next85', 40]],
        ['leftChild' => 5, 'values' => ['z', 'tail_divider_next85', 800]],
    ],
    'freelist pages after move' => [11, 10, 8, 9],
    'freelist allocation order after move' => [10, 9, 8, 11],
    'pointer map target type' => 'btree-page',
    'pointer map target parent' => 3,
    'pointer map freed page 8' => 'free-page',
    'pointer map freed page 9' => 'free-page',
    'pointer map moved old source absent by count' => true,
    'deleted key carried' => '_transient_current_source_next85',
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree page move freelist rebalance current source next85 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

for ($index = 0; $index < 34; $index++) {
    $tests['btree page move freelist rebalance current source next85 generated invariant ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($fixture, $index): void {
        [, $plan] = $fixture((bool) ($index % 2));
        $summary = $plan->toArray();

        $t->same(7, $summary['moved_target_page']);
        $t->same(11, $summary['database_page_count']);
        $t->same('btree-page', $plan->database->pointerMapEntryForPage(7)->typeName());
        $t->same('free-page', $plan->database->pointerMapEntryForPage([8, 9, 10][$index % 3])->typeName());
    };
}

$tests['btree page move freelist rebalance current source next85 can skip secure-delete clearing'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan] = $fixture(false);

    $t->same([], $plan->rebalanceFreelistPlan->freePlan->clearedPageNumbers);
    $t->same([7, 8, 9, 10], $plan->rebalanceFreelistPlan->obsoleteOverflowPageNumbers);
    $t->same(7, $plan->pageMovePlan->targetPageNumber);
};

$tests['btree page move freelist rebalance current source next85 rejects non-last source'] = static function (TestRunner $t) use ($fixture): void {
    [$database, , $deletedValues] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreePageMoveFreelistRebalanceCurrentSourceNextPlan::deleteRebalanceFreeAndMoveLastIndexLeaf(
        $database,
        3,
        4,
        5,
        0,
        $deletedValues,
        static fn (): array => [7, 8, 9, 10],
        10,
    ));
};

return $tests;
