<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage231 = static function (int $pageCount): string {
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

$putPointerMapEntry231 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database231 = static function () use ($makeFirstPage231, $putPointerMapEntry231): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage231(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next231', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry231($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan231 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database231;

    $database = $database231();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafWriterHandoffFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next231-current-handoff-', 48),
        3,
        true,
        $batchSize,
    );
};

$message231 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases231 = [
    'action label' => static fn (): mixed => $plan231()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan231()->handoffSummary()['status'],
    'handoff row count' => static fn (): mixed => $plan231()->handoffSummary()['handoff_row_count'],
    'handoff pages' => static fn (): mixed => $plan231()->handoffPages(),
    'summary handoff pages' => static fn (): mixed => $plan231()->handoffSummary()['handoff_pages'],
    'pointer map handoff pages' => static fn (): mixed => $plan231()->pointerMapHandoffPages(),
    'payload handoff pages' => static fn (): mixed => $plan231()->payloadHandoffPages(),
    'duplicate pointer map handoff pages' => static fn (): mixed => $plan231()->duplicatePointerMapHandoffPages(),
    'handoff pages match seals' => static fn (): mixed => $plan231()->handoffSummary()['handoff_pages_match_seal_pages'],
    'pointer map handoffs match seals' => static fn (): mixed => $plan231()->handoffSummary()['pointer_map_handoffs_match_seals'],
    'payload handoffs match seals' => static fn (): mixed => $plan231()->handoffSummary()['payload_handoffs_match_seals'],
    'duplicate pointer map handoffs match seals' => static fn (): mixed => $plan231()->handoffSummary()['duplicate_pointer_map_handoffs_match_seals'],
    'handoff errors' => static fn (): mixed => $plan231()->handoffErrors(),
    'summary handoff errors' => static fn (): mixed => $plan231()->handoffSummary()['handoff_errors'],
    'all seal tokens match' => static fn (): mixed => $plan231()->handoffSummary()['all_seal_tokens_match'],
    'all current source tokens match' => static fn (): mixed => $plan231()->handoffSummary()['all_current_source_tokens_match'],
    'all pointer maps admitted before payload' => static fn (): mixed => $plan231()->handoffSummary()['all_pointer_maps_admitted_before_payload'],
    'all tail pages fenced' => static fn (): mixed => $plan231()->handoffSummary()['all_tail_pages_fenced'],
    'all freeblock receipts handed off' => static fn (): mixed => $plan231()->handoffSummary()['all_freeblock_receipts_handed_off'],
    'all leaf freeblock receipts handed off' => static fn (): mixed => $plan231()->handoffSummary()['all_leaf_freeblock_receipts_handed_off'],
    'all handoff offsets contiguous' => static fn (): mixed => $plan231()->handoffSummary()['all_handoff_offsets_contiguous'],
    'handoff token count' => static fn (): mixed => count($plan231()->handoffTokens()),
    'handoff token lengths' => static fn (): mixed => array_map('strlen', $plan231()->handoffTokens()),
    'handoff signature length' => static fn (): mixed => strlen($plan231()->handoffSummary()['handoff_signature']),
    'current source token length' => static fn (): mixed => strlen($plan231()->handoffSummary()['current_source_next231_token']),
    'handoff ordinals' => static fn (): mixed => array_column($plan231()->handoffRows(), 'handoff_ordinal'),
    'source seal ordinals' => static fn (): mixed => array_column($plan231()->handoffRows(), 'source_seal_ordinal'),
    'handoff channels' => static fn (): mixed => array_column($plan231()->handoffRows(), 'handoff_channel'),
    'byte offsets' => static fn (): mixed => array_column($plan231()->handoffRows(), 'byte_offset'),
    'byte lengths' => static fn (): mixed => array_column($plan231()->handoffRows(), 'byte_length'),
    'duplicate pointer map flags' => static fn (): mixed => array_column($plan231()->handoffRows(), 'duplicate_pointer_map_rewrite_handoff'),
    'tail fenced flags' => static fn (): mixed => array_column($plan231()->handoffRows(), 'tail_page_fenced'),
    'freeblock receipt flags' => static fn (): mixed => array_column($plan231()->handoffRows(), 'freeblock_receipt_handoff'),
    'leaf freeblock receipt flags' => static fn (): mixed => array_column($plan231()->handoffRows(), 'leaf_freeblock_receipt_handoff'),
    'overflow payload flags' => static fn (): mixed => array_column($plan231()->handoffRows(), 'overflow_payload_handoff'),
    'seal token flags' => static fn (): mixed => array_column($plan231()->handoffRows(), 'seal_token_matches'),
    'current source token flags' => static fn (): mixed => array_column($plan231()->handoffRows(), 'current_source_token_matches'),
    'handoff chain flags' => static fn (): mixed => array_column($plan231()->handoffRows(), 'handoff_chain_valid'),
    'handoff offset flags' => static fn (): mixed => array_column($plan231()->handoffRows(), 'handoff_offset_contiguous'),
    'handoff states' => static fn (): mixed => array_column($plan231()->handoffRows(), 'handoff_state'),
    'first admitted pages' => static fn (): mixed => $plan231()->handoffRows()[0]['admitted_visible_pages'],
    'third admitted pages' => static fn (): mixed => $plan231()->handoffRows()[2]['admitted_visible_pages'],
    'last admitted pages' => static fn (): mixed => $plan231()->handoffRows()[6]['admitted_visible_pages'],
    'first previous handoff token' => static fn (): mixed => $plan231()->handoffRows()[0]['previous_handoff_token'],
    'second previous handoff token length' => static fn (): mixed => strlen((string) $plan231()->handoffRows()[1]['previous_handoff_token']),
    'batch size three handoff row count' => static fn (): mixed => $plan231(3)->handoffSummary()['handoff_row_count'],
    'batch size three handoff pages' => static fn (): mixed => $plan231(3)->handoffPages(),
    'batch size three source seal ordinals' => static fn (): mixed => array_column($plan231(3)->handoffRows(), 'source_seal_ordinal'),
    'dependency closure' => static fn (): mixed => $plan231()->handoffSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan231()->handoffSummary()['non_overlap'], 'does not repeat next227'),
    'base action' => static fn (): mixed => $plan231()->basePlan->toArray()['action'],
    'base seal row count' => static fn (): mixed => $plan231()->basePlan->sealSummary()['seal_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message231(static fn () => $plan231(0)),
];

