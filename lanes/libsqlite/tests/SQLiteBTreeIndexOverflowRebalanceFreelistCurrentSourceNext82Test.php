<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
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
    $pages[2] = substr_replace(
        $pages[2],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
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

    $deletedValues = ['no', '_transient_current_source_next82', str_repeat('deleted-index-overflow:', 80), 10];
    $deletedPayload = SQLiteRecord::encode($deletedValues);
    $deleted = SQLiteIndexCell::encodeWithOverflowPages($deletedPayload, 7, 512);
    $leftLeaf = SQLiteIndexLeafPage::assemble([
        $deleted['cell'],
        SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_keep_next82', 20])),
    ]);
    $rightLeaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_beta_next82', 40])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_gamma_next82', 50])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_omega_next82', 60])),
    ]);
    $tailLeaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_next82', 900])),
    ]);
    $parent = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_alpha_next82', 30]), leftChildPage: 4),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_divider_next82', 800]), leftChildPage: 5),
    ], 6);

    $pages[3] = $parent;
    $pages[4] = $leftLeaf;
    $pages[5] = $rightLeaf;
    $pages[6] = $tailLeaf;
    $overflowPages = array_combine(range(7, 6 + count($deleted['overflowPages'])), $deleted['overflowPages']);
    foreach ($overflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
        10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
        11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parentPageNumber]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parentPageNumber);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan::deleteFromLeftAndRebalanceRight(
        $database,
        3,
        4,
        5,
        0,
        $deletedValues,
        $overflowNumbersFor($overflowPages),
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

$parentRecordsFor = static function (SQLiteDatabase $database): array {
    $page = $database->page(3);
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => ['leftChild' => $cell->leftChildPage, 'values' => $cell->record()->values],
        SQLiteIndexCell::parsePageCells($page, $header, $database->usablePageSize()),
    );
};

$cases = [
    'plan class' => static fn (array $fx): mixed => get_class($fx[1]),
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'obsolete overflow pages' => static fn (array $fx): mixed => $fx[1]->obsoleteOverflowPageNumbers,
    'free plan freed pages' => static fn (array $fx): mixed => $fx[1]->freePlan->freedPageNumbers,
    'free plan leaf pages' => static fn (array $fx): mixed => $fx[1]->freePlan->leafPageNumbers,
    'free plan first trunk' => static fn (array $fx): mixed => $fx[1]->freePlan->firstFreelistTrunkPage,
    'free plan count' => static fn (array $fx): mixed => $fx[1]->freePlan->freelistPageCount,
    'free plan new trunks' => static fn (array $fx): mixed => $fx[1]->freePlan->newTrunkPageNumbers,
    'secure delete cleared leaf overflow' => static fn (array $fx): mixed => $fx[1]->freePlan->clearedPageNumbers,
    'updated page numbers' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers(),
    'updated pointer map pages' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'freed pointer map entry pages' => static fn (array $fx): mixed => array_column($fx[1]->freePlan->freedPointerMapEntries, 'page_number'),
    'freed pointer map entry types' => static fn (array $fx): mixed => array_column($fx[1]->freePlan->freedPointerMapEntries, 'type_name'),
    'freed pointer map entry parents' => static fn (array $fx): mixed => array_column($fx[1]->freePlan->freedPointerMapEntries, 'parent_page_number'),
    'rebalance moved one cell' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->toArray()['moved_cell_count'],
    'rebalance updated pages' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->updatedPageNumbers(),
    'after rebalance cells' => static fn (array $fx): mixed => $fx[1]->toArray()['after_rebalance_cells'],
    'updated parent divider' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_parent_divider']['record_values'],
    'left records after apply' => static fn (array $fx): mixed => $recordsFor($fx[1]->database, 4),
    'right records after apply' => static fn (array $fx): mixed => $recordsFor($fx[1]->database, 5),
    'parent records after apply' => static fn (array $fx): mixed => $parentRecordsFor($fx[1]->database),
    'post header first trunk' => static fn (array $fx): mixed => $fx[1]->database->header->firstFreelistTrunkPage,
    'post header freelist count' => static fn (array $fx): mixed => $fx[1]->database->header->freelistPageCount,
    'post freelist pages' => static fn (array $fx): mixed => $fx[1]->database->freelistPageNumbers(),
    'post allocation order' => static fn (array $fx): mixed => $fx[1]->database->freelistAllocationOrder(),
    'post pointer map page 7 type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(7)->typeName(),
    'post pointer map page 8 type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(8)->typeName(),
    'post pointer map page 4 parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(4)->parentPageNumber,
    'summary freed pages' => static fn (array $fx): mixed => $fx[1]->toArray()['freed_pages'],
    'summary secure delete pages' => static fn (array $fx): mixed => $fx[1]->toArray()['secure_delete_cleared_pages'],
    'summary nested release pages' => static fn (array $fx): mixed => $fx[1]->toArray()['freelist_release']['freed_page_numbers'],
    'summary nested rebalance action' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance']['action'],
    'database page count unchanged' => static fn (array $fx): mixed => $fx[1]->database->pageCount(),
    'deleted values carried by fixture' => static fn (array $fx): mixed => $fx[2][1],
];

