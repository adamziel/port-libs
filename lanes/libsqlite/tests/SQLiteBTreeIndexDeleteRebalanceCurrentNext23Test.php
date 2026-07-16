<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDeleteRebalancePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
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
    $tailLeaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail', 999])),
    ], $pageSize);
    $parent = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode($dividerValues), leftChildPage: 3),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'divider', 900]), leftChildPage: 4),
    ], 5, $pageSize);

    return SQLiteDatabase::fromBytes($makeFirstPage($pageSize, 5) . $parent . $leftLeaf . $rightLeaf . $tailLeaf);
};

$withPageImages = static function (SQLiteDatabase $database, array $pageImages): SQLiteDatabase {
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$recordsFor = static function (SQLiteDatabase $database, int $pageNumber): array {
    $page = $database->page($pageNumber);
    $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize, $pageNumber === 1 ? 100 : 0);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header, $database->usablePageSize()),
    );
};

$parentRecordsFor = static function (SQLiteDatabase $database): array {
    $header = SQLiteBTreePageHeader::parsePage($database->page(2), $database->header->pageSize);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => ['leftChild' => $cell->leftChildPage, 'values' => $cell->record()->values],
        SQLiteIndexCell::parsePageCells($database->page(2), $header, $database->usablePageSize()),
    );
};

