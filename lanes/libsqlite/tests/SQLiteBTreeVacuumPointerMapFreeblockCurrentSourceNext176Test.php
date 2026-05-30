<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage176 = static function (int $pageCount): string {
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

$putPointerMapEntry176 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage176 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database176 = static function () use ($makeFirstPage176, $putPointerMapEntry176, $overflowPage176): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage176(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next176', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage176(107, 'A');
    $pages[107] = $overflowPage176(108, 'B');
    $pages[108] = $overflowPage176(109, 'C');
    $pages[109] = $overflowPage176(110, 'D');
    $pages[110] = $overflowPage176(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry176($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan176 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
) use ($database176): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    $database = $database176();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafSourceBoundaryFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next176-current-source-boundary-', 46),
        3,
        true,
    );
};

$message176 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases176 = [
    'action label' => static fn (): mixed => $plan176()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan176()->sourceBoundarySummary()['status'],
    'post delete leaf pages' => static fn (): mixed => $plan176()->postDeleteLeafPages(),
    'replacement overflow pages' => static fn (): mixed => $plan176()->replacementOverflowPages(),
    'rejected tail pages' => static fn (): mixed => $plan176()->rejectedTailPages(),
    'rewritten next pointer pages' => static fn (): mixed => $plan176()->rewrittenNextPointerPages(),
    'stale source pages' => static fn (): mixed => $plan176()->staleSourcePages(),
    'source errors' => static fn (): mixed => $plan176()->sourceErrors(),
    'base action label' => static fn (): mixed => $plan176()->basePlan->toArray()['action'],
    'base stable leaf pages' => static fn (): mixed => $plan176()->basePlan->stableLeafPages(),
    'base replacement pages' => static fn (): mixed => $plan176()->basePlan->replacementOverflowPages(),
    'base truncated pages' => static fn (): mixed => $plan176()->basePlan->truncatedTailPages(),
    'source page numbers' => static fn (): mixed => array_column($plan176()->sourceRows(), 'page_number'),
    'transition statuses' => static fn (): mixed => array_column($plan176()->sourceRows(), 'transition_status'),
    'authoritative sources' => static fn (): mixed => array_column($plan176()->sourceRows(), 'authoritative_source'),
    'read admitted flags' => static fn (): mixed => array_column($plan176()->sourceRows(), 'read_admitted'),
    'source materialized flags' => static fn (): mixed => array_column($plan176()->sourceRows(), 'source_materialized'),
    'final materialized flags' => static fn (): mixed => array_column($plan176()->sourceRows(), 'final_materialized'),
    'source next pages' => static fn (): mixed => array_column($plan176()->sourceRows(), 'source_next_page'),
    'authoritative next pages' => static fn (): mixed => array_column($plan176()->sourceRows(), 'authoritative_next_page'),
    'next pointer rewritten flags' => static fn (): mixed => array_column($plan176()->sourceRows(), 'next_pointer_rewritten'),
    'source pointer types' => static fn (): mixed => array_column($plan176()->sourceRows(), 'source_pointer_map_type'),
    'final pointer types' => static fn (): mixed => array_column($plan176()->sourceRows(), 'final_pointer_map_type'),
    'final pointer parents' => static fn (): mixed => array_column($plan176()->sourceRows(), 'final_pointer_map_parent'),
    'freeblock preserved flags' => static fn (): mixed => array_column($plan176()->sourceRows(), 'freeblocks_preserved_after_allocation'),
    'final hash flags' => static fn (): mixed => array_column($plan176()->sourceRows(), 'final_hash_matches_post_vacuum'),
    'stale visible flags' => static fn (): mixed => array_column($plan176()->sourceRows(), 'stale_source_bytes_visible'),
    'summary leaf pages' => static fn (): mixed => $plan176()->sourceBoundarySummary()['post_delete_leaf_pages'],
    'summary overflow pages' => static fn (): mixed => $plan176()->sourceBoundarySummary()['replacement_overflow_pages'],
    'summary rejected pages' => static fn (): mixed => $plan176()->sourceBoundarySummary()['rejected_tail_pages'],
    'summary stale pages' => static fn (): mixed => $plan176()->sourceBoundarySummary()['stale_source_pages'],
    'summary signature length' => static fn (): mixed => strlen($plan176()->sourceBoundarySummary()['source_boundary_signature']),
    'dependency closure' => static fn (): mixed => $plan176()->sourceBoundarySummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan176()->sourceBoundarySummary()['non_overlap'], 'does not repeat next173 transition auditing'),
    'wide leaf pages' => static fn (): mixed => $plan176(null, 6)->postDeleteLeafPages(),
    'wide replacement pages' => static fn (): mixed => $plan176(null, 6)->replacementOverflowPages(),
    'wide rejected pages' => static fn (): mixed => $plan176(null, 6)->rejectedTailPages(),
    'wide stale pages' => static fn (): mixed => $plan176(null, 6)->staleSourcePages(),
    'too small replacement rejected' => static fn (): mixed => $message176(static fn () => $plan176(str_repeat('small', 20))),
    'empty replacement rejected' => static fn (): mixed => $message176(static fn () => $plan176('')),
];

