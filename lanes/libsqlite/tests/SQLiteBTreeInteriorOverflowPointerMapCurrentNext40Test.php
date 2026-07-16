<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorMergeApplicationPlan;
use PortLibs\LibSqlite\SQLiteBTreeInteriorMergePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

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
    $page = substr_replace($page, pack('N', 2), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$fixture = static function (int $suffix = 0) use ($firstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pages = array_fill(1, 18, str_repeat("\0", $pageSize));
    $pages[1] = $firstPage(18, $pageSize);

    $leftPayload = SQLiteRecord::encode(['no', '_transient_left_' . $suffix, 10 + $suffix]);
    $dividerPayload = SQLiteRecord::encode(['no', '_transient_timeout_merge_' . $suffix, 20 + $suffix]);
    $overflowPayload = SQLiteRecord::encode(['yes', '_transient_overflowed_plugin_setting_' . $suffix, str_repeat('current-next40:', 70), 30 + $suffix]);
    $tailPayload = SQLiteRecord::encode(['yes', 'siteurl_' . $suffix, 40 + $suffix]);
    $encodedOverflow = SQLiteIndexCell::encodeWithOverflowPages($overflowPayload, 14, $pageSize, $pageSize, 12);
    $overflowPages = array_combine(range(14, 13 + count($encodedOverflow['overflowPages'])), $encodedOverflow['overflowPages']);

    $pages[3] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($dividerPayload, $pageSize, null, 7),
    ], 8, $pageSize);
    $pages[7] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($leftPayload, $pageSize, null, 10),
    ], 11, $pageSize);
    $pages[8] = SQLiteIndexInteriorPage::assemble([
        $encodedOverflow['cell'],
    ], 13, $pageSize);
    foreach ($overflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 7 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ([10 => 7, 11 => 7, 12 => 8, 13 => 8] as $pageNumber => $parent) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, $parent, $pageSize);
    }
    $putPointerMapEntry($pages, 14, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 8, $pageSize);
    $putPointerMapEntry($pages, 15, SQLitePointerMapEntry::OVERFLOW_PAGE, 14, $pageSize);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $overflowReader = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): string {
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
    $overflowPageNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
        $numbers = [];
        $pageNumber = $firstOverflowPage;
        while ($pageNumber !== 0 && count($numbers) < 8 && $byteCount > 0) {
            $page = $overflowPages[$pageNumber] ?? null;
            if ($page === null) {
                throw new InvalidArgumentException('Fixture overflow page is missing');
            }
            $numbers[] = $pageNumber;
            $pageNumber = unpack('N', substr($page, 0, 4))[1];
            $byteCount -= 508;
        }

        return $numbers;
    };

    $merge = SQLiteBTreeInteriorMergePlan::indexInterior(
        $database->page(7),
        $database->page(8),
        7,
        8,
        3,
        $dividerPayload,
        $pageSize,
        $pageSize,
        0,
        0,
        $overflowReader,
        $overflowPageNumbers,
    );
    $apply = SQLiteBTreeInteriorMergeApplicationPlan::apply($database, $merge, true);
    $postPages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $postPages[] = $apply->pageImages[$pageNumber] ?? $database->page($pageNumber);
    }
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

    return [
        'merge' => $merge,
        'apply' => $apply,
        'post' => $postDatabase,
        'overflowPayload' => $overflowPayload,
        'overflowReader' => $overflowReader,
    ];
};

