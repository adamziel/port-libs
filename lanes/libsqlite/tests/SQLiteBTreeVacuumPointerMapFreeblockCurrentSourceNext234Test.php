<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage234 = static function (int $pageCount): string {
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

$putPointerMapEntry234 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database234 = static function () use ($makeFirstPage234, $putPointerMapEntry234): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage234(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next234', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry234($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan234 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database234;

    $database = $database234();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext234(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next234-current-freeblock-cursor-', 40),
        3,
        true,
        $batchSize,
    );
};

$cases234 = [
    'action label' => static fn (): mixed => $plan234()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan234()->cursorSummary()['status'],
    'cursor row count' => static fn (): mixed => $plan234()->cursorSummary()['cursor_row_count'],
    'cursor pages' => static fn (): mixed => $plan234()->cursorPages(),
    'summary cursor pages' => static fn (): mixed => $plan234()->cursorSummary()['cursor_pages'],
    'pointer map cursor pages' => static fn (): mixed => $plan234()->pointerMapCursorPages(),
    'freeblock cursor pages' => static fn (): mixed => $plan234()->freeblockCursorPages(),
    'payload cursor pages' => static fn (): mixed => $plan234()->payloadCursorPages(),
    'cursor pages match handoff pages' => static fn (): mixed => $plan234()->cursorSummary()['cursor_pages_match_handoff_pages'],
    'pointer map cursor pages match handoff' => static fn (): mixed => $plan234()->cursorSummary()['pointer_map_cursor_pages_match_handoff'],
    'payload cursor pages match handoff payload' => static fn (): mixed => $plan234()->cursorSummary()['payload_cursor_pages_match_handoff_payload'],
    'cursor errors' => static fn (): mixed => $plan234()->cursorErrors(),
    'summary cursor errors' => static fn (): mixed => $plan234()->cursorSummary()['cursor_errors'],
    'all handoff tokens match' => static fn (): mixed => $plan234()->cursorSummary()['all_handoff_tokens_match'],
    'all pointer maps visible before freeblocks' => static fn (): mixed => $plan234()->cursorSummary()['all_pointer_maps_visible_before_freeblocks'],
    'all freeblock rows have leaf receipt' => static fn (): mixed => $plan234()->cursorSummary()['all_freeblock_rows_have_leaf_receipt'],
    'all payload rows depend on freeblock cursor' => static fn (): mixed => $plan234()->cursorSummary()['all_payload_rows_depend_on_freeblock_cursor'],
    'all tail pages fenced' => static fn (): mixed => $plan234()->cursorSummary()['all_tail_pages_fenced'],
    'all cursor offsets contiguous' => static fn (): mixed => $plan234()->cursorSummary()['all_cursor_offsets_contiguous'],
    'cursor token count' => static fn (): mixed => count($plan234()->cursorTokens()),
    'cursor token lengths' => static fn (): mixed => array_map('strlen', $plan234()->cursorTokens()),
    'cursor signature length' => static fn (): mixed => strlen($plan234()->cursorSummary()['cursor_signature']),
    'current source token length' => static fn (): mixed => strlen($plan234()->cursorSummary()['current_source_next234_token']),
    'cursor ordinals' => static fn (): mixed => array_column($plan234()->cursorRows(), 'cursor_ordinal'),
    'source handoff ordinals' => static fn (): mixed => array_column($plan234()->cursorRows(), 'source_handoff_ordinal'),
    'cursor channels' => static fn (): mixed => array_column($plan234()->cursorRows(), 'cursor_channel'),
    'byte offsets' => static fn (): mixed => array_column($plan234()->cursorRows(), 'byte_offset'),
    'byte lengths' => static fn (): mixed => array_column($plan234()->cursorRows(), 'byte_length'),
    'visible pointer maps row one' => static fn (): mixed => $plan234()->cursorRows()[0]['visible_pointer_map_pages'],
    'visible pointer maps row three' => static fn (): mixed => $plan234()->cursorRows()[2]['visible_pointer_map_pages'],
    'visible pointer maps last row' => static fn (): mixed => $plan234()->cursorRows()[6]['visible_pointer_map_pages'],
    'freeblock receipt flags' => static fn (): mixed => array_column($plan234()->cursorRows(), 'freeblock_row_has_leaf_receipt'),
    'payload dependency flags' => static fn (): mixed => array_column($plan234()->cursorRows(), 'payload_depends_on_freeblock_cursor'),
    'pointer maps visible flags' => static fn (): mixed => array_column($plan234()->cursorRows(), 'pointer_maps_visible_for_cursor'),
    'tail fenced flags' => static fn (): mixed => array_column($plan234()->cursorRows(), 'tail_page_fenced'),
    'offset flags' => static fn (): mixed => array_column($plan234()->cursorRows(), 'cursor_offset_contiguous'),
    'cursor states' => static fn (): mixed => array_column($plan234()->cursorRows(), 'cursor_state'),
    'first previous cursor token' => static fn (): mixed => $plan234()->cursorRows()[0]['previous_cursor_token'],
    'second previous cursor token length' => static fn (): mixed => strlen((string) $plan234()->cursorRows()[1]['previous_cursor_token']),
    'batch size three cursor row count' => static fn (): mixed => $plan234(3)->cursorSummary()['cursor_row_count'],
    'batch size three cursor pages' => static fn (): mixed => $plan234(3)->cursorPages(),
    'batch size three channels' => static fn (): mixed => array_column($plan234(3)->cursorRows(), 'cursor_channel'),
    'dependency closure' => static fn (): mixed => $plan234()->cursorSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan234()->cursorSummary()['non_overlap'], 'does not repeat next231'),
    'base action' => static fn (): mixed => $plan234()->handoffPlan->toArray()['action'],
    'base handoff row count' => static fn (): mixed => $plan234()->handoffPlan->handoffSummary()['handoff_row_count'],
];

