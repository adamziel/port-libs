<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage154 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 0), 32, 4);
    $page = substr_replace($page, pack('N', 0), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry154 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$overflowPage154 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database154 = static function (bool $mismatchedCurrentNext = false) use ($makeFirstPage154, $putPointerMapEntry154, $overflowPage154): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage154(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_timeout_next154', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 24)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage154(107, 'A');
    $pages[107] = $overflowPage154($mismatchedCurrentNext ? 109 : 108, 'B');
    $pages[108] = $overflowPage154(109, 'C');
    $pages[109] = $overflowPage154(110, 'D');
    $pages[110] = $overflowPage154(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry154($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan154 = static function (int $maxTruncatedPages = 4, bool $mismatchedCurrentNext = false) use ($database154): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    $database = $database154($mismatchedCurrentNext);
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafOverflowChainAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        true,
    );
};

$message154 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases154 = [
    'action label' => static fn (): mixed => $plan154()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan154()->toArray()['released_overflow_pages'],
    'surviving released pages' => static fn (): mixed => $plan154()->toArray()['surviving_released_overflow_pages'],
    'truncated released pages' => static fn (): mixed => $plan154()->toArray()['truncated_released_overflow_pages'],
    'truncated pointer map pages empty for partial vacuum' => static fn (): mixed => $plan154()->toArray()['truncated_pointer_map_pages'],
    'surviving current source next pages' => static fn (): mixed => $plan154()->survivingCurrentSourceNextPages(),
    'mismatched current source pages empty' => static fn (): mixed => $plan154()->mismatchedCurrentSourceNextPages(),
    'row page numbers' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'page_number'),
    'row current next pointers' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'current_source_next_page'),
    'row expected next pointers' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'expected_next_page_from_delete_chain'),
    'row current next status' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'current_source_next_status'),
    'row current pointer types' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'current_pointer_map_type'),
    'row current pointer parents' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'current_pointer_map_parent'),
    'row post vacuum materialized' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'post_vacuum_materialized'),
    'row post vacuum next pointers' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'post_vacuum_next_page'),
    'row post vacuum statuses' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'post_vacuum_status'),
    'row next targets released page' => static fn (): mixed => array_column($plan154()->currentSourceRows(), 'next_pointer_targets_released_page'),
    'continuity released count' => static fn (): mixed => $plan154()->chainContinuitySummary()['released_page_count'],
    'continuity next pages' => static fn (): mixed => $plan154()->chainContinuitySummary()['current_source_next_pages'],
    'continuity ok flag' => static fn (): mixed => $plan154()->chainContinuitySummary()['chain_is_contiguous'],
    'continuity tail flag' => static fn (): mixed => $plan154()->chainContinuitySummary()['tail_terminates_chain'],
    'continuity surviving pages' => static fn (): mixed => $plan154()->chainContinuitySummary()['surviving_materialized_pages'],
    'continuity mismatch count' => static fn (): mixed => $plan154(4, true)->chainContinuitySummary()['mismatched_page_count'],
    'continuity mismatch pages' => static fn (): mixed => $plan154(4, true)->chainContinuitySummary()['mismatched_pages'],
    'freeblock leaf page' => static fn (): mixed => $plan154()->freeblockSummary()['leaf_page'],
    'freeblock leaf page type' => static fn (): mixed => $plan154()->freeblockSummary()['leaf_page_type'],
    'freeblock fragmented bytes' => static fn (): mixed => $plan154()->freeblockSummary()['fragmented_free_bytes'],
    'freeblock cell count after delete' => static fn (): mixed => $plan154()->freeblockSummary()['cell_count_after_delete'],
    'freeblock integrity status' => static fn (): mixed => $plan154()->freeblockSummary()['integrity_status'],
    'freeblock count' => static fn (): mixed => $plan154()->freeblockSummary()['freeblock_count'],
    'freeblock total bytes after compaction' => static fn (): mixed => $plan154()->freeblockSummary()['freeblock_total_bytes'],
    'summary current rows count' => static fn (): mixed => count($plan154()->toArray()['current_source_next_rows']),
    'summary freeblock status' => static fn (): mixed => $plan154()->toArray()['freeblock_summary']['integrity_status'],
    'wide vacuum truncated pointer map pages' => static fn (): mixed => $plan154(6)->toArray()['truncated_pointer_map_pages'],
    'wide vacuum surviving current source pages' => static fn (): mixed => $plan154(6)->survivingCurrentSourceNextPages(),
    'mismatch page surfaced' => static fn (): mixed => $plan154(4, true)->mismatchedCurrentSourceNextPages(),
    'mismatch statuses surfaced' => static fn (): mixed => array_column($plan154(4, true)->currentSourceRows(), 'current_source_next_status'),
    'zero truncation rejected' => static fn (): mixed => $message154(static fn () => $plan154(0)),
];

