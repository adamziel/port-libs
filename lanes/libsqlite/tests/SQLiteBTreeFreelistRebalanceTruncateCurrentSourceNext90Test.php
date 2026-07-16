<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistRebalanceTruncateCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

$makeFirstPage90 = static function (int $pageSize, int $pageCount): string {
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
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry90 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$overflowReaderFor90 = static function (array $overflowPages): callable {
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

$overflowNumbersFor90 = static function (array $overflowPages): callable {
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

$fixture90 = static function (int $maxTruncatedPages = 4, bool $secureDelete = true) use (
    $makeFirstPage90,
    $putPointerMapEntry90,
    $overflowReaderFor90,
    $overflowNumbersFor90,
): array {
    $pageSize = 512;
    $pageCount = 412;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage90($pageSize, $pageCount);

    $deletedValues = ['no', '_transient_rebalance_truncate_next90', str_repeat('deleted-next90:', 220), 701];
    $deleted = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($deletedValues), 406, $pageSize);
    $pages[3] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_alpha_next90', 10]), leftChildPage: 4),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_divider_next90', 900]), leftChildPage: 5),
    ], 6);
    $pages[4] = SQLiteIndexLeafPage::assemble([
        $deleted['cell'],
        SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_keep_next90', 20])),
    ]);
    $pages[5] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_beta_next90', 40])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_gamma_next90', 50])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_omega_next90', 60])),
    ]);
    $pages[6] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_next90', 900])),
    ]);

    $overflowPages = array_combine(range(406, 405 + count($deleted['overflowPages'])), $deleted['overflowPages']);
    foreach ($overflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 4 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 5 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 6 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry90($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach (array_keys($overflowPages) as $index => $pageNumber) {
        $putPointerMapEntry90(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 4 : $pageNumber - 1,
            $pageSize,
        );
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreeFreelistRebalanceTruncateCurrentSourceNextPlan::indexDeleteRebalanceAndTruncate(
        $database,
        3,
        4,
        5,
        0,
        $deletedValues,
        $overflowNumbersFor90($overflowPages),
        $maxTruncatedPages,
        $secureDelete,
        $overflowReaderFor90($overflowPages),
    );

    return [$database, $plan, $deletedValues];
};

$indexRecords90 = static function (SQLiteDatabase $database, int $pageNumber): array {
    $page = $database->page($pageNumber);
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header, $database->usablePageSize()),
    );
};

$rowFor90 = static function (SQLiteBTreeFreelistRebalanceTruncateCurrentSourceNextPlan $plan, int $pageNumber): array {
    foreach ($plan->overflowRebalanceTruncateRows() as $row) {
        if ($row['page_number'] === $pageNumber) {
            return $row;
        }
    }

    throw new RuntimeException('missing next90 row');
};

$cases = [
    'plan class' => static fn (array $fx): mixed => get_class($fx[1]),
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'nested rebalance action' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance_action'],
    'obsolete overflow pages' => static fn (array $fx): mixed => $fx[1]->obsoleteOverflowPageNumbers(),
    'truncated page numbers' => static fn (array $fx): mixed => $fx[1]->truncatedPageNumbers(),
    'surviving freelist pages' => static fn (array $fx): mixed => $fx[1]->survivingFreelistPageNumbers(),
    'source page count' => static fn (array $fx): mixed => $fx[1]->toArray()['source_database_page_count'],
    'released page count' => static fn (array $fx): mixed => $fx[1]->toArray()['released_database_page_count'],
    'next page count' => static fn (array $fx): mixed => $fx[1]->toArray()['next_database_page_count'],
    'released freelist count' => static fn (array $fx): mixed => $fx[1]->toArray()['released_freelist_page_count'],
    'next freelist count' => static fn (array $fx): mixed => $fx[1]->toArray()['next_freelist_page_count'],
    'next first trunk page' => static fn (array $fx): mixed => $fx[1]->nextDatabase->header->firstFreelistTrunkPage,
    'next freelist pages' => static fn (array $fx): mixed => $fx[1]->nextDatabase->freelistPageNumbers(),
    'row page numbers' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'page_number'),
    'row current statuses' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[1]->overflowRebalanceTruncateRows(), 'current_status'))),
    'row rebalance statuses' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[1]->overflowRebalanceTruncateRows(), 'after_rebalance_status'))),
    'row next statuses' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'next_status'),
    'row materialized flags' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'materialized'),
    'row truncated flags' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'truncated'),
    'source pointer map types' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'current_pointer_map_type'),
    'released pointer map types' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'after_rebalance_pointer_map_type'),
    'next pointer map types' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'next_pointer_map_type'),
    'truncated pointer map types' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'truncated_pointer_map_type'),
    'source overflow next pages' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'current_next_page'),
    'released overflow next pages' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'after_rebalance_next_page'),
    'next overflow next pages' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'next_next_page'),
    'secure delete flags' => static fn (array $fx): mixed => array_column($fx[1]->overflowRebalanceTruncateRows(), 'secure_deleted'),
    'updated page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_page_numbers'],
    'materialized omitted pages' => static fn (array $fx): mixed => $fx[1]->materializedApplySummary()['omitted_truncated_page_numbers'],
    'materialized byte length' => static fn (array $fx): mixed => strlen($fx[1]->materializedBytes()),
    'left records after next' => static fn (array $fx): mixed => $indexRecords90($fx[1]->nextDatabase, 4),
    'right records after next' => static fn (array $fx): mixed => $indexRecords90($fx[1]->nextDatabase, 5),
    'parent divider after rebalance' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance_freelist']['updated_parent_divider']['record_values'],
    'moved cell count' => static fn (array $fx): mixed => $fx[1]->toArray()['rebalance_freelist']['moved_cell_count'],
    'truncate boundary entry page' => static fn (array $fx): mixed => $fx[1]->toArray()['truncate_plan']['boundary_pointer_map_entry']['page_number'] ?? null,
    'deleted key carried' => static fn (array $fx): mixed => $fx[2][1],
];