$expected234 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next234',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next234-ready',
    'cursor row count' => 7,
    'cursor pages' => [2, 105, 105, 3, 106, 107, 108],
    'summary cursor pages' => [2, 105, 105, 3, 106, 107, 108],
    'pointer map cursor pages' => [2, 105, 105],
    'freeblock cursor pages' => [3],
    'payload cursor pages' => [106, 107, 108],
    'cursor pages match handoff pages' => true,
    'pointer map cursor pages match handoff' => true,
    'payload cursor pages match handoff payload' => true,
    'cursor errors' => [],
    'summary cursor errors' => [],
    'all handoff tokens match' => true,
    'all pointer maps visible before freeblocks' => true,
    'all freeblock rows have leaf receipt' => true,
    'all payload rows depend on freeblock cursor' => true,
    'all tail pages fenced' => true,
    'all cursor offsets contiguous' => true,
    'cursor token count' => 7,
    'cursor token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'cursor signature length' => 64,
    'current source token length' => 64,
    'cursor ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source handoff ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'cursor channels' => ['pointer-map', 'pointer-map', 'pointer-map', 'freeblock-source', 'payload-source', 'payload-source', 'payload-source'],
    'byte offsets' => [512, 53248, 53248, 1024, 53760, 54272, 54784],
    'byte lengths' => [512, 512, 512, 512, 512, 512, 512],
    'visible pointer maps row one' => [2],
    'visible pointer maps row three' => [2, 105],
    'visible pointer maps last row' => [2, 105],
    'freeblock receipt flags' => [true, true, true, true, true, true, true],
    'payload dependency flags' => [true, true, true, true, true, true, true],
    'pointer maps visible flags' => [true, true, true, true, true, true, true],
    'tail fenced flags' => [true, true, true, true, true, true, true],
    'offset flags' => [true, true, true, true, true, true, true],
    'cursor states' => ['current-source-freeblock-cursor-admitted', 'current-source-freeblock-cursor-admitted', 'current-source-freeblock-cursor-admitted', 'current-source-freeblock-cursor-admitted', 'current-source-freeblock-cursor-admitted', 'current-source-freeblock-cursor-admitted', 'current-source-freeblock-cursor-admitted'],
    'first previous cursor token' => null,
    'second previous cursor token length' => 64,
    'batch size three cursor row count' => 6,
    'batch size three cursor pages' => [2, 105, 3, 106, 107, 108],
    'batch size three channels' => ['pointer-map', 'pointer-map', 'freeblock-source', 'payload-source', 'payload-source', 'payload-source'],
    'dependency closure' => 'no new support component needed; next234 reuses next231 handoff rows, leaf freeblock receipts, pointer-map handoff ordering, and fenced-tail guards',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next231',
    'base handoff row count' => 7,
];

$tests = [];

foreach ($cases234 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next234 ' . $name] = static function (TestRunner $t) use ($callback, $expected234, $name): void {
        $t->same($expected234[$name], $callback());
    };
}

foreach (range(1, 84) as $index) {
    $tests['btree vacuum pointermap freeblock current source next234 cursor invariant ' . $index] = static function (TestRunner $t) use ($plan234): void {
        $plan = $plan234();
        $summary = $plan->cursorSummary();

        $t->same([], $plan->cursorErrors());
        $t->same([2, 105, 105, 3, 106, 107, 108], $plan->cursorPages());
        $t->same([2, 105, 105], $plan->pointerMapCursorPages());
        $t->same([3], $plan->freeblockCursorPages());
        $t->same([106, 107, 108], $plan->payloadCursorPages());
        $t->same([true, true, true, true, true, true, true], array_column($plan->cursorRows(), 'handoff_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->cursorRows(), 'tail_page_fenced'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->cursorRows(), 'payload_depends_on_freeblock_cursor'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->cursorTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next234-ready', $summary['status']);
        $t->same(true, $summary['cursor_pages_match_handoff_pages']);
        $t->same(true, $summary['all_pointer_maps_visible_before_freeblocks']);
        $t->same(true, $summary['all_freeblock_rows_have_leaf_receipt']);
        $t->same(true, $summary['all_payload_rows_depend_on_freeblock_cursor']);
    };
}

return $tests;
