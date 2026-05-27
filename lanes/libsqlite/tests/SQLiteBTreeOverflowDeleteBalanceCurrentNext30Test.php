<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeTableDeleteRebalancePlan;
use PortLibs\LibSqlite\SQLiteBTreeTableLeafBalanceApplyPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageSize = 512, int $databaseSizePages = 5): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$payload = static fn (string $name, string $value = 'yes'): string => SQLiteRecord::encode([null, $name, $value, 'yes']);
$makeDatabaseFromRowIds = static function (array $leftRowIds, array $rightRowIds, ?int $dividerKey = null) use ($makeFirstPage, $payload): array {
    $pageSize = 512;
    $leftCells = [];
    $rightCells = [];
    foreach ($leftRowIds as $rowId) {
        $leftCells[] = SQLiteTableLeafCell::encode($rowId, $payload('_transient_balance_' . $rowId), $pageSize);
    }
    foreach ($rightRowIds as $rowId) {
        $rightCells[] = SQLiteTableLeafCell::encode($rowId, $payload('autoload_balance_' . $rowId), $pageSize);
    }

    $leftLeaf = SQLiteTableLeafPage::assemble($leftCells, $pageSize);
    $rightLeaf = SQLiteTableLeafPage::assemble($rightCells, $pageSize);
    $tailLeaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(9000, $payload('tail_balance_9000'), $pageSize),
    ], $pageSize);
    $parent = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(3, $dividerKey ?? max($leftRowIds)),
        SQLiteTableInteriorCell::encode(4, 8999),
    ], 5, $pageSize);

    return [
        SQLiteDatabase::fromBytes($makeFirstPage($pageSize, 5) . $parent . $leftLeaf . $rightLeaf . $tailLeaf),
        [],
    ];
};

$makeOverflowDatabase = static function () use ($makeFirstPage, $payload): array {
    $pageSize = 512;
    $largePayload = SQLiteRecord::encode([null, '_transient_large_to_delete', str_repeat('delete-current-next30:', 62), 'no']);
    $large = SQLiteTableLeafCell::encodeWithOverflowPages(10, $largePayload, 6, $pageSize);
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
    $parent = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(3, 20),
        SQLiteTableInteriorCell::encode(4, 8999),
    ], 5, $pageSize);
    $tailLeaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(9000, $payload('tail_balance_9000'), $pageSize),
    ], $pageSize);
    $overflowPages = array_combine(range(6, 5 + count($large['overflowPages'])), $large['overflowPages']);

    $pages = [
        1 => $makeFirstPage($pageSize, 5 + count($large['overflowPages'])),
        2 => $parent,
        3 => $leftLeaf,
        4 => $rightLeaf,
        5 => $tailLeaf,
    ];
    foreach ($overflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    ksort($pages);

    return [SQLiteDatabase::fromBytes(implode('', $pages)), $overflowPages];
};

$withPageImages = static function (SQLiteDatabase $database, array $pageImages): SQLiteDatabase {
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$rowIdsFor = static function (SQLiteDatabase $database, int $pageNumber): array {
    $page = $database->page($pageNumber);
    $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize, $pageNumber === 1 ? 100 : 0);

    return array_map(
        static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
        SQLiteTableLeafCell::parsePageCells($page, $header, $database->usablePageSize(), static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount)),
    );
};

$parentKeysFor = static function (SQLiteDatabase $database): array {
    $header = SQLiteBTreePageHeader::parsePage($database->page(2), $database->header->pageSize);

    return array_map(
        static fn (SQLiteTableInteriorCell $cell): array => ['leftChild' => $cell->leftChildPage, 'key' => $cell->key],
        SQLiteTableInteriorCell::parsePageCells($database->page(2), $header),
    );
};

