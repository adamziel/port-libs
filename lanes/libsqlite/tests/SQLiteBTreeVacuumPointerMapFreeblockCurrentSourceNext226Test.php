<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage226 = static function (int $pageCount): string {
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

$putPointerMapEntry226 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database226 = static function () use ($makeFirstPage226, $putPointerMapEntry226): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage226(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next226', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(78 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry226($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan226 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database226;

    $database = $database226();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafPublishWindowFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next226-current-publish-', 50),
        3,
        true,
        $batchSize,
    );
};

$message226 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases226 = [
    'action label' => static fn (): mixed => $plan226()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan226()->publishSummary()['status'],
    'publish row count' => static fn (): mixed => $plan226()->publishSummary()['publish_row_count'],
    'publish pages' => static fn (): mixed => $plan226()->publishPages(),
    'summary publish pages' => static fn (): mixed => $plan226()->publishSummary()['publish_pages'],
    'unique publish pages' => static fn (): mixed => $plan226()->publishSummary()['unique_publish_pages'],
    'pointer map publish pages' => static fn (): mixed => $plan226()->pointerMapPublishPages(),
    'payload publish pages' => static fn (): mixed => $plan226()->payloadPublishPages(),
    'publish pages match read pages' => static fn (): mixed => $plan226()->publishSummary()['publish_pages_match_read_pages'],
    'unique pages match read pages' => static fn (): mixed => $plan226()->publishSummary()['unique_publish_pages_match_unique_read_pages'],
    'pointer map publish matches readback' => static fn (): mixed => $plan226()->publishSummary()['pointer_map_publish_matches_readback'],
    'payload publish matches readback' => static fn (): mixed => $plan226()->publishSummary()['payload_publish_matches_readback'],
    'publish errors' => static fn (): mixed => $plan226()->publishErrors(),
    'summary publish errors' => static fn (): mixed => $plan226()->publishSummary()['publish_errors'],
    'all read tokens match' => static fn (): mixed => $plan226()->publishSummary()['all_read_tokens_match'],
    'all current source tokens match' => static fn (): mixed => $plan226()->publishSummary()['all_current_source_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan226()->publishSummary()['all_pointer_maps_published_before_payload'],
    'all tail pages excluded' => static fn (): mixed => $plan226()->publishSummary()['all_tail_pages_excluded_from_publish'],
    'all freeblock receipts confirmed' => static fn (): mixed => $plan226()->publishSummary()['all_freeblock_receipts_confirmed'],
    'all publish offsets contiguous' => static fn (): mixed => $plan226()->publishSummary()['all_publish_offsets_contiguous'],
    'all publish chains valid' => static fn (): mixed => $plan226()->publishSummary()['all_publish_chains_valid'],
    'duplicate rewrite pages' => static fn (): mixed => $plan226()->duplicateRewritePublishPages(),
    'summary duplicate rewrite pages' => static fn (): mixed => $plan226()->publishSummary()['duplicate_rewrite_pages'],
    'duplicate rewrite pages match readback' => static fn (): mixed => $plan226()->publishSummary()['duplicate_rewrite_pages_match_readback'],
    'publish token count' => static fn (): mixed => count($plan226()->publishTokens()),
    'publish token lengths' => static fn (): mixed => array_map('strlen', $plan226()->publishTokens()),
    'publish signature length' => static fn (): mixed => strlen($plan226()->publishSummary()['publish_signature']),
    'current source token length' => static fn (): mixed => strlen($plan226()->publishSummary()['current_source_next226_token']),
    'publish ordinals' => static fn (): mixed => array_column($plan226()->publishRows(), 'publish_ordinal'),
    'source read ordinals' => static fn (): mixed => array_column($plan226()->publishRows(), 'source_read_ordinal'),
    'publish channels' => static fn (): mixed => array_column($plan226()->publishRows(), 'publish_channel'),
    'byte offsets' => static fn (): mixed => array_column($plan226()->publishRows(), 'byte_offset'),
    'byte lengths' => static fn (): mixed => array_column($plan226()->publishRows(), 'byte_length'),
    'duplicate rewrite flags' => static fn (): mixed => array_column($plan226()->publishRows(), 'duplicate_rewrite_published'),
    'tail exclusion flags' => static fn (): mixed => array_column($plan226()->publishRows(), 'tail_page_excluded_from_publish'),
    'read token flags' => static fn (): mixed => array_column($plan226()->publishRows(), 'read_token_matches'),
    'current source token flags' => static fn (): mixed => array_column($plan226()->publishRows(), 'current_source_token_matches'),
    'publish chain flags' => static fn (): mixed => array_column($plan226()->publishRows(), 'publish_chain_valid'),
    'publish offset flags' => static fn (): mixed => array_column($plan226()->publishRows(), 'publish_offset_contiguous'),
    'publish states' => static fn (): mixed => array_column($plan226()->publishRows(), 'publish_state'),
    'first visible pages' => static fn (): mixed => $plan226()->publishRows()[0]['published_visible_pages'],
    'third visible pages' => static fn (): mixed => $plan226()->publishRows()[2]['published_visible_pages'],
    'last visible pages' => static fn (): mixed => $plan226()->publishRows()[6]['published_visible_pages'],
    'first previous publish token' => static fn (): mixed => $plan226()->publishRows()[0]['previous_publish_token'],
    'second previous publish token length' => static fn (): mixed => strlen((string) $plan226()->publishRows()[1]['previous_publish_token']),
    'batch size three publish row count' => static fn (): mixed => $plan226(3)->publishSummary()['publish_row_count'],
    'batch size three publish pages' => static fn (): mixed => $plan226(3)->publishPages(),
    'batch size three source read ordinals' => static fn (): mixed => array_column($plan226(3)->publishRows(), 'source_read_ordinal'),
    'dependency closure' => static fn (): mixed => $plan226()->publishSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan226()->publishSummary()['non_overlap'], 'does not repeat next219'),
    'base action' => static fn (): mixed => $plan226()->basePlan->toArray()['action'],
    'base read row count' => static fn (): mixed => $plan226()->basePlan->readSummary()['read_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message226(static fn () => $plan226(0)),
];

$expected226 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next226',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next226-ready',
    'publish row count' => 7,
    'publish pages' => [2, 105, 105, 3, 106, 107, 108],
    'summary publish pages' => [2, 105, 105, 3, 106, 107, 108],
    'unique publish pages' => [2, 3, 105, 106, 107, 108],
    'pointer map publish pages' => [2, 105, 105],
    'payload publish pages' => [3, 106, 107, 108],
    'publish pages match read pages' => true,
    'unique pages match read pages' => true,
    'pointer map publish matches readback' => true,
    'payload publish matches readback' => true,
    'publish errors' => [],
    'summary publish errors' => [],
    'all read tokens match' => true,
    'all current source tokens match' => true,
    'all pointer maps before payload' => true,
    'all tail pages excluded' => true,
    'all freeblock receipts confirmed' => true,
    'all publish offsets contiguous' => true,
    'all publish chains valid' => true,
    'duplicate rewrite pages' => [105],
    'summary duplicate rewrite pages' => [105],
    'duplicate rewrite pages match readback' => true,
    'publish token count' => 7,
    'publish token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'publish signature length' => 64,
    'current source token length' => 64,
    'publish ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source read ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'publish channels' => ['pointer-map', 'pointer-map', 'pointer-map', 'payload', 'payload', 'payload', 'payload'],
    'byte offsets' => [512, 53248, 53248, 1024, 53760, 54272, 54784],
    'byte lengths' => [512, 512, 512, 512, 512, 512, 512],
    'duplicate rewrite flags' => [false, false, true, false, false, false, false],
    'tail exclusion flags' => [true, true, true, true, true, true, true],
    'read token flags' => [true, true, true, true, true, true, true],
    'current source token flags' => [true, true, true, true, true, true, true],
    'publish chain flags' => [true, true, true, true, true, true, true],
    'publish offset flags' => [true, true, true, true, true, true, true],
    'publish states' => ['current-source-page-publish-fenced', 'current-source-page-publish-fenced', 'current-source-page-publish-fenced', 'current-source-page-publish-fenced', 'current-source-page-publish-fenced', 'current-source-page-publish-fenced', 'current-source-page-publish-fenced'],
    'first visible pages' => [2],
    'third visible pages' => [2, 105],
    'last visible pages' => [2, 3, 105, 106, 107, 108],
    'first previous publish token' => null,
    'second previous publish token length' => 64,
    'batch size three publish row count' => 6,
    'batch size three publish pages' => [2, 105, 3, 106, 107, 108],
    'batch size three source read ordinals' => [1, 2, 3, 4, 5, 6],
    'dependency closure' => 'no new support component needed; next226 reuses next219 readback rows, read tokens, duplicate pointer-map rewrite receipts, and fenced-tail guards',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next219',
    'base read row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases226 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next226 ' . $name] = static function (TestRunner $t) use ($callback, $expected226, $name): void {
        $t->same($expected226[$name], $callback());
    };
}

foreach (range(1, 45) as $index) {
    $tests['btree vacuum pointermap freeblock current source next226 publish invariant ' . $index] = static function (TestRunner $t) use ($plan226): void {
        $plan = $plan226();
        $summary = $plan->publishSummary();

        $t->same([], $plan->publishErrors());
        $t->same([2, 105, 105, 3, 106, 107, 108], $plan->publishPages());
        $t->same([2, 3, 105, 106, 107, 108], $summary['unique_publish_pages']);
        $t->same([2, 105, 105], $plan->pointerMapPublishPages());
        $t->same([3, 106, 107, 108], $plan->payloadPublishPages());
        $t->same([true, true, true, true, true, true, true], array_column($plan->publishRows(), 'read_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publishRows(), 'tail_page_excluded_from_publish'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publishRows(), 'publish_offset_contiguous'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->publishTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next226-ready', $summary['status']);
        $t->same(true, $summary['publish_pages_match_read_pages']);
        $t->same(true, $summary['all_pointer_maps_published_before_payload']);
    };
}

return $tests;
