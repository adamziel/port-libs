<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage831846 = static function (int $pageCount): string {
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

$putPointerMapEntry831846 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - $pointerMapPage - 1), 5);
};

$database831846 = static function (int $sliceNumber) use ($makeFirstPage831846, $putPointerMapEntry831846): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage831846(110);
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
        $putPointerMapEntry831846($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan831846 = static function (int $sliceNumber, int $batchSize = 2) use ($database831846): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database831846($sliceNumber);
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafCurrentSourceFreelistHandoffFromDeleteResult(
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

foreach ([831, 832, 833, 834, 835, 836, 837, 838, 839, 840, 841, 842, 843, 844, 845, 846] as $sliceNumber) {
    $tests["btree vacuum pointermap freeblock current source next{$sliceNumber} extends next831-846 handoff receipts"] = static function (TestRunner $t) use ($plan831846, $sliceNumber): void {
        $plan = $plan831846($sliceNumber);
        $summary = $plan->currentSourceSummary();
        $rows = $plan->currentSourceRows();

        $t->same("btree-vacuum-pointermap-freeblock-current-source-freelist-handoff", $plan->toArray()['action']);
        $t->same("btree-vacuum-pointermap-freeblock-current-source-freelist-handoff-ready", $summary['status']);
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
        $t->same("current-source-freelist-handoff-ready", $rows[0]['current_source_state']);
        $t->same(null, $rows[0]['previous_current_source_token']);
        $t->same($rows[0]['current_source_token'], $rows[1]['previous_current_source_token']);
        $t->same(true, isset($summary["current_source_freelist_handoff_token"]));
        $t->contains('existing freelist splice shape', $summary['dependency_closure']);
        $t->contains('does not repeat', $summary['non_overlap']);
        $t->same(6, $plan831846($sliceNumber, 3)->currentSourceSummary()['current_source_row_count']);
    };
}

return $tests;
