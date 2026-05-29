<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage229 = static function (int $pageCount): string {
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

$putPointerMapEntry229 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database229 = static function () use ($makeFirstPage229, $putPointerMapEntry229): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage229(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_resume', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry229($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan229 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database229;

    $database = $database229();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafPublicationSealFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('resume-current-source-resume-', 50),
        3,
        true,
        $batchSize,
    );
};

$message229 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases229 = [
    'action label' => static fn (): mixed => $plan229()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan229()->resumeSummary()['status'],
    'resume row count' => static fn (): mixed => $plan229()->resumeSummary()['resume_row_count'],
    'resume pages' => static fn (): mixed => $plan229()->resumePages(),
    'summary resume pages' => static fn (): mixed => $plan229()->resumeSummary()['resume_pages'],
    'next resume pages' => static fn (): mixed => $plan229()->nextResumePages(),
    'pointer map resume pages' => static fn (): mixed => $plan229()->pointerMapResumePages(),
    'payload resume pages' => static fn (): mixed => $plan229()->payloadResumePages(),
    'current source pages' => static fn (): mixed => $plan229()->resumeSummary()['current_source_pages'],
    'resume pages match source pages' => static fn (): mixed => $plan229()->resumeSummary()['resume_pages_match_current_source_pages'],
    'resume errors' => static fn (): mixed => $plan229()->resumeErrors(),
    'summary resume errors' => static fn (): mixed => $plan229()->resumeSummary()['resume_errors'],
    'all source tokens match' => static fn (): mixed => $plan229()->resumeSummary()['all_source_tokens_match'],
    'all resume links valid' => static fn (): mixed => $plan229()->resumeSummary()['all_resume_links_valid'],
    'all pointer maps visible' => static fn (): mixed => $plan229()->resumeSummary()['all_pointer_map_resume_visible'],
    'all freeblock receipts carried' => static fn (): mixed => $plan229()->resumeSummary()['all_freeblock_resume_receipts_carried'],
    'all tail pages fenced' => static fn (): mixed => $plan229()->resumeSummary()['all_tail_pages_fenced_at_resume'],
    'all windows monotonic' => static fn (): mixed => $plan229()->resumeSummary()['all_resume_windows_monotonic'],
    'resume token count' => static fn (): mixed => count($plan229()->resumeTokens()),
    'resume token lengths' => static fn (): mixed => array_map('strlen', $plan229()->resumeTokens()),
    'resume signature length' => static fn (): mixed => strlen($plan229()->resumeSummary()['resume_signature']),
    'current source token length' => static fn (): mixed => strlen($plan229()->resumeSummary()['current_source_resume_window_token']),
    'first resume channel' => static fn (): mixed => $plan229()->resumeRows()[0]['resume_channel'],
    'first resume page' => static fn (): mixed => $plan229()->resumeRows()[0]['resume_page'],
    'first next resume page' => static fn (): mixed => $plan229()->resumeRows()[0]['next_resume_page'],
    'first visible pages' => static fn (): mixed => $plan229()->resumeRows()[0]['resume_visible_pages'],
    'first visible pointer maps' => static fn (): mixed => $plan229()->resumeRows()[0]['resume_visible_pointer_map_pages'],
    'first previous token' => static fn (): mixed => $plan229()->resumeRows()[0]['previous_resume_token'],
    'second resume channel' => static fn (): mixed => $plan229()->resumeRows()[1]['resume_channel'],
    'second resume page' => static fn (): mixed => $plan229()->resumeRows()[1]['resume_page'],
    'second next resume page' => static fn (): mixed => $plan229()->resumeRows()[1]['next_resume_page'],
    'second visible pages' => static fn (): mixed => $plan229()->resumeRows()[1]['resume_visible_pages'],
    'second visible pointer maps' => static fn (): mixed => $plan229()->resumeRows()[1]['resume_visible_pointer_map_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan229()->resumeRows()[1]['previous_resume_token']),
    'third resume channel' => static fn (): mixed => $plan229()->resumeRows()[2]['resume_channel'],
    'third resume page' => static fn (): mixed => $plan229()->resumeRows()[2]['resume_page'],
    'third visible pointer maps' => static fn (): mixed => $plan229()->resumeRows()[2]['resume_visible_pointer_map_pages'],
    'fourth resume channel' => static fn (): mixed => $plan229()->resumeRows()[3]['resume_channel'],
    'fourth resume page' => static fn (): mixed => $plan229()->resumeRows()[3]['resume_page'],
    'fifth resume channel' => static fn (): mixed => $plan229()->resumeRows()[4]['resume_channel'],
    'fifth resume page' => static fn (): mixed => $plan229()->resumeRows()[4]['resume_page'],
    'sixth resume channel' => static fn (): mixed => $plan229()->resumeRows()[5]['resume_channel'],
    'sixth resume page' => static fn (): mixed => $plan229()->resumeRows()[5]['resume_page'],
    'last resume channel' => static fn (): mixed => $plan229()->resumeRows()[6]['resume_channel'],
    'last resume page' => static fn (): mixed => $plan229()->resumeRows()[6]['resume_page'],
    'last next resume page' => static fn (): mixed => $plan229()->resumeRows()[6]['next_resume_page'],
    'resume ordinals' => static fn (): mixed => array_column($plan229()->resumeRows(), 'resume_ordinal'),
    'source ordinals' => static fn (): mixed => array_column($plan229()->resumeRows(), 'source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan229()->resumeRows(), 'resume_state'),
    'row source token flags' => static fn (): mixed => array_column($plan229()->resumeRows(), 'source_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan229()->resumeRows(), 'resume_link_valid'),
    'row pointer flags' => static fn (): mixed => array_column($plan229()->resumeRows(), 'pointer_map_resume_visible'),
    'row freeblock flags' => static fn (): mixed => array_column($plan229()->resumeRows(), 'freeblock_resume_receipt_carried'),
    'row tail fence flags' => static fn (): mixed => array_column($plan229()->resumeRows(), 'tail_pages_fenced_at_resume'),
    'row monotonic flags' => static fn (): mixed => array_column($plan229()->resumeRows(), 'resume_window_monotonic'),
    'batch size three row count' => static fn (): mixed => $plan229(3)->resumeSummary()['resume_row_count'],
    'batch size three pages' => static fn (): mixed => $plan229(3)->resumePages(),
    'batch size three next pages' => static fn (): mixed => $plan229(3)->nextResumePages(),
    'batch size three token count' => static fn (): mixed => count($plan229(3)->resumeTokens()),
    'dependency closure' => static fn (): mixed => $plan229()->resumeSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan229()->resumeSummary()['non_overlap'], 'does not repeat checkpoint-validation'),
    'source action' => static fn (): mixed => $plan229()->sourcePlan->toArray()['action'],
    'source row count' => static fn (): mixed => $plan229()->sourcePlan->sourceSummary()['source_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message229(static fn () => $plan229(0)),
];

$expected229 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-publication-resume-window',
    'summary status' => 'btree-vacuum-pointermap-freeblock-publication-resume-window-ready',
    'resume row count' => 7,
    'resume pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary resume pages' => [2, 3, 105, 106, 105, 107, 108],
    'next resume pages' => [3, 105, 106, 105, 107, 108, null],
    'pointer map resume pages' => [2, 105],
    'payload resume pages' => [3, 106, 107, 108],
    'current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'resume pages match source pages' => true,
    'resume errors' => [],
    'summary resume errors' => [],
    'all source tokens match' => true,
    'all resume links valid' => true,
    'all pointer maps visible' => true,
    'all freeblock receipts carried' => true,
    'all tail pages fenced' => true,
    'all windows monotonic' => true,
    'resume token count' => 7,
    'resume token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'resume signature length' => 64,
    'current source token length' => 64,
    'first resume channel' => 'pointer-map',
    'first resume page' => 2,
    'first next resume page' => 3,
    'first visible pages' => [2],
    'first visible pointer maps' => [2],
    'first previous token' => null,
    'second resume channel' => 'payload',
    'second resume page' => 3,
    'second next resume page' => 105,
    'second visible pages' => [2, 3],
    'second visible pointer maps' => [2],
    'second previous token length' => 64,
    'third resume channel' => 'pointer-map',
    'third resume page' => 105,
    'third visible pointer maps' => [2, 105],
    'fourth resume channel' => 'payload',
    'fourth resume page' => 106,
    'fifth resume channel' => 'pointer-map',
    'fifth resume page' => 105,
    'sixth resume channel' => 'payload',
    'sixth resume page' => 107,
    'last resume channel' => 'payload',
    'last resume page' => 108,
    'last next resume page' => null,
    'resume ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-resume-window-receipted', 'current-source-resume-window-receipted', 'current-source-resume-window-receipted', 'current-source-resume-window-receipted', 'current-source-resume-window-receipted', 'current-source-resume-window-receipted', 'current-source-resume-window-receipted'],
    'row source token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row pointer flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'row monotonic flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three token count' => 6,
    'dependency closure' => 'no new support component needed; publication-resume-window reuses checkpoint-validation current-source cursor rows and adds resume-window admission receipts only',
    'non overlap' => true,
    'source action' => 'btree-vacuum-pointermap-freeblock-checkpoint-validation',
    'source row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases229 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source publication-resume-window ' . $name] = static function (TestRunner $t) use ($callback, $expected229, $name): void {
        $t->same($expected229[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source publication-resume-window resume invariant ' . $index] = static function (TestRunner $t) use ($plan229): void {
        $plan = $plan229();
        $summary = $plan->resumeSummary();

        $t->same([], $plan->resumeErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->resumePages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->nextResumePages());
        $t->same([2, 105], $plan->pointerMapResumePages());
        $t->same([3, 106, 107, 108], $plan->payloadResumePages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->resumeRows(), 'resume_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->resumeRows(), 'source_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->resumeRows(), 'resume_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->resumeRows(), 'pointer_map_resume_visible'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->resumeRows(), 'tail_pages_fenced_at_resume'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->resumeTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-publication-resume-window-ready', $summary['status']);
        $t->same(true, $summary['resume_pages_match_current_source_pages']);
        $t->same(true, $summary['all_resume_windows_monotonic']);
    };
}

return $tests;
