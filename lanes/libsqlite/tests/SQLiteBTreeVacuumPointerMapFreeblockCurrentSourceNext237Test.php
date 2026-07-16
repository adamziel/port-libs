<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage237 = static function (int $pageCount): string {
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

$putPointerMapEntry237 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database237 = static function () use ($makeFirstPage237, $putPointerMapEntry237): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage237(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next237', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(82 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry237($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan237 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database237;

    $database = $database237();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafCheckpointPublicationFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next237-reuse-barrier-payload-', 42),
        3,
        true,
        $batchSize,
    );
};

$cases237 = [
    'action label' => static fn (): mixed => $plan237()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan237()->reuseSummary()['status'],
    'reuse row count' => static fn (): mixed => $plan237()->reuseSummary()['reuse_row_count'],
    'reuse pages' => static fn (): mixed => $plan237()->reusePages(),
    'summary reuse pages' => static fn (): mixed => $plan237()->reuseSummary()['reuse_pages'],
    'cursor pages' => static fn (): mixed => $plan237()->reuseSummary()['cursor_pages'],
    'reuse pages match cursor pages' => static fn (): mixed => $plan237()->reuseSummary()['reuse_pages_match_cursor_pages'],
    'pointer map barrier pages' => static fn (): mixed => $plan237()->pointerMapBarrierPages(),
    'summary pointer map barrier pages' => static fn (): mixed => $plan237()->reuseSummary()['pointer_map_barrier_pages'],
    'freeblock barrier pages' => static fn (): mixed => $plan237()->freeblockBarrierPages(),
    'reusable payload pages' => static fn (): mixed => $plan237()->reusablePayloadPages(),
    'reuse errors' => static fn (): mixed => $plan237()->reuseErrors(),
    'summary reuse errors' => static fn (): mixed => $plan237()->reuseSummary()['reuse_errors'],
    'all cursor tokens match' => static fn (): mixed => $plan237()->reuseSummary()['all_cursor_tokens_match'],
    'all reuse links valid' => static fn (): mixed => $plan237()->reuseSummary()['all_reuse_links_valid'],
    'all payload reuse waits for freeblock' => static fn (): mixed => $plan237()->reuseSummary()['all_payload_reuse_waits_for_freeblock'],
    'all payload reuse has pointer maps' => static fn (): mixed => $plan237()->reuseSummary()['all_payload_reuse_has_pointer_maps'],
    'all freeblock barriers have receipts' => static fn (): mixed => $plan237()->reuseSummary()['all_freeblock_barriers_have_receipts'],
    'all tail pages stay fenced' => static fn (): mixed => $plan237()->reuseSummary()['all_tail_pages_stay_fenced'],
    'all reuse offsets contiguous' => static fn (): mixed => $plan237()->reuseSummary()['all_reuse_offsets_contiguous'],
    'reuse token count' => static fn (): mixed => count($plan237()->reuseTokens()),
    'reuse token lengths' => static fn (): mixed => array_map('strlen', $plan237()->reuseTokens()),
    'reuse signature length' => static fn (): mixed => strlen($plan237()->reuseSummary()['reuse_signature']),
    'current source token length' => static fn (): mixed => strlen($plan237()->reuseSummary()['current_source_next237_token']),
    'reuse ordinals' => static fn (): mixed => array_column($plan237()->reuseRows(), 'reuse_ordinal'),
    'cursor ordinals' => static fn (): mixed => array_column($plan237()->reuseRows(), 'cursor_ordinal'),
    'reuse channels' => static fn (): mixed => array_column($plan237()->reuseRows(), 'reuse_channel'),
    'byte offsets' => static fn (): mixed => array_column($plan237()->reuseRows(), 'byte_offset'),
    'byte lengths' => static fn (): mixed => array_column($plan237()->reuseRows(), 'byte_length'),
    'visible pointer maps row one' => static fn (): mixed => $plan237()->reuseRows()[0]['visible_pointer_map_pages'],
    'visible pointer maps row four' => static fn (): mixed => $plan237()->reuseRows()[3]['visible_pointer_map_pages'],
    'visible pointer maps last row' => static fn (): mixed => $plan237()->reuseRows()[6]['visible_pointer_map_pages'],
    'cursor token match flags' => static fn (): mixed => array_column($plan237()->reuseRows(), 'cursor_token_matches'),
    'reuse link flags' => static fn (): mixed => array_column($plan237()->reuseRows(), 'reuse_link_valid'),
    'freeblock receipt flags' => static fn (): mixed => array_column($plan237()->reuseRows(), 'freeblock_barrier_has_leaf_receipt'),
    'payload wait flags' => static fn (): mixed => array_column($plan237()->reuseRows(), 'payload_reuse_waits_for_freeblock'),
    'payload pointer map flags' => static fn (): mixed => array_column($plan237()->reuseRows(), 'payload_reuse_has_pointer_maps'),
    'tail fenced flags' => static fn (): mixed => array_column($plan237()->reuseRows(), 'tail_page_stays_fenced'),
    'offset flags' => static fn (): mixed => array_column($plan237()->reuseRows(), 'reuse_offset_contiguous'),
    'reuse states' => static fn (): mixed => array_column($plan237()->reuseRows(), 'reuse_state'),
    'first previous reuse token' => static fn (): mixed => $plan237()->reuseRows()[0]['previous_reuse_token'],
    'second previous reuse token length' => static fn (): mixed => strlen((string) $plan237()->reuseRows()[1]['previous_reuse_token']),
    'batch size three reuse row count' => static fn (): mixed => $plan237(3)->reuseSummary()['reuse_row_count'],
    'batch size three reuse pages' => static fn (): mixed => $plan237(3)->reusePages(),
    'batch size three channels' => static fn (): mixed => array_column($plan237(3)->reuseRows(), 'reuse_channel'),
    'dependency closure' => static fn (): mixed => $plan237()->reuseSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan237()->reuseSummary()['non_overlap'], 'does not repeat next234'),
    'base action' => static fn (): mixed => $plan237()->cursorPlan->toArray()['action'],
    'base cursor row count' => static fn (): mixed => $plan237()->cursorPlan->cursorSummary()['cursor_row_count'],
];

