<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageSize, int $pageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$payload = static fn (string $name, string $value = 'yes'): string => SQLiteRecord::encode([null, $name, $value, 'yes']);

$fixture = static function (string $replacementPayload = '') use ($makeFirstPage, $payload): array {
    $pageSize = 512;
    $largePayload = SQLiteRecord::encode([null, '_transient_rebalance_delete_10', str_repeat('delete-current-next:', 62), 'no']);
    $large = SQLiteTableLeafCell::encodeWithOverflowPages(10, $largePayload, 7, $pageSize);
    $leftLeaf = SQLiteTableLeafPage::assemble([
        $large['cell'],
        SQLiteTableLeafCell::encode(20, $payload('_transient_keep_20'), $pageSize),
    ], $pageSize);
    $rightLeaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(30, $payload('autoload_next_30'), $pageSize),
        SQLiteTableLeafCell::encode(40, $payload('autoload_next_40'), $pageSize),
        SQLiteTableLeafCell::encode(50, $payload('autoload_next_50'), $pageSize),
        SQLiteTableLeafCell::encode(60, $payload('autoload_next_60'), $pageSize),
    ], $pageSize);
    $tailLeaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(9000, $payload('tail_balance_9000'), $pageSize),
    ], $pageSize);
    $parent = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(4, 20),
        SQLiteTableInteriorCell::encode(5, 8999),
    ], 6, $pageSize);

    $pointerMapPage = str_repeat("\0", $pageSize);
    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
    ] as $pageNumber => [$type, $parentPageNumber]) {
        $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
    }

    $database = SQLiteDatabase::fromBytes(
        $makeFirstPage($pageSize, 8)
        . $pointerMapPage
        . $parent
        . $leftLeaf
        . $rightLeaf
        . $tailLeaf
        . implode('', $large['overflowPages']),
    );
    $overflowPages = array_combine(range(7, 6 + count($large['overflowPages'])), $large['overflowPages']);
    $replacementPayload = $replacementPayload !== '' ? $replacementPayload : str_repeat('replacement-current-next:', 42);
    $overflowPageNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
        $pageNumbers = [];
        $pageNumber = $firstOverflowPage;
        $remaining = $byteCount;
        while ($pageNumber !== 0 && $remaining > 0) {
            $page = $overflowPages[$pageNumber] ?? null;
            if ($page === null) {
                throw new InvalidArgumentException('Fixture overflow page is missing');
            }
            $pageNumbers[] = $pageNumber;
            $pageNumber = unpack('N', substr($page, 0, 4))[1];
            $remaining -= min($remaining, 508);
        }

        return $pageNumbers;
    };
    $plan = SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan::tableDeleteRebalanceThenReplaceOverflow(
        $database,
        3,
        4,
        5,
        0,
        10,
        $overflowPageNumbers,
        $replacementPayload,
        true,
        true,
    );

    return [$database, $plan, $replacementPayload];
};

$rowIdsFor = static function (SQLiteDatabase $database, int $pageNumber): array {
    $page = $database->page($pageNumber);
    $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize);

    return array_map(
        static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
        SQLiteTableLeafCell::parsePageCells($page, $header, $database->usablePageSize(), static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount)),
    );
};

$parentKeysFor = static function (SQLiteDatabase $database): array {
    $header = SQLiteBTreePageHeader::parsePage($database->page(3), $database->header->pageSize);

    return array_map(
        static fn (SQLiteTableInteriorCell $cell): array => ['leftChild' => $cell->leftChildPage, 'key' => $cell->key],
        SQLiteTableInteriorCell::parsePageCells($database->page(3), $header),
    );
};

