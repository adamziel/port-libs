<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorRedistributionApplyPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;

$firstPage = static function (int $pageCount, int $pageSize = 512): string {
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
    $page = substr_replace($page, pack('N', 12), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);
    $page = substr_replace($page, pack('N', 0), 64, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

$tableFixture = static function () use ($firstPage, $putPointerMapEntry): SQLiteBTreeInteriorRedistributionApplyPlan {
    $pageSize = 512;
    $pages = array_fill(1, 15, str_repeat("\0", $pageSize));
    $pages[1] = $firstPage(15, $pageSize);
    $pages[3] = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(7, 20),
    ], 8, $pageSize);
    $pages[7] = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(10, 10),
    ], 11, $pageSize);
    $pages[8] = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(12, 30),
        SQLiteTableInteriorCell::encode(13, 40),
        SQLiteTableInteriorCell::encode(14, 50),
    ], 15, $pageSize);
    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 7 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ([10 => 7, 11 => 7, 12 => 8, 13 => 8, 14 => 8, 15 => 8] as $pageNumber => $parent) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, $parent, $pageSize);
    }

    return SQLiteBTreeInteriorRedistributionApplyPlan::tableInterior(SQLiteDatabase::fromBytes(implode('', $pages)), 7, 8, 3, 20);
};

$payload = static fn (string $autoload, string $name, int $rowid): string => SQLiteRecord::encode([$autoload, $name, $rowid]);
$indexFixture = static function () use ($firstPage, $putPointerMapEntry, $payload): SQLiteBTreeInteriorRedistributionApplyPlan {
    $pageSize = 512;
    $pages = array_fill(1, 15, str_repeat("\0", $pageSize));
    $pages[1] = $firstPage(15, $pageSize);
    $divider = $payload('no', '_transient_timeout_feed', 20);
    $pages[3] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($divider, $pageSize, null, 7),
    ], 8, $pageSize);
    $pages[7] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($payload('no', '_transient_feed', 10), $pageSize, null, 10),
    ], 11, $pageSize);
    $pages[8] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($payload('yes', 'blogname', 30), $pageSize, null, 12),
        SQLiteIndexCell::encode($payload('yes', 'siteurl', 40), $pageSize, null, 13),
        SQLiteIndexCell::encode($payload('yes', 'users_can_register', 50), $pageSize, null, 14),
    ], 15, $pageSize);
    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 7 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ([10 => 7, 11 => 7, 12 => 8, 13 => 8, 14 => 8, 15 => 8] as $pageNumber => $parent) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, $parent, $pageSize);
    }

    return SQLiteBTreeInteriorRedistributionApplyPlan::indexInterior(SQLiteDatabase::fromBytes(implode('', $pages)), 7, 8, 3, $divider);
};

$tableChildren = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);
    $children = array_map(
        static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage,
        SQLiteTableInteriorCell::parsePageCells($page, $header),
    );
    $children[] = $header->rightMostPointer;

    return $children;
};

$tableKeys = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteTableInteriorCell $cell): int => $cell->key,
        SQLiteTableInteriorCell::parsePageCells($page, $header),
    );
};

$indexChildren = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);
    $children = array_map(
        static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage ?? 0,
        SQLiteIndexCell::parsePageCells($page, $header, 512),
    );
    $children[] = $header->rightMostPointer;

    return $children;
};

$indexValues = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header, 512),
    );
};

