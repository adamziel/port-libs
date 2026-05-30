<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage208 = static function (int $pageCount): string {
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

$putPointerMapEntry208 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database208 = static function () use ($makeFirstPage208, $putPointerMapEntry208): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage208(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next208', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry208($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan208 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database208;

    $database = $database208();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafReuseCursorFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next208-current-source-next-', 50),
        3,
        true,
        $batchSize,
    );
};

$message208 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases208 = [
    'action label' => static fn (): mixed => $plan208()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan208()->sourceNextSummary()['status'],
    'row count' => static fn (): mixed => $plan208()->sourceNextSummary()['source_next_row_count'],
    'next readable pages' => static fn (): mixed => $plan208()->nextReadablePages(),
    'summary readable pages' => static fn (): mixed => $plan208()->sourceNextSummary()['next_readable_pages'],
    'pointer map pages' => static fn (): mixed => $plan208()->pointerMapSourcePages(),
    'payload pages' => static fn (): mixed => $plan208()->payloadSourcePages(),
    'sealed pages carried' => static fn (): mixed => $plan208()->sourceNextSummary()['sealed_pages'],
    'errors' => static fn (): mixed => $plan208()->sourceNextErrors(),
    'summary errors' => static fn (): mixed => $plan208()->sourceNextSummary()['source_next_errors'],
    'tokens count' => static fn (): mixed => count($plan208()->sourceNextTokens()),
    'tokens lengths' => static fn (): mixed => array_map('strlen', $plan208()->sourceNextTokens()),
    'signature length' => static fn (): mixed => strlen($plan208()->sourceNextSummary()['source_next_signature']),
    'next reader token length' => static fn (): mixed => strlen($plan208()->sourceNextSummary()['next_reader_source_token']),
    'all seal tokens match' => static fn (): mixed => $plan208()->sourceNextSummary()['all_seal_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan208()->sourceNextSummary()['all_pointer_maps_before_payload'],
    'all tail pages fenced' => static fn (): mixed => $plan208()->sourceNextSummary()['all_tail_pages_fenced'],
    'all chains valid' => static fn (): mixed => $plan208()->sourceNextSummary()['all_source_next_chains_valid'],
    'first kind' => static fn (): mixed => $plan208()->sourceNextRows()[0]['source_next_kind'],
    'first pages' => static fn (): mixed => $plan208()->sourceNextRows()[0]['source_next_pages'],
    'first previous token' => static fn (): mixed => $plan208()->sourceNextRows()[0]['previous_source_next_token'],
    'first high water' => static fn (): mixed => $plan208()->sourceNextRows()[0]['high_water_page'],
    'second kind' => static fn (): mixed => $plan208()->sourceNextRows()[1]['source_next_kind'],
    'second pages' => static fn (): mixed => $plan208()->sourceNextRows()[1]['source_next_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan208()->sourceNextRows()[1]['previous_source_next_token']),
    'second high water' => static fn (): mixed => $plan208()->sourceNextRows()[1]['high_water_page'],
    'row ordinals' => static fn (): mixed => array_column($plan208()->sourceNextRows(), 'source_next_ordinal'),
    'row states' => static fn (): mixed => array_column($plan208()->sourceNextRows(), 'source_next_state'),
    'row seal flags' => static fn (): mixed => array_column($plan208()->sourceNextRows(), 'seal_token_matches'),
    'row admitted flags' => static fn (): mixed => array_column($plan208()->sourceNextRows(), 'next_reader_admitted'),
    'row tail fence flags' => static fn (): mixed => array_column($plan208()->sourceNextRows(), 'tail_pages_fenced'),
    'row chain flags' => static fn (): mixed => array_column($plan208()->sourceNextRows(), 'source_next_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan208()->sourceNextRows(), 'high_water_page'),
    'batch size three rows' => static fn (): mixed => $plan208(3)->sourceNextSummary()['source_next_row_count'],
    'batch size three readable pages' => static fn (): mixed => $plan208(3)->nextReadablePages(),
    'batch size three source pages' => static fn (): mixed => array_column($plan208(3)->sourceNextRows(), 'source_next_pages'),
    'base action' => static fn (): mixed => $plan208()->basePlan->toArray()['action'],
    'base sealed pages' => static fn (): mixed => $plan208()->basePlan->sealedPages(),
    'base seal row count' => static fn (): mixed => $plan208()->basePlan->sealedCurrentSourceSummary()['seal_row_count'],
    'base tail pages fenced' => static fn (): mixed => $plan208()->basePlan->sealedCurrentSourceSummary()['all_tail_pages_fenced'],
    'seal signature length' => static fn (): mixed => strlen($plan208()->sourceNextSummary()['seal_signature']),
    'writer freeblock token length' => static fn (): mixed => strlen($plan208()->sourceNextSummary()['next_writer_freeblock_source_token']),
    'dependency closure' => static fn (): mixed => $plan208()->sourceNextSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan208()->sourceNextSummary()['non_overlap'], 'does not repeat sealed-writer-admission'),
    'bad batch size rejected' => static fn (): mixed => $message208(static fn () => $plan208(0)),
];

$expected208 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next208',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next208-ready',
    'row count' => 2,
    'next readable pages' => [2, 3, 105, 106, 107, 108],
    'summary readable pages' => [2, 3, 105, 106, 107, 108],
    'pointer map pages' => [2, 105],
    'payload pages' => [3, 106, 107, 108],
    'sealed pages carried' => [2, 3, 105, 106, 107, 108],
    'errors' => [],
    'summary errors' => [],
    'tokens count' => 2,
    'tokens lengths' => [64, 64],
    'signature length' => 64,
    'next reader token length' => 64,
    'all seal tokens match' => true,
    'all pointer maps before payload' => true,
    'all tail pages fenced' => true,
    'all chains valid' => true,
    'first kind' => 'pointer-map-source-next',
    'first pages' => [2, 105],
    'first previous token' => null,
    'first high water' => 105,
    'second kind' => 'payload-source-next',
    'second pages' => [3, 106, 107, 108],
    'second previous token length' => 64,
    'second high water' => 108,
    'row ordinals' => [1, 2],
    'row states' => ['current-source-next-reader-ready', 'current-source-next-reader-ready'],
    'row seal flags' => [true, true],
    'row admitted flags' => [true, true],
    'row tail fence flags' => [true, true],
    'row chain flags' => [true, true],
    'row high water pages' => [105, 108],
    'batch size three rows' => 2,
    'batch size three readable pages' => [2, 3, 105, 106, 107, 108],
    'batch size three source pages' => [[2, 105], [3, 106, 107, 108]],
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-sealed-writer-admission',
    'base sealed pages' => [2, 3, 105, 106, 107, 108],
    'base seal row count' => 6,
    'base tail pages fenced' => true,
    'seal signature length' => 64,
    'writer freeblock token length' => 64,
    'dependency closure' => 'no new support component needed; next208 reuses sealed-writer-admission sealed pointer-map/payload rows, freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases208 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next208 ' . $name] = static function (TestRunner $t) use ($callback, $expected208, $name): void {
        $t->same($expected208[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next208 source-next invariant ' . $index] = static function (TestRunner $t) use ($plan208): void {
        $plan = $plan208();
        $summary = $plan->sourceNextSummary();

        $t->same([], $plan->sourceNextErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->nextReadablePages());
        $t->same([2, 105], $plan->pointerMapSourcePages());
        $t->same([3, 106, 107, 108], $plan->payloadSourcePages());
        $t->same([1, 2], array_column($plan->sourceNextRows(), 'source_next_ordinal'));
        $t->same(['pointer-map-source-next', 'payload-source-next'], array_column($plan->sourceNextRows(), 'source_next_kind'));
        $t->same([true, true], array_column($plan->sourceNextRows(), 'seal_token_matches'));
        $t->same([true, true], array_column($plan->sourceNextRows(), 'tail_pages_fenced'));
        $t->same([64, 64], array_map('strlen', $plan->sourceNextTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next208-ready', $summary['status']);
    };
}

return $tests;
