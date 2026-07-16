<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage171 = static function (int $pageCount): string {
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

$putPointerMapEntry171 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage171 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database171 = static function () use ($makeFirstPage171, $putPointerMapEntry171, $overflowPage171): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage171(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next171', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage171(107, 'A');
    $pages[107] = $overflowPage171(108, 'B');
    $pages[108] = $overflowPage171(109, 'C');
    $pages[109] = $overflowPage171(110, 'D');
    $pages[110] = $overflowPage171(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry171($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan171 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
) use ($database171): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    $database = $database171();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafSourceTransitionFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next171-current-source-transition-', 55),
        3,
        true,
    );
};

$message171 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases171 = [
    'action label' => static fn (): mixed => $plan171()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan171()->sourceTransitionSummary()['status'],
    'stable leaf pages' => static fn (): mixed => $plan171()->stableLeafPages(),
    'replacement overflow pages' => static fn (): mixed => $plan171()->replacementOverflowPages(),
    'surviving free pages' => static fn (): mixed => $plan171()->survivingFreePages(),
    'rejected truncated pages' => static fn (): mixed => $plan171()->rejectedTruncatedPages(),
    'source transition errors' => static fn (): mixed => $plan171()->sourceTransitionErrors(),
    'base action label' => static fn (): mixed => $plan171()->basePlan->toArray()['action'],
    'summary base replacement pages' => static fn (): mixed => $plan171()->sourceTransitionSummary()['base_replacement_pointer_map_pages'],
    'summary base free pages' => static fn (): mixed => $plan171()->sourceTransitionSummary()['base_free_pointer_map_pages'],
    'summary base changed next pages' => static fn (): mixed => $plan171()->sourceTransitionSummary()['base_changed_current_source_next_pages'],
    'summary base reused truncated pages' => static fn (): mixed => $plan171()->sourceTransitionSummary()['base_reused_truncated_current_source_pages'],
    'source row pages' => static fn (): mixed => array_column($plan171()->sourceRows(), 'page_number'),
    'source row statuses' => static fn (): mixed => array_column($plan171()->sourceRows(), 'transition_status'),
    'source row final materialized flags' => static fn (): mixed => array_column($plan171()->sourceRows(), 'final_materialized'),
    'source row allocated flags' => static fn (): mixed => array_column($plan171()->sourceRows(), 'allocated_for_replacement'),
    'source row final pointer types' => static fn (): mixed => array_column($plan171()->sourceRows(), 'final_pointer_map_type'),
    'source row final pointer parents' => static fn (): mixed => array_column($plan171()->sourceRows(), 'final_pointer_map_parent'),
    'source row hash match flags' => static fn (): mixed => array_column($plan171()->sourceRows(), 'hash_matches_post_vacuum'),
    'source row final next pages' => static fn (): mixed => array_values(array_filter(array_column($plan171()->sourceRows(), 'final_next_page'), static fn (mixed $value): bool => $value !== null)),
    'wide truncation replacement pages' => static fn (): mixed => $plan171(null, 6)->replacementOverflowPages(),
    'wide truncation rejected pages' => static fn (): mixed => $plan171(null, 6)->rejectedTruncatedPages(),
    'wide truncation changed next pages' => static fn (): mixed => $plan171(null, 6)->sourceTransitionSummary()['base_changed_current_source_next_pages'],
    'wide truncation reused pages' => static fn (): mixed => $plan171(null, 6)->sourceTransitionSummary()['base_reused_truncated_current_source_pages'],
    'too small replacement rejected' => static fn (): mixed => $message171(static fn () => $plan171(str_repeat('small', 20))),
    'empty replacement rejected' => static fn (): mixed => $message171(static fn () => $plan171('')),
];

$expected171 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next171',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next171-ready',
    'stable leaf pages' => [3],
    'replacement overflow pages' => [106, 107, 108, 109],
    'surviving free pages' => [],
    'rejected truncated pages' => [110],
    'source transition errors' => [],
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next167',
    'summary base replacement pages' => [106, 107, 108, 109],
    'summary base free pages' => [],
    'summary base changed next pages' => [109, 110],
    'summary base reused truncated pages' => [107, 108, 109],
    'source row pages' => [3, 106, 107, 108, 109, 110],
    'source row statuses' => [
        'stable-leaf-freeblock-page',
        'replacement-overflow-current-source-page',
        'replacement-overflow-current-source-page',
        'replacement-overflow-current-source-page',
        'replacement-overflow-current-source-page',
        'rejected-truncated-current-source-page',
    ],
    'source row final materialized flags' => [true, true, true, true, true, false],
    'source row allocated flags' => [false, true, true, true, true, false],
    'source row final pointer types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', null],
    'source row final pointer parents' => [0, 3, 106, 107, 108, null],
    'source row hash match flags' => [true, null, null, null, null, null],
    'source row final next pages' => [107, 108, 109, 0],
    'wide truncation replacement pages' => [106, 107, 108, 109],
    'wide truncation rejected pages' => [110],
    'wide truncation changed next pages' => [109, 110],
    'wide truncation reused pages' => [106, 107, 108, 109],
    'too small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
    'empty replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases171 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next171 ' . $name] = static function (TestRunner $t) use ($callback, $expected171, $name): void {
        $t->same($expected171[$name], $callback());
    };
}

foreach (range(1, 48) as $index) {
    $tests['btree vacuum pointermap freeblock current source next171 transition invariant ' . $index] = static function (TestRunner $t) use ($plan171): void {
        $plan = $plan171();
        $rows = $plan->sourceRows();

        $t->same([], $plan->sourceTransitionErrors());
        $t->same([3], $plan->stableLeafPages());
        $t->same([106, 107, 108, 109], $plan->replacementOverflowPages());
        $t->same([110], $plan->rejectedTruncatedPages());
        $t->same([], $plan->survivingFreePages());
        $t->same('stable-leaf-freeblock-page', $rows[0]['transition_status']);
        $t->same('rejected-truncated-current-source-page', $rows[5]['transition_status']);
        $t->same(false, $rows[5]['final_materialized']);
    };
}

return $tests;