$expected176 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next176',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next176-ready',
    'post delete leaf pages' => [3],
    'replacement overflow pages' => [106, 107, 108],
    'rejected tail pages' => [109, 110],
    'rewritten next pointer pages' => [108, 109, 110],
    'stale source pages' => [],
    'source errors' => [],
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next173',
    'base stable leaf pages' => [3],
    'base replacement pages' => [106, 107, 108],
    'base truncated pages' => [109, 110],
    'source page numbers' => [3, 106, 107, 108, 109, 110],
    'transition statuses' => ['stable-leaf-freeblock', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail-page', 'truncated-tail-page'],
    'authoritative sources' => ['post-delete-leaf-current-source', 'replacement-overflow-current-source', 'replacement-overflow-current-source', 'replacement-overflow-current-source', 'rejected-truncated-tail', 'rejected-truncated-tail'],
    'read admitted flags' => [true, true, true, true, false, false],
    'source materialized flags' => [true, true, true, true, true, true],
    'final materialized flags' => [true, true, true, true, false, false],
    'source next pages' => [null, 107, 108, 109, 110, 0],
    'authoritative next pages' => [null, 107, 108, 0, null, null],
    'next pointer rewritten flags' => [false, false, false, true, true, true],
    'source pointer types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'final pointer types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', null, null],
    'final pointer parents' => [0, 3, 106, 107, null, null],
    'freeblock preserved flags' => [true, null, null, null, null, null],
    'final hash flags' => [true, null, null, null, null, null],
    'stale visible flags' => [false, false, false, false, false, false],
    'summary leaf pages' => [3],
    'summary overflow pages' => [106, 107, 108],
    'summary rejected pages' => [109, 110],
    'summary stale pages' => [],
    'summary signature length' => 64,
    'dependency closure' => 'no new support component needed; next176 reuses native b-tree leaf/freeblock, overflow-chain, incremental-vacuum truncation, and auto-vacuum pointer-map helpers',
    'non overlap' => true,
    'wide leaf pages' => [3],
    'wide replacement pages' => [106, 107, 108],
    'wide rejected pages' => [109, 110],
    'wide stale pages' => [],
    'too small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
    'empty replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases176 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next176 ' . $name] = static function (TestRunner $t) use ($callback, $expected176, $name): void {
        $t->same($expected176[$name], $callback());
    };
}

foreach (range(1, 42) as $index) {
    $tests['btree vacuum pointermap freeblock current source next176 invariant ' . $index] = static function (TestRunner $t) use ($plan176): void {
        $plan = $plan176();
        $rows = $plan->sourceRows();
        $summary = $plan->sourceBoundarySummary();

        $t->same([], $plan->sourceErrors());
        $t->same([], $plan->staleSourcePages());
        $t->same([3], $plan->postDeleteLeafPages());
        $t->same([106, 107, 108], $plan->replacementOverflowPages());
        $t->same([109, 110], $plan->rejectedTailPages());
        $t->same([108, 109, 110], $plan->rewrittenNextPointerPages());
        $t->same([false, false, false, false, false, false], array_column($rows, 'stale_source_bytes_visible'));
        $t->same([true, true, true, true, false, false], array_column($rows, 'read_admitted'));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next176-ready', $summary['status']);
    };
}

return $tests;
