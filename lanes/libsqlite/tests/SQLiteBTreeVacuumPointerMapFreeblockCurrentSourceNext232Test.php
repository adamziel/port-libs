<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage232 = static function (int $pageCount): string {
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

$putPointerMapEntry232 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database232 = static function () use ($makeFirstPage232, $putPointerMapEntry232): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage232(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next232', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(81 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry232($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan232 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database232;

    $database = $database232();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafResumeHandoffGateFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next229-current-source-resume-', 50),
        3,
        true,
        $batchSize,
    );
};

$message232 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases232 = [
    'action label' => static fn (): mixed => $plan232()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan232()->handoffSummary()['status'],
    'handoff row count' => static fn (): mixed => $plan232()->handoffSummary()['handoff_row_count'],
    'handoff pages' => static fn (): mixed => $plan232()->handoffPages(),
    'summary handoff pages' => static fn (): mixed => $plan232()->handoffSummary()['handoff_pages'],
    'next handoff pages' => static fn (): mixed => $plan232()->nextHandoffPages(),
    'pointer map handoff pages' => static fn (): mixed => $plan232()->pointerMapHandoffPages(),
    'payload handoff pages' => static fn (): mixed => $plan232()->payloadHandoffPages(),
    'resume pages' => static fn (): mixed => $plan232()->handoffSummary()['resume_pages'],
    'handoff pages match resume pages' => static fn (): mixed => $plan232()->handoffSummary()['handoff_pages_match_resume_pages'],
    'handoff errors' => static fn (): mixed => $plan232()->handoffErrors(),
    'summary handoff errors' => static fn (): mixed => $plan232()->handoffSummary()['handoff_errors'],
    'all resume tokens match' => static fn (): mixed => $plan232()->handoffSummary()['all_resume_tokens_match'],
    'all handoff links valid' => static fn (): mixed => $plan232()->handoffSummary()['all_handoff_links_valid'],
    'all pointer maps visible' => static fn (): mixed => $plan232()->handoffSummary()['all_pointer_map_handoffs_visible'],
    'all payload admitted after pointer map' => static fn (): mixed => $plan232()->handoffSummary()['all_payload_handoffs_admitted_after_pointer_map'],
    'all freeblock receipts carried' => static fn (): mixed => $plan232()->handoffSummary()['all_freeblock_handoff_receipts_carried'],
    'all tail pages fenced' => static fn (): mixed => $plan232()->handoffSummary()['all_tail_pages_fenced_for_handoff'],
    'all windows monotonic' => static fn (): mixed => $plan232()->handoffSummary()['all_handoff_windows_monotonic'],
    'handoff token count' => static fn (): mixed => count($plan232()->handoffTokens()),
    'handoff token lengths' => static fn (): mixed => array_map('strlen', $plan232()->handoffTokens()),
    'handoff signature length' => static fn (): mixed => strlen($plan232()->handoffSummary()['handoff_signature']),
    'current source token length' => static fn (): mixed => strlen($plan232()->handoffSummary()['current_source_next232_token']),
    'first handoff channel' => static fn (): mixed => $plan232()->handoffRows()[0]['handoff_channel'],
    'first handoff page' => static fn (): mixed => $plan232()->handoffRows()[0]['handoff_page'],
    'first next handoff page' => static fn (): mixed => $plan232()->handoffRows()[0]['next_handoff_page'],
    'first admitted pages' => static fn (): mixed => $plan232()->handoffRows()[0]['handoff_admitted_pages'],
    'first admitted pointer maps' => static fn (): mixed => $plan232()->handoffRows()[0]['handoff_admitted_pointer_map_pages'],
    'first previous token' => static fn (): mixed => $plan232()->handoffRows()[0]['previous_handoff_token'],
    'second handoff channel' => static fn (): mixed => $plan232()->handoffRows()[1]['handoff_channel'],
    'second handoff page' => static fn (): mixed => $plan232()->handoffRows()[1]['handoff_page'],
    'second next handoff page' => static fn (): mixed => $plan232()->handoffRows()[1]['next_handoff_page'],
    'second admitted pages' => static fn (): mixed => $plan232()->handoffRows()[1]['handoff_admitted_pages'],
    'second admitted pointer maps' => static fn (): mixed => $plan232()->handoffRows()[1]['handoff_admitted_pointer_map_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan232()->handoffRows()[1]['previous_handoff_token']),
    'third handoff channel' => static fn (): mixed => $plan232()->handoffRows()[2]['handoff_channel'],
    'third handoff page' => static fn (): mixed => $plan232()->handoffRows()[2]['handoff_page'],
    'third admitted pointer maps' => static fn (): mixed => $plan232()->handoffRows()[2]['handoff_admitted_pointer_map_pages'],
    'fourth handoff channel' => static fn (): mixed => $plan232()->handoffRows()[3]['handoff_channel'],
    'fourth handoff page' => static fn (): mixed => $plan232()->handoffRows()[3]['handoff_page'],
    'fifth handoff channel' => static fn (): mixed => $plan232()->handoffRows()[4]['handoff_channel'],
    'fifth handoff page' => static fn (): mixed => $plan232()->handoffRows()[4]['handoff_page'],
    'sixth handoff channel' => static fn (): mixed => $plan232()->handoffRows()[5]['handoff_channel'],
    'sixth handoff page' => static fn (): mixed => $plan232()->handoffRows()[5]['handoff_page'],
    'last handoff channel' => static fn (): mixed => $plan232()->handoffRows()[6]['handoff_channel'],
    'last handoff page' => static fn (): mixed => $plan232()->handoffRows()[6]['handoff_page'],
    'last next handoff page' => static fn (): mixed => $plan232()->handoffRows()[6]['next_handoff_page'],
    'handoff ordinals' => static fn (): mixed => array_column($plan232()->handoffRows(), 'handoff_ordinal'),
    'resume ordinals' => static fn (): mixed => array_column($plan232()->handoffRows(), 'resume_ordinal'),
    'row states' => static fn (): mixed => array_column($plan232()->handoffRows(), 'handoff_state'),
    'row resume token flags' => static fn (): mixed => array_column($plan232()->handoffRows(), 'resume_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan232()->handoffRows(), 'handoff_link_valid'),
    'row pointer flags' => static fn (): mixed => array_column($plan232()->handoffRows(), 'pointer_map_handoff_visible'),
    'row payload admission flags' => static fn (): mixed => array_column($plan232()->handoffRows(), 'payload_handoff_admitted_after_pointer_map'),
    'row freeblock flags' => static fn (): mixed => array_column($plan232()->handoffRows(), 'freeblock_handoff_receipt_carried'),
    'row tail fence flags' => static fn (): mixed => array_column($plan232()->handoffRows(), 'tail_pages_fenced_for_handoff'),
    'row monotonic flags' => static fn (): mixed => array_column($plan232()->handoffRows(), 'handoff_window_monotonic'),
    'batch size three row count' => static fn (): mixed => $plan232(3)->handoffSummary()['handoff_row_count'],
    'batch size three pages' => static fn (): mixed => $plan232(3)->handoffPages(),
    'batch size three next pages' => static fn (): mixed => $plan232(3)->nextHandoffPages(),
    'batch size three token count' => static fn (): mixed => count($plan232(3)->handoffTokens()),
    'dependency closure' => static fn (): mixed => $plan232()->handoffSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan232()->handoffSummary()['non_overlap'], 'does not repeat publication-resume-window'),
    'resume action' => static fn (): mixed => $plan232()->resumePlan->toArray()['action'],
    'resume row count' => static fn (): mixed => $plan232()->resumePlan->resumeSummary()['resume_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message232(static fn () => $plan232(0)),
];

$expected232 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next232',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next232-ready',
    'handoff row count' => 7,
    'handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'next handoff pages' => [3, 105, 106, 105, 107, 108, null],
    'pointer map handoff pages' => [2, 105],
    'payload handoff pages' => [3, 106, 107, 108],
    'resume pages' => [2, 3, 105, 106, 105, 107, 108],
    'handoff pages match resume pages' => true,
    'handoff errors' => [],
    'summary handoff errors' => [],
    'all resume tokens match' => true,
    'all handoff links valid' => true,
    'all pointer maps visible' => true,
    'all payload admitted after pointer map' => true,
    'all freeblock receipts carried' => true,
    'all tail pages fenced' => true,
    'all windows monotonic' => true,
    'handoff token count' => 7,
    'handoff token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'handoff signature length' => 64,
    'current source token length' => 64,
    'first handoff channel' => 'pointer-map',
    'first handoff page' => 2,
    'first next handoff page' => 3,
    'first admitted pages' => [2],
    'first admitted pointer maps' => [2],
    'first previous token' => null,
    'second handoff channel' => 'payload',
    'second handoff page' => 3,
    'second next handoff page' => 105,
    'second admitted pages' => [2, 3],
    'second admitted pointer maps' => [2],
    'second previous token length' => 64,
    'third handoff channel' => 'pointer-map',
    'third handoff page' => 105,
    'third admitted pointer maps' => [2, 105],
    'fourth handoff channel' => 'payload',
    'fourth handoff page' => 106,
    'fifth handoff channel' => 'pointer-map',
    'fifth handoff page' => 105,
    'sixth handoff channel' => 'payload',
    'sixth handoff page' => 107,
    'last handoff channel' => 'payload',
    'last handoff page' => 108,
    'last next handoff page' => null,
    'handoff ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'resume ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next-writer-handoff-admitted', 'current-source-next-writer-handoff-admitted', 'current-source-next-writer-handoff-admitted', 'current-source-next-writer-handoff-admitted', 'current-source-next-writer-handoff-admitted', 'current-source-next-writer-handoff-admitted', 'current-source-next-writer-handoff-admitted'],
    'row resume token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row pointer flags' => [true, true, true, true, true, true, true],
    'row payload admission flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'row monotonic flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three token count' => 6,
    'dependency closure' => 'no new support component needed; next232 reuses publication-resume-window resume rows and adds next-writer handoff admission only',
    'non overlap' => true,
    'resume action' => 'btree-vacuum-pointermap-freeblock-publication-resume-window',
    'resume row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases232 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next232 ' . $name] = static function (TestRunner $t) use ($callback, $expected232, $name): void {
        $t->same($expected232[$name], $callback());
    };
}

foreach (range(1, 90) as $index) {
    $tests['btree vacuum pointermap freeblock current source next232 handoff invariant ' . $index] = static function (TestRunner $t) use ($plan232): void {
        $plan = $plan232();
        $summary = $plan->handoffSummary();

        $t->same([], $plan->handoffErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->handoffPages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->nextHandoffPages());
        $t->same([2, 105], $plan->pointerMapHandoffPages());
        $t->same([3, 106, 107, 108], $plan->payloadHandoffPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->handoffRows(), 'handoff_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'resume_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'handoff_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'pointer_map_handoff_visible'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'payload_handoff_admitted_after_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'tail_pages_fenced_for_handoff'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->handoffTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next232-ready', $summary['status']);
        $t->same(true, $summary['handoff_pages_match_resume_pages']);
        $t->same(true, $summary['all_payload_handoffs_admitted_after_pointer_map']);
    };
}

return $tests;
