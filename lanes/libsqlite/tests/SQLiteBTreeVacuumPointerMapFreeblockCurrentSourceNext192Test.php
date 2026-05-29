<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage192 = static function (int $pageCount): string {
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

$putPointerMapEntry192 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database192 = static function () use ($makeFirstPage192, $putPointerMapEntry192): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage192(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next192', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(70 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry192($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan192 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database192;

    $database = $database192();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext192(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next192-current-source-reader-', 48),
        3,
        true,
        $batchSize,
    );
};

$message192 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases192 = [
    'action label' => static fn (): mixed => $plan192()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan192()->validationSummary()['status'],
    'validation row count' => static fn (): mixed => $plan192()->validationSummary()['validation_row_count'],
    'admitted reader pages' => static fn (): mixed => $plan192()->admittedReaderPages(),
    'summary admitted reader pages' => static fn (): mixed => $plan192()->validationSummary()['admitted_reader_pages'],
    'validation errors' => static fn (): mixed => $plan192()->validationErrors(),
    'summary validation errors' => static fn (): mixed => $plan192()->validationSummary()['validation_errors'],
    'all checkpoint tokens match' => static fn (): mixed => $plan192()->validationSummary()['all_checkpoint_tokens_match'],
    'all pointer maps validated' => static fn (): mixed => $plan192()->validationSummary()['all_pointer_maps_validated'],
    'all freeblock pages validated' => static fn (): mixed => $plan192()->validationSummary()['all_freeblock_pages_validated'],
    'all fenced pages excluded' => static fn (): mixed => $plan192()->validationSummary()['all_fenced_pages_excluded'],
    'all page hashes replayed' => static fn (): mixed => $plan192()->validationSummary()['all_page_hashes_replayed'],
    'checkpoint token count' => static fn (): mixed => count($plan192()->validationSummary()['checkpoint_tokens']),
    'validation token count' => static fn (): mixed => count($plan192()->validationTokens()),
    'validation token lengths' => static fn (): mixed => array_map('strlen', $plan192()->validationTokens()),
    'validation signature length' => static fn (): mixed => strlen($plan192()->validationSummary()['validation_signature']),
    'reader token length' => static fn (): mixed => strlen($plan192()->validationSummary()['current_source_reader_token']),
    'first row validated pages' => static fn (): mixed => $plan192()->validationRows()[0]['validated_pages'],
    'first row cumulative pages' => static fn (): mixed => $plan192()->validationRows()[0]['cumulative_validated_pages'],
    'first row previous token' => static fn (): mixed => $plan192()->validationRows()[0]['previous_validation_token'],
    'first row pointer maps' => static fn (): mixed => $plan192()->validationRows()[0]['visible_pointer_map_pages'],
    'first row payload pages' => static fn (): mixed => $plan192()->validationRows()[0]['visible_payload_pages'],
    'first row freeblock validated' => static fn (): mixed => $plan192()->validationRows()[0]['leaf_freeblock_validated'],
    'second row validated pages' => static fn (): mixed => $plan192()->validationRows()[1]['validated_pages'],
    'second row cumulative pages' => static fn (): mixed => $plan192()->validationRows()[1]['cumulative_validated_pages'],
    'second row previous token length' => static fn (): mixed => strlen((string) $plan192()->validationRows()[1]['previous_validation_token']),
    'second row pointer maps' => static fn (): mixed => $plan192()->validationRows()[1]['visible_pointer_map_pages'],
    'second row payload pages' => static fn (): mixed => $plan192()->validationRows()[1]['visible_payload_pages'],
    'third row validated pages' => static fn (): mixed => $plan192()->validationRows()[2]['validated_pages'],
    'third row cumulative pages' => static fn (): mixed => $plan192()->validationRows()[2]['cumulative_validated_pages'],
    'third row previous token length' => static fn (): mixed => strlen((string) $plan192()->validationRows()[2]['previous_validation_token']),
    'third row pointer maps' => static fn (): mixed => $plan192()->validationRows()[2]['visible_pointer_map_pages'],
    'third row payload pages' => static fn (): mixed => $plan192()->validationRows()[2]['visible_payload_pages'],
    'row states' => static fn (): mixed => array_column($plan192()->validationRows(), 'validation_state'),
    'row checkpoint flags' => static fn (): mixed => array_column($plan192()->validationRows(), 'checkpoint_token_matches'),
    'row pointer flags' => static fn (): mixed => array_column($plan192()->validationRows(), 'pointer_map_validated_before_payload'),
    'row freeblock flags' => static fn (): mixed => array_column($plan192()->validationRows(), 'leaf_freeblock_validated'),
    'row fenced flags' => static fn (): mixed => array_column($plan192()->validationRows(), 'fenced_pages_excluded'),
    'row deleted flags' => static fn (): mixed => array_column($plan192()->validationRows(), 'deleted_cell_hidden'),
    'row hash flags' => static fn (): mixed => array_column($plan192()->validationRows(), 'page_hashes_replayed'),
    'row page counts' => static fn (): mixed => array_column($plan192()->validationRows(), 'validated_page_count'),
    'row high water pages' => static fn (): mixed => array_column($plan192()->validationRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan192(3)->validationSummary()['validation_row_count'],
    'batch size three admitted pages' => static fn (): mixed => $plan192(3)->admittedReaderPages(),
    'batch size three validated page batches' => static fn (): mixed => array_column($plan192(3)->validationRows(), 'validated_pages'),
    'batch size three token count' => static fn (): mixed => count($plan192(3)->validationTokens()),
    'dependency closure' => static fn (): mixed => $plan192()->validationSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan192()->validationSummary()['non_overlap'], 'does not repeat next189'),
    'base action' => static fn (): mixed => $plan192()->basePlan->toArray()['action'],
    'base checkpoint rows' => static fn (): mixed => $plan192()->basePlan->checkpointSummary()['checkpoint_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message192(static fn () => $plan192(0)),
];

$expected192 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next192',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next192-ready',
    'validation row count' => 3,
    'admitted reader pages' => [1, 2, 3, 105, 106, 107, 108],
    'summary admitted reader pages' => [1, 2, 3, 105, 106, 107, 108],
    'validation errors' => [],
    'summary validation errors' => [],
    'all checkpoint tokens match' => true,
    'all pointer maps validated' => true,
    'all freeblock pages validated' => true,
    'all fenced pages excluded' => true,
    'all page hashes replayed' => true,
    'checkpoint token count' => 3,
    'validation token count' => 3,
    'validation token lengths' => [64, 64, 64],
    'validation signature length' => 64,
    'reader token length' => 64,
    'first row validated pages' => [1, 2, 3],
    'first row cumulative pages' => [1, 2, 3],
    'first row previous token' => null,
    'first row pointer maps' => [2],
    'first row payload pages' => [3],
    'first row freeblock validated' => true,
    'second row validated pages' => [105, 106],
    'second row cumulative pages' => [1, 2, 3, 105, 106],
    'second row previous token length' => 64,
    'second row pointer maps' => [105],
    'second row payload pages' => [106],
    'third row validated pages' => [107, 108],
    'third row cumulative pages' => [1, 2, 3, 105, 106, 107, 108],
    'third row previous token length' => 64,
    'third row pointer maps' => [105],
    'third row payload pages' => [107, 108],
    'row states' => ['next-reader-admitted', 'next-reader-admitted', 'next-reader-admitted'],
    'row checkpoint flags' => [true, true, true],
    'row pointer flags' => [true, true, true],
    'row freeblock flags' => [true, true, true],
    'row fenced flags' => [true, true, true],
    'row deleted flags' => [true, true, true],
    'row hash flags' => [true, true, true],
    'row page counts' => [3, 2, 2],
    'row high water pages' => [3, 106, 108],
    'batch size three row count' => 2,
    'batch size three admitted pages' => [1, 2, 3, 105, 106, 107, 108],
    'batch size three validated page batches' => [[1, 2, 3, 105], [106, 107, 108]],
    'batch size three token count' => 2,
    'dependency closure' => 'no new support component needed; next192 reuses next189 checkpoint tokens, cursor page hashes, pointer-map ordering, and fenced-tail current-source metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next189',
    'base checkpoint rows' => 3,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases192 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next192 ' . $name] = static function (TestRunner $t) use ($callback, $expected192, $name): void {
        $t->same($expected192[$name], $callback());
    };
}

foreach (range(1, 55) as $index) {
    $tests['btree vacuum pointermap freeblock current source next192 validation invariant ' . $index] = static function (TestRunner $t) use ($plan192): void {
        $plan = $plan192();
        $summary = $plan->validationSummary();

        $t->same([], $plan->validationErrors());
        $t->same([1, 2, 3, 105, 106, 107, 108], $plan->admittedReaderPages());
        $t->same([true, true, true], array_column($plan->validationRows(), 'checkpoint_token_matches'));
        $t->same([true, true, true], array_column($plan->validationRows(), 'pointer_map_validated_before_payload'));
        $t->same([true, true, true], array_column($plan->validationRows(), 'leaf_freeblock_validated'));
        $t->same([true, true, true], array_column($plan->validationRows(), 'fenced_pages_excluded'));
        $t->same([true, true, true], array_column($plan->validationRows(), 'page_hashes_replayed'));
        $t->same([64, 64, 64], array_map('strlen', $plan->validationTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next192-ready', $summary['status']);
    };
}

return $tests;