$expected154 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next154',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'surviving released pages' => [106],
    'truncated released pages' => [107, 108, 109, 110],
    'truncated pointer map pages empty for partial vacuum' => [],
    'surviving current source next pages' => [106],
    'mismatched current source pages empty' => [],
    'row page numbers' => [106, 107, 108, 109, 110],
    'row current next pointers' => [107, 108, 109, 110, 0],
    'row expected next pointers' => [107, 108, 109, 110, 0],
    'row current next status' => array_fill(0, 5, 'matches-delete-chain'),
    'row current pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'row current pointer parents' => [3, 106, 107, 108, 109],
    'row post vacuum materialized' => [true, false, false, false, false],
    'row post vacuum next pointers' => [0, null, null, null, null],
    'row post vacuum statuses' => ['survives-as-freelist-page', 'truncated-by-vacuum', 'truncated-by-vacuum', 'truncated-by-vacuum', 'truncated-by-vacuum'],
    'row next targets released page' => [true, true, true, true, false],
    'continuity released count' => 5,
    'continuity next pages' => [107, 108, 109, 110, 0],
    'continuity ok flag' => true,
    'continuity tail flag' => true,
    'continuity surviving pages' => [106],
    'continuity mismatch count' => 1,
    'continuity mismatch pages' => [107],
    'freeblock leaf page' => 3,
    'freeblock leaf page type' => 'table-leaf',
    'freeblock fragmented bytes' => 0,
    'freeblock cell count after delete' => 2,
    'freeblock integrity status' => 'ok',
    'freeblock count' => 0,
    'freeblock total bytes after compaction' => 0,
    'summary current rows count' => 5,
    'summary freeblock status' => 'ok',
    'wide vacuum truncated pointer map pages' => [105],
    'wide vacuum surviving current source pages' => [],
    'mismatch page surfaced' => [107],
    'mismatch statuses surfaced' => ['matches-delete-chain', 'differs-from-delete-chain', 'matches-delete-chain', 'matches-delete-chain', 'matches-delete-chain'],
    'zero truncation rejected' => 'SQLite pointer-map vacuum freeblock next127 requires a positive truncation limit',
];

foreach ($cases154 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next154 ' . $name] = static function (TestRunner $t) use ($callback, $expected154, $name): void {
        $t->same($expected154[$name], $callback());
    };
}

foreach (range(1, 34) as $index) {
    $tests['btree vacuum pointermap freeblock current source next154 invariant ' . $index] = static function (TestRunner $t) use ($plan154): void {
        $plan = $plan154();
        $rows = $plan->currentSourceRows();

        $t->same([106, 107, 108, 109, 110], array_column($rows, 'page_number'));
        $t->same([107, 108, 109, 110, 0], array_column($rows, 'current_source_next_page'));
        $t->same([], $plan->mismatchedCurrentSourceNextPages());
        $t->same([106], $plan->survivingCurrentSourceNextPages());
        $t->same('ok', $plan->freeblockSummary()['integrity_status']);
        $t->same([106], $plan->basePlan->survivingReleasedOverflowPages());
    };
}

return $tests;
