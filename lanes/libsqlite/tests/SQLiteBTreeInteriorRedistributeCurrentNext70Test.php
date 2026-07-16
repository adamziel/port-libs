<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorRedistributionCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
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

$tableFixture = static function () use ($firstPage, $putPointerMapEntry): SQLiteBTreeInteriorRedistributionCurrentNextPlan {
    $pageSize = 512;
    $pages = array_fill(1, 16, str_repeat("\0", $pageSize));
    $pages[1] = $firstPage(16, $pageSize);
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

    return SQLiteBTreeInteriorRedistributionCurrentNextPlan::tableInterior(SQLiteDatabase::fromBytes(implode('', $pages)), 7, 8, 3, 20);
};

$payload = static fn (string $autoload, string $name, int $rowid): string => SQLiteRecord::encode([$autoload, $name, $rowid]);
$indexFixture = static function () use ($firstPage, $putPointerMapEntry, $payload): SQLiteBTreeInteriorRedistributionCurrentNextPlan {
    $pageSize = 512;
    $pages = array_fill(1, 16, str_repeat("\0", $pageSize));
    $pages[1] = $firstPage(16, $pageSize);
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

    return SQLiteBTreeInteriorRedistributionCurrentNextPlan::indexInterior(SQLiteDatabase::fromBytes(implode('', $pages)), 7, 8, 3, $divider);
};

$tableChildren = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);
    $children = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, SQLiteTableInteriorCell::parsePageCells($page, $header));
    $children[] = $header->rightMostPointer;

    return $children;
};

$tableKeys = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, SQLiteTableInteriorCell::parsePageCells($page, $header));
};

$indexChildren = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);
    $children = array_map(static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage ?? 0, SQLiteIndexCell::parsePageCells($page, $header, 512));
    $children[] = $header->rightMostPointer;

    return $children;
};

$indexValues = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, SQLiteIndexCell::parsePageCells($page, $header, 512));
};

$readers = [
    'table summary action' => static fn () => $tableFixture()->toArray()['action'],
    'table page type' => static fn () => $tableFixture()->toArray()['page_type'],
    'table current page count' => static fn () => $tableFixture()->toArray()['current_page_count'],
    'table next page count' => static fn () => $tableFixture()->toArray()['next_page_count'],
    'table updated pages' => static fn () => $tableFixture()->updatedPageNumbers(),
    'table parent old divider' => static fn () => $tableFixture()->toArray()['parent_divider_update']['old_separator'],
    'table parent new divider' => static fn () => $tableFixture()->toArray()['parent_divider_update']['new_separator'],
    'table moved children' => static fn () => $tableFixture()->toArray()['moved_child_page_numbers'],
    'table current left children' => static fn () => $tableChildren($tableFixture()->currentDatabase->page(7)),
    'table next left children' => static fn () => $tableChildren($tableFixture()->nextDatabase->page(7)),
    'table current right children' => static fn () => $tableChildren($tableFixture()->currentDatabase->page(8)),
    'table next right children' => static fn () => $tableChildren($tableFixture()->nextDatabase->page(8)),
    'table current parent keys' => static fn () => $tableKeys($tableFixture()->currentDatabase->page(3)),
    'table next parent keys' => static fn () => $tableKeys($tableFixture()->nextDatabase->page(3)),
    'table current child12 parent' => static fn () => $tableFixture()->currentDatabase->pointerMapEntryForPage(12)->parentPageNumber,
    'table next child12 parent' => static fn () => $tableFixture()->nextDatabase->pointerMapEntryForPage(12)->parentPageNumber,
    'table page transition pages' => static fn () => array_column($tableFixture()->pageTransitions, 'page'),
    'table page transition changed' => static fn () => array_column($tableFixture()->pageTransitions, 'changed'),
    'table pointer transition pages' => static fn () => array_column($tableFixture()->pointerMapTransitions, 'page'),
    'table pointer changed pages' => static fn () => array_values(array_column(array_filter($tableFixture()->pointerMapTransitions, static fn (array $transition): bool => $transition['changed']), 'page')),
    'table pointer current parents' => static fn () => array_column($tableFixture()->pointerMapTransitions, 'current_parent_page_number'),
    'table pointer next parents' => static fn () => array_column($tableFixture()->pointerMapTransitions, 'next_parent_page_number'),
    'table current page hash differs' => static fn () => $tableFixture()->pageTransitions[1]['current_sha1'] !== $tableFixture()->pageTransitions[1]['next_sha1'],
    'table apply action' => static fn () => $tableFixture()->toArray()['apply']['action'],
    'table apply embeds redistribution' => static fn () => $tableFixture()->toArray()['apply']['redistribution']['page_type'],
    'index summary action' => static fn () => $indexFixture()->toArray()['action'],
    'index page type' => static fn () => $indexFixture()->toArray()['page_type'],
    'index current page count' => static fn () => $indexFixture()->toArray()['current_page_count'],
    'index next page count' => static fn () => $indexFixture()->toArray()['next_page_count'],
    'index updated pages' => static fn () => $indexFixture()->updatedPageNumbers(),
    'index parent old divider' => static fn () => $indexFixture()->toArray()['parent_divider_update']['old_separator'],
    'index parent new divider' => static fn () => $indexFixture()->toArray()['parent_divider_update']['new_separator'],
    'index moved children' => static fn () => $indexFixture()->toArray()['moved_child_page_numbers'],
    'index current left children' => static fn () => $indexChildren($indexFixture()->currentDatabase->page(7)),
    'index next left children' => static fn () => $indexChildren($indexFixture()->nextDatabase->page(7)),
    'index current right children' => static fn () => $indexChildren($indexFixture()->currentDatabase->page(8)),
    'index next right children' => static fn () => $indexChildren($indexFixture()->nextDatabase->page(8)),
    'index current parent values' => static fn () => $indexValues($indexFixture()->currentDatabase->page(3)),
    'index next parent values' => static fn () => $indexValues($indexFixture()->nextDatabase->page(3)),
    'index current child12 parent' => static fn () => $indexFixture()->currentDatabase->pointerMapEntryForPage(12)->parentPageNumber,
    'index next child12 parent' => static fn () => $indexFixture()->nextDatabase->pointerMapEntryForPage(12)->parentPageNumber,
    'index page transition pages' => static fn () => array_column($indexFixture()->pageTransitions, 'page'),
    'index page transition changed' => static fn () => array_column($indexFixture()->pageTransitions, 'changed'),
    'index pointer transition pages' => static fn () => array_column($indexFixture()->pointerMapTransitions, 'page'),
    'index pointer changed pages' => static fn () => array_values(array_column(array_filter($indexFixture()->pointerMapTransitions, static fn (array $transition): bool => $transition['changed']), 'page')),
    'index pointer current parents' => static fn () => array_column($indexFixture()->pointerMapTransitions, 'current_parent_page_number'),
    'index pointer next parents' => static fn () => array_column($indexFixture()->pointerMapTransitions, 'next_parent_page_number'),
    'index current page hash differs' => static fn () => $indexFixture()->pageTransitions[1]['current_sha1'] !== $indexFixture()->pageTransitions[1]['next_sha1'],
    'index apply action' => static fn () => $indexFixture()->toArray()['apply']['action'],
    'index apply embeds redistribution' => static fn () => $indexFixture()->toArray()['apply']['redistribution']['page_type'],
];

