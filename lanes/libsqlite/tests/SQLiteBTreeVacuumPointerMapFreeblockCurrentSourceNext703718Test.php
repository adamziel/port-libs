<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage703718 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry703718 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - $pointerMapPage - 1), 5);
};

$database703718 = static function (int $sliceNumber) use ($makeFirstPage703718, $putPointerMapEntry703718): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage703718(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, "_transient_next{$sliceNumber}", str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(118 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry703718($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan703718 = static function (int $sliceNumber, int $batchSize = 2) use ($database703718): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database703718($sliceNumber);
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);
    $method = "tableLeafFromDeleteResultNext{$sliceNumber}";

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::{$method}(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat("next{$sliceNumber}-current-source-followon-handoff-", 36),
        3,
        true,
        $batchSize,
    );
};

$tests = [];

foreach ([703, 704, 705, 706, 707, 708, 709, 710, 711, 712, 713, 714, 715, 716, 717, 718] as $sliceNumber) {
    $tests["btree vacuum pointermap freeblock current source next{$sliceNumber} publishes next703-718 follow-on handoff receipts"] = static function (TestRunner $t) use ($plan703718, $sliceNumber): void {
        $plan = $plan703718($sliceNumber);
        $summary = $plan->currentSourceSummary();

        $t->same("btree-vacuum-pointermap-freeblock-current-source-next{$sliceNumber}", $plan->toArray()['action']);
        $t->same("btree-vacuum-pointermap-freeblock-current-source-next{$sliceNumber}-ready", $summary['status']);
        $t->same(7, $summary['current_source_row_count']);
        $t->same([2, 3, 105, 106, 105, 107, 108], $summary['current_source_pages']);
        $t->same([3, 106, 107, 108], $summary['current_source_leaf_pages']);
        $t->same([2, 3, 105, 106, 105, 107, 108], $summary['freelist_pages']);
        $t->same([], $summary['current_source_errors']);
        $t->same(true, $summary['current_source_pages_match_freelist_pages']);
        $t->same(true, $summary['current_source_leaf_pages_match_freelist_leaf_pages']);
        $t->same(true, $summary['all_freelist_tokens_preserved']);
        $t->same(true, $summary['all_trunk_receipts_publish_before_leaf_receipts']);
        $t->same(true, $summary['all_leaf_receipts_current_at_source']);
        $t->same(true, $summary['all_tail_pages_remain_excluded_from_source']);
        $t->same(true, $summary['all_current_source_links_valid']);
        $t->same(true, isset($summary["current_source_next{$sliceNumber}_token"]));
        $t->contains('next431-446 freelist splice shape', $summary['dependency_closure']);
        $t->contains('does not repeat', $summary['non_overlap']);
        $t->same(6, $plan703718($sliceNumber, 3)->currentSourceSummary()['current_source_row_count']);
    };
}

return $tests;
