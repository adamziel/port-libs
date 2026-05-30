<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage224 = static function (int $pageCount): string {
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

$putPointerMapEntry224 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database224 = static function () use ($makeFirstPage224, $putPointerMapEntry224): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage224(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_val', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry224($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan224 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database224;

    $database = $database224();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafCheckpointValidationAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('validn-current-source-cursor-', 50),
        3,
        true,
        $batchSize,
    );
};

$message224 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases224 = [
    'action label' => static fn (): mixed => $plan224()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan224()->sourceSummary()['status'],
    'source row count' => static fn (): mixed => $plan224()->sourceSummary()['source_row_count'],
    'current source pages' => static fn (): mixed => $plan224()->currentSourcePages(),
    'summary current source pages' => static fn (): mixed => $plan224()->sourceSummary()['current_source_pages'],
    'next source pages' => static fn (): mixed => $plan224()->nextSourcePages(),
    'pointer map source pages' => static fn (): mixed => $plan224()->pointerMapSourcePages(),
    'payload source pages' => static fn (): mixed => $plan224()->payloadSourcePages(),
    'write pages' => static fn (): mixed => $plan224()->sourceSummary()['write_pages'],
    'source pages match write pages' => static fn (): mixed => $plan224()->sourceSummary()['source_pages_match_write_pages'],
    'source errors' => static fn (): mixed => $plan224()->sourceErrors(),
    'summary source errors' => static fn (): mixed => $plan224()->sourceSummary()['source_errors'],
    'all source tokens match writes' => static fn (): mixed => $plan224()->sourceSummary()['all_source_tokens_match_writes'],
    'all next links valid' => static fn (): mixed => $plan224()->sourceSummary()['all_next_links_valid'],
    'all pointer maps visible before payload source' => static fn (): mixed => $plan224()->sourceSummary()['all_pointer_maps_visible_before_payload_source'],
    'all freeblock receipts carried' => static fn (): mixed => $plan224()->sourceSummary()['all_freeblock_receipts_carried'],
    'all tail pages fenced' => static fn (): mixed => $plan224()->sourceSummary()['all_tail_pages_fenced_for_source'],
    'source token count' => static fn (): mixed => count($plan224()->sourceTokens()),
    'source token lengths' => static fn (): mixed => array_map('strlen', $plan224()->sourceTokens()),
    'source signature length' => static fn (): mixed => strlen($plan224()->sourceSummary()['source_signature']),
    'current source token length' => static fn (): mixed => strlen($plan224()->sourceSummary()['current_source_checkpoint_validation_token']),
    'first source channel' => static fn (): mixed => $plan224()->sourceRows()[0]['source_channel'],
    'first current source page' => static fn (): mixed => $plan224()->sourceRows()[0]['current_source_page'],
    'first next source page' => static fn (): mixed => $plan224()->sourceRows()[0]['next_source_page'],
    'first visible pointer maps' => static fn (): mixed => $plan224()->sourceRows()[0]['visible_pointer_map_pages'],
    'first previous token' => static fn (): mixed => $plan224()->sourceRows()[0]['previous_source_token'],
    'second source channel' => static fn (): mixed => $plan224()->sourceRows()[1]['source_channel'],
    'second current source page' => static fn (): mixed => $plan224()->sourceRows()[1]['current_source_page'],
    'second next source page' => static fn (): mixed => $plan224()->sourceRows()[1]['next_source_page'],
    'second visible pointer maps' => static fn (): mixed => $plan224()->sourceRows()[1]['visible_pointer_map_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan224()->sourceRows()[1]['previous_source_token']),
    'third source channel' => static fn (): mixed => $plan224()->sourceRows()[2]['source_channel'],
    'third current source page' => static fn (): mixed => $plan224()->sourceRows()[2]['current_source_page'],
    'third next source page' => static fn (): mixed => $plan224()->sourceRows()[2]['next_source_page'],
    'third visible pointer maps' => static fn (): mixed => $plan224()->sourceRows()[2]['visible_pointer_map_pages'],
    'fourth source channel' => static fn (): mixed => $plan224()->sourceRows()[3]['source_channel'],
    'fourth current source page' => static fn (): mixed => $plan224()->sourceRows()[3]['current_source_page'],
    'fourth next source page' => static fn (): mixed => $plan224()->sourceRows()[3]['next_source_page'],
    'fifth source channel' => static fn (): mixed => $plan224()->sourceRows()[4]['source_channel'],
    'fifth current source page' => static fn (): mixed => $plan224()->sourceRows()[4]['current_source_page'],
    'fifth next source page' => static fn (): mixed => $plan224()->sourceRows()[4]['next_source_page'],
    'sixth source channel' => static fn (): mixed => $plan224()->sourceRows()[5]['source_channel'],
    'sixth current source page' => static fn (): mixed => $plan224()->sourceRows()[5]['current_source_page'],
    'sixth next source page' => static fn (): mixed => $plan224()->sourceRows()[5]['next_source_page'],
    'last source channel' => static fn (): mixed => $plan224()->sourceRows()[6]['source_channel'],
    'last current source page' => static fn (): mixed => $plan224()->sourceRows()[6]['current_source_page'],
    'last next source page' => static fn (): mixed => $plan224()->sourceRows()[6]['next_source_page'],
    'source ordinals' => static fn (): mixed => array_column($plan224()->sourceRows(), 'source_ordinal'),
    'write ordinals' => static fn (): mixed => array_column($plan224()->sourceRows(), 'write_ordinal'),
    'row states' => static fn (): mixed => array_column($plan224()->sourceRows(), 'source_state'),
    'row write token flags' => static fn (): mixed => array_column($plan224()->sourceRows(), 'write_token_matches'),
    'row next flags' => static fn (): mixed => array_column($plan224()->sourceRows(), 'next_link_valid'),
    'row pointer visibility flags' => static fn (): mixed => array_column($plan224()->sourceRows(), 'pointer_map_visible_before_payload_source'),
    'row freeblock flags' => static fn (): mixed => array_column($plan224()->sourceRows(), 'freeblock_receipt_carried'),
    'row tail fence flags' => static fn (): mixed => array_column($plan224()->sourceRows(), 'tail_pages_fenced_for_source'),
    'batch size three source count' => static fn (): mixed => $plan224(3)->sourceSummary()['source_row_count'],
    'batch size three current pages' => static fn (): mixed => $plan224(3)->currentSourcePages(),
    'batch size three next pages' => static fn (): mixed => $plan224(3)->nextSourcePages(),
    'batch size three token count' => static fn (): mixed => count($plan224(3)->sourceTokens()),
    'dependency closure' => static fn (): mixed => $plan224()->sourceSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan224()->sourceSummary()['non_overlap'], 'does not repeat write-receipts'),
    'base action' => static fn (): mixed => $plan224()->basePlan->toArray()['action'],
    'base write rows' => static fn (): mixed => $plan224()->basePlan->writeSummary()['write_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message224(static fn () => $plan224(0)),
];

$expected224 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-checkpoint-validation',
    'summary status' => 'btree-vacuum-pointermap-freeblock-checkpoint-validation-ready',
    'source row count' => 7,
    'current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'next source pages' => [3, 105, 106, 105, 107, 108, null],
    'pointer map source pages' => [2, 105],
    'payload source pages' => [3, 106, 107, 108],
    'write pages' => [2, 3, 105, 106, 107, 108],
    'source pages match write pages' => true,
    'source errors' => [],
    'summary source errors' => [],
    'all source tokens match writes' => true,
    'all next links valid' => true,
    'all pointer maps visible before payload source' => true,
    'all freeblock receipts carried' => true,
    'all tail pages fenced' => true,
    'source token count' => 7,
    'source token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'source signature length' => 64,
    'current source token length' => 64,
    'first source channel' => 'pointer-map',
    'first current source page' => 2,
    'first next source page' => 3,
    'first visible pointer maps' => [2],
    'first previous token' => null,
    'second source channel' => 'payload',
    'second current source page' => 3,
    'second next source page' => 105,
    'second visible pointer maps' => [2],
    'second previous token length' => 64,
    'third source channel' => 'pointer-map',
    'third current source page' => 105,
    'third next source page' => 106,
    'third visible pointer maps' => [2, 105],
    'fourth source channel' => 'payload',
    'fourth current source page' => 106,
    'fourth next source page' => 105,
    'fifth source channel' => 'pointer-map',
    'fifth current source page' => 105,
    'fifth next source page' => 107,
    'sixth source channel' => 'payload',
    'sixth current source page' => 107,
    'sixth next source page' => 108,
    'last source channel' => 'payload',
    'last current source page' => 108,
    'last next source page' => null,
    'source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'write ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next-page-receipted', 'current-source-next-page-receipted', 'current-source-next-page-receipted', 'current-source-next-page-receipted', 'current-source-next-page-receipted', 'current-source-next-page-receipted', 'current-source-next-page-receipted'],
    'row write token flags' => [true, true, true, true, true, true, true],
    'row next flags' => [true, true, true, true, true, true, true],
    'row pointer visibility flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three source count' => 6,
    'batch size three current pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three token count' => 6,
    'dependency closure' => 'no new support component needed; checkpoint-validation reuses write-receipts write receipts and adds current-source next-page cursor sequencing only',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-write-receipts',
    'base write rows' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases224 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source checkpoint-validation ' . $name] = static function (TestRunner $t) use ($callback, $expected224, $name): void {
        $t->same($expected224[$name], $callback());
    };
}

foreach (range(1, 75) as $index) {
    $tests['btree vacuum pointermap freeblock current source checkpoint-validation cursor invariant ' . $index] = static function (TestRunner $t) use ($plan224): void {
        $plan = $plan224();
        $summary = $plan->sourceSummary();

        $t->same([], $plan->sourceErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->currentSourcePages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->nextSourcePages());
        $t->same([2, 105], $plan->pointerMapSourcePages());
        $t->same([3, 106, 107, 108], $plan->payloadSourcePages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->sourceRows(), 'source_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'write_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'next_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'pointer_map_visible_before_payload_source'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->sourceTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-checkpoint-validation-ready', $summary['status']);
        $t->same(true, $summary['source_pages_match_write_pages']);
        $t->same(true, $summary['all_next_links_valid']);
    };
}

return $tests;