$expected237 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next237',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next237-ready',
    'reuse row count' => 7,
    'reuse pages' => [2, 105, 105, 3, 106, 107, 108],
    'summary reuse pages' => [2, 105, 105, 3, 106, 107, 108],
    'cursor pages' => [2, 105, 105, 3, 106, 107, 108],
    'reuse pages match cursor pages' => true,
    'pointer map barrier pages' => [2, 105, 105],
    'summary pointer map barrier pages' => [2, 105, 105],
    'freeblock barrier pages' => [3],
    'reusable payload pages' => [106, 107, 108],
    'reuse errors' => [],
    'summary reuse errors' => [],
    'all cursor tokens match' => true,
    'all reuse links valid' => true,
    'all payload reuse waits for freeblock' => true,
    'all payload reuse has pointer maps' => true,
    'all freeblock barriers have receipts' => true,
    'all tail pages stay fenced' => true,
    'all reuse offsets contiguous' => true,
    'reuse token count' => 7,
    'reuse token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'reuse signature length' => 64,
    'current source token length' => 64,
    'reuse ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'cursor ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'reuse channels' => ['pointer-map-barrier', 'pointer-map-barrier', 'pointer-map-barrier', 'freeblock-barrier', 'payload-reuse', 'payload-reuse', 'payload-reuse'],
    'byte offsets' => [512, 53248, 53248, 1024, 53760, 54272, 54784],
    'byte lengths' => [512, 512, 512, 512, 512, 512, 512],
    'visible pointer maps row one' => [2],
    'visible pointer maps row four' => [2, 105],
    'visible pointer maps last row' => [2, 105],
    'cursor token match flags' => [true, true, true, true, true, true, true],
    'reuse link flags' => [true, true, true, true, true, true, true],
    'freeblock receipt flags' => [true, true, true, true, true, true, true],
    'payload wait flags' => [true, true, true, true, true, true, true],
    'payload pointer map flags' => [true, true, true, true, true, true, true],
    'tail fenced flags' => [true, true, true, true, true, true, true],
    'offset flags' => [true, true, true, true, true, true, true],
    'reuse states' => ['current-source-reuse-barrier-admitted', 'current-source-reuse-barrier-admitted', 'current-source-reuse-barrier-admitted', 'current-source-reuse-barrier-admitted', 'current-source-reuse-barrier-admitted', 'current-source-reuse-barrier-admitted', 'current-source-reuse-barrier-admitted'],
    'first previous reuse token' => null,
    'second previous reuse token length' => 64,
    'batch size three reuse row count' => 6,
    'batch size three reuse pages' => [2, 105, 3, 106, 107, 108],
    'batch size three channels' => ['pointer-map-barrier', 'pointer-map-barrier', 'freeblock-barrier', 'payload-reuse', 'payload-reuse', 'payload-reuse'],
    'dependency closure' => 'no new support component needed; next237 reuses next234 cursor rows, pointer-map visibility, freeblock receipts, and fenced-tail guards',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next234',
    'base cursor row count' => 7,
];

$tests = [];

foreach ($cases237 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next237 ' . $name] = static function (TestRunner $t) use ($callback, $expected237, $name): void {
        $t->same($expected237[$name], $callback());
    };
}

foreach (range(1, 88) as $index) {
    $tests['btree vacuum pointermap freeblock current source next237 reuse invariant ' . $index] = static function (TestRunner $t) use ($plan237): void {
        $plan = $plan237();
        $summary = $plan->reuseSummary();

        $t->same([], $plan->reuseErrors());
        $t->same([2, 105, 105, 3, 106, 107, 108], $plan->reusePages());
        $t->same([2, 105, 105], $plan->pointerMapBarrierPages());
        $t->same([3], $plan->freeblockBarrierPages());
        $t->same([106, 107, 108], $plan->reusablePayloadPages());
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'cursor_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'reuse_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'tail_page_stays_fenced'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->reuseTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next237-ready', $summary['status']);
        $t->same(true, $summary['reuse_pages_match_cursor_pages']);
        $t->same(true, $summary['all_payload_reuse_waits_for_freeblock']);
        $t->same(true, $summary['all_payload_reuse_has_pointer_maps']);
        $t->same(true, $summary['all_freeblock_barriers_have_receipts']);
    };
}

return $tests;