$expected = [
    'plan class' => SQLiteBTreeFreelistRebalanceTruncateCurrentSourceNextPlan::class,
    'action label' => 'btree-freelist-rebalance-truncate-current-source-next90',
    'nested rebalance action' => 'btree-index-overflow-rebalance-freelist-current-source-next82',
    'obsolete overflow pages' => [406, 407, 408, 409, 410, 411, 412],
    'truncated page numbers' => [412, 411, 410, 409],
    'surviving freelist pages' => [406, 407, 408],
    'source page count' => 412,
    'released page count' => 412,
    'next page count' => 408,
    'released freelist count' => 7,
    'next freelist count' => 3,
    'next first trunk page' => 406,
    'next freelist pages' => [406, 407, 408],
    'row page numbers' => [406, 407, 408, 409, 410, 411, 412],
    'row current statuses' => ['obsolete-overflow'],
    'row rebalance statuses' => ['freelist-page'],
    'row next statuses' => [
        'survives-as-freelist-page',
        'survives-as-freelist-page',
        'survives-as-freelist-page',
        'truncated-from-database',
        'truncated-from-database',
        'truncated-from-database',
        'truncated-from-database',
    ],
    'row materialized flags' => [true, true, true, false, false, false, false],
    'row truncated flags' => [false, false, false, true, true, true, true],
    'source pointer map types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'released pointer map types' => ['free-page', 'free-page', 'free-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'next pointer map types' => ['free-page', 'free-page', 'free-page', null, null, null, null],
    'truncated pointer map types' => [null, null, null, 'free-page', 'free-page', 'free-page', 'free-page'],
    'source overflow next pages' => [407, 408, 409, 410, 411, 412, 0],
    'released overflow next pages' => [0, 0, 0, 0, 0, 0, 0],
    'next overflow next pages' => [0, 0, 0, null, null, null, null],
    'secure delete flags' => [false, true, true, false, false, false, false],
    'updated page numbers' => [1, 3, 4, 5, 311, 406, 407, 408],
    'materialized omitted pages' => [412, 411, 410, 409],
    'materialized byte length' => 408 * 512,
    'left records after next' => [
        ['no', '_transient_keep_next90', 20],
        ['yes', 'autoload_alpha_next90', 10],
    ],
    'right records after next' => [
        ['yes', 'autoload_gamma_next90', 50],
        ['yes', 'autoload_omega_next90', 60],
    ],
    'parent divider after rebalance' => ['yes', 'autoload_beta_next90', 40],
    'moved cell count' => 1,
    'truncate boundary entry page' => 408,
    'deleted key carried' => '_transient_rebalance_truncate_next90',
];

$tests = [];

foreach ($cases as $name => $callback) {
    $tests['btree freelist rebalance truncate current source next90 ' . $name] = static function (TestRunner $t) use ($fixture90, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture90()));
    };
}

foreach (range(1, 30) as $index) {
    $tests['btree freelist rebalance truncate current source next90 invariant ' . $index] = static function (TestRunner $t) use ($fixture90, $rowFor90, $index): void {
        $limit = $index % 2 === 0 ? 4 : 5;
        $secureDelete = $index % 3 !== 0;
        [, $plan] = $fixture90($limit, $secureDelete);
        $rows = $plan->overflowRebalanceTruncateRows();
        $survivors = array_values(array_filter($rows, static fn (array $row): bool => $row['materialized']));
        $truncated = array_values(array_filter($rows, static fn (array $row): bool => $row['truncated']));

        $t->same(array_column($survivors, 'page_number'), $plan->survivingFreelistPageNumbers());
        $truncatedPages = array_column($truncated, 'page_number');
        sort($truncatedPages);
        $planTruncatedPages = $plan->truncatedPageNumbers();
        sort($planTruncatedPages);

        $t->same($truncatedPages, $planTruncatedPages);
        $t->same(max(array_column($survivors, 'page_number')), $plan->nextDatabase->pageCount());
        $t->same($plan->nextDatabase->pageCount() * 512, strlen($plan->materializedBytes()));
        $t->same($plan->nextDatabase->freelistPageNumbers(), $plan->materializedApplySummary()['freelist_page_numbers']);
        $t->same('free-page', $rowFor90($plan, 406)['next_pointer_map_type']);
        $t->same(null, $rowFor90($plan, 412)['next_pointer_map_type']);
        $t->same($secureDelete, $rowFor90($plan, 407)['secure_deleted']);
    };
}

$tests['btree freelist rebalance truncate current source next90 tail read is unavailable'] = static function (TestRunner $t) use ($fixture90): void {
    [, $plan] = $fixture90();
    try {
        $plan->nextDatabase->page(412);
    } catch (Throwable) {
        $t->same('unavailable', 'unavailable');
        return;
    }

    $t->same('unavailable', 'available');
};

$tests['btree freelist rebalance truncate current source next90 rejects invalid truncate limit'] = static function (TestRunner $t) use ($fixture90): void {
    try {
        $fixture90(0);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite btree freelist rebalance truncate current-source next90 requires a positive truncation limit', $exception->getMessage());
        return;
    }

    $t->same('exception', 'none');
};

return $tests;
