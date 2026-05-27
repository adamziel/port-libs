<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexLeafBalanceApplyPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;

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

$makeDatabase = static function () use ($makeFirstPage): SQLiteDatabase {
    $pageSize = 512;
    $leftLeaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_old', 7])),
    ], $pageSize);
    $rightLeaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_c', 13])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_d', 14])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_e', 15])),
    ], $pageSize);
    $trailingLeaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'zz_plugin', 90])),
    ], $pageSize);
    $parent = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_b', 12]), leftChildPage: 3),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_z', 80]), leftChildPage: 4),
    ], 5, $pageSize);

    return SQLiteDatabase::fromBytes($makeFirstPage($pageSize, 5) . $parent . $leftLeaf . $rightLeaf . $trailingLeaf);
};

$makeDatabaseFromValues = static function (array $leftValues, array $dividerValues, array $rightValues) use ($makeFirstPage): SQLiteDatabase {
    $pageSize = 512;
    $leftLeaf = SQLiteIndexLeafPage::assemble(array_map(
        static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values)),
        $leftValues,
    ), $pageSize);
    $rightLeaf = SQLiteIndexLeafPage::assemble(array_map(
        static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values)),
        $rightValues,
    ), $pageSize);
    $trailingLeaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail', 999])),
    ], $pageSize);
    $parent = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode($dividerValues), leftChildPage: 3),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'divider', 900]), leftChildPage: 4),
    ], 5, $pageSize);

    return SQLiteDatabase::fromBytes($makeFirstPage($pageSize, 5) . $parent . $leftLeaf . $rightLeaf . $trailingLeaf);
};

$recordsFor = static function (SQLiteDatabase $database, int $pageNumber): array {
    $page = $database->page($pageNumber);
    $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize, $pageNumber === 1 ? 100 : 0);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header, $database->usablePageSize()),
    );
};

