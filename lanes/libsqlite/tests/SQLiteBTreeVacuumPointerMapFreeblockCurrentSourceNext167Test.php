<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage167 = static function (int $pageCount): string {
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

$putPointerMapEntry167 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage167 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database167 = static function () use ($makeFirstPage167, $putPointerMapEntry167, $overflowPage167): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage167(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next167', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage167(107, 'A');
    $pages[107] = $overflowPage167(108, 'B');
    $pages[108] = $overflowPage167(109, 'C');
    $pages[109] = $overflowPage167(110, 'D');
    $pages[110] = $overflowPage167(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry167($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan167 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
) use ($database167): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database167();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext167(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next167-current-source-freeblock-audit-', 40),
        3,
        true,
    );
};

$message167 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases167 = [
    'action label' => static fn (): mixed => $plan167()->toArray()['action'],
    'audit status' => static fn (): mixed => $plan167()->currentSourceAudit()['status'],
    'stable leaf pages' => static fn (): mixed => $plan167()->stableLeafPages(),
    'free pointer map pages after vacuum' => static fn (): mixed => $plan167()->freePointerMapPagesAfterVacuum(),
    'replacement pointer map pages after vacuum' => static fn (): mixed => $plan167()->replacementPointerMapPagesAfterVacuum(),
    'integrity errors' => static fn (): mixed => $plan167()->integrityErrors(),
    'base action label' => static fn (): mixed => $plan167()->basePlan->toArray()['action'],
    'base changed current source next pages' => static fn (): mixed => $plan167()->basePlan->currentSourceNextChangedPages(),
    'base reused truncated current source pages' => static fn (): mixed => $plan167()->basePlan->reusedTruncatedCurrentSourcePages(),
    'audit changed current source next pages' => static fn (): mixed => $plan167()->currentSourceAudit()['changed_current_source_next_pages'],
    'audit reused truncated current source pages' => static fn (): mixed => $plan167()->currentSourceAudit()['reused_truncated_current_source_pages'],
    'leaf page numbers' => static fn (): mixed => array_column($plan167()->leafRows(), 'page_number'),
    'leaf source cell counts' => static fn (): mixed => array_column($plan167()->leafRows(), 'source_cell_count'),
    'leaf final cell counts' => static fn (): mixed => array_column($plan167()->leafRows(), 'final_cell_count'),
    'leaf source freeblock counts' => static fn (): mixed => array_column($plan167()->leafRows(), 'source_freeblock_count'),
    'leaf final freeblock counts' => static fn (): mixed => array_column($plan167()->leafRows(), 'final_freeblock_count'),
    'leaf final hash match flags' => static fn (): mixed => array_column($plan167()->leafRows(), 'final_hash_matches_post_vacuum'),
    'leaf pointer type after vacuum' => static fn (): mixed => array_column($plan167()->leafRows(), 'post_vacuum_pointer_map_type'),
    'leaf pointer type final' => static fn (): mixed => array_column($plan167()->leafRows(), 'final_pointer_map_type'),
    'leaf pointer parent final' => static fn (): mixed => array_column($plan167()->leafRows(), 'final_pointer_map_parent'),
    'released page numbers' => static fn (): mixed => array_column($plan167()->releasedPageRows(), 'page_number'),
    'released allocated flags' => static fn (): mixed => array_column($plan167()->releasedPageRows(), 'allocated_for_replacement'),
    'released final materialized flags' => static fn (): mixed => array_column($plan167()->releasedPageRows(), 'final_materialized'),
    'released final pointer types' => static fn (): mixed => array_column($plan167()->releasedPageRows(), 'final_pointer_map_type'),
    'released final pointer parents' => static fn (): mixed => array_column($plan167()->releasedPageRows(), 'final_pointer_map_parent'),
    'released final next pages' => static fn (): mixed => array_column($plan167()->releasedPageRows(), 'final_next_page'),
    'released final statuses' => static fn (): mixed => array_column($plan167()->releasedPageRows(), 'final_status'),
    'wide truncation stable leaf pages' => static fn (): mixed => $plan167(null, 6)->stableLeafPages(),
    'wide truncation free pointer pages' => static fn (): mixed => $plan167(null, 6)->freePointerMapPagesAfterVacuum(),
    'wide truncation replacement pointer pages' => static fn (): mixed => $plan167(null, 6)->replacementPointerMapPagesAfterVacuum(),
    'wide truncation reused pages' => static fn (): mixed => $plan167(null, 6)->currentSourceAudit()['reused_truncated_current_source_pages'],
    'too small replacement rejected' => static fn (): mixed => $message167(static fn () => $plan167(str_repeat('small', 20))),
    'empty replacement rejected' => static fn (): mixed => $message167(static fn () => $plan167('')),
];

$expected167 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next167',
    'audit status' => 'btree-vacuum-pointermap-freeblock-current-source-next167-ready',
    'stable leaf pages' => [3],
    'free pointer map pages after vacuum' => [],
    'replacement pointer map pages after vacuum' => [106, 107, 108, 109],
    'integrity errors' => [],
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next164',
    'base changed current source next pages' => [109, 110],
    'base reused truncated current source pages' => [107, 108, 109],
    'audit changed current source next pages' => [109, 110],
    'audit reused truncated current source pages' => [107, 108, 109],
    'leaf page numbers' => [3],
    'leaf source cell counts' => [3],
    'leaf final cell counts' => [2],
    'leaf source freeblock counts' => [0],
    'leaf final freeblock counts' => [0],
    'leaf final hash match flags' => [true],
    'leaf pointer type after vacuum' => ['root-page'],
    'leaf pointer type final' => ['root-page'],
    'leaf pointer parent final' => [0],
    'released page numbers' => [106, 107, 108, 109, 110],
    'released allocated flags' => [true, true, true, true, false],
    'released final materialized flags' => [true, true, true, true, false],
    'released final pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', null],
    'released final pointer parents' => [3, 106, 107, 108, null],
    'released final next pages' => [107, 108, 109, 0, null],
    'released final statuses' => ['replacement-overflow-reused', 'replacement-overflow-appended', 'replacement-overflow-appended', 'replacement-overflow-appended', 'truncated-tail-page'],
    'wide truncation stable leaf pages' => [3],
    'wide truncation free pointer pages' => [],
    'wide truncation replacement pointer pages' => [106, 107, 108, 109],
    'wide truncation reused pages' => [106, 107, 108, 109],
    'too small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
    'empty replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases167 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next167 ' . $name] = static function (TestRunner $t) use ($callback, $expected167, $name): void {
        $t->same($expected167[$name], $callback());
    };
}

foreach (range(1, 40) as $index) {
    $tests['btree vacuum pointermap freeblock current source next167 invariant ' . $index] = static function (TestRunner $t) use ($plan167): void {
        $plan = $plan167();
        $leaf = $plan->leafRows()[0];
        $released = $plan->releasedPageRows();

        $t->same([], $plan->integrityErrors());
        $t->same([3], $plan->stableLeafPages());
        $t->same($leaf['post_vacuum_freeblocks'], $leaf['final_freeblocks']);
        $t->same($leaf['post_vacuum_hash'], $leaf['final_hash']);
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'], array_slice(array_column($released, 'final_pointer_map_type'), 0, 4));
        $t->same([3, 106, 107, 108], array_slice(array_column($released, 'final_pointer_map_parent'), 0, 4));
        $t->same([107, 108, 109, 0], array_slice(array_column($released, 'final_next_page'), 0, 4));
        $t->same([], $plan->freePointerMapPagesAfterVacuum());
    };
}

return $tests;
