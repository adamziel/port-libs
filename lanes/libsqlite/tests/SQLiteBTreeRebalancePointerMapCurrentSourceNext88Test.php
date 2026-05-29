<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeRebalancePointerMapCurrentSourceNextPlan;
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

    $deletedValues = ['no', '_transient_current_source_next88', str_repeat('deleted-index-rebalance-next88:', 64), 10];
    $deleted = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($deletedValues), 7, 512);
    $pages[3] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_alpha_next88', 30]), leftChildPage: 4),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_divider_next88', 800]), leftChildPage: 5),
    ], 6);
    $pages[4] = SQLiteIndexLeafPage::assemble([
        $deleted['cell'],
        SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_keep_next88', 20])),
    ]);
    $pages[5] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_beta_next88', 40])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_gamma_next88', 50])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_omega_next88', 60])),
    ]);
    $pages[6] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_next88', 900])),
    ]);
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
    $plan = SQLiteBTreeRebalancePointerMapCurrentSourceNextPlan::indexDeleteRebalanceCurrentSource(
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

$transitionFor = static function (array $fx, int $pageNumber): array {
    foreach ($fx[1]->pointerMapTransitions as $transition) {
        if ($transition['page_number'] === $pageNumber) {
            return $transition;
        }
    }

    throw new RuntimeException('missing transition');
};

$cases = [
    'plan class' => static fn (array $fx): mixed => get_class($fx[1]),
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'parent page' => static fn (array $fx): mixed => $fx[1]->toArray()['parent_page'],
    'left page' => static fn (array $fx): mixed => $fx[1]->toArray()['left_page'],
    'right page' => static fn (array $fx): mixed => $fx[1]->toArray()['right_page'],
    'divider index' => static fn (array $fx): mixed => $fx[1]->toArray()['divider_index'],
    'obsolete overflow pages' => static fn (array $fx): mixed => $fx[1]->toArray()['obsolete_overflow_pages'],
    'freed pages' => static fn (array $fx): mixed => $fx[1]->toArray()['freed_pages'],
    'updated pages' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers(),
    'updated pointer map pages' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'changed pointer map pages' => static fn (array $fx): mixed => $fx[1]->changedPointerMapPageNumbers(),
    'transition page numbers' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'page_number'),
    'transition current types' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'current_type_name'),
    'transition delete types' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'after_delete_type_name'),
    'transition rebalance types' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'after_rebalance_type_name'),
    'transition next types' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'next_type_name'),
    'transition current parents' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'current_parent_page_number'),
    'transition next parents' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'next_parent_page_number'),
    'left page current type' => static fn (array $fx): mixed => $transitionFor($fx, 4)['current_type_name'],
    'left page delete type' => static fn (array $fx): mixed => $transitionFor($fx, 4)['after_delete_type_name'],
    'left page rebalance changed' => static fn (array $fx): mixed => $transitionFor($fx, 4)['changed_by_rebalance'],
    'right page rebalance changed' => static fn (array $fx): mixed => $transitionFor($fx, 5)['changed_by_rebalance'],
    'overflow page 7 current type' => static fn (array $fx): mixed => $transitionFor($fx, 7)['current_type_name'],
    'overflow page 7 next type' => static fn (array $fx): mixed => $transitionFor($fx, 7)['next_type_name'],
    'overflow page 7 release changed' => static fn (array $fx): mixed => $transitionFor($fx, 7)['changed_by_freelist_release'],
    'overflow page 8 current parent' => static fn (array $fx): mixed => $transitionFor($fx, 8)['current_parent_page_number'],
    'overflow page 8 next parent' => static fn (array $fx): mixed => $transitionFor($fx, 8)['next_parent_page_number'],
    'overflow page 9 current parent' => static fn (array $fx): mixed => $transitionFor($fx, 9)['current_parent_page_number'],
    'overflow page 9 next parent' => static fn (array $fx): mixed => $transitionFor($fx, 9)['next_parent_page_number'],
    'overflow page 10 current parent' => static fn (array $fx): mixed => $transitionFor($fx, 10)['current_parent_page_number'],
    'overflow page 10 next parent' => static fn (array $fx): mixed => $transitionFor($fx, 10)['next_parent_page_number'],
    'delete database keeps pointer map source page 7' => static fn (array $fx): mixed => $fx[1]->deleteDatabase->pointerMapEntryForPage(7)->typeName(),
    'rebalance database keeps pointer map source page 7' => static fn (array $fx): mixed => $fx[1]->rebalanceDatabase->pointerMapEntryForPage(7)->typeName(),
    'next database frees page 7' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pointerMapEntryForPage(7)->typeName(),
    'next database frees page 10' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pointerMapEntryForPage(10)->typeName(),
    'next database freelist pages' => static fn (array $fx): mixed => $fx[1]->nextDatabase->freelistPageNumbers(),
    'next database allocation order' => static fn (array $fx): mixed => $fx[1]->nextDatabase->freelistAllocationOrder(),
    'next header freelist count' => static fn (array $fx): mixed => $fx[1]->nextDatabase->header->freelistPageCount,
    'next first trunk page' => static fn (array $fx): mixed => $fx[1]->nextDatabase->header->firstFreelistTrunkPage,
    'left records after next' => static fn (array $fx): mixed => $recordsFor($fx[1]->nextDatabase, 4),
    'right records after next' => static fn (array $fx): mixed => $recordsFor($fx[1]->nextDatabase, 5),
    'summary nested action' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance_freelist']['action'],
    'summary moved cell count' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance_freelist']['moved_cell_count'],
    'summary parent divider' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance_freelist']['updated_parent_divider']['record_values'],
    'summary freed pointer map pages' => static fn (array $fx): mixed => array_column($fx[1]->toArray()['rebalance_freelist']['freed_pointer_map_entries'], 'page_number'),
    'summary freed pointer map types' => static fn (array $fx): mixed => array_column($fx[1]->toArray()['rebalance_freelist']['freed_pointer_map_entries'], 'type_name'),
    'summary secure delete pages' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance_freelist']['secure_delete_cleared_pages'],
    'source database remains unchanged page 7' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(7)->typeName(),
    'source database page count' => static fn (array $fx): mixed => $fx[0]->pageCount(),
    'next database page count' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pageCount(),
    'deleted key carried' => static fn (array $fx): mixed => $fx[2][1],
];