$cases = [
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'deleted rowid' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->deletedRowId,
    'deleted payload bytes positive' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->deletedPayloadBytes > 0,
    'obsolete overflow pages' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->obsoleteOverflowPageNumbers,
    'replacement overflow pages reuse current freelist' => static fn (array $fx): mixed => $fx[1]->replacementOverflowPageNumbers(),
    'reused obsolete overflow pages' => static fn (array $fx): mixed => $fx[1]->reusedObsoleteOverflowPages,
    'appended page supplies replacement tail' => static fn (array $fx): mixed => $fx[1]->allocationPlan->appendedPageNumbers,
    'rebalance moved two next cells' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->toArray()['moved_cell_count'],
    'after rebalance cell counts' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->toArray()['after_rebalance_cells'],
    'parent divider updates after move' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->toArray()['updated_parent_divider'],
    'current leaf rowids after rebalance' => static fn (array $fx): mixed => $rowIdsFor($fx[1]->database, 4),
    'next leaf rowids after rebalance' => static fn (array $fx): mixed => $rowIdsFor($fx[1]->database, 5),
    'parent keys after rebalance' => static fn (array $fx): mixed => $parentKeysFor($fx[1]->database),
    'freelist count after release' => static fn (array $fx): mixed => $fx[1]->freePlan->freelistPageCount,
    'first freelist trunk after release' => static fn (array $fx): mixed => $fx[1]->freePlan->firstFreelistTrunkPage,
    'free plan freed pages' => static fn (array $fx): mixed => $fx[1]->freePlan->freedPageNumbers,
    'free plan new trunk pages' => static fn (array $fx): mixed => $fx[1]->freePlan->newTrunkPageNumbers,
    'free plan leaf pages' => static fn (array $fx): mixed => $fx[1]->freePlan->leafPageNumbers,
    'secure delete cleared pages' => static fn (array $fx): mixed => $fx[1]->freePlan->clearedPageNumbers,
    'free pointer map entry pages' => static fn (array $fx): mixed => array_column($fx[1]->freePlan->freedPointerMapEntries, 'page_number'),
    'free pointer map entry types' => static fn (array $fx): mixed => array_column($fx[1]->freePlan->freedPointerMapEntries, 'type_name'),
    'allocation count after replacement' => static fn (array $fx): mixed => $fx[1]->allocationPlan->freelistPageCount,
    'allocation first trunk after replacement' => static fn (array $fx): mixed => $fx[1]->allocationPlan->firstFreelistTrunkPage,
    'allocation database page count unchanged' => static fn (array $fx): mixed => $fx[1]->allocationPlan->databasePageCount,
    'allocation steps source order' => static fn (array $fx): mixed => array_column($fx[1]->allocationPlan->allocationSteps(), 'source'),
    'allocation steps pages' => static fn (array $fx): mixed => array_column($fx[1]->allocationPlan->allocationSteps(), 'allocated_page'),
    'allocated pointer map pages' => static fn (array $fx): mixed => array_column($fx[1]->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocated pointer map types' => static fn (array $fx): mixed => array_column($fx[1]->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocated pointer map parents' => static fn (array $fx): mixed => array_column($fx[1]->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'chain link count' => static fn (array $fx): mixed => count($fx[1]->replacementChainLinks),
    'chain current pages' => static fn (array $fx): mixed => array_column($fx[1]->replacementChainLinks, 'current_page'),
    'chain next pages' => static fn (array $fx): mixed => array_column($fx[1]->replacementChainLinks, 'next_page'),
    'chain terminal flags' => static fn (array $fx): mixed => array_column($fx[1]->replacementChainLinks, 'terminal'),
    'first chain payload bytes' => static fn (array $fx): mixed => $fx[1]->replacementChainLinks[0]['payload_bytes'],
    'second chain payload bytes' => static fn (array $fx): mixed => $fx[1]->replacementChainLinks[1]['payload_bytes'],
    'third chain terminal payload bytes' => static fn (array $fx): mixed => $fx[1]->replacementChainLinks[2]['payload_bytes'],
    'overflow page image keys' => static fn (array $fx): mixed => array_keys($fx[1]->overflowPageImages),
    'first replacement next pointer' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->pageImages[8], 0, 4))[1],
    'second replacement next pointer' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->pageImages[7], 0, 4))[1],
    'terminal replacement next pointer' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->pageImages[9], 0, 4))[1],
    'post database freelist empty' => static fn (array $fx): mixed => $fx[1]->database->freelistPageNumbers(),
    'post allocation order empty' => static fn (array $fx): mixed => $fx[1]->database->freelistAllocationOrder(),
    'post pointer map page 8 type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(8)->typeName(),
    'post pointer map page 7 type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(7)->typeName(),
    'post pointer map page 9 type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(9)->typeName(),
    'post pointer map page 8 parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(8)->parentPageNumber,
    'post pointer map page 7 parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(7)->parentPageNumber,
    'post pointer map page 9 parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(9)->parentPageNumber,
    'updated pages include rebalance freelist and overflow' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers(),
    'toArray replacement pages' => static fn (array $fx): mixed => $fx[1]->toArray()['replacement_overflow_pages'],
    'toArray reused pages' => static fn (array $fx): mixed => $fx[1]->toArray()['reused_obsolete_overflow_pages'],
    'toArray updated pointer map pages' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'toArray nested rebalance action' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance']['action'],
    'toArray nested release pages' => static fn (array $fx): mixed => $fx[1]->toArray()['freelist_release']['freed_page_numbers'],
    'toArray nested allocation pages' => static fn (array $fx): mixed => $fx[1]->toArray()['allocation']['allocated_page_numbers'],
];