$expected = [
    'table summary action' => 'btree-interior-redistribute-current-next',
    'table page type' => 'table-interior',
    'table current page count' => 16,
    'table next page count' => 16,
    'table updated pages' => [2, 3, 7, 8],
    'table parent old divider' => 20,
    'table parent new divider' => 30,
    'table moved children' => [12],
    'table current left children' => [10, 11],
    'table next left children' => [10, 11, 12],
    'table current right children' => [12, 13, 14, 15],
    'table next right children' => [13, 14, 15],
    'table current parent keys' => [20],
    'table next parent keys' => [30],
    'table current child12 parent' => 8,
    'table next child12 parent' => 7,
    'table page transition pages' => [2, 3, 7, 8],
    'table page transition changed' => [true, true, true, true],
    'table pointer transition pages' => [10, 11, 12, 13, 14, 15],
    'table pointer changed pages' => [12],
    'table pointer current parents' => [7, 7, 8, 8, 8, 8],
    'table pointer next parents' => [7, 7, 7, 8, 8, 8],
    'table current page hash differs' => true,
    'table apply action' => 'table-interior-sibling-redistribute-apply',
    'table apply embeds redistribution' => 'table-interior',
    'index summary action' => 'btree-interior-redistribute-current-next',
    'index page type' => 'index-interior',
    'index current page count' => 16,
    'index next page count' => 16,
    'index updated pages' => [2, 3, 7, 8],
    'index parent old divider' => ['no', '_transient_timeout_feed', 20],
    'index parent new divider' => ['yes', 'blogname', 30],
    'index moved children' => [12],
    'index current left children' => [10, 11],
    'index next left children' => [10, 11, 12],
    'index current right children' => [12, 13, 14, 15],
    'index next right children' => [13, 14, 15],
    'index current parent values' => [['no', '_transient_timeout_feed', 20]],
    'index next parent values' => [['yes', 'blogname', 30]],
    'index current child12 parent' => 8,
    'index next child12 parent' => 7,
    'index page transition pages' => [2, 3, 7, 8],
    'index page transition changed' => [true, true, true, true],
    'index pointer transition pages' => [10, 11, 12, 13, 14, 15],
    'index pointer changed pages' => [12],
    'index pointer current parents' => [7, 7, 8, 8, 8, 8],
    'index pointer next parents' => [7, 7, 7, 8, 8, 8],
    'index current page hash differs' => true,
    'index apply action' => 'index-interior-sibling-redistribute-apply',
    'index apply embeds redistribution' => 'index-interior',
];

$tests = [];
foreach ($readers as $name => $reader) {
    $tests['btree interior redistribute current next70 ' . $name] = static function (TestRunner $t) use ($name, $reader, $expected): void {
        $t->same($expected[$name], $reader());
    };
}

$tests['btree interior redistribute current next70 rejects non auto vacuum current image'] = static function (TestRunner $t) use ($tableFixture): void {
    $plan = $tableFixture();
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $plan->currentDatabase->pageCount(); $pageNumber++) {
        $pages[] = $plan->currentDatabase->page($pageNumber);
    }
    $pages[0] = substr_replace($pages[0], pack('N', 0), 52, 4);

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorRedistributionCurrentNextPlan::tableInterior(SQLiteDatabase::fromBytes(implode('', $pages)), 7, 8, 3, 20));
};

return $tests;