$readers = [
    'table action name' => static fn () => $tableFixture()->toArray()['action'],
    'table updated pages include parent and siblings' => static fn () => $tableFixture()->updatedPageNumbers(),
    'table pointer map pages' => static fn () => $tableFixture()->toArray()['updated_pointer_map_page_numbers'],
    'table moved child' => static fn () => $tableFixture()->toArray()['moved_child_page_numbers'],
    'table left children after apply' => static fn () => $tableChildren($tableFixture()->postDatabase->page(7)),
    'table right children after apply' => static fn () => $tableChildren($tableFixture()->postDatabase->page(8)),
    'table left keys after apply' => static fn () => $tableKeys($tableFixture()->postDatabase->page(7)),
    'table right keys after apply' => static fn () => $tableKeys($tableFixture()->postDatabase->page(8)),
    'table parent divider after apply' => static fn () => $tableKeys($tableFixture()->postDatabase->page(3)),
    'table parent children preserved' => static fn () => $tableChildren($tableFixture()->postDatabase->page(3)),
    'table child 12 new parent' => static fn () => $tableFixture()->postDatabase->pointerMapEntryForPage(12)->parentPageNumber,
    'table child 15 stays right parent' => static fn () => $tableFixture()->postDatabase->pointerMapEntryForPage(15)->parentPageNumber,
    'table pointer map entry pages' => static fn () => array_column($tableFixture()->pointerMapEntries, 'page_number'),
    'table pointer map entry parents' => static fn () => array_values(array_unique(array_column($tableFixture()->pointerMapEntries, 'parent_page_number'))),
    'table pointer map entry type names' => static fn () => array_values(array_unique(array_column($tableFixture()->pointerMapEntries, 'type_name'))),
    'table divider update action' => static fn () => $tableFixture()->parentDividerUpdate['action'],
    'table divider update old' => static fn () => $tableFixture()->parentDividerUpdate['old_separator'],
    'table divider update new' => static fn () => $tableFixture()->parentDividerUpdate['new_separator'],
    'table redis old divider' => static fn () => $tableFixture()->redistributionPlan->oldDividerKey,
    'table redis new divider' => static fn () => $tableFixture()->redistributionPlan->newDividerKey,
    'table left cell count' => static fn () => SQLiteBTreePageHeader::parsePage($tableFixture()->postDatabase->page(7), 512)->cellCount,
    'table right cell count' => static fn () => SQLiteBTreePageHeader::parsePage($tableFixture()->postDatabase->page(8), 512)->cellCount,
    'table page count unchanged' => static fn () => $tableFixture()->postDatabase->pageCount(),
    'table header page count unchanged' => static fn () => $tableFixture()->postDatabase->header->databaseSizePages,
    'table page seven pointer map parent' => static fn () => $tableFixture()->postDatabase->pointerMapEntryForPage(7)->parentPageNumber,
    'table summary child lists left' => static fn () => $tableFixture()->toArray()['left_child_page_numbers'],
    'table summary child lists right' => static fn () => $tableFixture()->toArray()['right_child_page_numbers'],
    'table summary embeds redistribution page type' => static fn () => $tableFixture()->toArray()['redistribution']['page_type'],
    'table page image count' => static fn () => count($tableFixture()->pageImages),
    'table pointer map image count' => static fn () => count($tableFixture()->pointerMapPageImages),
    'index action name' => static fn () => $indexFixture()->toArray()['action'],
    'index updated pages include parent and siblings' => static fn () => $indexFixture()->updatedPageNumbers(),
    'index pointer map pages' => static fn () => $indexFixture()->toArray()['updated_pointer_map_page_numbers'],
    'index moved child' => static fn () => $indexFixture()->toArray()['moved_child_page_numbers'],
    'index left children after apply' => static fn () => $indexChildren($indexFixture()->postDatabase->page(7)),
    'index right children after apply' => static fn () => $indexChildren($indexFixture()->postDatabase->page(8)),
    'index left values after apply' => static fn () => $indexValues($indexFixture()->postDatabase->page(7)),
    'index right values after apply' => static fn () => $indexValues($indexFixture()->postDatabase->page(8)),
    'index parent divider after apply' => static fn () => $indexValues($indexFixture()->postDatabase->page(3)),
    'index parent children preserved' => static fn () => $indexChildren($indexFixture()->postDatabase->page(3)),
    'index child 12 new parent' => static fn () => $indexFixture()->postDatabase->pointerMapEntryForPage(12)->parentPageNumber,
    'index child 15 stays right parent' => static fn () => $indexFixture()->postDatabase->pointerMapEntryForPage(15)->parentPageNumber,
    'index pointer map entry pages' => static fn () => array_column($indexFixture()->pointerMapEntries, 'page_number'),
    'index pointer map entry parents' => static fn () => array_values(array_unique(array_column($indexFixture()->pointerMapEntries, 'parent_page_number'))),
    'index pointer map entry type names' => static fn () => array_values(array_unique(array_column($indexFixture()->pointerMapEntries, 'type_name'))),
    'index divider update action' => static fn () => $indexFixture()->parentDividerUpdate['action'],
    'index divider update old' => static fn () => $indexFixture()->parentDividerUpdate['old_separator'],
    'index divider update new' => static fn () => $indexFixture()->parentDividerUpdate['new_separator'],
    'index redis old divider values' => static fn () => $indexFixture()->redistributionPlan->oldDividerValues,
    'index redis new divider values' => static fn () => $indexFixture()->redistributionPlan->newDividerValues,
    'index left cell count' => static fn () => SQLiteBTreePageHeader::parsePage($indexFixture()->postDatabase->page(7), 512)->cellCount,
    'index right cell count' => static fn () => SQLiteBTreePageHeader::parsePage($indexFixture()->postDatabase->page(8), 512)->cellCount,
    'index page count unchanged' => static fn () => $indexFixture()->postDatabase->pageCount(),
    'index header page count unchanged' => static fn () => $indexFixture()->postDatabase->header->databaseSizePages,
    'index page seven pointer map parent' => static fn () => $indexFixture()->postDatabase->pointerMapEntryForPage(7)->parentPageNumber,
    'index summary child lists left' => static fn () => $indexFixture()->toArray()['left_child_page_numbers'],
    'index summary child lists right' => static fn () => $indexFixture()->toArray()['right_child_page_numbers'],
    'index summary embeds redistribution page type' => static fn () => $indexFixture()->toArray()['redistribution']['page_type'],
    'index page image count' => static fn () => count($indexFixture()->pageImages),
    'index pointer map image count' => static fn () => count($indexFixture()->pointerMapPageImages),
    'rejects non auto vacuum database' => static fn () => (static function () use ($tableFixture): string {
        $plan = $tableFixture();
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->postDatabase->pageCount(); $pageNumber++) {
            $pages[] = $plan->postDatabase->page($pageNumber);
        }
        $pages[0] = substr_replace($pages[0], pack('N', 0), 52, 4);
        try {
            SQLiteBTreeInteriorRedistributionApplyPlan::tableInterior(SQLiteDatabase::fromBytes(implode('', $pages)), 7, 8, 3, 20);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'no exception';
    })(),
    'rejects missing table parent divider' => static fn () => (static function () use ($firstPage): string {
        $pages = array_fill(1, 15, str_repeat("\0", 512));
        $pages[1] = $firstPage(15);
        $pages[3] = SQLiteTableInteriorPage::assemble([SQLiteTableInteriorCell::encode(9, 20)], 8);
        $pages[7] = SQLiteTableInteriorPage::assemble([SQLiteTableInteriorCell::encode(10, 10)], 11);
        $pages[8] = SQLiteTableInteriorPage::assemble([
            SQLiteTableInteriorCell::encode(12, 30),
            SQLiteTableInteriorCell::encode(13, 40),
            SQLiteTableInteriorCell::encode(14, 50),
        ], 15);
        try {
            SQLiteBTreeInteriorRedistributionApplyPlan::tableInterior(SQLiteDatabase::fromBytes(implode('', $pages)), 7, 8, 3, 20);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'no exception';
    })(),
];

$expected = [
    'table action name' => 'table-interior-sibling-redistribute-apply',
    'table updated pages include parent and siblings' => [2, 3, 7, 8],
    'table pointer map pages' => [2],
    'table moved child' => [12],
    'table left children after apply' => [10, 11, 12],
    'table right children after apply' => [13, 14, 15],
    'table left keys after apply' => [10, 20],
    'table right keys after apply' => [40, 50],
    'table parent divider after apply' => [30],
    'table parent children preserved' => [7, 8],
    'table child 12 new parent' => 7,
    'table child 15 stays right parent' => 8,
    'table pointer map entry pages' => [10, 11, 12, 13, 14, 15],
    'table pointer map entry parents' => [7, 8],
    'table pointer map entry type names' => ['btree-page'],
    'table divider update action' => 'replace-table-parent-divider',
    'table divider update old' => 20,
    'table divider update new' => 30,
    'table redis old divider' => 20,
    'table redis new divider' => 30,
    'table left cell count' => 2,
    'table right cell count' => 2,
    'table page count unchanged' => 15,
    'table header page count unchanged' => 15,
    'table page seven pointer map parent' => 3,
    'table summary child lists left' => [10, 11, 12],
    'table summary child lists right' => [13, 14, 15],
    'table summary embeds redistribution page type' => 'table-interior',
    'table page image count' => 4,
    'table pointer map image count' => 1,
    'index action name' => 'index-interior-sibling-redistribute-apply',
    'index updated pages include parent and siblings' => [2, 3, 7, 8],
    'index pointer map pages' => [2],
    'index moved child' => [12],
    'index left children after apply' => [10, 11, 12],
    'index right children after apply' => [13, 14, 15],
    'index left values after apply' => [['no', '_transient_feed', 10], ['no', '_transient_timeout_feed', 20]],
    'index right values after apply' => [['yes', 'siteurl', 40], ['yes', 'users_can_register', 50]],
    'index parent divider after apply' => [[ 'yes', 'blogname', 30]],
    'index parent children preserved' => [7, 8],
    'index child 12 new parent' => 7,
    'index child 15 stays right parent' => 8,
    'index pointer map entry pages' => [10, 11, 12, 13, 14, 15],
    'index pointer map entry parents' => [7, 8],
    'index pointer map entry type names' => ['btree-page'],
    'index divider update action' => 'replace-index-parent-divider',
    'index divider update old' => ['no', '_transient_timeout_feed', 20],
    'index divider update new' => ['yes', 'blogname', 30],
    'index redis old divider values' => ['no', '_transient_timeout_feed', 20],
    'index redis new divider values' => ['yes', 'blogname', 30],
    'index left cell count' => 2,
    'index right cell count' => 2,
    'index page count unchanged' => 15,
    'index header page count unchanged' => 15,
    'index page seven pointer map parent' => 3,
    'index summary child lists left' => [10, 11, 12],
    'index summary child lists right' => [13, 14, 15],
    'index summary embeds redistribution page type' => 'index-interior',
    'index page image count' => 4,
    'index pointer map image count' => 1,
    'rejects non auto vacuum database' => 'SQLite b-tree interior redistribution apply requires an auto-vacuum database',
    'rejects missing table parent divider' => 'SQLite b-tree interior redistribution parent divider was not found',
];

$tests = [];
foreach ($readers as $name => $reader) {
    $tests['btree interior redistribute pointermap current next32 ' . $name] = static function (TestRunner $t) use ($name, $reader, $expected): void {
        $t->same($expected[$name], $reader());
    };
}

return $tests;
