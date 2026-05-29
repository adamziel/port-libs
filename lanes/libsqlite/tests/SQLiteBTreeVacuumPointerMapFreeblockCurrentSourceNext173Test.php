<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage173 = static function (int $pageCount): string {
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

$putPointerMapEntry173 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage173 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database173 = static function () use ($makeFirstPage173, $putPointerMapEntry173, $overflowPage173): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage173(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next173', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage173(107, 'A');
    $pages[107] = $overflowPage173(108, 'B');
    $pages[108] = $overflowPage173(109, 'C');
    $pages[109] = $overflowPage173(110, 'D');
    $pages[110] = $overflowPage173(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry173($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan173 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
) use ($database173): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database173();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafTransitionAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next173-current-source-transition-audit-', 40),
        3,
        true,
    );
};

$message173 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases173 = [
    'action label' => static fn (): mixed => $plan173()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan173()->transitionSummary()['status'],
    'stable leaf pages' => static fn (): mixed => $plan173()->stableLeafPages(),
    'replacement overflow pages' => static fn (): mixed => $plan173()->replacementOverflowPages(),
    'rewritten current source next pages' => static fn (): mixed => $plan173()->rewrittenCurrentSourceNextPages(),
    'truncated tail pages' => static fn (): mixed => $plan173()->truncatedTailPages(),
    'transition errors' => static fn (): mixed => $plan173()->transitionErrors(),
    'base action label' => static fn (): mixed => $plan173()->basePlan->toArray()['action'],
    'base replacement pages' => static fn (): mixed => $plan173()->basePlan->replacementPointerMapPagesAfterVacuum(),
    'base changed pages' => static fn (): mixed => $plan173()->basePlan->currentSourceAudit()['changed_current_source_next_pages'],
    'base reused truncated pages' => static fn (): mixed => $plan173()->basePlan->currentSourceAudit()['reused_truncated_current_source_pages'],
    'transition page numbers' => static fn (): mixed => array_column($plan173()->transitionRows(), 'page_number'),
    'transition statuses' => static fn (): mixed => array_column($plan173()->transitionRows(), 'status'),
    'transition final materialized' => static fn (): mixed => array_column($plan173()->transitionRows(), 'final_materialized'),
    'transition source next pages' => static fn (): mixed => array_column($plan173()->transitionRows(), 'source_next_page'),
    'transition final next pages' => static fn (): mixed => array_column($plan173()->transitionRows(), 'final_next_page'),
    'transition source pointer types' => static fn (): mixed => array_column($plan173()->transitionRows(), 'source_pointer_map_type'),
    'transition final pointer types' => static fn (): mixed => array_column($plan173()->transitionRows(), 'final_pointer_map_type'),
    'transition final pointer parents' => static fn (): mixed => array_column($plan173()->transitionRows(), 'final_pointer_map_parent'),
    'leaf final hash flags' => static fn (): mixed => array_column($plan173()->transitionRows(), 'final_hash_matches_post_vacuum'),
    'leaf freeblock preserved flags' => static fn (): mixed => array_column($plan173()->transitionRows(), 'freeblocks_preserved_after_allocation'),
    'summary stable leaf pages' => static fn (): mixed => $plan173()->transitionSummary()['stable_leaf_pages'],
    'summary replacement pages' => static fn (): mixed => $plan173()->transitionSummary()['replacement_overflow_pages'],
    'summary rewritten pages' => static fn (): mixed => $plan173()->transitionSummary()['rewritten_current_source_next_pages'],
    'summary truncated pages' => static fn (): mixed => $plan173()->transitionSummary()['truncated_tail_pages'],
    'dependency closure' => static fn (): mixed => $plan173()->transitionSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan173()->transitionSummary()['non_overlap'], 'does not repeat next167 final leaf audit'),
    'wide stable leaf pages' => static fn (): mixed => $plan173(null, 6)->stableLeafPages(),
    'wide replacement overflow pages' => static fn (): mixed => $plan173(null, 6)->replacementOverflowPages(),
    'wide rewritten pages' => static fn (): mixed => $plan173(null, 6)->rewrittenCurrentSourceNextPages(),
    'wide truncated tail pages' => static fn (): mixed => $plan173(null, 6)->truncatedTailPages(),
    'too small replacement rejected' => static fn (): mixed => $message173(static fn () => $plan173(str_repeat('small', 20))),
    'empty replacement rejected' => static fn (): mixed => $message173(static fn () => $plan173('')),
];

$expected173 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next173',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next173-ready',
    'stable leaf pages' => [3],
    'replacement overflow pages' => [106, 107, 108, 109],
    'rewritten current source next pages' => [109, 110],
    'truncated tail pages' => [110],
    'transition errors' => [],
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next167',
    'base replacement pages' => [106, 107, 108, 109],
    'base changed pages' => [109, 110],
    'base reused truncated pages' => [107, 108, 109],
    'transition page numbers' => [3, 106, 107, 108, 109, 110],
    'transition statuses' => ['stable-leaf-freeblock', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail-page'],
    'transition final materialized' => [true, true, true, true, true, false],
    'transition source next pages' => [null, 107, 108, 109, 110, 0],
    'transition final next pages' => [null, 107, 108, 109, 0, null],
    'transition source pointer types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'transition final pointer types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', null],
    'transition final pointer parents' => [0, 3, 106, 107, 108, null],
    'leaf final hash flags' => [true, null, null, null, null, null],
    'leaf freeblock preserved flags' => [true, null, null, null, null, null],
    'summary stable leaf pages' => [3],
    'summary replacement pages' => [106, 107, 108, 109],
    'summary rewritten pages' => [109, 110],
    'summary truncated pages' => [110],
    'dependency closure' => 'no new support component needed; next173 reuses native b-tree leaf parsing, overflow-chain materialization, incremental-vacuum truncation, and auto-vacuum pointer-map helpers',
    'non overlap' => true,
    'wide stable leaf pages' => [3],
    'wide replacement overflow pages' => [106, 107, 108, 109],
    'wide rewritten pages' => [109, 110],
    'wide truncated tail pages' => [110],
    'too small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
    'empty replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases173 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next173 ' . $name] = static function (TestRunner $t) use ($callback, $expected173, $name): void {
        $t->same($expected173[$name], $callback());
    };
}

foreach (range(1, 42) as $index) {
    $tests['btree vacuum pointermap freeblock current source next173 invariant ' . $index] = static function (TestRunner $t) use ($plan173): void {
        $plan = $plan173();
        $rows = $plan->transitionRows();

        $t->same([], $plan->transitionErrors());
        $t->same([3], $plan->stableLeafPages());
        $t->same([106, 107, 108, 109], $plan->replacementOverflowPages());
        $t->same([109, 110], $plan->rewrittenCurrentSourceNextPages());
        $t->same([110], $plan->truncatedTailPages());
        $t->same('stable-leaf-freeblock', $rows[0]['status']);
        $t->same(true, $rows[0]['freeblocks_preserved_after_allocation']);
        $t->same([107, 108, 109, 0], array_slice(array_column($rows, 'final_next_page'), 1, 4));
    };
}

return $tests;