$withPageImages = static function (SQLiteDatabase $database, array $pageImages): SQLiteDatabase {
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tests = [
    'applies sqlite current index leaf balance through parent divider rewrite' => static function (TestRunner $t) use ($makeDatabase, $recordsFor, $withPageImages): void {
        $database = $makeDatabase();
        $plan = SQLiteBTreeIndexLeafBalanceApplyPlan::apply($database, 2, 3, 4, 0);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $parentHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(2), 512);
        $parentCells = SQLiteIndexCell::parsePageCells($postDatabase->page(2), $parentHeader, $postDatabase->usablePageSize());
        $summary = $plan->toArray();

        $t->same(SQLiteBTreeIndexLeafBalanceApplyPlan::class, get_class($plan));
        $t->same(2, $plan->parentPageNumber);
        $t->same(3, $plan->leftPageNumber);
        $t->same(4, $plan->rightPageNumber);
        $t->same(0, $plan->dividerIndex);
        $t->same(1, $plan->beforeLeftCellCount);
        $t->same(3, $plan->beforeRightCellCount);
        $t->same([2, 3, 4], $plan->updatedPageNumbers());
        $t->same([2, 3, 4], array_keys($plan->pageImages));
        $t->same([
            ['no', '_transient_old', 7],
            ['yes', 'autoload_b', 12],
        ], $recordsFor($postDatabase, 3));
        $t->same([
            ['yes', 'autoload_d', 14],
            ['yes', 'autoload_e', 15],
        ], $recordsFor($postDatabase, 4));
        $t->same(2, $parentHeader->cellCount);
        $t->same(5, $parentHeader->rightMostPointer);
        $t->same([3, 4], array_map(static fn (SQLiteIndexCell $cell): ?int => $cell->leftChildPage, $parentCells));
        $t->same([
            ['yes', 'autoload_c', 13],
            ['yes', 'autoload_z', 80],
        ], array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, $parentCells));
        $t->same('index-leaf-balance-apply', $summary['action']);
        $t->same(2, $summary['parent_page']);
        $t->same(3, $summary['left_page']);
        $t->same(4, $summary['right_page']);
        $t->same(0, $summary['divider_index']);
        $t->same(['left' => 1, 'right' => 3], $summary['before_cells']);
        $t->same(['left' => 2, 'right' => 2], $summary['after_cells']);
        $t->same(1, $summary['moved_cell_count']);
        $t->same(['left_child' => 3, 'record_values' => ['yes', 'autoload_c', 13]], $summary['updated_parent_divider']);
        $t->same([2, 3, 4], $summary['updated_page_numbers']);
        $t->same([], $summary['removed_page_numbers']);
        $t->same(SQLiteHeader::parse($database->page(1))->databaseSizePages, SQLiteHeader::parse($postDatabase->page(1))->databaseSizePages);
        $t->same($database->page(5), $postDatabase->page(5));
    },
    'keeps sqlite index leaf balance scoped to the selected parent divider' => static function (TestRunner $t) use ($makeDatabase, $recordsFor, $withPageImages): void {
        $database = $makeDatabase();
        $plan = SQLiteBTreeIndexLeafBalanceApplyPlan::apply($database, 2, 3, 4, 0);
        $postDatabase = $withPageImages($database, $plan->pageImages);

        $t->same([['yes', 'zz_plugin', 90]], $recordsFor($postDatabase, 5));
        $t->same($database->page(1), $postDatabase->page(1));
        $t->same($database->page(5), $postDatabase->page(5));
        $t->same(['yes', 'autoload_c', 13], $plan->dividerEntry['values']);
        $t->same([['no', '_transient_old', 7], ['yes', 'autoload_b', 12]], array_map(static fn (array $entry): array => $entry['values'], $plan->leftEntries));
        $t->same([['yes', 'autoload_d', 14], ['yes', 'autoload_e', 15]], array_map(static fn (array $entry): array => $entry['values'], $plan->rightEntries));
    },
    'rejects corrupt sqlite index leaf balance inputs' => static function (TestRunner $t) use ($makeDatabase, $withPageImages): void {
        $database = $makeDatabase();
        $badParent = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['bad', 1])),
        ]);
        $badLeaf = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['bad', 1]), leftChildPage: 3),
        ], 4);
        $unorderedRight = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_d', 14])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_c', 13])),
        ]);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexLeafBalanceApplyPlan::apply($database, 2, 4, 3, 0));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexLeafBalanceApplyPlan::apply($database, 2, 3, 4, 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexLeafBalanceApplyPlan::apply($database, 9, 3, 4, 0));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexLeafBalanceApplyPlan::apply($withPageImages($database, [2 => $badParent]), 2, 3, 4, 0));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexLeafBalanceApplyPlan::apply($withPageImages($database, [4 => $badLeaf]), 2, 3, 4, 0));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexLeafBalanceApplyPlan::apply($withPageImages($database, [4 => $unorderedRight]), 2, 3, 4, 0));
    },
];

