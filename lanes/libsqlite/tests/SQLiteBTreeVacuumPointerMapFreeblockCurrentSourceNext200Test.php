<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage200 = static function (int $pageCount): string {
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

$putPointerMapEntry200 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database200 = static function () use ($makeFirstPage200, $putPointerMapEntry200): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage200(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next200', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(80 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry200($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan200 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database200;

    $database = $database200();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafCommitBoundaryFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next200-current-source-commit-boundary-', 48),
        3,
        true,
    );
};

$message200 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases200 = [
    'action label' => static fn (): mixed => $plan200()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan200()->commitSummary()['status'],
    'commit errors' => static fn (): mixed => $plan200()->commitErrors(),
    'committed current source pages' => static fn (): mixed => $plan200()->committedCurrentSourcePages(),
    'committed leaf freeblock pages' => static fn (): mixed => $plan200()->committedLeafFreeblockPages(),
    'committed overflow freelist pages' => static fn (): mixed => $plan200()->committedOverflowFreelistPages(),
    'excluded tail pages' => static fn (): mixed => $plan200()->excludedTailPages(),
    'summary committed pages pointer map safe' => static fn (): mixed => $plan200()->commitSummary()['all_committed_pages_pointer_map_safe'],
    'summary committed pages reader visible' => static fn (): mixed => $plan200()->commitSummary()['all_committed_pages_reader_visible'],
    'summary tail pages not committed' => static fn (): mixed => $plan200()->commitSummary()['tail_pages_not_committed'],
    'summary leaf before overflow' => static fn (): mixed => $plan200()->commitSummary()['leaf_freeblock_commits_before_overflow_freelist'],
    'summary commit error count' => static fn (): mixed => $plan200()->commitSummary()['commit_error_count'],
    'summary dependencies' => static fn (): mixed => $plan200()->commitSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan200()->commitSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan200()->commitSummary()['non_overlap'], 'does not repeat next194 writer admission'),
    'row pages' => static fn (): mixed => array_column($plan200()->commitRows(), 'page_number'),
    'commit states' => static fn (): mixed => array_column($plan200()->commitRows(), 'commit_state'),
    'commit channels' => static fn (): mixed => array_column($plan200()->commitRows(), 'commit_channel'),
    'commit admitted' => static fn (): mixed => array_column($plan200()->commitRows(), 'commit_admitted'),
    'commit ordinals' => static fn (): mixed => array_column($plan200()->commitRows(), 'commit_ordinal'),
    'reader visible before commit' => static fn (): mixed => array_column($plan200()->commitRows(), 'reader_visible_before_commit'),
    'pointer map safe for commit' => static fn (): mixed => array_column($plan200()->commitRows(), 'pointer_map_safe_for_commit'),
    'tail excluded' => static fn (): mixed => array_column($plan200()->commitRows(), 'tail_excluded_from_next_source'),
    'tail fence required' => static fn (): mixed => array_column($plan200()->commitRows(), 'tail_fence_required'),
    'source replayable' => static fn (): mixed => array_column($plan200()->commitRows(), 'source_replayable'),
    'final materialized' => static fn (): mixed => array_column($plan200()->commitRows(), 'final_materialized'),
    'commit token length' => static fn (): mixed => strlen($plan200()->commitSummary()['commit_sequence_token']),
    'writer token length' => static fn (): mixed => strlen($plan200()->commitSummary()['writer_admission_token']),
    'commit key length' => static fn (): mixed => strlen($plan200()->commitRows()[0]['commit_key']),
    'base action label' => static fn (): mixed => $plan200()->basePlan->toArray()['action'],
    'base admitted leaf pages' => static fn (): mixed => $plan200()->basePlan->admittedLeafFreeblockPages(),
    'base admitted overflow pages' => static fn (): mixed => $plan200()->basePlan->admittedOverflowFreelistPages(),
    'base fenced tail pages' => static fn (): mixed => $plan200()->basePlan->fencedTailPages(),
    'wide committed current source pages' => static fn (): mixed => $plan200(null, 6)->committedCurrentSourcePages(),
    'wide excluded tail pages' => static fn (): mixed => $plan200(null, 6)->excludedTailPages(),
    'small replacement rejected' => static fn (): mixed => $message200(static fn () => $plan200(str_repeat('small', 20))),
];