$overflowPageNumbers = static function (array $overflowPages, int $firstOverflowPage, int $byteCount): array {
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

$tests = [
    'applies table overflow delete then balances current leaf with next sibling' => static function (TestRunner $t) use ($makeOverflowDatabase, $withPageImages, $rowIdsFor, $parentKeysFor, $overflowPageNumbers): void {
        [$database, $overflowPages] = $makeOverflowDatabase();
        $plan = SQLiteBTreeTableDeleteRebalancePlan::deleteFromLeftAndRebalanceRight(
            $database,
            2,
            3,
            4,
            0,
            10,
            static fn (int $firstOverflowPage, int $byteCount): array => $overflowPageNumbers($overflowPages, $firstOverflowPage, $byteCount),
            true,
        );
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same(SQLiteBTreeTableDeleteRebalancePlan::class, get_class($plan));
        $t->same('table-delete-rebalance-apply', $summary['action']);
        $t->same(10, $summary['deleted_rowid']);
        $t->same([6, 7], $summary['obsolete_overflow_pages']);
        $t->same(2, $summary['before_left_cell_count']);
        $t->same(1, $summary['after_delete_left_cell_count']);
        $t->same(['left' => 3, 'right' => 2], $summary['after_rebalance_cells']);
        $t->same(2, $summary['moved_cell_count']);
        $t->same([2, 3, 4], $summary['updated_page_numbers']);
        $t->same([20, 30, 40], $rowIdsFor($postDatabase, 3));
        $t->same([50, 60], $rowIdsFor($postDatabase, 4));
        $t->same([
            ['leftChild' => 3, 'key' => 40],
            ['leftChild' => 4, 'key' => 8999],
        ], $parentKeysFor($postDatabase));
        $t->same($database->page(1), $postDatabase->page(1));
        $t->same($database->page(5), $postDatabase->page(5));
        $t->true($summary['deleted_payload_bytes'] > 0);
    },
    'applies table leaf balance without delete for current next sibling pages' => static function (TestRunner $t) use ($makeDatabaseFromRowIds, $withPageImages, $rowIdsFor, $parentKeysFor): void {
        [$database] = $makeDatabaseFromRowIds([10], [20, 30, 40], 10);
        $plan = SQLiteBTreeTableLeafBalanceApplyPlan::apply($database, 2, 3, 4, 0);
        $postDatabase = $withPageImages($database, $plan->pageImages);

        $t->same(SQLiteBTreeTableLeafBalanceApplyPlan::class, get_class($plan));
        $t->same('table-leaf-balance-apply', $plan->toArray()['action']);
        $t->same([10, 20], $rowIdsFor($postDatabase, 3));
        $t->same([30, 40], $rowIdsFor($postDatabase, 4));
        $t->same(20, $parentKeysFor($postDatabase)[0]['key']);
        $t->same(['left' => 2, 'right' => 2], $plan->toArray()['after_cells']);
    },
    'rejects table delete balance when row is absent or the current leaf becomes empty' => static function (TestRunner $t) use ($makeDatabaseFromRowIds): void {
        [$database] = $makeDatabaseFromRowIds([10, 20], [30, 40, 50], 20);
        [$single] = $makeDatabaseFromRowIds([10], [30, 40, 50], 10);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeTableDeleteRebalancePlan::deleteFromLeftAndRebalanceRight($database, 2, 3, 4, 0, 999, static fn (): array => []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeTableDeleteRebalancePlan::deleteFromLeftAndRebalanceRight($single, 2, 3, 4, 0, 10, static fn (): array => []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeTableDeleteRebalancePlan::deleteFromLeftAndRebalanceRight($database, 2, 3, 4, 2, 10, static fn (): array => []));
    },
    'rejects table leaf balance when siblings are not adjacent under divider' => static function (TestRunner $t) use ($makeDatabaseFromRowIds): void {
        [$database] = $makeDatabaseFromRowIds([10], [20, 30, 40], 10);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeTableLeafBalanceApplyPlan::apply($database, 2, 4, 3, 0));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeTableLeafBalanceApplyPlan::apply($database, 2, 3, 3, 0));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeTableLeafBalanceApplyPlan::apply($database, 3, 3, 4, 0));
    },
];

$variants = [
    [[11, 12], [21, 22, 23], 12, [11, 12, 21], [22, 23], 21],
    [[101, 102], [201, 202, 203, 204], 102, [101, 102, 201], [202, 203, 204], 201],
    [[301, 302, 303], [401, 402, 403], 303, [301, 302, 303], [401, 402, 403], 303],
    [[501, 502, 503], [601, 602, 603, 604], 503, [501, 502, 503, 601], [602, 603, 604], 601],
];
foreach ($variants as $index => [$left, $right, $divider, $expectedLeft, $expectedRight, $expectedDivider]) {
    $tests['applies generated table current next balance variant ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($makeDatabaseFromRowIds, $withPageImages, $rowIdsFor, $parentKeysFor, $left, $right, $divider, $expectedLeft, $expectedRight, $expectedDivider): void {
        [$database] = $makeDatabaseFromRowIds($left, $right, $divider);
        $plan = SQLiteBTreeTableLeafBalanceApplyPlan::apply($database, 2, 3, 4, 0);
        $postDatabase = $withPageImages($database, $plan->pageImages);

        $t->same($expectedLeft, $rowIdsFor($postDatabase, 3));
        $t->same($expectedRight, $rowIdsFor($postDatabase, 4));
        $t->same($expectedDivider, $parentKeysFor($postDatabase)[0]['key']);
        $t->same([2, 3, 4], $plan->updatedPageNumbers());
    };
}

for ($index = 0; $index < 42; $index++) {
    $base = 1000 + ($index * 10);
    $left = [$base + 1, $base + 2];
    $right = [$base + 3, $base + 4, $base + 5 + ($index % 2)];
    $tests['applies generated table delete current next overflow-free case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($base, $left, $right, $makeDatabaseFromRowIds, $withPageImages, $rowIdsFor, $parentKeysFor): void {
        [$database] = $makeDatabaseFromRowIds($left, $right, $base + 2);
        $plan = SQLiteBTreeTableDeleteRebalancePlan::deleteFromLeftAndRebalanceRight(
            $database,
            2,
            3,
            4,
            0,
            $base + 1,
            static fn (): array => [],
        );
        $postDatabase = $withPageImages($database, $plan->pageImages);

        $t->same($base + 1, $plan->deletedRowId);
        $t->same([$base + 2, $base + 3], $rowIdsFor($postDatabase, 3));
        $t->same([$base + 4, $base + 5 + ($base / 10 % 2)], $rowIdsFor($postDatabase, 4));
        $t->same($base + 3, $parentKeysFor($postDatabase)[0]['key']);
        $t->same(1, $plan->toArray()['moved_cell_count']);
    };
}

return $tests;
