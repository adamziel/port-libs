<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage799814 = static function (int $pageCount): string {
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

$putPointerMapEntry799814 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - $pointerMapPage - 1), 5);
};

$database799814 = static function (int $sliceNumber) use ($makeFirstPage799814, $putPointerMapEntry799814): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage799814(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, "_transient_timeout_next{$sliceNumber}", str_repeat('ttl:', 52)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'template', str_repeat('theme:', 12)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(129 + ($pageNumber - 106)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry799814($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan799814 = static function (int $sliceNumber, int $batchSize = 2) use ($database799814): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database799814($sliceNumber);
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
        str_repeat("next{$sliceNumber}-current-source-pointermap-freeblock-", 34),
        3,
        true,
        $batchSize,
    );
};

$tests = [];

foreach ([799, 800, 801, 802, 803, 804, 805, 806, 807, 808, 809, 810, 811, 812, 813, 814] as $sliceNumber) {
    $tests["btree vacuum pointermap freeblock current source next{$sliceNumber} extends next799-814 handoff receipts"] = static function (TestRunner $t) use ($plan799814, $sliceNumber): void {
        $plan = $plan799814($sliceNumber);
        $summary = $plan->currentSourceSummary();
        $rows = $plan->currentSourceRows();

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
        $t->same("current-source-next{$sliceNumber}-handoff-ready", $rows[0]['current_source_state']);
        $t->same(null, $rows[0]['previous_current_source_token']);
        $t->same($rows[0]['current_source_token'], $rows[1]['previous_current_source_token']);
        $t->same(true, isset($summary["current_source_next{$sliceNumber}_token"]));
        $t->contains('next431-446 freelist splice shape', $summary['dependency_closure']);
        $t->contains('does not repeat', $summary['non_overlap']);
        $t->same(6, $plan799814($sliceNumber, 3)->currentSourceSummary()['current_source_row_count']);
    };
}

return $tests;
