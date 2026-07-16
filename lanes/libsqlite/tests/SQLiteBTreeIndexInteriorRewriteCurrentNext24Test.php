<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorRedistributionPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

$payload = static fn (string $autoload, string $name, int $rowid): string => SQLiteRecord::encode([$autoload, $name, $rowid]);

$valuesForPage = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header, 512),
    );
};

$childrenForPage = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);
    $children = array_map(
        static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage ?? 0,
        SQLiteIndexCell::parsePageCells($page, $header, 512),
    );
    $children[] = $header->rightMostPointer;

    return $children;
};

$yieldRowsForPage = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);
    $rows = [];
    foreach (SQLiteIndexCell::parsePageCells($page, $header, 512) as $cell) {
        $rows[] = [
            'left_child' => $cell->leftChildPage,
            'values' => $cell->record()->values,
        ];
    }
    $rows[] = [
        'right_most_child' => $header->rightMostPointer,
        'values' => null,
    ];

    return $rows;
};

$makePlan = static function () use ($payload): SQLiteBTreeInteriorRedistributionPlan {
    $leftPage = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($payload('no', '_transient_old_a', 10), leftChildPage: 10),
    ], 11);
    $rightPage = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($payload('yes', '_autoload_b', 30), leftChildPage: 12),
        SQLiteIndexCell::encode($payload('yes', '_autoload_c', 40), leftChildPage: 13),
        SQLiteIndexCell::encode($payload('yes', '_autoload_d', 50), leftChildPage: 14),
    ], 15);

    return SQLiteBTreeInteriorRedistributionPlan::indexInterior(
        $leftPage,
        $rightPage,
        7,
        8,
        3,
        $payload('no', '_transient_old_b', 20),
    );
};

