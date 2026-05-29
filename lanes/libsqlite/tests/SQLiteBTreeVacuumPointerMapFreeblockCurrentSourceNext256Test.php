<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage256 = static function (int $pageCount): string {
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

$putPointerMapEntry256 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database256 = static function () use ($makeFirstPage256, $putPointerMapEntry256): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage256(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next256', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry256($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan256 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database256;

    $database = $database256();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafReceiptPublicationFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next256-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message256 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases256 = [
    'action label' => static fn (): mixed => $plan256()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan256()->publicationSummary()['status'],
    'publication row count' => static fn (): mixed => $plan256()->publicationSummary()['publication_row_count'],
    'published pages' => static fn (): mixed => $plan256()->publishedPages(),
    'summary published pages' => static fn (): mixed => $plan256()->publicationSummary()['published_pages'],
    'summary admitted pages' => static fn (): mixed => $plan256()->publicationSummary()['admitted_pages'],
    'published pages match admitted pages' => static fn (): mixed => $plan256()->publicationSummary()['published_pages_match_admitted_pages'],
    'pointer map publication pages' => static fn (): mixed => $plan256()->pointerMapPublicationPages(),
    'summary pointer map publication pages' => static fn (): mixed => $plan256()->publicationSummary()['pointer_map_publication_pages'],
    'freeblock receipt pages' => static fn (): mixed => $plan256()->freeblockReceiptPages(),
    'summary freeblock receipt pages' => static fn (): mixed => $plan256()->publicationSummary()['freeblock_receipt_pages'],
    'reusable payload pages' => static fn (): mixed => $plan256()->reusablePayloadPages(),
    'summary reusable payload pages' => static fn (): mixed => $plan256()->publicationSummary()['reusable_payload_pages'],
    'duplicate pointer map pages' => static fn (): mixed => $plan256()->duplicatePointerMapPages(),
    'summary duplicate pointer map pages' => static fn (): mixed => $plan256()->publicationSummary()['duplicate_pointer_map_pages'],
    'publication errors' => static fn (): mixed => $plan256()->publicationErrors(),
    'summary publication errors' => static fn (): mixed => $plan256()->publicationSummary()['publication_errors'],
    'all admission tokens match' => static fn (): mixed => $plan256()->publicationSummary()['all_admission_tokens_match'],
    'all pointer maps publish before payload reuse' => static fn (): mixed => $plan256()->publicationSummary()['all_pointer_maps_publish_before_payload_reuse'],
    'all freeblock receipts visible before reuse' => static fn (): mixed => $plan256()->publicationSummary()['all_freeblock_receipts_visible_before_reuse'],
    'all payload reuse has cursor advance' => static fn (): mixed => $plan256()->publicationSummary()['all_payload_reuse_has_cursor_advance'],
    'all duplicate pointer maps keep generation' => static fn (): mixed => $plan256()->publicationSummary()['all_duplicate_pointer_maps_keep_generation'],
    'all tail pages remain fenced' => static fn (): mixed => $plan256()->publicationSummary()['all_tail_pages_remain_fenced'],
    'token count' => static fn (): mixed => count($plan256()->publicationTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan256()->publicationTokens()),
    'signature length' => static fn (): mixed => strlen($plan256()->publicationSummary()['publication_signature']),
    'current token length' => static fn (): mixed => strlen($plan256()->publicationSummary()['current_source_next256_token']),
    'first row state' => static fn (): mixed => $plan256()->publicationRows()[0]['publication_state'],
    'first channel' => static fn (): mixed => $plan256()->publicationRows()[0]['publication_channel'],
    'first published page' => static fn (): mixed => $plan256()->publicationRows()[0]['published_page'],
    'first pointer map generations' => static fn (): mixed => $plan256()->publicationRows()[0]['pointer_map_generations'],
    'second channel' => static fn (): mixed => $plan256()->publicationRows()[1]['publication_channel'],
    'second published page' => static fn (): mixed => $plan256()->publicationRows()[1]['published_page'],
    'second freeblock receipts' => static fn (): mixed => $plan256()->publicationRows()[1]['visible_freeblock_receipt_pages'],
    'third pointer map generations' => static fn (): mixed => $plan256()->publicationRows()[2]['pointer_map_generations'],
    'fifth duplicate pointer map flag' => static fn (): mixed => $plan256()->publicationRows()[4]['duplicate_pointer_map_generation'],
    'last row published page' => static fn (): mixed => $plan256()->publicationRows()[6]['published_page'],
    'ordinals' => static fn (): mixed => array_column($plan256()->publicationRows(), 'publication_ordinal'),
    'admission ordinals' => static fn (): mixed => array_column($plan256()->publicationRows(), 'admission_ordinal'),
    'row states' => static fn (): mixed => array_column($plan256()->publicationRows(), 'publication_state'),
    'row admission token flags' => static fn (): mixed => array_column($plan256()->publicationRows(), 'admission_token_matches'),
    'row pointer before payload flags' => static fn (): mixed => array_column($plan256()->publicationRows(), 'pointer_maps_publish_before_payload_reuse'),
    'row freeblock before reuse flags' => static fn (): mixed => array_column($plan256()->publicationRows(), 'freeblock_receipts_visible_before_reuse'),
    'row payload cursor flags' => static fn (): mixed => array_column($plan256()->publicationRows(), 'payload_reuse_has_cursor_advance'),
    'row duplicate generation flags' => static fn (): mixed => array_column($plan256()->publicationRows(), 'duplicate_pointer_map_keeps_generation'),
    'row tail fence flags' => static fn (): mixed => array_column($plan256()->publicationRows(), 'tail_pages_remain_fenced'),
    'batch size three row count' => static fn (): mixed => $plan256(3)->publicationSummary()['publication_row_count'],
    'batch size three pages' => static fn (): mixed => $plan256(3)->publishedPages(),
    'batch size three reusable payload pages' => static fn (): mixed => $plan256(3)->reusablePayloadPages(),
    'dependency closure' => static fn (): mixed => $plan256()->publicationSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan256()->publicationSummary()['non_overlap'], 'does not repeat next251'),
    'admission action' => static fn (): mixed => $plan256()->admissionPlan->toArray()['action'],
    'admission row count' => static fn (): mixed => $plan256()->admissionPlan->admissionSummary()['admission_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message256(static fn () => $plan256(0)),
];

$expected256 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next256',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next256-ready',
    'publication row count' => 7,
    'published pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary published pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary admitted pages' => [2, 3, 105, 106, 105, 107, 108],
    'published pages match admitted pages' => true,
    'pointer map publication pages' => [2, 105],
    'summary pointer map publication pages' => [2, 105],
    'freeblock receipt pages' => [2, 3, 105, 106, 107, 108],
    'summary freeblock receipt pages' => [2, 3, 105, 106, 107, 108],
    'reusable payload pages' => [3, 106, 107, 108],
    'summary reusable payload pages' => [3, 106, 107, 108],
    'duplicate pointer map pages' => [105],
    'summary duplicate pointer map pages' => [105],
    'publication errors' => [],
    'summary publication errors' => [],
    'all admission tokens match' => true,
    'all pointer maps publish before payload reuse' => true,
    'all freeblock receipts visible before reuse' => true,
    'all payload reuse has cursor advance' => true,
    'all duplicate pointer maps keep generation' => true,
    'all tail pages remain fenced' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row state' => 'current-source-next256-publication-committed',
    'first channel' => 'pointer-map-generation',
    'first published page' => 2,
    'first pointer map generations' => ['2:1'],
    'second channel' => 'payload-reuse',
    'second published page' => 3,
    'second freeblock receipts' => [2, 3],
    'third pointer map generations' => ['2:1', '105:1'],
    'fifth duplicate pointer map flag' => true,
    'last row published page' => 108,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'admission ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => array_fill(0, 7, 'current-source-next256-publication-committed'),
    'row admission token flags' => [true, true, true, true, true, true, true],
    'row pointer before payload flags' => [true, true, true, true, true, true, true],
    'row freeblock before reuse flags' => [true, true, true, true, true, true, true],
    'row payload cursor flags' => [true, true, true, true, true, true, true],
    'row duplicate generation flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three reusable payload pages' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; next256 reuses next251 admission rows and adds commit-ready publication ordering for pointer-map generations, freeblock receipts, and reusable payload pages',
    'non overlap' => true,
    'admission action' => 'btree-vacuum-pointermap-freeblock-current-source-next251',
    'admission row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases256 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next256 ' . $name] = static function (TestRunner $t) use ($callback, $expected256, $name): void {
        $t->same($expected256[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next256 publication invariant ' . $index] = static function (TestRunner $t) use ($plan256): void {
        $plan = $plan256();
        $summary = $plan->publicationSummary();

        $t->same([], $plan->publicationErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->publishedPages());
        $t->same([2, 105], $plan->pointerMapPublicationPages());
        $t->same([2, 3, 105, 106, 107, 108], $plan->freeblockReceiptPages());
        $t->same([3, 106, 107, 108], $plan->reusablePayloadPages());
        $t->same([105], $plan->duplicatePointerMapPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->publicationRows(), 'publication_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'admission_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'pointer_maps_publish_before_payload_reuse'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'freeblock_receipts_visible_before_reuse'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'payload_reuse_has_cursor_advance'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'duplicate_pointer_map_keeps_generation'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'tail_pages_remain_fenced'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->publicationTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next256-ready', $summary['status']);
        $t->same(true, $summary['published_pages_match_admitted_pages']);
        $t->same(true, $summary['all_payload_reuse_has_cursor_advance']);
    };
}

return $tests;
