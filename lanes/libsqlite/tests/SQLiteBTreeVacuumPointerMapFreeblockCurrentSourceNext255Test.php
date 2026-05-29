<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage255 = static function (int $pageCount): string {
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

$putPointerMapEntry255 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database255 = static function () use ($makeFirstPage255, $putPointerMapEntry255): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage255(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next255', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(74 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry255($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan255 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database255;

    $database = $database255();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafPublicationFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next255-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message255 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases255 = [
    'action label' => static fn (): mixed => $plan255()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan255()->publicationSummary()['status'],
    'publication row count' => static fn (): mixed => $plan255()->publicationSummary()['publication_row_count'],
    'published pages' => static fn (): mixed => $plan255()->publishedPages(),
    'summary published pages' => static fn (): mixed => $plan255()->publicationSummary()['published_pages'],
    'next published pages' => static fn (): mixed => $plan255()->nextPublishedPages(),
    'summary admitted pages' => static fn (): mixed => $plan255()->publicationSummary()['admitted_pages'],
    'published pages match admitted pages' => static fn (): mixed => $plan255()->publicationSummary()['published_pages_match_admitted_pages'],
    'pointer map publication pages' => static fn (): mixed => $plan255()->pointerMapPublicationPages(),
    'freeblock payload publication pages' => static fn (): mixed => $plan255()->freeblockPayloadPublicationPages(),
    'duplicate pointer map publication pages' => static fn (): mixed => $plan255()->duplicatePointerMapPublicationPages(),
    'publication errors' => static fn (): mixed => $plan255()->publicationErrors(),
    'summary publication errors' => static fn (): mixed => $plan255()->publicationSummary()['publication_errors'],
    'all admission tokens match' => static fn (): mixed => $plan255()->publicationSummary()['all_admission_tokens_match'],
    'all publication links valid' => static fn (): mixed => $plan255()->publicationSummary()['all_publication_links_valid'],
    'all payload publications wait for pointer maps' => static fn (): mixed => $plan255()->publicationSummary()['all_payload_publications_wait_for_pointer_maps'],
    'all freeblock payload publications visible' => static fn (): mixed => $plan255()->publicationSummary()['all_freeblock_payload_publications_visible'],
    'all tail pages fenced' => static fn (): mixed => $plan255()->publicationSummary()['all_tail_pages_remain_fenced_for_publication'],
    'token count' => static fn (): mixed => count($plan255()->publicationTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan255()->publicationTokens()),
    'signature length' => static fn (): mixed => strlen($plan255()->publicationSummary()['publication_signature']),
    'current token length' => static fn (): mixed => strlen($plan255()->publicationSummary()['current_source_next255_token']),
    'first row state' => static fn (): mixed => $plan255()->publicationRows()[0]['publication_state'],
    'first row channel' => static fn (): mixed => $plan255()->publicationRows()[0]['publication_channel'],
    'first row published page' => static fn (): mixed => $plan255()->publicationRows()[0]['published_page'],
    'first pointer generations' => static fn (): mixed => $plan255()->publicationRows()[0]['published_pointer_map_generations'],
    'second row state' => static fn (): mixed => $plan255()->publicationRows()[1]['publication_state'],
    'second row payload pages' => static fn (): mixed => $plan255()->publicationRows()[1]['published_payload_pages'],
    'third row pointer generations' => static fn (): mixed => $plan255()->publicationRows()[2]['published_pointer_map_generations'],
    'fifth duplicate generation carried' => static fn (): mixed => $plan255()->publicationRows()[4]['duplicate_pointer_map_generation_carried'],
    'fifth pointer generations' => static fn (): mixed => $plan255()->publicationRows()[4]['published_pointer_map_generations'],
    'last row next page' => static fn (): mixed => $plan255()->publicationRows()[6]['next_published_page'],
    'ordinals' => static fn (): mixed => array_column($plan255()->publicationRows(), 'publication_ordinal'),
    'admission ordinals' => static fn (): mixed => array_column($plan255()->publicationRows(), 'admission_ordinal'),
    'row states' => static fn (): mixed => array_column($plan255()->publicationRows(), 'publication_state'),
    'row admission token flags' => static fn (): mixed => array_column($plan255()->publicationRows(), 'admission_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan255()->publicationRows(), 'publication_link_valid'),
    'row payload wait flags' => static fn (): mixed => array_column($plan255()->publicationRows(), 'payload_publication_waits_for_pointer_map'),
    'row payload visible flags' => static fn (): mixed => array_column($plan255()->publicationRows(), 'freeblock_payload_published'),
    'row duplicate flags' => static fn (): mixed => array_column($plan255()->publicationRows(), 'duplicate_pointer_map_generation_carried'),
    'row tail fence flags' => static fn (): mixed => array_column($plan255()->publicationRows(), 'tail_pages_remain_fenced_for_publication'),
    'batch size three row count' => static fn (): mixed => $plan255(3)->publicationSummary()['publication_row_count'],
    'batch size three pages' => static fn (): mixed => $plan255(3)->publishedPages(),
    'batch size three next pages' => static fn (): mixed => $plan255(3)->nextPublishedPages(),
    'batch size three freeblock payload pages' => static fn (): mixed => $plan255(3)->freeblockPayloadPublicationPages(),
    'dependency closure' => static fn (): mixed => $plan255()->publicationSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan255()->publicationSummary()['non_overlap'], 'does not repeat next251'),
    'admission action' => static fn (): mixed => $plan255()->admissionPlan->toArray()['action'],
    'admission row count' => static fn (): mixed => $plan255()->admissionPlan->admissionSummary()['admission_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message255(static fn () => $plan255(0)),
];

$expected255 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next255',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next255-ready',
    'publication row count' => 7,
    'published pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary published pages' => [2, 3, 105, 106, 105, 107, 108],
    'next published pages' => [3, 105, 106, 105, 107, 108, null],
    'summary admitted pages' => [2, 3, 105, 106, 105, 107, 108],
    'published pages match admitted pages' => true,
    'pointer map publication pages' => [2, 105],
    'freeblock payload publication pages' => [3, 106, 107, 108],
    'duplicate pointer map publication pages' => [105],
    'publication errors' => [],
    'summary publication errors' => [],
    'all admission tokens match' => true,
    'all publication links valid' => true,
    'all payload publications wait for pointer maps' => true,
    'all freeblock payload publications visible' => true,
    'all tail pages fenced' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row state' => 'current-source-next255-pointer-map-published',
    'first row channel' => 'pointer-map',
    'first row published page' => 2,
    'first pointer generations' => ['2:1'],
    'second row state' => 'current-source-next255-payload-published',
    'second row payload pages' => [3],
    'third row pointer generations' => ['2:1', '105:1'],
    'fifth duplicate generation carried' => true,
    'fifth pointer generations' => ['2:1', '105:2'],
    'last row next page' => null,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'admission ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next255-pointer-map-published', 'current-source-next255-payload-published', 'current-source-next255-pointer-map-published', 'current-source-next255-payload-published', 'current-source-next255-pointer-map-published', 'current-source-next255-payload-published', 'current-source-next255-payload-published'],
    'row admission token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row payload wait flags' => [true, true, true, true, true, true, true],
    'row payload visible flags' => [true, true, true, true, true, true, true],
    'row duplicate flags' => [false, false, false, false, true, false, false],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three freeblock payload pages' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; next255 reuses next251 admission rows and publishes current-source next-page order for pointer-map/freeblock visibility',
    'non overlap' => true,
    'admission action' => 'btree-vacuum-pointermap-freeblock-current-source-next251',
    'admission row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases255 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next255 ' . $name] = static function (TestRunner $t) use ($callback, $expected255, $name): void {
        $t->same($expected255[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next255 publication invariant ' . $index] = static function (TestRunner $t) use ($plan255): void {
        $plan = $plan255();
        $summary = $plan->publicationSummary();

        $t->same([], $plan->publicationErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->publishedPages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->nextPublishedPages());
        $t->same([2, 105], $plan->pointerMapPublicationPages());
        $t->same([3, 106, 107, 108], $plan->freeblockPayloadPublicationPages());
        $t->same([105], $plan->duplicatePointerMapPublicationPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->publicationRows(), 'publication_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'admission_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'publication_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'payload_publication_waits_for_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'freeblock_payload_published'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->publicationRows(), 'tail_pages_remain_fenced_for_publication'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->publicationTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next255-ready', $summary['status']);
        $t->same(true, $summary['published_pages_match_admitted_pages']);
        $t->same(true, $summary['all_payload_publications_wait_for_pointer_maps']);
    };
}

return $tests;
