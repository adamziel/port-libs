<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Plan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage215 = static function (int $pageCount): string {
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

$putPointerMapEntry215 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database215 = static function () use ($makeFirstPage215, $putPointerMapEntry215): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage215(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next215', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry215($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan215 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Plan {
    global $database215;

    $database = $database215();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Plan::tableLeafFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next215-current-source-commit-', 50),
        3,
        true,
        $batchSize,
    );
};

$message215 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases215 = [
    'action label' => static fn (): mixed => $plan215()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan215()->commitSummary()['status'],
    'commit row count' => static fn (): mixed => $plan215()->commitSummary()['commit_row_count'],
    'committed pages' => static fn (): mixed => $plan215()->committedPages(),
    'summary committed pages' => static fn (): mixed => $plan215()->commitSummary()['committed_pages'],
    'pointer map committed pages' => static fn (): mixed => $plan215()->committedPointerMapPages(),
    'payload committed pages' => static fn (): mixed => $plan215()->committedPayloadPages(),
    'apply pages' => static fn (): mixed => $plan215()->commitSummary()['apply_pages'],
    'commit matches apply pages' => static fn (): mixed => $plan215()->commitSummary()['commit_matches_apply_pages'],
    'commit errors' => static fn (): mixed => $plan215()->commitErrors(),
    'summary commit errors' => static fn (): mixed => $plan215()->commitSummary()['commit_errors'],
    'all apply tokens match' => static fn (): mixed => $plan215()->commitSummary()['all_apply_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan215()->commitSummary()['all_pointer_maps_committed_before_payload'],
    'all freeblock receipts committed' => static fn (): mixed => $plan215()->commitSummary()['all_freeblock_receipts_committed'],
    'all tail pages fenced' => static fn (): mixed => $plan215()->commitSummary()['all_tail_pages_fenced_for_commit'],
    'all commit chains valid' => static fn (): mixed => $plan215()->commitSummary()['all_commit_chains_valid'],
    'commit token count' => static fn (): mixed => count($plan215()->commitTokens()),
    'commit token lengths' => static fn (): mixed => array_map('strlen', $plan215()->commitTokens()),
    'commit signature length' => static fn (): mixed => strlen($plan215()->commitSummary()['commit_signature']),
    'next writer token length' => static fn (): mixed => strlen($plan215()->commitSummary()['next_writer_commit_token']),
    'first commit channel' => static fn (): mixed => $plan215()->commitRows()[0]['commit_channel'],
    'first commit pages' => static fn (): mixed => $plan215()->commitRows()[0]['commit_pages'],
    'first visible pages' => static fn (): mixed => $plan215()->commitRows()[0]['committed_visible_pages'],
    'first previous token' => static fn (): mixed => $plan215()->commitRows()[0]['previous_commit_token'],
    'second commit channel' => static fn (): mixed => $plan215()->commitRows()[1]['commit_channel'],
    'second commit pages' => static fn (): mixed => $plan215()->commitRows()[1]['commit_pages'],
    'second visible pages' => static fn (): mixed => $plan215()->commitRows()[1]['committed_visible_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan215()->commitRows()[1]['previous_commit_token']),
    'third commit channel' => static fn (): mixed => $plan215()->commitRows()[2]['commit_channel'],
    'third commit pages' => static fn (): mixed => $plan215()->commitRows()[2]['commit_pages'],
    'third visible pages' => static fn (): mixed => $plan215()->commitRows()[2]['committed_visible_pages'],
    'fourth commit channel' => static fn (): mixed => $plan215()->commitRows()[3]['commit_channel'],
    'fourth commit pages' => static fn (): mixed => $plan215()->commitRows()[3]['commit_pages'],
    'fourth visible pages' => static fn (): mixed => $plan215()->commitRows()[3]['committed_visible_pages'],
    'fifth commit channel' => static fn (): mixed => $plan215()->commitRows()[4]['commit_channel'],
    'fifth commit pages' => static fn (): mixed => $plan215()->commitRows()[4]['commit_pages'],
    'fifth visible pages' => static fn (): mixed => $plan215()->commitRows()[4]['committed_visible_pages'],
    'sixth commit channel' => static fn (): mixed => $plan215()->commitRows()[5]['commit_channel'],
    'sixth commit pages' => static fn (): mixed => $plan215()->commitRows()[5]['commit_pages'],
    'sixth visible pages' => static fn (): mixed => $plan215()->commitRows()[5]['committed_visible_pages'],
    'commit ordinals' => static fn (): mixed => array_column($plan215()->commitRows(), 'commit_ordinal'),
    'row states' => static fn (): mixed => array_column($plan215()->commitRows(), 'commit_state'),
    'row apply token flags' => static fn (): mixed => array_column($plan215()->commitRows(), 'apply_token_matches'),
    'row freeblock flags' => static fn (): mixed => array_column($plan215()->commitRows(), 'freeblock_receipt_committed'),
    'row tail fence flags' => static fn (): mixed => array_column($plan215()->commitRows(), 'tail_pages_fenced_for_commit'),
    'row chain flags' => static fn (): mixed => array_column($plan215()->commitRows(), 'commit_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan215()->commitRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan215(3)->commitSummary()['commit_row_count'],
    'batch size three pages' => static fn (): mixed => $plan215(3)->committedPages(),
    'batch size three commit batches' => static fn (): mixed => array_column($plan215(3)->commitRows(), 'commit_pages'),
    'batch size three token count' => static fn (): mixed => count($plan215(3)->commitTokens()),
    'dependency closure' => static fn (): mixed => $plan215()->commitSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan215()->commitSummary()['non_overlap'], 'does not repeat next212'),
    'base action' => static fn (): mixed => $plan215()->basePlan->toArray()['action'],
    'base apply rows' => static fn (): mixed => $plan215()->basePlan->applySummary()['apply_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message215(static fn () => $plan215(0)),
];

$expected215 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next215',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next215-ready',
    'commit row count' => 6,
    'committed pages' => [2, 3, 105, 106, 107, 108],
    'summary committed pages' => [2, 3, 105, 106, 107, 108],
    'pointer map committed pages' => [2, 105],
    'payload committed pages' => [3, 106, 107, 108],
    'apply pages' => [2, 3, 105, 106, 107, 108],
    'commit matches apply pages' => true,
    'commit errors' => [],
    'summary commit errors' => [],
    'all apply tokens match' => true,
    'all pointer maps before payload' => true,
    'all freeblock receipts committed' => true,
    'all tail pages fenced' => true,
    'all commit chains valid' => true,
    'commit token count' => 6,
    'commit token lengths' => [64, 64, 64, 64, 64, 64],
    'commit signature length' => 64,
    'next writer token length' => 64,
    'first commit channel' => 'pointer-map',
    'first commit pages' => [2],
    'first visible pages' => [2],
    'first previous token' => null,
    'second commit channel' => 'payload',
    'second commit pages' => [3],
    'second visible pages' => [2, 3],
    'second previous token length' => 64,
    'third commit channel' => 'pointer-map',
    'third commit pages' => [105],
    'third visible pages' => [2, 3, 105],
    'fourth commit channel' => 'payload',
    'fourth commit pages' => [106],
    'fourth visible pages' => [2, 3, 105, 106],
    'fifth commit channel' => 'pointer-map',
    'fifth commit pages' => [105],
    'fifth visible pages' => [2, 3, 105, 106],
    'sixth commit channel' => 'payload',
    'sixth commit pages' => [107, 108],
    'sixth visible pages' => [2, 3, 105, 106, 107, 108],
    'commit ordinals' => [1, 2, 3, 4, 5, 6],
    'row states' => ['current-source-page-commit-ready', 'current-source-page-commit-ready', 'current-source-page-commit-ready', 'current-source-page-commit-ready', 'current-source-page-commit-ready', 'current-source-page-commit-ready'],
    'row apply token flags' => [true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true],
    'row chain flags' => [true, true, true, true, true, true],
    'row high water pages' => [3, 3, 106, 106, 108, 108],
    'batch size three row count' => 4,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three commit batches' => [[2], [3], [105], [106, 107, 108]],
    'batch size three token count' => 4,
    'dependency closure' => 'no new support component needed; next215 reuses next212 applied current-source page rows, pointer-map apply ordering, leaf freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next212',
    'base apply rows' => 6,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases215 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next215 ' . $name] = static function (TestRunner $t) use ($callback, $expected215, $name): void {
        $t->same($expected215[$name], $callback());
    };
}

foreach (range(1, 85) as $index) {
    $tests['btree vacuum pointermap freeblock current source next215 commit invariant ' . $index] = static function (TestRunner $t) use ($plan215): void {
        $plan = $plan215();
        $summary = $plan->commitSummary();

        $t->same([], $plan->commitErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->committedPages());
        $t->same([2, 105], $plan->committedPointerMapPages());
        $t->same([3, 106, 107, 108], $plan->committedPayloadPages());
        $t->same([1, 2, 3, 4, 5, 6], array_column($plan->commitRows(), 'commit_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($plan->commitRows(), 'apply_token_matches'));
        $t->same([true, true, true, true, true, true], array_column($plan->commitRows(), 'freeblock_receipt_committed'));
        $t->same([true, true, true, true, true, true], array_column($plan->commitRows(), 'tail_pages_fenced_for_commit'));
        $t->same([64, 64, 64, 64, 64, 64], array_map('strlen', $plan->commitTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next215-ready', $summary['status']);
        $t->same(true, $summary['commit_matches_apply_pages']);
    };
}

return $tests;