$expected = [
    'action label' => 'btree-freeblock-rebalance-cell-overflow-current-next',
    'deleted rowid' => 10,
    'deleted payload bytes positive' => true,
    'obsolete overflow pages' => [7, 8],
    'replacement overflow pages reuse current freelist' => [8, 7, 9],
    'reused obsolete overflow pages' => [8, 7],
    'appended page supplies replacement tail' => [9],
    'rebalance moved two next cells' => 2,
    'after rebalance cell counts' => ['left' => 3, 'right' => 2],
    'parent divider updates after move' => ['left_child' => 4, 'key' => 40],
    'current leaf rowids after rebalance' => [20, 30, 40],
    'next leaf rowids after rebalance' => [50, 60],
    'parent keys after rebalance' => [
        ['leftChild' => 4, 'key' => 40],
        ['leftChild' => 5, 'key' => 8999],
    ],
    'freelist count after release' => 2,
    'first freelist trunk after release' => 7,
    'free plan freed pages' => [7, 8],
    'free plan new trunk pages' => [7],
    'free plan leaf pages' => [8],
    'secure delete cleared pages' => [8],
    'free pointer map entry pages' => [7, 8],
    'free pointer map entry types' => ['free-page', 'free-page'],
    'allocation count after replacement' => 0,
    'allocation first trunk after replacement' => 0,
    'allocation database page count unchanged' => 9,
    'allocation steps source order' => ['freelist-leaf', 'freelist-trunk', 'append'],
    'allocation steps pages' => [8, 7, 9],
    'allocated pointer map pages' => [8, 7, 9],
    'allocated pointer map types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'allocated pointer map parents' => [4, 8, 7],
    'chain link count' => 3,
    'chain current pages' => [8, 7, 9],
    'chain next pages' => [7, 9, 0],
    'chain terminal flags' => [false, false, true],
    'first chain payload bytes' => 508,
    'second chain payload bytes' => 508,
    'third chain terminal payload bytes' => 34,
    'overflow page image keys' => [8, 7, 9],
    'first replacement next pointer' => 7,
    'second replacement next pointer' => 9,
    'terminal replacement next pointer' => 0,
    'post database freelist empty' => [],
    'post allocation order empty' => [],
    'post pointer map page 8 type' => 'first-overflow-page',
    'post pointer map page 7 type' => 'overflow-page',
    'post pointer map page 9 type' => 'overflow-page',
    'post pointer map page 8 parent' => 4,
    'post pointer map page 7 parent' => 8,
    'post pointer map page 9 parent' => 7,
    'updated pages include rebalance freelist and overflow' => [1, 2, 3, 4, 5, 7, 8, 9],
    'toArray replacement pages' => [8, 7, 9],
    'toArray reused pages' => [8, 7],
    'toArray updated pointer map pages' => [2],
    'toArray nested rebalance action' => 'table-delete-rebalance-apply',
    'toArray nested release pages' => [7, 8],
    'toArray nested allocation pages' => [8, 7, 9],
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree freeblock rebalance cell overflow current next ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

$tests['btree freeblock rebalance cell overflow current next reads replacement payload back'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan, $payload] = $fixture();
    $readBack = '';
    $pageNumber = 8;
    while ($pageNumber !== 0 && strlen($readBack) < strlen($payload)) {
        $page = $plan->database->page($pageNumber);
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $readBack .= substr($page, 4);
    }

    $t->same($payload, substr($readBack, 0, strlen($payload)));
};

$tests['btree freeblock rebalance cell overflow current next rejects empty replacement payload'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan::tableDeleteRebalanceThenReplaceOverflow(
        $database,
        3,
        4,
        5,
        0,
        10,
        static fn (): array => [7, 8],
        '',
    ));
};

$tests['btree freeblock rebalance cell overflow current next rejects absent row'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan::tableDeleteRebalanceThenReplaceOverflow(
        $database,
        3,
        4,
        5,
        0,
        999,
        static fn (): array => [],
        str_repeat('replacement-current-next:', 42),
    ));
};

$tests['btree freeblock rebalance cell overflow current next rejects no append when replacement needs extra page'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan::tableDeleteRebalanceThenReplaceOverflow(
        $database,
        3,
        4,
        5,
        0,
        10,
        static fn (): array => [7, 8],
        str_repeat('replacement-current-next:', 90),
        true,
        false,
    ));
};

return $tests;