$tests = [
    'applies upstream index delete rebalance through parent divider rewrite' => static function (TestRunner $t) use ($makeDatabaseFromValues, $withPageImages, $recordsFor, $parentRecordsFor): void {
        $database = $makeDatabaseFromValues(
            [['no', '_transient_deleted', 7], ['no', '_transient_keep', 8]],
            ['yes', 'autoload_b', 12],
            [['yes', 'autoload_c', 13], ['yes', 'autoload_d', 14], ['yes', 'autoload_e', 15]],
        );
        $plan = SQLiteBTreeIndexDeleteRebalancePlan::deleteFromLeftAndRebalanceRight($database, 2, 3, 4, 0, ['no', '_transient_deleted', 7]);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same(SQLiteBTreeIndexDeleteRebalancePlan::class, get_class($plan));
        $t->same('index-delete-rebalance-apply', $summary['action']);
        $t->same(['no', '_transient_deleted', 7], $summary['deleted_record_values']);
        $t->same(2, $summary['before_left_cell_count']);
        $t->same(1, $summary['after_delete_left_cell_count']);
        $t->same(['left' => 2, 'right' => 2], $summary['after_rebalance_cells']);
        $t->same(1, $summary['moved_cell_count']);
        $t->same([2, 3, 4], $summary['updated_page_numbers']);
        $t->same([['no', '_transient_keep', 8], ['yes', 'autoload_b', 12]], $recordsFor($postDatabase, 3));
        $t->same([['yes', 'autoload_d', 14], ['yes', 'autoload_e', 15]], $recordsFor($postDatabase, 4));
        $t->same([
            ['leftChild' => 3, 'values' => ['yes', 'autoload_c', 13]],
            ['leftChild' => 4, 'values' => ['z', 'divider', 900]],
        ], $parentRecordsFor($postDatabase));
        $t->same($database->page(1), $postDatabase->page(1));
        $t->same($database->page(5), $postDatabase->page(5));
        $t->true($summary['deleted_payload_bytes'] > 0);
    },
    'rejects index delete rebalance when the deleted key is absent or empties the leaf' => static function (TestRunner $t) use ($makeDatabaseFromValues): void {
        $database = $makeDatabaseFromValues(
            [['a', 'k001', 1], ['a', 'k002', 2]],
            ['a', 'k003', 3],
            [['a', 'k004', 4], ['a', 'k005', 5], ['a', 'k006', 6]],
        );
        $single = $makeDatabaseFromValues(
            [['a', 'k001', 1]],
            ['a', 'k002', 2],
            [['a', 'k003', 3], ['a', 'k004', 4], ['a', 'k005', 5]],
        );

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDeleteRebalancePlan::deleteFromLeftAndRebalanceRight($database, 2, 3, 4, 0, ['missing']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDeleteRebalancePlan::deleteFromLeftAndRebalanceRight($single, 2, 3, 4, 0, ['a', 'k001', 1]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDeleteRebalancePlan::deleteFromLeftAndRebalanceRight($database, 2, 3, 4, 2, ['a', 'k001', 1]));
    },
];

$cases = [
    'delete first duplicate-prefix key' => [
        'left' => [['a', 'k001', 1], ['a', 'k002', 2]],
        'delete' => ['a', 'k001', 1],
        'divider' => ['a', 'k003', 3],
        'right' => [['a', 'k004', 4], ['a', 'k005', 5], ['a', 'k006', 6]],
        'leftAfter' => [['a', 'k002', 2], ['a', 'k003', 3]],
        'rightAfter' => [['a', 'k005', 5], ['a', 'k006', 6]],
        'dividerAfter' => ['a', 'k004', 4],
    ],
    'delete second duplicate-prefix key' => [
        'left' => [['b', 'k011', 11], ['b', 'k012', 12]],
        'delete' => ['b', 'k012', 12],
        'divider' => ['b', 'k013', 13],
        'right' => [['b', 'k014', 14], ['b', 'k015', 15], ['b', 'k016', 16], ['b', 'k017', 17]],
        'leftAfter' => [['b', 'k011', 11], ['b', 'k013', 13], ['b', 'k014', 14]],
        'rightAfter' => [['b', 'k016', 16], ['b', 'k017', 17]],
        'dividerAfter' => ['b', 'k015', 15],
    ],
    'delete integer tie-breaker key' => [
        'left' => [['c', 'same', 1], ['c', 'same', 2]],
        'delete' => ['c', 'same', 1],
        'divider' => ['c', 'same', 3],
        'right' => [['c', 'same', 4], ['c', 'same', 5], ['c', 'same', 6]],
        'leftAfter' => [['c', 'same', 2], ['c', 'same', 3]],
        'rightAfter' => [['c', 'same', 5], ['c', 'same', 6]],
        'dividerAfter' => ['c', 'same', 4],
    ],
    'delete null-leading key' => [
        'left' => [[null, 'k021', 21], [null, 'k022', 22]],
        'delete' => [null, 'k021', 21],
        'divider' => [null, 'k023', 23],
        'right' => [[null, 'k024', 24], ['d', 'k025', 25], ['d', 'k026', 26]],
        'leftAfter' => [[null, 'k022', 22], [null, 'k023', 23]],
        'rightAfter' => [['d', 'k025', 25], ['d', 'k026', 26]],
        'dividerAfter' => [null, 'k024', 24],
    ],
];

foreach ($cases as $label => $case) {
    $tests['applies upstream index delete rebalance variant - ' . $label] = static function (TestRunner $t) use ($case, $makeDatabaseFromValues, $withPageImages, $recordsFor, $parentRecordsFor): void {
        $database = $makeDatabaseFromValues($case['left'], $case['divider'], $case['right']);
        $plan = SQLiteBTreeIndexDeleteRebalancePlan::deleteFromLeftAndRebalanceRight($database, 2, 3, 4, 0, $case['delete']);
        $postDatabase = $withPageImages($database, $plan->pageImages);

        $t->same($case['delete'], $plan->deletedRecordValues);
        $t->same($case['leftAfter'], $recordsFor($postDatabase, 3));
        $t->same($case['rightAfter'], $recordsFor($postDatabase, 4));
        $t->same($case['dividerAfter'], $parentRecordsFor($postDatabase)[0]['values']);
        $t->same(3, $parentRecordsFor($postDatabase)[0]['leftChild']);
    };
}

for ($index = 0; $index < 34; $index++) {
    $base = 1000 + ($index * 10);
    $rightCount = 3 + ($index % 3);
    $right = [];
    for ($offset = 4; $offset < 4 + $rightCount; $offset++) {
        $right[] = ['wp_autoload', 'k' . str_pad((string) ($base + $offset), 4, '0', STR_PAD_LEFT), $base + $offset];
    }
    $tests['applies generated upstream index delete rebalance case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($base, $right, $makeDatabaseFromValues, $withPageImages, $recordsFor, $parentRecordsFor): void {
        $delete = ['wp_autoload', 'k' . str_pad((string) ($base + 1), 4, '0', STR_PAD_LEFT), $base + 1];
        $keep = ['wp_autoload', 'k' . str_pad((string) ($base + 2), 4, '0', STR_PAD_LEFT), $base + 2];
        $divider = ['wp_autoload', 'k' . str_pad((string) ($base + 3), 4, '0', STR_PAD_LEFT), $base + 3];
        $database = $makeDatabaseFromValues([$delete, $keep], $divider, $right);
        $plan = SQLiteBTreeIndexDeleteRebalancePlan::deleteFromLeftAndRebalanceRight($database, 2, 3, 4, 0, $delete);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $expectedDividerOffset = intdiv(1 + 1 + count($right), 2);

        $t->same('index-delete-rebalance-apply', $plan->toArray()['action']);
        $t->same($keep, $recordsFor($postDatabase, 3)[0]);
        $t->same($base + 2 + $expectedDividerOffset, $parentRecordsFor($postDatabase)[0]['values'][2]);
    };
}

return $tests;
