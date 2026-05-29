<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageCount): string {
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

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - $pointerMapPage - 1), 5);
};

$databaseForSlice = static function (int $sliceNumber) use ($makeFirstPage, $putPointerMapEntry): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, "_transient_next{$sliceNumber}", str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(134 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$rows = [];
foreach (range(959, 974) as $sliceNumber) {
    $database = $databaseForSlice($sliceNumber);
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);
    $method = "tableLeafFromDeleteResultNext{$sliceNumber}";
    $plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::{$method}(
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
    );
    $summary = $plan->currentSourceSummary();
    $rows[] = [
        'slice' => $sliceNumber,
        'status' => $summary['status'],
        'current_source_pages' => $summary['current_source_pages'],
        'current_source_leaf_pages' => $summary['current_source_leaf_pages'],
        'matches_freelist' => $summary['current_source_pages_match_freelist_pages'],
        'tail_pages_excluded' => $summary['all_tail_pages_remain_excluded_from_source'],
        'errors' => $summary['current_source_errors'],
    ];
}

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