$expected = [
    'plan class' => SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan::class,
    'action label' => 'btree-index-overflow-rebalance-freelist-current-source-next82',
    'obsolete overflow pages' => [7, 8, 9, 10],
    'free plan freed pages' => [7, 8, 9, 10],
    'free plan leaf pages' => [7, 8, 9, 10],
    'free plan first trunk' => 11,
    'free plan count' => 5,
    'free plan new trunks' => [],
    'secure delete cleared leaf overflow' => [7, 8, 9, 10],
    'updated page numbers' => [1, 2, 3, 4, 5, 7, 8, 9, 10, 11],
    'updated pointer map pages' => [2],
    'freed pointer map entry pages' => [7, 8, 9, 10],
    'freed pointer map entry types' => ['free-page', 'free-page', 'free-page', 'free-page'],
    'freed pointer map entry parents' => [0, 0, 0, 0],
    'rebalance moved one cell' => 1,
    'rebalance updated pages' => [3, 4, 5],
    'after rebalance cells' => ['left' => 2, 'right' => 2],
    'updated parent divider' => ['yes', 'autoload_beta_next82', 40],
    'left records after apply' => [
        ['no', '_transient_keep_next82', 20],
        ['yes', 'autoload_alpha_next82', 30],
    ],
    'right records after apply' => [
        ['yes', 'autoload_gamma_next82', 50],
        ['yes', 'autoload_omega_next82', 60],
    ],
    'parent records after apply' => [
        ['leftChild' => 4, 'values' => ['yes', 'autoload_beta_next82', 40]],
        ['leftChild' => 5, 'values' => ['z', 'tail_divider_next82', 800]],
    ],
    'post header first trunk' => 11,
    'post header freelist count' => 5,
    'post freelist pages' => [11, 7, 8, 9, 10],
    'post allocation order' => [7, 10, 9, 8, 11],
    'post pointer map page 7 type' => 'free-page',
    'post pointer map page 8 type' => 'free-page',
    'post pointer map page 4 parent' => 3,
    'summary freed pages' => [7, 8, 9, 10],
    'summary secure delete pages' => [7, 8, 9, 10],
    'summary nested release pages' => [7, 8, 9, 10],
    'summary nested rebalance action' => 'index-leaf-balance-apply',
    'database page count unchanged' => 12,
    'deleted values carried by fixture' => '_transient_current_source_next82',
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree index overflow rebalance freelist current source next82 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

for ($index = 0; $index < 24; $index++) {
    $tests['btree index overflow rebalance freelist current source next82 generated invariant ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($fixture, $index): void {
        [, $plan] = $fixture((bool) ($index % 2));
        $entry = $plan->freePlan->freedPointerMapEntries[$index % 4];

        $t->same([7, 8, 9, 10][$index % 4], $entry['page_number']);
        $t->same('free-page', $entry['type_name']);
        $t->same(0, $entry['parent_page_number']);
        $t->same([4, 5], array_keys(array_intersect_key($plan->pageImages, [4 => true, 5 => true])));
    };
}

$tests['btree index overflow rebalance freelist current source next82 can skip secure-delete clearing'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan] = $fixture(false);

    $t->same([], $plan->freePlan->clearedPageNumbers);
    $t->same([7, 8, 9, 10], $plan->obsoleteOverflowPageNumbers);
};

$tests['btree index overflow rebalance freelist current source next82 rejects missing overflow reader'] = static function (TestRunner $t) use ($fixture): void {
    [$database, , $deletedValues] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan::deleteFromLeftAndRebalanceRight(
        $database,
        3,
        4,
        5,
        0,
        $deletedValues,
        static fn (): array => [7, 8],
    ));
};

$tests['btree index overflow rebalance freelist current source next82 rejects absent index key'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan::deleteFromLeftAndRebalanceRight(
        $database,
        3,
        4,
        5,
        0,
        ['missing', 1],
        static fn (): array => [],
    ));
};

return $tests;