$expected231 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next231',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next231-ready',
    'handoff row count' => 7,
    'handoff pages' => [2, 105, 105, 3, 106, 107, 108],
    'summary handoff pages' => [2, 105, 105, 3, 106, 107, 108],
    'pointer map handoff pages' => [2, 105, 105],
    'payload handoff pages' => [3, 106, 107, 108],
    'duplicate pointer map handoff pages' => [105],
    'handoff pages match seals' => true,
    'pointer map handoffs match seals' => true,
    'payload handoffs match seals' => true,
    'duplicate pointer map handoffs match seals' => true,
    'handoff errors' => [],
    'summary handoff errors' => [],
    'all seal tokens match' => true,
    'all current source tokens match' => true,
    'all pointer maps admitted before payload' => true,
    'all tail pages fenced' => true,
    'all freeblock receipts handed off' => true,
    'all leaf freeblock receipts handed off' => true,
    'all handoff offsets contiguous' => true,
    'handoff token count' => 7,
    'handoff token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'handoff signature length' => 64,
    'current source token length' => 64,
    'handoff ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source seal ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'handoff channels' => ['pointer-map', 'pointer-map', 'pointer-map', 'payload', 'payload', 'payload', 'payload'],
    'byte offsets' => [512, 53248, 53248, 1024, 53760, 54272, 54784],
    'byte lengths' => [512, 512, 512, 512, 512, 512, 512],
    'duplicate pointer map flags' => [false, false, true, false, false, false, false],
    'tail fenced flags' => [true, true, true, true, true, true, true],
    'freeblock receipt flags' => [true, true, true, true, true, true, true],
    'leaf freeblock receipt flags' => [false, false, false, true, false, false, false],
    'overflow payload flags' => [false, false, false, false, true, true, true],
    'seal token flags' => [true, true, true, true, true, true, true],
    'current source token flags' => [true, true, true, true, true, true, true],
    'handoff chain flags' => [true, true, true, true, true, true, true],
    'handoff offset flags' => [true, true, true, true, true, true, true],
    'handoff states' => ['current-source-next-writer-admitted', 'current-source-next-writer-admitted', 'current-source-next-writer-admitted', 'current-source-next-writer-admitted', 'current-source-next-writer-admitted', 'current-source-next-writer-admitted', 'current-source-next-writer-admitted'],
    'first admitted pages' => [2],
    'third admitted pages' => [2, 105],
    'last admitted pages' => [2, 3, 105, 106, 107, 108],
    'first previous handoff token' => null,
    'second previous handoff token length' => 64,
    'batch size three handoff row count' => 6,
    'batch size three handoff pages' => [2, 105, 3, 106, 107, 108],
    'batch size three source seal ordinals' => [1, 2, 3, 4, 5, 6],
    'dependency closure' => 'no new support component needed; next231 reuses next227 publication seals, duplicate pointer-map rewrite receipts, leaf freeblock receipts, and fenced-tail guards',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next227',
    'base seal row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases231 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next231 ' . $name] = static function (TestRunner $t) use ($callback, $expected231, $name): void {
        $t->same($expected231[$name], $callback());
    };
}

foreach (range(1, 88) as $index) {
    $tests['btree vacuum pointermap freeblock current source next231 handoff invariant ' . $index] = static function (TestRunner $t) use ($plan231): void {
        $plan = $plan231();
        $summary = $plan->handoffSummary();

        $t->same([], $plan->handoffErrors());
        $t->same([2, 105, 105, 3, 106, 107, 108], $plan->handoffPages());
        $t->same([2, 105, 105], $plan->pointerMapHandoffPages());
        $t->same([3, 106, 107, 108], $plan->payloadHandoffPages());
        $t->same([105], $plan->duplicatePointerMapHandoffPages());
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'seal_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'tail_page_fenced'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'freeblock_receipt_handoff'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->handoffTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next231-ready', $summary['status']);
        $t->same(true, $summary['handoff_pages_match_seal_pages']);
        $t->same(true, $summary['all_pointer_maps_admitted_before_payload']);
        $t->same(true, $summary['all_leaf_freeblock_receipts_handed_off']);
    };
}

return $tests;
