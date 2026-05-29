<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage183 = static function (int $pageCount): string {
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

$putPointerMapEntry183 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database183 = static function () use ($makeFirstPage183, $putPointerMapEntry183): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage183(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next183', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(85 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry183($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan183 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database183;

    $database = $database183();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext183(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next183-current-source-apply-', 50),
        3,
        true,
        $batchSize,
    );
};

$message183 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases183 = [
    'action label' => static fn (): mixed => $plan183()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan183()->commitSummary()['status'],
    'leaf page' => static fn (): mixed => $plan183()->commitSummary()['leaf_page'],
    'batch count' => static fn (): mixed => $plan183()->commitSummary()['batch_count'],
    'committed page images' => static fn (): mixed => $plan183()->committedPageImages(),
    'committed pointer map pages' => static fn (): mixed => $plan183()->committedPointerMapPages(),
    'committed leaf freeblock pages' => static fn (): mixed => $plan183()->committedLeafFreeblockPages(),
    'committed overflow pages' => static fn (): mixed => $plan183()->committedOverflowPages(),
    'committed fenced pages' => static fn (): mixed => $plan183()->committedFencedPages(),
    'commit errors' => static fn (): mixed => $plan183()->commitErrors(),
    'receipt count' => static fn (): mixed => $plan183()->commitSummary()['receipt_count'],
    'all pointer maps precede pages' => static fn (): mixed => $plan183()->commitSummary()['all_pointer_maps_precede_pages'],
    'all page hashes present' => static fn (): mixed => $plan183()->commitSummary()['all_page_hashes_present'],
    'first row pointer maps' => static fn (): mixed => $plan183()->commitRows()[0]['pointer_map_pages'],
    'first row page images' => static fn (): mixed => $plan183()->commitRows()[0]['page_image_pages'],
    'first row leaf receipt pages' => static fn (): mixed => $plan183()->commitRows()[0]['leaf_freeblock_pages'],
    'first row receipt kinds' => static fn (): mixed => $plan183()->commitRows()[0]['receipt_kinds'],
    'second row pointer maps' => static fn (): mixed => $plan183()->commitRows()[1]['pointer_map_pages'],
    'second row page images' => static fn (): mixed => $plan183()->commitRows()[1]['page_image_pages'],
    'second row overflow pages' => static fn (): mixed => $plan183()->commitRows()[1]['overflow_page_images'],
    'third row page images' => static fn (): mixed => $plan183()->commitRows()[2]['page_image_pages'],
    'third row overflow pages' => static fn (): mixed => $plan183()->commitRows()[2]['overflow_page_images'],
    'row receipt counts' => static fn (): mixed => array_column($plan183()->commitRows(), 'receipt_count'),
    'row hash counts' => static fn (): mixed => array_map('count', array_column($plan183()->commitRows(), 'page_hashes')),
    'row hash lengths' => static fn (): mixed => array_map(static fn (array $hashes): array => array_map('strlen', $hashes), array_column($plan183()->commitRows(), 'page_hashes')),
    'row fenced flags' => static fn (): mixed => array_column($plan183()->commitRows(), 'contains_fenced_page'),
    'row deleted visibility flags' => static fn (): mixed => array_column($plan183()->commitRows(), 'deleted_cell_visible_to_next'),
    'summary final page count' => static fn (): mixed => $plan183()->commitSummary()['final_database_page_count'],
    'summary dependencies' => static fn (): mixed => $plan183()->commitSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => str_contains($plan183()->commitSummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan183()->commitSummary()['non_overlap'], 'does not repeat next180'),
    'receipt signature length' => static fn (): mixed => strlen($plan183()->commitSummary()['current_source_receipt_signature']),
    'freeblock signature length' => static fn (): mixed => strlen($plan183()->commitSummary()['freeblock_receipt_signature']),
    'batch size three committed pages' => static fn (): mixed => $plan183(3)->committedPageImages(),
    'batch size three pointer maps' => static fn (): mixed => $plan183(3)->committedPointerMapPages(),
    'batch size three row count' => static fn (): mixed => count($plan183(3)->commitRows()),
    'batch size three row page images' => static fn (): mixed => array_column($plan183(3)->commitRows(), 'page_image_pages'),
    'bad batch size rejected' => static fn (): mixed => $message183(static fn () => $plan183(0)),
];

$expected183 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next183',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next183-ready',
    'leaf page' => 3,
    'batch count' => 3,
    'committed page images' => [1, 3, 105, 106, 107, 108],
    'committed pointer map pages' => [2, 105],
    'committed leaf freeblock pages' => [3],
    'committed overflow pages' => [106, 107, 108],
    'committed fenced pages' => [],
    'commit errors' => [],
    'receipt count' => 6,
    'all pointer maps precede pages' => true,
    'all page hashes present' => true,
    'first row pointer maps' => [2],
    'first row page images' => [1, 3],
    'first row leaf receipt pages' => [3],
    'first row receipt kinds' => ['pointer-map-before-page-image', 'leaf-freeblock-current-source'],
    'second row pointer maps' => [105],
    'second row page images' => [105, 106],
    'second row overflow pages' => [106],
    'third row page images' => [107, 108],
    'third row overflow pages' => [107, 108],
    'row receipt counts' => [2, 2, 2],
    'row hash counts' => [2, 2, 2],
    'row hash lengths' => [[64, 64], [64, 64], [64, 64]],
    'row fenced flags' => [false, false, false],
    'row deleted visibility flags' => [false, false, false],
    'summary final page count' => 108,
    'summary dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next180', 'sqlite-current-source-next183'],
    'dependency closure' => true,
    'non overlap' => true,
    'receipt signature length' => 64,
    'freeblock signature length' => 64,
    'batch size three committed pages' => [1, 3, 105, 106, 107, 108],
    'batch size three pointer maps' => [2, 105],
    'batch size three row count' => 2,
    'batch size three row page images' => [[1, 3, 105], [106, 107, 108]],
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases183 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next183 ' . $name] = static function (TestRunner $t) use ($callback, $expected183, $name): void {
        $t->same($expected183[$name], $callback());
    };
}

foreach (range(1, 45) as $index) {
    $tests['btree vacuum pointermap freeblock current source next183 commit invariant ' . $index] = static function (TestRunner $t) use ($plan183): void {
        $plan = $plan183();
        $summary = $plan->commitSummary();
        $rows = $plan->commitRows();

        $t->same([], $plan->commitErrors());
        $t->same([1, 3, 105, 106, 107, 108], $plan->committedPageImages());
        $t->same([2, 105], $plan->committedPointerMapPages());
        $t->same([3], $plan->committedLeafFreeblockPages());
        $t->same([106, 107, 108], $plan->committedOverflowPages());
        $t->same([], $plan->committedFencedPages());
        $t->same(true, $summary['all_pointer_maps_precede_pages']);
        $t->same(true, $summary['all_page_hashes_present']);
        $t->same([2, 2, 2], array_column($rows, 'receipt_count'));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next183-ready', $summary['status']);
    };
}

return $tests;
