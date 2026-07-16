<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPageFinalBatch = static function (int $pageCount): string {
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

$putPointerMapEntryFinalBatch = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - $pointerMapPage - 1), 5);
};

$databaseFinalBatch = static function (int $sliceNumber) use ($makeFirstPageFinalBatch, $putPointerMapEntryFinalBatch): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPageFinalBatch(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'home', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, "_transient_timeout_next{$sliceNumber}", str_repeat('ttl:', 52)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'stylesheet', str_repeat('theme:', 12)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(161 + ($pageNumber - 106)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntryFinalBatch($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$planFinalBatch = static function (int $sliceNumber, int $batchSize = 2) use ($databaseFinalBatch): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    $database = $databaseFinalBatch($sliceNumber);
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafFreelistSpliceFromDeleteResult(
        $sliceNumber,
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

foreach (range(975, 990) as $sliceNumber) {
    $tests["btree vacuum pointermap freeblock final freelist splice slice {$sliceNumber} preserves handoff receipts"] = static function (TestRunner $t) use ($planFinalBatch, $sliceNumber): void {
        $plan = $planFinalBatch($sliceNumber);
        $summary = $plan->freelistSummary();
        $rows = $plan->freelistRows();

        $t->same("btree-vacuum-pointermap-freeblock-current-source-next{$sliceNumber}", $plan->toArray()['action']);
        $t->same("btree-vacuum-pointermap-freeblock-current-source-next{$sliceNumber}-ready", $summary['status']);
        $t->same(7, $summary['freelist_row_count']);
        $t->same([2, 3, 105, 106, 105, 107, 108], $summary['freelist_pages']);
        $t->same([2, 105], $summary['trunk_anchor_pages']);
        $t->same([3, 106, 107, 108], $summary['leaf_slot_pages']);
        $t->same([], $summary['freelist_errors']);
        $t->same(true, $summary['freelist_leaf_pages_match_vacuum']);
        $t->same(true, $summary['all_vacuum_tokens_preserved']);
        $t->same(true, $summary['all_trunks_seen_before_leaf_slots']);
        $t->same(true, $summary['all_leaf_slots_ordered']);
        $t->same(true, $summary['all_offsets_match_vacuum_finalization']);
        $t->same(true, $summary['all_tail_pages_rejected_from_freelist']);
        $t->same(true, $summary['all_freelist_links_valid']);
        $t->same("current-source-next{$sliceNumber}-freelist-splice-ready", $rows[0]['freelist_state']);
        $t->same(null, $rows[0]['previous_freelist_token']);
        $t->same($rows[0]['freelist_token'], $rows[1]['previous_freelist_token']);
        $t->same(true, isset($summary["current_source_next{$sliceNumber}_token"]));
        $t->contains('freelist splice receipts', $summary['dependency_closure']);
        $t->contains('does not repeat', $summary['non_overlap']);
        $t->same(6, $planFinalBatch($sliceNumber, 3)->freelistSummary()['freelist_row_count']);
    };
}

return $tests;