$readers = [
    'merged page type' => static fn (array $f): string => $f['merge']->pageType,
    'merged page number' => static fn (array $f): int => $f['merge']->leftPageNumber,
    'obsolete sibling number' => static fn (array $f): int => $f['merge']->rightPageNumber,
    'merged child pages' => static fn (array $f): array => $f['merge']->mergedChildPageNumbers,
    'pointer map update pages include overflow chain' => static fn (array $f): array => array_keys($f['merge']->pointerMapUpdates),
    'first overflow update type' => static fn (array $f): int => $f['merge']->pointerMapUpdates[14]['type'],
    'first overflow update parent' => static fn (array $f): int => $f['merge']->pointerMapUpdates[14]['parent_page_number'],
    'next overflow update type' => static fn (array $f): int => $f['merge']->pointerMapUpdates[15]['type'],
    'next overflow update parent' => static fn (array $f): int => $f['merge']->pointerMapUpdates[15]['parent_page_number'],
    'child twelve update parent' => static fn (array $f): int => $f['merge']->pointerMapUpdates[12]['parent_page_number'],
    'child thirteen update parent' => static fn (array $f): int => $f['merge']->pointerMapUpdates[13]['parent_page_number'],
    'summary pointer map update pages' => static fn (array $f): array => $f['merge']->toArray()['pointer_map_update_pages'],
    'summary merged cell count' => static fn (array $f): int => $f['merge']->toArray()['merged_cell_count'],
    'summary removed page' => static fn (array $f): array => $f['merge']->toArray()['removed_page_numbers'],
    'apply updated pages include pointer map' => static fn (array $f): array => $f['apply']->updatedPageNumbers(),
    'apply freed pages' => static fn (array $f): array => $f['apply']->freePlan->freedPageNumbers,
    'post right sibling is free page' => static fn (array $f): string => $f['post']->pointerMapEntryForPage(8)->typeName(),
    'post first overflow type' => static fn (array $f): string => $f['post']->pointerMapEntryForPage(14)->typeName(),
    'post first overflow parent' => static fn (array $f): int => $f['post']->pointerMapEntryForPage(14)->parentPageNumber,
    'post next overflow type' => static fn (array $f): string => $f['post']->pointerMapEntryForPage(15)->typeName(),
    'post next overflow parent' => static fn (array $f): int => $f['post']->pointerMapEntryForPage(15)->parentPageNumber,
    'post child twelve parent' => static fn (array $f): int => $f['post']->pointerMapEntryForPage(12)->parentPageNumber,
    'post child thirteen parent' => static fn (array $f): int => $f['post']->pointerMapEntryForPage(13)->parentPageNumber,
    'merged header cell count' => static fn (array $f): int => SQLiteBTreePageHeader::parsePage($f['post']->page(7), 512)->cellCount,
    'merged overflow cell payload preserved' => static fn (array $f): bool => in_array($f['overflowPayload'], array_map(static fn (SQLiteIndexCell $cell): string => $cell->payload, SQLiteIndexCell::parsePageCells($f['post']->page(7), SQLiteBTreePageHeader::parsePage($f['post']->page(7), 512), 512, $f['overflowReader'])), true),
    'merged overflow first page preserved' => static fn (array $f): bool => in_array(14, array_map(static fn (SQLiteIndexCell $cell): ?int => $cell->firstOverflowPage, SQLiteIndexCell::parsePageCells($f['post']->page(7), SQLiteBTreePageHeader::parsePage($f['post']->page(7), 512), 512, $f['overflowReader'])), true),
];

$expected = [
    'merged page type' => 'index-interior',
    'merged page number' => 7,
    'obsolete sibling number' => 8,
    'merged child pages' => [10, 11, 12, 13],
    'pointer map update pages include overflow chain' => [10, 11, 12, 13, 14, 15],
    'first overflow update type' => SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE,
    'first overflow update parent' => 7,
    'next overflow update type' => SQLitePointerMapEntry::OVERFLOW_PAGE,
    'next overflow update parent' => 14,
    'child twelve update parent' => 7,
    'child thirteen update parent' => 7,
    'summary pointer map update pages' => [10, 11, 12, 13, 14, 15],
    'summary merged cell count' => 3,
    'summary removed page' => [8],
    'apply updated pages include pointer map' => [1, 2, 7, 8],
    'apply freed pages' => [8],
    'post right sibling is free page' => 'free-page',
    'post first overflow type' => 'first-overflow-page',
    'post first overflow parent' => 7,
    'post next overflow type' => 'overflow-page',
    'post next overflow parent' => 14,
    'post child twelve parent' => 7,
    'post child thirteen parent' => 7,
    'merged header cell count' => 3,
    'merged overflow cell payload preserved' => true,
    'merged overflow first page preserved' => true,
];

$tests = [];
foreach ($readers as $name => $reader) {
    $tests['btree interior overflow pointermap current next40 ' . $name] = static function (TestRunner $t) use ($fixture, $reader, $expected, $name): void {
        $t->same($expected[$name], $reader($fixture()));
    };
}

for ($index = 0; $index < 24; $index++) {
    $tests['btree interior overflow pointermap current next40 generated moved overflow case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($fixture, $index): void {
        $f = $fixture($index + 100);
        $t->same(7, $f['post']->pointerMapEntryForPage(14)->parentPageNumber);
        $t->same(14, $f['post']->pointerMapEntryForPage(15)->parentPageNumber);
    };
}

return $tests;