$readers = [
    'plan class' => static fn () => SQLiteBTreeInteriorRedistributionPlan::class,
    'page type is index interior' => static fn () => $makePlan()->pageType,
    'left page number' => static fn () => $makePlan()->leftPageNumber,
    'right page number' => static fn () => $makePlan()->rightPageNumber,
    'parent page number' => static fn () => $makePlan()->parentPageNumber,
    'old divider payload length' => static fn () => $makePlan()->oldDividerKey,
    'new divider payload length' => static fn () => $makePlan()->newDividerKey,
    'before left cell count' => static fn () => $makePlan()->beforeLeftCellCount,
    'before right cell count' => static fn () => $makePlan()->beforeRightCellCount,
    'after left cell count' => static fn () => count($makePlan()->leftKeys),
    'after right cell count' => static fn () => count($makePlan()->rightKeys),
    'left child yield after rewrite' => static fn () => $makePlan()->leftChildPageNumbers,
    'right child yield after rewrite' => static fn () => $makePlan()->rightChildPageNumbers,
    'moved child page number' => static fn () => $makePlan()->movedChildPageNumbers,
    'old divider values' => static fn () => $makePlan()->oldDividerValues,
    'new divider values' => static fn () => $makePlan()->newDividerValues,
    'left values after rewrite' => static fn () => $valuesForPage($makePlan()->leftPage),
    'right values after rewrite' => static fn () => $valuesForPage($makePlan()->rightPage),
    'left current-next yield rows' => static fn () => $yieldRowsForPage($makePlan()->leftPage),
    'right current-next yield rows' => static fn () => $yieldRowsForPage($makePlan()->rightPage),
    'left header cell count' => static fn () => SQLiteBTreePageHeader::parsePage($makePlan()->leftPage, 512)->cellCount,
    'right header cell count' => static fn () => SQLiteBTreePageHeader::parsePage($makePlan()->rightPage, 512)->cellCount,
    'left header right most child' => static fn () => SQLiteBTreePageHeader::parsePage($makePlan()->leftPage, 512)->rightMostPointer,
    'right header right most child' => static fn () => SQLiteBTreePageHeader::parsePage($makePlan()->rightPage, 512)->rightMostPointer,
    'left parsed children after rewrite' => static fn () => $childrenForPage($makePlan()->leftPage),
    'right parsed children after rewrite' => static fn () => $childrenForPage($makePlan()->rightPage),
    'page images keys' => static fn () => array_keys($makePlan()->pageImages()),
    'summary page type' => static fn () => $makePlan()->toArray()['page_type'],
    'summary left page' => static fn () => $makePlan()->toArray()['left_page'],
    'summary right page' => static fn () => $makePlan()->toArray()['right_page'],
    'summary parent page' => static fn () => $makePlan()->toArray()['parent_page'],
    'summary left count' => static fn () => $makePlan()->toArray()['left_cell_count'],
    'summary right count' => static fn () => $makePlan()->toArray()['right_cell_count'],
    'summary updated pages' => static fn () => $makePlan()->toArray()['updated_page_numbers'],
    'summary removed pages' => static fn () => $makePlan()->toArray()['removed_page_numbers'],
    'summary parent divider action' => static fn () => $makePlan()->toArray()['updated_parent_divider']['action'],
    'summary old divider values' => static fn () => $makePlan()->toArray()['updated_parent_divider']['old_separator_values'],
    'summary new divider values' => static fn () => $makePlan()->toArray()['updated_parent_divider']['new_separator_values'],
    'summary pointer map update pages' => static fn () => $makePlan()->toArray()['pointer_map_update_pages'],
    'rebalance action name' => static fn () => $makePlan()->rebalanceAction()['action'],
    'rebalance before cells' => static fn () => $makePlan()->rebalanceAction()['before_cells'],
    'rebalance after cells' => static fn () => $makePlan()->rebalanceAction()['after_cells'],
    'rebalance moved children' => static fn () => $makePlan()->rebalanceAction()['moved_child_page_numbers'],
    'pointer map update count' => static fn () => count($makePlan()->pointerMapUpdates),
    'child ten parent after rewrite' => static fn () => $makePlan()->pointerMapUpdates[10]['parent_page_number'],
    'child eleven parent after rewrite' => static fn () => $makePlan()->pointerMapUpdates[11]['parent_page_number'],
    'child twelve parent after rewrite' => static fn () => $makePlan()->pointerMapUpdates[12]['parent_page_number'],
    'child thirteen parent after rewrite' => static fn () => $makePlan()->pointerMapUpdates[13]['parent_page_number'],
    'child fourteen parent after rewrite' => static fn () => $makePlan()->pointerMapUpdates[14]['parent_page_number'],
    'child fifteen parent after rewrite' => static fn () => $makePlan()->pointerMapUpdates[15]['parent_page_number'],
    'pointer map entry type for moved child' => static fn () => $makePlan()->pointerMapUpdates[12]['type'],
    'left page stays page sized' => static fn () => strlen($makePlan()->leftPage),
    'right page stays page sized' => static fn () => strlen($makePlan()->rightPage),
    'left free space is positive' => static fn () => $makePlan()->afterLeftFreeSpaceBytes > 0,
    'right free space is positive' => static fn () => $makePlan()->afterRightFreeSpaceBytes > 0,
];