$expected200 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next200',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next200-ready',
    'commit errors' => [],
    'committed current source pages' => [3, 109],
    'committed leaf freeblock pages' => [3],
    'committed overflow freelist pages' => [109],
    'excluded tail pages' => [110],
    'summary committed pages pointer map safe' => true,
    'summary committed pages reader visible' => true,
    'summary tail pages not committed' => true,
    'summary leaf before overflow' => true,
    'summary commit error count' => 0,
    'summary dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next194', 'sqlite-current-source-next200'],
    'dependency closure' => 'no new support component needed; next200 reuses next194 writer admission, pointer-map-safe reuse receipts, and truncated-tail fences',
    'non overlap' => true,
    'row pages' => [3, 106, 107, 108, 109, 110],
    'commit states' => ['committed-current-source', 'preserved-reader-source', 'preserved-reader-source', 'preserved-reader-source', 'committed-current-source', 'excluded-truncated-tail'],
    'commit channels' => ['leaf-freeblock', 'current-source-page', 'current-source-page', 'current-source-page', 'overflow-freelist', 'tail-fence'],
    'commit admitted' => [true, false, false, false, true, false],
    'commit ordinals' => [1, null, null, null, 2, null],
    'reader visible before commit' => [true, true, true, true, true, false],
    'pointer map safe for commit' => [true, true, true, true, true, true],
    'tail excluded' => [true, true, true, true, true, true],
    'tail fence required' => [false, false, false, false, false, true],
    'source replayable' => [true, true, true, true, true, false],
    'final materialized' => [true, true, true, true, true, false],
    'commit token length' => 64,
    'writer token length' => 64,
    'commit key length' => 64,
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next194',
    'base admitted leaf pages' => [3],
    'base admitted overflow pages' => [109],
    'base fenced tail pages' => [110],
    'wide committed current source pages' => [3, 109],
    'wide excluded tail pages' => [110],
    'small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
];

$tests = [];

foreach ($cases200 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next200 ' . $name] = static function (TestRunner $t) use ($callback, $expected200, $name): void {
        $t->same($expected200[$name], $callback());
    };
}

foreach (range(1, 82) as $index) {
    $tests['btree vacuum pointermap freeblock current source next200 commit invariant ' . $index] = static function (TestRunner $t) use ($plan200): void {
        $plan = $plan200();
        $rows = $plan->commitRows();
        $summary = $plan->commitSummary();

        $t->same([], $plan->commitErrors());
        $t->same([3, 109], $plan->committedCurrentSourcePages());
        $t->same([3], $plan->committedLeafFreeblockPages());
        $t->same([109], $plan->committedOverflowFreelistPages());
        $t->same([110], $plan->excludedTailPages());
        $t->same([true, false, false, false, true, false], array_column($rows, 'commit_admitted'));
        $t->same([1, null, null, null, 2, null], array_column($rows, 'commit_ordinal'));
        $t->same('committed-current-source', $rows[0]['commit_state']);
        $t->same('committed-current-source', $rows[4]['commit_state']);
        $t->same('excluded-truncated-tail', $rows[5]['commit_state']);
        $t->same(false, $rows[5]['commit_admitted']);
        $t->same(true, $rows[5]['tail_fence_required']);
        $t->same(true, $summary['tail_pages_not_committed']);
        $t->same(true, $summary['all_committed_pages_reader_visible']);
        $t->same(true, $summary['all_committed_pages_pointer_map_safe']);
        $t->same(true, $summary['leaf_freeblock_commits_before_overflow_freelist']);
    };
}

return $tests;
