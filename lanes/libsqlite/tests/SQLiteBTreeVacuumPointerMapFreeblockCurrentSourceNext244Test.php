<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage244 = static function (int $pageCount): string {
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

$putPointerMapEntry244 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database244 = static function () use ($makeFirstPage244, $putPointerMapEntry244): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage244(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next244', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(90 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry244($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan244 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database244;

    $database = $database244();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafPublishOrderingFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next244-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message244 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases244 = [
    'action label' => static fn (): mixed => $plan244()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan244()->publishSummary()['status'],
    'publish row count' => static fn (): mixed => $plan244()->publishSummary()['publish_row_count'],
    'publish pages' => static fn (): mixed => $plan244()->publishPages(),
    'summary publish pages' => static fn (): mixed => $plan244()->publishSummary()['publish_pages'],
    'next publish pages' => static fn (): mixed => $plan244()->nextPublishPages(),
    'source pages' => static fn (): mixed => $plan244()->publishSummary()['source_pages'],
    'publish pages match source pages' => static fn (): mixed => $plan244()->publishSummary()['publish_pages_match_source_pages'],
    'pointer map fence pages' => static fn (): mixed => $plan244()->pointerMapFencePages(),
    'summary pointer map fence pages' => static fn (): mixed => $plan244()->publishSummary()['pointer_map_fence_pages'],
    'publishable payload pages' => static fn (): mixed => $plan244()->publishablePayloadPages(),
    'summary publishable payload pages' => static fn (): mixed => $plan244()->publishSummary()['publishable_payload_pages'],
    'duplicate pointer map fence pages' => static fn (): mixed => $plan244()->duplicatePointerMapFencePages(),
    'summary duplicate pointer map fence pages' => static fn (): mixed => $plan244()->publishSummary()['duplicate_pointer_map_fence_pages'],
    'publish errors' => static fn (): mixed => $plan244()->publishErrors(),
    'summary publish errors' => static fn (): mixed => $plan244()->publishSummary()['publish_errors'],
    'all source tokens match' => static fn (): mixed => $plan244()->publishSummary()['all_source_tokens_match'],
    'all publish links current' => static fn (): mixed => $plan244()->publishSummary()['all_publish_links_current'],
    'all payload publish after pointer map' => static fn (): mixed => $plan244()->publishSummary()['all_payload_publish_after_pointer_map'],
    'all duplicate pointer maps republished' => static fn (): mixed => $plan244()->publishSummary()['all_duplicate_pointer_maps_republished'],
    'all freeblock receipts published' => static fn (): mixed => $plan244()->publishSummary()['all_freeblock_receipts_published'],
    'all tail pages excluded from publish' => static fn (): mixed => $plan244()->publishSummary()['all_tail_pages_excluded_from_publish'],
    'token count' => static fn (): mixed => count($plan244()->publishTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan244()->publishTokens()),
    'signature length' => static fn (): mixed => strlen($plan244()->publishSummary()['publish_signature']),
    'current token length' => static fn (): mixed => strlen($plan244()->publishSummary()['current_source_next244_token']),
    'first row state' => static fn (): mixed => $plan244()->publishRows()[0]['publish_state'],
    'second row state' => static fn (): mixed => $plan244()->publishRows()[1]['publish_state'],
    'first row channel' => static fn (): mixed => $plan244()->publishRows()[0]['publish_channel'],
    'second row channel' => static fn (): mixed => $plan244()->publishRows()[1]['publish_channel'],
    'first pointer generations' => static fn (): mixed => $plan244()->publishRows()[0]['published_pointer_map_generations'],
    'second published payloads' => static fn (): mixed => $plan244()->publishRows()[1]['published_payload_pages'],
    'fourth published payloads' => static fn (): mixed => $plan244()->publishRows()[3]['published_payload_pages'],
    'fifth duplicate pointer map' => static fn (): mixed => $plan244()->publishRows()[4]['duplicate_pointer_map_publish'],
    'fifth pointer generations' => static fn (): mixed => $plan244()->publishRows()[4]['published_pointer_map_generations'],
    'last row next page' => static fn (): mixed => $plan244()->publishRows()[6]['next_publish_page'],
    'ordinals' => static fn (): mixed => array_column($plan244()->publishRows(), 'publish_ordinal'),
    'source ordinals' => static fn (): mixed => array_column($plan244()->publishRows(), 'source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan244()->publishRows(), 'publish_state'),
    'row source token flags' => static fn (): mixed => array_column($plan244()->publishRows(), 'source_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan244()->publishRows(), 'publish_link_current'),
    'row payload publish flags' => static fn (): mixed => array_column($plan244()->publishRows(), 'payload_publish_after_pointer_map'),
    'row duplicate publish flags' => static fn (): mixed => array_column($plan244()->publishRows(), 'duplicate_pointer_map_republished'),
    'row freeblock flags' => static fn (): mixed => array_column($plan244()->publishRows(), 'freeblock_receipt_published'),
    'row tail exclusion flags' => static fn (): mixed => array_column($plan244()->publishRows(), 'tail_page_excluded_from_publish'),
    'batch size three row count' => static fn (): mixed => $plan244(3)->publishSummary()['publish_row_count'],
    'batch size three pages' => static fn (): mixed => $plan244(3)->publishPages(),
    'batch size three next pages' => static fn (): mixed => $plan244(3)->nextPublishPages(),
    'batch size three duplicate pointer maps' => static fn (): mixed => $plan244(3)->duplicatePointerMapFencePages(),
    'dependency closure' => static fn (): mixed => $plan244()->publishSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan244()->publishSummary()['non_overlap'], 'does not repeat cursor row construction'),
    'source action' => static fn (): mixed => $plan244()->sourcePlan->toArray()['action'],
    'source row count' => static fn (): mixed => $plan244()->sourcePlan->sourceSummary()['source_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message244(static fn () => $plan244(0)),
];

$expected244 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next244',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next244-ready',
    'publish row count' => 7,
    'publish pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary publish pages' => [2, 3, 105, 106, 105, 107, 108],
    'next publish pages' => [3, 105, 106, 105, 107, 108, null],
    'source pages' => [2, 3, 105, 106, 105, 107, 108],
    'publish pages match source pages' => true,
    'pointer map fence pages' => [2, 105],
    'summary pointer map fence pages' => [2, 105],
    'publishable payload pages' => [3, 106, 107, 108],
    'summary publishable payload pages' => [3, 106, 107, 108],
    'duplicate pointer map fence pages' => [105],
    'summary duplicate pointer map fence pages' => [105],
    'publish errors' => [],
    'summary publish errors' => [],
    'all source tokens match' => true,
    'all publish links current' => true,
    'all payload publish after pointer map' => true,
    'all duplicate pointer maps republished' => true,
    'all freeblock receipts published' => true,
    'all tail pages excluded from publish' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row state' => 'current-source-next244-publish-cursor-visible',
    'second row state' => 'current-source-next244-publish-cursor-visible',
    'first row channel' => 'pointer-map',
    'second row channel' => 'payload',
    'first pointer generations' => ['2:1'],
    'second published payloads' => [3],
    'fourth published payloads' => [3, 106],
    'fifth duplicate pointer map' => true,
    'fifth pointer generations' => ['2:1', '105:2'],
    'last row next page' => null,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => array_fill(0, 7, 'current-source-next244-publish-cursor-visible'),
    'row source token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row payload publish flags' => [true, true, true, true, true, true, true],
    'row duplicate publish flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail exclusion flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three duplicate pointer maps' => [],
    'dependency closure' => 'no new support component needed; next244 reuses current-source cursor rows and adds publish-order validation only',
    'non overlap' => true,
    'source action' => 'btree-vacuum-pointermap-freeblock-current-source-cursor',
    'source row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases244 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next244 ' . $name] = static function (TestRunner $t) use ($callback, $expected244, $name): void {
        $t->same($expected244[$name], $callback());
    };
}

foreach (range(1, 72) as $index) {
    $tests['btree vacuum pointermap freeblock current source next244 publish invariant ' . $index] = static function (TestRunner $t) use ($plan244): void {
        $plan = $plan244();
        $summary = $plan->publishSummary();

        $t->same([], $plan->publishErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->publishPages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->nextPublishPages());
        $t->same([2, 105], $plan->pointerMapFencePages());
        $t->same([3, 106, 107, 108], $plan->publishablePayloadPages());
        $t->same([105], $plan->duplicatePointerMapFencePages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->publishRows(), 'publish_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publishRows(), 'source_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publishRows(), 'publish_link_current'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publishRows(), 'payload_publish_after_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publishRows(), 'duplicate_pointer_map_republished'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publishRows(), 'freeblock_receipt_published'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publishRows(), 'tail_page_excluded_from_publish'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->publishTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next244-ready', $summary['status']);
        $t->same(true, $summary['publish_pages_match_source_pages']);
        $t->same(true, $summary['all_publish_links_current']);
        $t->same(true, $summary['all_tail_pages_excluded_from_publish']);
    };
}

return $tests;
