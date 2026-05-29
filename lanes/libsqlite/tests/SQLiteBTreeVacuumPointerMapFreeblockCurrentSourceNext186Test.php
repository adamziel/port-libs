<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage186 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
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

$putPointerMapEntry186 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database186 = static function () use ($makeFirstPage186, $putPointerMapEntry186): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage186(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next186', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(80 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry186($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan186 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database186;

    $database = $database186();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext186(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next186-current-source-cursor-', 50),
        3,
        true,
        $batchSize,
    );
};

$message186 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases186 = [
    'action label' => static fn (): mixed => $plan186()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan186()->cursorSummary()['status'],
    'leaf page' => static fn (): mixed => $plan186()->cursorSummary()['leaf_page'],
    'cursor row count' => static fn (): mixed => $plan186()->cursorSummary()['cursor_row_count'],
    'visible current-source pages' => static fn (): mixed => $plan186()->visibleCurrentSourcePages(),
    'visible pointer-map pages' => static fn (): mixed => $plan186()->visiblePointerMapPages(),
    'visible leaf freeblock pages' => static fn (): mixed => $plan186()->visibleLeafFreeblockPages(),
    'visible overflow pages' => static fn (): mixed => $plan186()->visibleOverflowPages(),
    'fenced pages visible' => static fn (): mixed => $plan186()->cursorSummary()['fenced_pages_visible'],
    'cursor errors' => static fn (): mixed => $plan186()->cursorErrors(),
    'summary cursor errors' => static fn (): mixed => $plan186()->cursorSummary()['cursor_errors'],
    'all visibility rows' => static fn (): mixed => $plan186()->cursorSummary()['all_rows_have_pointer_map_or_page_visibility'],
    'all rows hide deleted cell' => static fn (): mixed => $plan186()->cursorSummary()['all_rows_hide_deleted_cell'],
    'all rows hide fenced pages' => static fn (): mixed => $plan186()->cursorSummary()['all_rows_hide_fenced_pages'],
    'resume token count' => static fn (): mixed => count($plan186()->resumeTokens()),
    'resume token lengths' => static fn (): mixed => array_map('strlen', $plan186()->resumeTokens()),
    'resume signature length' => static fn (): mixed => strlen($plan186()->cursorSummary()['resume_signature']),
    'current source revision length' => static fn (): mixed => strlen($plan186()->cursorSummary()['current_source_revision']),
    'first row pointer maps' => static fn (): mixed => $plan186()->cursorRows()[0]['visible_pointer_map_pages'],
    'first row visible pages' => static fn (): mixed => $plan186()->cursorRows()[0]['visible_current_source_pages'],
    'first row leaf freeblock' => static fn (): mixed => $plan186()->cursorRows()[0]['visible_leaf_freeblock_pages'],
    'first row overflow count' => static fn (): mixed => $plan186()->cursorRows()[0]['overflow_visible_count'],
    'second row pointer maps' => static fn (): mixed => $plan186()->cursorRows()[1]['visible_pointer_map_pages'],
    'second row visible pages' => static fn (): mixed => $plan186()->cursorRows()[1]['visible_current_source_pages'],
    'second row overflow pages' => static fn (): mixed => $plan186()->cursorRows()[1]['visible_overflow_pages'],
    'third row visible pages' => static fn (): mixed => $plan186()->cursorRows()[2]['visible_current_source_pages'],
    'third row overflow pages' => static fn (): mixed => $plan186()->cursorRows()[2]['visible_overflow_pages'],
    'row leaf visibility flags' => static fn (): mixed => array_column($plan186()->cursorRows(), 'leaf_freeblock_visible'),
    'row deleted hidden flags' => static fn (): mixed => array_column($plan186()->cursorRows(), 'deleted_cell_hidden'),
    'row fenced hidden flags' => static fn (): mixed => array_column($plan186()->cursorRows(), 'fenced_pages_hidden'),
    'row visibility flags' => static fn (): mixed => array_column($plan186()->cursorRows(), 'has_pointer_map_or_page_visibility'),
    'row receipt kinds' => static fn (): mixed => array_column($plan186()->cursorRows(), 'receipt_kinds'),
    'row hash counts' => static fn (): mixed => array_map('count', array_column($plan186()->cursorRows(), 'page_hashes')),
    'row hash lengths' => static fn (): mixed => array_map(static fn (array $hashes): array => array_map('strlen', $hashes), array_column($plan186()->cursorRows(), 'page_hashes')),
    'batch size three row count' => static fn (): mixed => $plan186(3)->cursorSummary()['cursor_row_count'],
    'batch size three visible pages' => static fn (): mixed => array_column($plan186(3)->cursorRows(), 'visible_current_source_pages'),
    'batch size three resume count' => static fn (): mixed => count($plan186(3)->resumeTokens()),
    'dependency closure' => static fn (): mixed => str_contains($plan186()->cursorSummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan186()->cursorSummary()['non_overlap'], 'does not repeat next183'),
    'bad batch size rejected' => static fn (): mixed => $message186(static fn () => $plan186(0)),
];

