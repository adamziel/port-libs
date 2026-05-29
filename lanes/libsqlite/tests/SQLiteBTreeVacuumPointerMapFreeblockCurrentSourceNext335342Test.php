<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage335342 = static function (int $pageCount): string {
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

$putPointerMapEntry335342 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - $pointerMapPage - 1), 5);
};

$database335342 = static function (int $sliceNumber) use ($makeFirstPage335342, $putPointerMapEntry335342): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage335342(110);
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
        $putPointerMapEntry335342($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan335342 = static function (int $sliceNumber, int $batchSize = 2) use ($database335342): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database335342($sliceNumber);
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);
    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFreelistSpliceFromDeleteResult(
        $sliceNumber,
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat("next{$sliceNumber}-current-source-final-splice-", 36),
        3,
        true,
        $batchSize,
    );
};

$tests = [];

foreach ([335, 336, 337, 338, 339, 340, 341, 342] as $sliceNumber) {
    $tests["btree vacuum pointermap freeblock current source next{$sliceNumber} keeps final next335-342 freelist splice isolated"] = static function (TestRunner $t) use ($plan335342, $sliceNumber): void {
        $plan = $plan335342($sliceNumber);
        $summary = $plan->freelistSummary();

        $t->same("btree-vacuum-pointermap-freeblock-current-source-next{$sliceNumber}", $plan->toArray()['action']);
        $t->same("btree-vacuum-pointermap-freeblock-current-source-next{$sliceNumber}-ready", $summary['status']);
        $t->same(7, $summary['freelist_row_count']);
        $t->same([2, 3, 105, 106, 105, 107, 108], $summary['freelist_pages']);
        $t->same([2, 105], $summary['trunk_anchor_pages']);
        $t->same([3, 106, 107, 108], $summary['leaf_slot_pages']);
        $t->same([2 => [3], 105 => [106, 107, 108]], $summary['leaf_slots_by_trunk']);
        $t->same([40, 128, 144, 160], $summary['freelist_write_offsets']);
        $t->same([], $summary['freelist_errors']);
        $t->same(true, $summary['all_vacuum_tokens_preserved']);
        $t->same(true, $summary['all_trunks_seen_before_leaf_slots']);
        $t->same(true, $summary['all_leaf_slots_ordered']);
        $t->same(true, $summary['all_offsets_match_vacuum_finalization']);
        $t->same(true, $summary['all_tail_pages_rejected_from_freelist']);
        $t->same(true, $summary['all_freelist_links_valid']);
        $t->same(true, isset($summary["current_source_next{$sliceNumber}_token"]));
        $t->contains("next{$sliceNumber} reuses next261", $summary['dependency_closure']);
        $t->contains('does not repeat next261', $summary['non_overlap']);
        $t->same(6, $plan335342($sliceNumber, 3)->freelistSummary()['freelist_row_count']);
    };
}

return $tests;