$expected = [
    'plan class' => SQLiteBTreeRebalancePointerMapCurrentSourceNextPlan::class,
    'action label' => 'btree-rebalance-pointermap-current-source-next88',
    'parent page' => 3,
    'left page' => 4,
    'right page' => 5,
    'divider index' => 0,
    'obsolete overflow pages' => [7, 8, 9, 10],
    'freed pages' => [7, 8, 9, 10],
    'updated pages' => [1, 2, 3, 4, 5, 7, 8, 9, 10, 11],
    'updated pointer map pages' => [2],
    'changed pointer map pages' => [7, 8, 9, 10],
    'transition page numbers' => [3, 4, 5, 7, 8, 9, 10],
    'transition current types' => ['root-page', 'btree-page', 'btree-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'transition delete types' => ['root-page', 'btree-page', 'btree-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'transition rebalance types' => ['root-page', 'btree-page', 'btree-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'transition next types' => ['root-page', 'btree-page', 'btree-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'transition current parents' => [0, 3, 3, 4, 7, 8, 9],
    'transition next parents' => [0, 3, 3, 0, 0, 0, 0],
    'left page current type' => 'btree-page',
    'left page delete type' => 'btree-page',
    'left page rebalance changed' => false,
    'right page rebalance changed' => false,
    'overflow page 7 current type' => 'first-overflow-page',
    'overflow page 7 next type' => 'free-page',
    'overflow page 7 release changed' => true,
    'overflow page 8 current parent' => 7,
    'overflow page 8 next parent' => 0,
    'overflow page 9 current parent' => 8,
    'overflow page 9 next parent' => 0,
    'overflow page 10 current parent' => 9,
    'overflow page 10 next parent' => 0,
    'delete database keeps pointer map source page 7' => 'first-overflow-page',
    'rebalance database keeps pointer map source page 7' => 'first-overflow-page',
    'next database frees page 7' => 'free-page',
    'next database frees page 10' => 'free-page',
    'next database freelist pages' => [11, 7, 8, 9, 10],
    'next database allocation order' => [7, 10, 9, 8, 11],
    'next header freelist count' => 5,
    'next first trunk page' => 11,
    'left records after next' => [
        ['no', '_transient_keep_next88', 20],
        ['yes', 'autoload_alpha_next88', 30],
    ],
    'right records after next' => [
        ['yes', 'autoload_gamma_next88', 50],
        ['yes', 'autoload_omega_next88', 60],
    ],
    'summary nested action' => 'btree-index-overflow-rebalance-freelist-current-source-next82',
    'summary moved cell count' => 1,
    'summary parent divider' => ['yes', 'autoload_beta_next88', 40],
    'summary freed pointer map pages' => [7, 8, 9, 10],
    'summary freed pointer map types' => ['free-page', 'free-page', 'free-page', 'free-page'],
    'summary secure delete pages' => [7, 8, 9, 10],
    'source database remains unchanged page 7' => 'first-overflow-page',
    'source database page count' => 12,
    'next database page count' => 12,
    'deleted key carried' => '_transient_current_source_next88',
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree rebalance pointermap current source next88 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

for ($index = 0; $index < 28; $index++) {
    $tests['btree rebalance pointermap current source next88 generated transition invariant ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($fixture, $index): void {
        [, $plan] = $fixture((bool) ($index % 2));
        $page = [7, 8, 9, 10][$index % 4];
        $transition = null;
        foreach ($plan->pointerMapTransitions as $row) {
            if ($row['page_number'] === $page) {
                $transition = $row;
                break;
            }
        }

        $t->same($page, $transition['page_number']);
        $t->same('free-page', $transition['next_type_name']);
        $t->same(0, $transition['next_parent_page_number']);
        $t->same(true, $transition['changed_by_freelist_release']);
    };
}

$tests['btree rebalance pointermap current source next88 can skip secure delete clearing'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan] = $fixture(false);

    $t->same([], $plan->toArray()['rebalance_freelist']['secure_delete_cleared_pages']);
    $t->same([7, 8, 9, 10], $plan->changedPointerMapPageNumbers());
};

$tests['btree rebalance pointermap current source next88 rejects non autovacuum image'] = static function (TestRunner $t) use ($fixture): void {
    [$database, , $deletedValues] = $fixture();
    $first = $database->page(1);
    $first = substr_replace($first, pack('N', 0), 52, 4);
    $first = substr_replace($first, pack('N', 0), 56, 4);
    $pages = [$first];
    for ($pageNumber = 2; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pages[] = $database->page($pageNumber);
    }
    $nonAutoVacuum = SQLiteDatabase::fromBytes(implode('', $pages));

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeRebalancePointerMapCurrentSourceNextPlan::indexDeleteRebalanceCurrentSource(
        $nonAutoVacuum,
        3,
        4,
        5,
        0,
        $deletedValues,
        static fn (): array => [7, 8, 9, 10],
    ));
};

$tests['btree rebalance pointermap current source next88 rejects missing overflow reader'] = static function (TestRunner $t) use ($fixture): void {
    [$database, , $deletedValues] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeRebalancePointerMapCurrentSourceNextPlan::indexDeleteRebalanceCurrentSource(
        $database,
        3,
        4,
        5,
        0,
        $deletedValues,
        static fn (): array => [7, 8],
    ));
};

return $tests;