$expected186 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next186',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next186-ready',
    'leaf page' => 3,
    'cursor row count' => 3,
    'visible current-source pages' => [1, 2, 3, 105, 106, 107, 108],
    'visible pointer-map pages' => [2, 105],
    'visible leaf freeblock pages' => [3],
    'visible overflow pages' => [106, 107, 108],
    'fenced pages visible' => [],
    'cursor errors' => [],
    'summary cursor errors' => [],
    'all visibility rows' => true,
    'all rows hide deleted cell' => true,
    'all rows hide fenced pages' => true,
    'resume token count' => 3,
    'resume token lengths' => [64, 64, 64],
    'resume signature length' => 64,
    'current source revision length' => 64,
    'first row pointer maps' => [2],
    'first row visible pages' => [1, 2, 3],
    'first row leaf freeblock' => [3],
    'first row overflow count' => 0,
    'second row pointer maps' => [105],
    'second row visible pages' => [105, 106],
    'second row overflow pages' => [106],
    'third row visible pages' => [105, 107, 108],
    'third row overflow pages' => [107, 108],
    'row leaf visibility flags' => [true, false, false],
    'row deleted hidden flags' => [true, true, true],
    'row fenced hidden flags' => [true, true, true],
    'row visibility flags' => [true, true, true],
    'row receipt kinds' => [
        ['pointer-map-before-page-image', 'leaf-freeblock-current-source'],
        ['pointer-map-before-page-image', 'overflow-page-image-current-source'],
        ['pointer-map-before-page-image', 'overflow-page-image-current-source'],
    ],
    'row hash counts' => [2, 2, 2],
    'row hash lengths' => [[64, 64], [64, 64], [64, 64]],
    'batch size three row count' => 2,
    'batch size three visible pages' => [[1, 2, 3, 105], [105, 106, 107, 108]],
    'batch size three resume count' => 2,
    'dependency closure' => true,
    'non overlap' => true,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases186 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next186 ' . $name] = static function (TestRunner $t) use ($callback, $expected186, $name): void {
        $t->same($expected186[$name], $callback());
    };
}

foreach (range(1, 45) as $index) {
    $tests['btree vacuum pointermap freeblock current source next186 cursor invariant ' . $index] = static function (TestRunner $t) use ($plan186): void {
        $plan = $plan186();
        $summary = $plan->cursorSummary();

        $t->same([], $plan->cursorErrors());
        $t->same([1, 2, 3, 105, 106, 107, 108], $plan->visibleCurrentSourcePages());
        $t->same([2, 105], $plan->visiblePointerMapPages());
        $t->same([3], $plan->visibleLeafFreeblockPages());
        $t->same([106, 107, 108], $plan->visibleOverflowPages());
        $t->same([], $summary['fenced_pages_visible']);
        $t->same(true, $summary['all_rows_have_pointer_map_or_page_visibility']);
        $t->same(true, $summary['all_rows_hide_deleted_cell']);
        $t->same(true, $summary['all_rows_hide_fenced_pages']);
        $t->same([64, 64, 64], array_map('strlen', $plan->resumeTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next186-ready', $summary['status']);
    };
}

return $tests;
