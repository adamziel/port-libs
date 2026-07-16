<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPageFinal = static function (int $pageCount): string {
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

$putPointerMapEntryFinal = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - $pointerMapPage - 1), 5);
};

$databaseFinal = static function (int $caseNumber) use ($makeFirstPageFinal, $putPointerMapEntryFinal): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPageFinal(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'home', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, "_transient_timeout_final_{$caseNumber}", str_repeat('ttl:', 52)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'stylesheet', str_repeat('theme:', 12)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(145 + ($pageNumber - 106)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntryFinal($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$planFinal = static function (int $caseNumber, int $batchSize = 2) use ($databaseFinal): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    $database = $databaseFinal($caseNumber);
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafWriterHandoffFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('final-current-source-pointermap-freeblock-', 34),
        3,
        true,
        $batchSize,
    );
};

$tests = [];

foreach (range(1, 16) as $caseNumber) {
    $tests["btree vacuum pointermap freeblock final writer handoff batch case {$caseNumber} uses canonical writer handoff"] = static function (TestRunner $t) use ($planFinal, $caseNumber): void {
        $plan = $planFinal($caseNumber);
        $summary = $plan->handoffSummary();
        $rows = $plan->handoffRows();

        $t->same('btree-vacuum-pointermap-freeblock-current-source-final-handoff', $plan->toArray()['action']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-final-handoff-ready', $summary['status']);
        $t->same(7, $summary['handoff_row_count']);
        $t->same([2, 105, 105, 3, 106, 107, 108], $summary['handoff_pages']);
        $t->same([2, 105, 105], $summary['pointer_map_handoff_pages']);
        $t->same([3, 106, 107, 108], $summary['payload_handoff_pages']);
        $t->same([], $summary['handoff_errors']);
        $t->same(true, $summary['handoff_pages_match_seal_pages']);
        $t->same(true, $summary['pointer_map_handoffs_match_seals']);
        $t->same(true, $summary['payload_handoffs_match_seals']);
        $t->same(true, $summary['all_seal_tokens_match']);
        $t->same(true, $summary['all_current_source_tokens_match']);
        $t->same(true, $summary['all_pointer_maps_admitted_before_payload']);
        $t->same(true, $summary['all_tail_pages_fenced']);
        $t->same(true, $summary['all_freeblock_receipts_handed_off']);
        $t->same(true, $summary['all_leaf_freeblock_receipts_handed_off']);
        $t->same(true, $summary['all_handoff_offsets_contiguous']);
        $t->same('current-source-next-writer-admitted', $rows[0]['handoff_state']);
        $t->same(null, $rows[0]['previous_handoff_token']);
        $t->same($rows[0]['handoff_token'], $rows[1]['previous_handoff_token']);
        $t->same(true, isset($summary['current_source_final_handoff_token']));
        $t->contains('final-handoff reuses publication seals', $summary['dependency_closure']);
        $t->contains('does not repeat', $summary['non_overlap']);
        $t->same(6, $planFinal($caseNumber, 3)->handoffSummary()['handoff_row_count']);
    };
}

return $tests;
