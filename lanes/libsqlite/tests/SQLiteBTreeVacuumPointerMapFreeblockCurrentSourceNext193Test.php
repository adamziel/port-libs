<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage193 = static function (int $pageCount): string {
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

$putPointerMapEntry193 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database193 = static function () use ($makeFirstPage193, $putPointerMapEntry193): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage193(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next193', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(74 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry193($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan193 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database193;

    $database = $database193();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext193(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next193-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message193 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases193 = [
    'action label' => static fn (): mixed => $plan193()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan193()->manifestSummary()['status'],
    'manifest errors' => static fn (): mixed => $plan193()->manifestErrors(),
    'summary errors' => static fn (): mixed => $plan193()->manifestSummary()['manifest_errors'],
    'manifest row count' => static fn (): mixed => $plan193()->manifestSummary()['manifest_row_count'],
    'published pages' => static fn (): mixed => $plan193()->publishedPages(),
    'summary published pages' => static fn (): mixed => $plan193()->manifestSummary()['published_pages'],
    'fenced tail pages' => static fn (): mixed => $plan193()->fencedTailPages(),
    'summary fenced tail pages' => static fn (): mixed => $plan193()->manifestSummary()['fenced_tail_pages'],
    'final visible page count' => static fn (): mixed => $plan193()->manifestSummary()['final_visible_page_count'],
    'all manifest tokens unique' => static fn (): mixed => $plan193()->manifestSummary()['all_manifest_tokens_unique'],
    'all checkpoints preserve order' => static fn (): mixed => $plan193()->manifestSummary()['all_checkpoints_preserve_order'],
    'all tail pages fenced' => static fn (): mixed => $plan193()->manifestSummary()['all_tail_pages_fenced'],
    'all published pages readable' => static fn (): mixed => $plan193()->manifestSummary()['all_published_pages_readable'],
    'all pointer maps precede payload' => static fn (): mixed => $plan193()->manifestSummary()['all_pointer_maps_precede_payload'],
    'manifest token count' => static fn (): mixed => count($plan193()->manifestTokens()),
    'manifest token lengths' => static fn (): mixed => array_map('strlen', $plan193()->manifestTokens()),
    'manifest signature length' => static fn (): mixed => strlen($plan193()->manifestSummary()['manifest_signature']),
    'reader restart token length' => static fn (): mixed => strlen($plan193()->manifestSummary()['reader_restart_token']),
    'checkpoint signature length' => static fn (): mixed => strlen($plan193()->manifestSummary()['checkpoint_signature']),
    'row orders' => static fn (): mixed => array_column($plan193()->manifestRows(), 'manifest_order'),
    'row batch indexes' => static fn (): mixed => array_column($plan193()->manifestRows(), 'checkpoint_batch_index'),
    'row order flags' => static fn (): mixed => array_column($plan193()->manifestRows(), 'checkpoint_order_preserved'),
    'row states' => static fn (): mixed => array_column($plan193()->manifestRows(), 'manifest_state'),
    'row published page counts' => static fn (): mixed => array_column($plan193()->manifestRows(), 'published_page_count'),
    'row high-water pages' => static fn (): mixed => array_column($plan193()->manifestRows(), 'current_source_high_water_page'),
    'row published pages' => static fn (): mixed => array_column($plan193()->manifestRows(), 'published_pages'),
    'row fenced tails' => static fn (): mixed => array_column($plan193()->manifestRows(), 'fenced_tail_pages'),
    'row pointer maps' => static fn (): mixed => array_column($plan193()->manifestRows(), 'visible_pointer_map_pages'),
    'row payload pages' => static fn (): mixed => array_column($plan193()->manifestRows(), 'visible_payload_pages'),
    'row pointer map flags' => static fn (): mixed => array_column($plan193()->manifestRows(), 'pointer_map_before_payload'),
    'row tail flags' => static fn (): mixed => array_column($plan193()->manifestRows(), 'tail_pages_fenced'),
    'row readable flags' => static fn (): mixed => array_column($plan193()->manifestRows(), 'published_pages_readable'),
    'row unique flags' => static fn (): mixed => array_column($plan193()->manifestRows(), 'manifest_token_unique'),
    'base action' => static fn (): mixed => $plan193()->basePlan->toArray()['action'],
    'base status' => static fn (): mixed => $plan193()->basePlan->checkpointSummary()['status'],
    'base newly visible pages' => static fn (): mixed => $plan193()->basePlan->newlyVisiblePages(),
    'base high-water pages' => static fn (): mixed => $plan193()->basePlan->cumulativeHighWaterPages(),
    'batch size three rows' => static fn (): mixed => $plan193(3)->manifestSummary()['manifest_row_count'],
    'batch size three published' => static fn (): mixed => array_column($plan193(3)->manifestRows(), 'published_pages'),
    'batch size three high water' => static fn (): mixed => array_column($plan193(3)->manifestRows(), 'current_source_high_water_page'),
    'batch size three token count' => static fn (): mixed => count($plan193(3)->manifestTokens()),
    'dependency closure' => static fn (): mixed => str_contains($plan193()->manifestSummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan193()->manifestSummary()['non_overlap'], 'does not repeat next189 checkpoint construction'),
    'bad batch size rejected' => static fn (): mixed => $message193(static fn () => $plan193(0)),
];

$expected193 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next193',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next193-ready',
    'manifest errors' => [],
    'summary errors' => [],
    'manifest row count' => 3,
    'published pages' => [1, 2, 3, 105, 106, 107, 108],
    'summary published pages' => [1, 2, 3, 105, 106, 107, 108],
    'fenced tail pages' => [109, 110],
    'summary fenced tail pages' => [109, 110],
    'final visible page count' => 108,
    'all manifest tokens unique' => true,
    'all checkpoints preserve order' => true,
    'all tail pages fenced' => true,
    'all published pages readable' => true,
    'all pointer maps precede payload' => true,
    'manifest token count' => 3,
    'manifest token lengths' => [64, 64, 64],
    'manifest signature length' => 64,
    'reader restart token length' => 64,
    'checkpoint signature length' => 64,
    'row orders' => [0, 1, 2],
    'row batch indexes' => [0, 1, 2],
    'row order flags' => [true, true, true],
    'row states' => ['current-source-manifest-published', 'current-source-manifest-published', 'current-source-manifest-published'],
    'row published page counts' => [3, 2, 2],
    'row high-water pages' => [3, 106, 108],
    'row published pages' => [[1, 2, 3], [105, 106], [107, 108]],
    'row fenced tails' => [[109, 110], [109, 110], [109, 110]],
    'row pointer maps' => [[2], [105], [105]],
    'row payload pages' => [[3], [106], [107, 108]],
    'row pointer map flags' => [true, true, true],
    'row tail flags' => [true, true, true],
    'row readable flags' => [true, true, true],
    'row unique flags' => [true, true, true],
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next189',
    'base status' => 'btree-vacuum-pointermap-freeblock-current-source-next189-ready',
    'base newly visible pages' => [1, 2, 3, 105, 106, 107, 108],
    'base high-water pages' => [3, 106, 108],
    'batch size three rows' => 2,
    'batch size three published' => [[1, 2, 3, 105], [106, 107, 108]],
    'batch size three high water' => [105, 108],
    'batch size three token count' => 2,
    'dependency closure' => true,
    'non overlap' => true,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases193 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next193 ' . $name] = static function (TestRunner $t) use ($callback, $expected193, $name): void {
        $t->same($expected193[$name], $callback());
    };
}

foreach (range(1, 55) as $index) {
    $tests['btree vacuum pointermap freeblock current source next193 manifest invariant ' . $index] = static function (TestRunner $t) use ($plan193): void {
        $plan = $plan193();
        $summary = $plan->manifestSummary();

        $t->same([], $plan->manifestErrors());
        $t->same([1, 2, 3, 105, 106, 107, 108], $plan->publishedPages());
        $t->same([109, 110], $plan->fencedTailPages());
        $t->same(3, $summary['manifest_row_count']);
        $t->same(108, $summary['final_visible_page_count']);
        $t->same(true, $summary['all_manifest_tokens_unique']);
        $t->same(true, $summary['all_checkpoints_preserve_order']);
        $t->same(true, $summary['all_tail_pages_fenced']);
        $t->same(true, $summary['all_published_pages_readable']);
        $t->same(true, $summary['all_pointer_maps_precede_payload']);
        $t->same([64, 64, 64], array_map('strlen', $plan->manifestTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next193-ready', $summary['status']);
    };
}

return $tests;