$balanceCases = [
    'single left borrows from three right records' => [
        'left' => [['a', 'k001', 1]],
        'divider' => ['a', 'k002', 2],
        'right' => [['a', 'k003', 3], ['a', 'k004', 4], ['a', 'k005', 5]],
        'afterLeft' => 2,
        'afterRight' => 2,
        'dividerAfter' => ['a', 'k003', 3],
    ],
    'single left borrows from four right records' => [
        'left' => [['a', 'k011', 11]],
        'divider' => ['a', 'k012', 12],
        'right' => [['a', 'k013', 13], ['a', 'k014', 14], ['a', 'k015', 15], ['a', 'k016', 16]],
        'afterLeft' => 3,
        'afterRight' => 2,
        'dividerAfter' => ['a', 'k014', 14],
    ],
    'two left balances against three right records' => [
        'left' => [['b', 'k021', 21], ['b', 'k022', 22]],
        'divider' => ['b', 'k023', 23],
        'right' => [['b', 'k024', 24], ['b', 'k025', 25], ['b', 'k026', 26]],
        'afterLeft' => 3,
        'afterRight' => 2,
        'dividerAfter' => ['b', 'k024', 24],
    ],
    'two left balances against four right records' => [
        'left' => [['b', 'k031', 31], ['b', 'k032', 32]],
        'divider' => ['b', 'k033', 33],
        'right' => [['b', 'k034', 34], ['b', 'k035', 35], ['b', 'k036', 36], ['b', 'k037', 37]],
        'afterLeft' => 3,
        'afterRight' => 3,
        'dividerAfter' => ['b', 'k034', 34],
    ],
    'null-leading keys remain ordered across divider rewrite' => [
        'left' => [[null, 'k041', 41]],
        'divider' => [null, 'k042', 42],
        'right' => [[null, 'k043', 43], ['c', 'k044', 44], ['c', 'k045', 45]],
        'afterLeft' => 2,
        'afterRight' => 2,
        'dividerAfter' => [null, 'k043', 43],
    ],
    'integer tie-breakers choose middle separator' => [
        'left' => [['d', 'same', 1]],
        'divider' => ['d', 'same', 2],
        'right' => [['d', 'same', 3], ['d', 'same', 4], ['d', 'same', 5]],
        'afterLeft' => 2,
        'afterRight' => 2,
        'dividerAfter' => ['d', 'same', 3],
    ],
];

foreach ($balanceCases as $label => $case) {
    $tests['applies sqlite index leaf balance variant - ' . $label] = static function (TestRunner $t) use ($case, $makeDatabaseFromValues, $recordsFor, $withPageImages): void {
        $database = $makeDatabaseFromValues($case['left'], $case['divider'], $case['right']);
        $plan = SQLiteBTreeIndexLeafBalanceApplyPlan::apply($database, 2, 3, 4, 0);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $parentHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(2), 512);
        $parentCells = SQLiteIndexCell::parsePageCells($postDatabase->page(2), $parentHeader, $postDatabase->usablePageSize());

        $t->same('index-leaf-balance-apply', $plan->toArray()['action']);
        $t->same($case['afterLeft'], count($recordsFor($postDatabase, 3)));
        $t->same($case['afterRight'], count($recordsFor($postDatabase, 4)));
        $t->same($case['dividerAfter'], $parentCells[0]->record()->values);
    };
}

for ($index = 0; $index < 20; $index++) {
    $base = 100 + ($index * 10);
    $rightCount = 3 + ($index % 3);
    $right = [];
    for ($offset = 3; $offset < 3 + $rightCount; $offset++) {
        $right[] = ['g' . str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'k' . str_pad((string) ($base + $offset), 3, '0', STR_PAD_LEFT), $base + $offset];
    }
    $tests['applies generated sqlite index leaf balance case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($base, $right, $makeDatabaseFromValues, $withPageImages): void {
        $prefix = 'g' . str_pad((string) intdiv($base - 100, 10), 2, '0', STR_PAD_LEFT);
        $database = $makeDatabaseFromValues(
            [[$prefix, 'k' . str_pad((string) ($base + 1), 3, '0', STR_PAD_LEFT), $base + 1]],
            [$prefix, 'k' . str_pad((string) ($base + 2), 3, '0', STR_PAD_LEFT), $base + 2],
            $right,
        );
        $plan = SQLiteBTreeIndexLeafBalanceApplyPlan::apply($database, 2, 3, 4, 0);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $parentHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(2), 512);
        $parentCells = SQLiteIndexCell::parsePageCells($postDatabase->page(2), $parentHeader, $postDatabase->usablePageSize());
        $expectedDividerPosition = intdiv(2 + count($right), 2);

        $t->same('index-leaf-balance-apply', $plan->toArray()['action']);
        $t->same($base + 1 + $expectedDividerPosition, $parentCells[0]->record()->values[2]);
        $t->same(3, $parentCells[0]->leftChildPage);
        $t->same(4, $parentCells[1]->leftChildPage);
    };
}

return $tests;
