<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage189 = static function (int $pageCount): string {
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

$putPointerMapEntry189 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database189 = static function () use ($makeFirstPage189, $putPointerMapEntry189): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage189(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next189', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(88 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry189($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan189 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database189;

    $database = $database189();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafLeaseAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next189-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message189 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases189 = [
    'action label' => static fn (): mixed => $plan189()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan189()->checkpointSummary()['status'],
    'checkpoint row count' => static fn (): mixed => $plan189()->checkpointSummary()['checkpoint_row_count'],
    'visible current-source pages' => static fn (): mixed => $plan189()->checkpointSummary()['visible_current_source_pages'],
    'newly visible pages' => static fn (): mixed => $plan189()->newlyVisiblePages(),
    'summary newly visible pages' => static fn (): mixed => $plan189()->checkpointSummary()['newly_visible_pages'],
    'high-water pages' => static fn (): mixed => $plan189()->cumulativeHighWaterPages(),
    'summary high-water pages' => static fn (): mixed => $plan189()->checkpointSummary()['cumulative_high_water_pages'],
    'final visible page count' => static fn (): mixed => $plan189()->checkpointSummary()['final_visible_page_count'],
    'fenced pages visible' => static fn (): mixed => $plan189()->checkpointSummary()['fenced_pages_visible'],
    'checkpoint errors' => static fn (): mixed => $plan189()->checkpointErrors(),
    'summary checkpoint errors' => static fn (): mixed => $plan189()->checkpointSummary()['checkpoint_errors'],
    'all pointer maps precede payload' => static fn (): mixed => $plan189()->checkpointSummary()['all_pointer_maps_precede_payload'],
    'all resume tokens unique' => static fn (): mixed => $plan189()->checkpointSummary()['all_resume_tokens_unique'],
    'all deleted cells hidden' => static fn (): mixed => $plan189()->checkpointSummary()['all_deleted_cells_hidden'],
    'all fenced pages hidden' => static fn (): mixed => $plan189()->checkpointSummary()['all_fenced_pages_hidden'],
    'checkpoint token count' => static fn (): mixed => count($plan189()->checkpointTokens()),
    'checkpoint token lengths' => static fn (): mixed => array_map('strlen', $plan189()->checkpointTokens()),
    'checkpoint signature length' => static fn (): mixed => strlen($plan189()->checkpointSummary()['checkpoint_signature']),
    'restart token length' => static fn (): mixed => strlen($plan189()->checkpointSummary()['current_source_restart_token']),
    'first row new pages' => static fn (): mixed => $plan189()->checkpointRows()[0]['newly_visible_pages'],
    'first row high water' => static fn (): mixed => $plan189()->checkpointRows()[0]['current_source_high_water_page'],
    'first row previous token' => static fn (): mixed => $plan189()->checkpointRows()[0]['previous_resume_token'],
    'first row payload pages' => static fn (): mixed => $plan189()->checkpointRows()[0]['visible_payload_pages'],
    'second row new pages' => static fn (): mixed => $plan189()->checkpointRows()[1]['newly_visible_pages'],
    'second row payload pages' => static fn (): mixed => $plan189()->checkpointRows()[1]['visible_payload_pages'],
    'second row previous token length' => static fn (): mixed => strlen((string) $plan189()->checkpointRows()[1]['previous_resume_token']),
    'third row new pages' => static fn (): mixed => $plan189()->checkpointRows()[2]['newly_visible_pages'],
    'third row payload pages' => static fn (): mixed => $plan189()->checkpointRows()[2]['visible_payload_pages'],
    'third row previous token length' => static fn (): mixed => strlen((string) $plan189()->checkpointRows()[2]['previous_resume_token']),
    'row checkpoint states' => static fn (): mixed => array_column($plan189()->checkpointRows(), 'checkpoint_state'),
    'row pointer map flags' => static fn (): mixed => array_column($plan189()->checkpointRows(), 'pointer_map_visible_before_payload'),
    'row unique flags' => static fn (): mixed => array_column($plan189()->checkpointRows(), 'resume_token_unique'),
    'row deleted flags' => static fn (): mixed => array_column($plan189()->checkpointRows(), 'deleted_cell_hidden'),
    'row fenced flags' => static fn (): mixed => array_column($plan189()->checkpointRows(), 'fenced_pages_hidden'),
    'row hash counts' => static fn (): mixed => array_column($plan189()->checkpointRows(), 'page_hash_count'),
    'row receipt kinds' => static fn (): mixed => array_column($plan189()->checkpointRows(), 'receipt_kinds'),
    'batch size three rows' => static fn (): mixed => $plan189(3)->checkpointSummary()['checkpoint_row_count'],
    'batch size three high water' => static fn (): mixed => $plan189(3)->cumulativeHighWaterPages(),
    'batch size three new pages' => static fn (): mixed => array_column($plan189(3)->checkpointRows(), 'newly_visible_pages'),
    'batch size three token count' => static fn (): mixed => count($plan189(3)->checkpointTokens()),
    'dependency closure' => static fn (): mixed => str_contains($plan189()->checkpointSummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan189()->checkpointSummary()['non_overlap'], 'does not repeat next186'),
    'bad batch size rejected' => static fn (): mixed => $message189(static fn () => $plan189(0)),
];

$expected189 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next189',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next189-ready',
    'checkpoint row count' => 3,
    'visible current-source pages' => [1, 2, 3, 105, 106, 107, 108],
    'newly visible pages' => [1, 2, 3, 105, 106, 107, 108],
    'summary newly visible pages' => [1, 2, 3, 105, 106, 107, 108],
    'high-water pages' => [3, 106, 108],
    'summary high-water pages' => [3, 106, 108],
    'final visible page count' => 108,
    'fenced pages visible' => [],
    'checkpoint errors' => [],
    'summary checkpoint errors' => [],
    'all pointer maps precede payload' => true,
    'all resume tokens unique' => true,
    'all deleted cells hidden' => true,
    'all fenced pages hidden' => true,
    'checkpoint token count' => 3,
    'checkpoint token lengths' => [64, 64, 64],
    'checkpoint signature length' => 64,
    'restart token length' => 64,
    'first row new pages' => [1, 2, 3],
    'first row high water' => 3,
    'first row previous token' => null,
    'first row payload pages' => [3],
    'second row new pages' => [105, 106],
    'second row payload pages' => [106],
    'second row previous token length' => 64,
    'third row new pages' => [107, 108],
    'third row payload pages' => [107, 108],
    'third row previous token length' => 64,
    'row checkpoint states' => ['current-source-resume-ready', 'current-source-resume-ready', 'current-source-resume-ready'],
    'row pointer map flags' => [true, true, true],
    'row unique flags' => [true, true, true],
    'row deleted flags' => [true, true, true],
    'row fenced flags' => [true, true, true],
    'row hash counts' => [2, 2, 2],
    'row receipt kinds' => [
        ['pointer-map-before-page-image', 'leaf-freeblock-current-source'],
        ['pointer-map-before-page-image', 'overflow-page-image-current-source'],
        ['pointer-map-before-page-image', 'overflow-page-image-current-source'],
    ],
    'batch size three rows' => 2,
    'batch size three high water' => [105, 108],
    'batch size three new pages' => [[1, 2, 3, 105], [106, 107, 108]],
    'batch size three token count' => 2,
    'dependency closure' => true,
    'non overlap' => true,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases189 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next189 ' . $name] = static function (TestRunner $t) use ($callback, $expected189, $name): void {
        $t->same($expected189[$name], $callback());
    };
}

foreach (range(1, 50) as $index) {
    $tests['btree vacuum pointermap freeblock current source next189 checkpoint invariant ' . $index] = static function (TestRunner $t) use ($plan189): void {
        $plan = $plan189();
        $summary = $plan->checkpointSummary();

        $t->same([], $plan->checkpointErrors());
        $t->same([1, 2, 3, 105, 106, 107, 108], $plan->newlyVisiblePages());
        $t->same([3, 106, 108], $plan->cumulativeHighWaterPages());
        $t->same(108, $summary['final_visible_page_count']);
        $t->same([], $summary['fenced_pages_visible']);
        $t->same(true, $summary['all_pointer_maps_precede_payload']);
        $t->same(true, $summary['all_resume_tokens_unique']);
        $t->same(true, $summary['all_deleted_cells_hidden']);
        $t->same(true, $summary['all_fenced_pages_hidden']);
        $t->same([64, 64, 64], array_map('strlen', $plan->checkpointTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next189-ready', $summary['status']);
    };
}

return $tests;