$expected = [
    'plan class' => SQLiteBTreeInteriorRedistributionPlan::class,
    'page type is index interior' => 'index-interior',
    'left page number' => 7,
    'right page number' => 8,
    'parent page number' => 3,
    'old divider payload length' => strlen($payload('no', '_transient_old_b', 20)),
    'new divider payload length' => strlen($payload('yes', '_autoload_b', 30)),
    'before left cell count' => 1,
    'before right cell count' => 3,
    'after left cell count' => 2,
    'after right cell count' => 2,
    'left child yield after rewrite' => [10, 11, 12],
    'right child yield after rewrite' => [13, 14, 15],
    'moved child page number' => [12],
    'old divider values' => ['no', '_transient_old_b', 20],
    'new divider values' => ['yes', '_autoload_b', 30],
    'left values after rewrite' => [['no', '_transient_old_a', 10], ['no', '_transient_old_b', 20]],
    'right values after rewrite' => [['yes', '_autoload_c', 40], ['yes', '_autoload_d', 50]],
    'left current-next yield rows' => [
        ['left_child' => 10, 'values' => ['no', '_transient_old_a', 10]],
        ['left_child' => 11, 'values' => ['no', '_transient_old_b', 20]],
        ['right_most_child' => 12, 'values' => null],
    ],
    'right current-next yield rows' => [
        ['left_child' => 13, 'values' => ['yes', '_autoload_c', 40]],
        ['left_child' => 14, 'values' => ['yes', '_autoload_d', 50]],
        ['right_most_child' => 15, 'values' => null],
    ],
    'left header cell count' => 2,
    'right header cell count' => 2,
    'left header right most child' => 12,
    'right header right most child' => 15,
    'left parsed children after rewrite' => [10, 11, 12],
    'right parsed children after rewrite' => [13, 14, 15],
    'page images keys' => [7, 8],
    'summary page type' => 'index-interior',
    'summary left page' => 7,
    'summary right page' => 8,
    'summary parent page' => 3,
    'summary left count' => 2,
    'summary right count' => 2,
    'summary updated pages' => [7, 8],
    'summary removed pages' => [],
    'summary parent divider action' => 'replace-parent-divider',
    'summary old divider values' => ['no', '_transient_old_b', 20],
    'summary new divider values' => ['yes', '_autoload_b', 30],
    'summary pointer map update pages' => [10, 11, 12, 13, 14, 15],
    'rebalance action name' => 'index-interior-sibling-redistribute',
    'rebalance before cells' => ['left' => 1, 'right' => 3],
    'rebalance after cells' => ['left' => 2, 'right' => 2],
    'rebalance moved children' => [12],
    'pointer map update count' => 6,
    'child ten parent after rewrite' => 7,
    'child eleven parent after rewrite' => 7,
    'child twelve parent after rewrite' => 7,
    'child thirteen parent after rewrite' => 8,
    'child fourteen parent after rewrite' => 8,
    'child fifteen parent after rewrite' => 8,
    'pointer map entry type for moved child' => SQLitePointerMapEntry::BTREE_PAGE,
    'left page stays page sized' => 512,
    'right page stays page sized' => 512,
    'left free space is positive' => true,
    'right free space is positive' => true,
];

foreach ($expected as $name => $want) {
    $tests['btree index interior current-next24 ' . $name] = static function (TestRunner $t) use ($readers, $name, $want): void {
        $t->same($want, $readers[$name]());
    };
}

$tests['btree index interior current-next24 rejects non index siblings'] = static function (TestRunner $t) use ($payload): void {
    $badPage = str_repeat("\0", 512);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorRedistributionPlan::indexInterior(
        $badPage,
        $badPage,
        7,
        8,
        3,
        $payload('no', '_bad', 1),
    ));
};

$tests['btree index interior current-next24 rejects empty divider payload'] = static function (TestRunner $t) use ($payload): void {
    $page = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($payload('no', '_transient_old_a', 10), leftChildPage: 10),
    ], 11);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorRedistributionPlan::indexInterior($page, $page, 7, 8, 3, ''));
};

$tests['btree index interior current-next24 rejects too few separator payloads'] = static function (TestRunner $t) use ($payload): void {
    $leftPage = SQLiteIndexInteriorPage::assemble([], 10);
    $rightPage = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($payload('yes', '_autoload_b', 30), leftChildPage: 11),
    ], 12);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorRedistributionPlan::indexInterior(
        $leftPage,
        $rightPage,
        7,
        8,
        3,
        $payload('no', '_transient_old_b', 20),
    ));
};

return $tests;
