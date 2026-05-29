<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage164 = static function (int $pageCount): string {
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

$putPointerMapEntry164 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage164 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database164 = static function () use ($makeFirstPage164, $putPointerMapEntry164, $overflowPage164): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage164(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next164', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage164(107, 'A');
    $pages[107] = $overflowPage164(108, 'B');
    $pages[108] = $overflowPage164(109, 'C');
    $pages[109] = $overflowPage164(110, 'D');
    $pages[110] = $overflowPage164(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry164($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan164 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
) use ($database164): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database164();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafContinuityFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next164-current-source-replacement-chain-', 38),
        3,
        true,
    );
};

$message164 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases164 = [
    'action label' => static fn (): mixed => $plan164()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan164()->toArray()['released_overflow_pages'],
    'allocated overflow pages' => static fn (): mixed => $plan164()->toArray()['allocated_overflow_pages'],
    'appended overflow pages' => static fn (): mixed => $plan164()->toArray()['appended_overflow_pages'],
    'changed current source next pages' => static fn (): mixed => $plan164()->currentSourceNextChangedPages(),
    'reused truncated current source pages' => static fn (): mixed => $plan164()->reusedTruncatedCurrentSourcePages(),
    'continuity errors' => static fn (): mixed => $plan164()->continuityErrors(),
    'final database page count' => static fn (): mixed => $plan164()->toArray()['final_database_page_count'],
    'final freelist page numbers' => static fn (): mixed => $plan164()->toArray()['final_freelist_page_numbers'],
    'row page numbers' => static fn (): mixed => array_column($plan164()->chainRows(), 'page_number'),
    'row statuses' => static fn (): mixed => array_column($plan164()->chainRows(), 'status'),
    'row source next pages' => static fn (): mixed => array_column($plan164()->chainRows(), 'source_next_page'),
    'row post vacuum next pages' => static fn (): mixed => array_column($plan164()->chainRows(), 'post_vacuum_next_page'),
    'row final next pages' => static fn (): mixed => array_column($plan164()->chainRows(), 'final_next_page'),
    'row final pointer types' => static fn (): mixed => array_column($plan164()->chainRows(), 'final_pointer_map_type'),
    'row final pointer parents' => static fn (): mixed => array_column($plan164()->chainRows(), 'final_pointer_map_parent'),
    'row final materialized flags' => static fn (): mixed => array_column($plan164()->chainRows(), 'final_materialized'),
    'row appended after truncate flags' => static fn (): mixed => array_column($plan164()->chainRows(), 'appended_after_truncate'),
    'row allocated flags' => static fn (): mixed => array_column($plan164()->chainRows(), 'allocated_for_replacement'),
    'final hashes are sha256' => static fn (): mixed => array_map(static fn (?string $hash): int => $hash === null ? 0 : strlen($hash), array_column($plan164()->chainRows(), 'final_page_hash')),
    'wide truncation changed pages' => static fn (): mixed => $plan164(null, 6)->currentSourceNextChangedPages(),
    'wide truncation reused truncated pages' => static fn (): mixed => $plan164(null, 6)->reusedTruncatedCurrentSourcePages(),
    'too small replacement rejected' => static fn (): mixed => $message164(static fn () => $plan164(str_repeat('small', 20))),
    'empty replacement rejected' => static fn (): mixed => $message164(static fn () => $plan164('')),
];

$expected164 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next164',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'allocated overflow pages' => [106, 107, 108, 109],
    'appended overflow pages' => [107, 108, 109],
    'changed current source next pages' => [109, 110],
    'reused truncated current source pages' => [107, 108, 109],
    'continuity errors' => [],
    'final database page count' => 109,
    'final freelist page numbers' => [],
    'row page numbers' => [106, 107, 108, 109, 110],
    'row statuses' => ['replacement-overflow-reused', 'replacement-overflow-appended', 'replacement-overflow-appended', 'replacement-overflow-appended', 'truncated-tail-page'],
    'row source next pages' => [107, 108, 109, 110, 0],
    'row post vacuum next pages' => [0, null, null, null, null],
    'row final next pages' => [107, 108, 109, 0, null],
    'row final pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', null],
    'row final pointer parents' => [3, 106, 107, 108, null],
    'row final materialized flags' => [true, true, true, true, false],
    'row appended after truncate flags' => [false, true, true, true, false],
    'row allocated flags' => [true, true, true, true, false],
    'final hashes are sha256' => [64, 64, 64, 64, 0],
    'wide truncation changed pages' => [109, 110],
    'wide truncation reused truncated pages' => [106, 107, 108, 109],
    'too small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
    'empty replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases164 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next164 ' . $name] = static function (TestRunner $t) use ($callback, $expected164, $name): void {
        $t->same($expected164[$name], $callback());
    };
}

foreach (range(1, 36) as $index) {
    $tests['btree vacuum pointermap freeblock current source next164 invariant ' . $index] = static function (TestRunner $t) use ($plan164): void {
        $plan = $plan164();
        $rows = $plan->chainRows();

        $t->same([], $plan->continuityErrors());
        $t->same([106, 107, 108, 109], $plan->basePlan->allocatedOverflowPages());
        $t->same([107, 108, 109], $plan->basePlan->appendedPreviouslyTruncatedOverflowPages());
        $t->same([107, 108, 109, 0], [$rows[0]['final_next_page'], $rows[1]['final_next_page'], $rows[2]['final_next_page'], $rows[3]['final_next_page']]);
        $t->same([3, 106, 107, 108], [$rows[0]['final_pointer_map_parent'], $rows[1]['final_pointer_map_parent'], $rows[2]['final_pointer_map_parent'], $rows[3]['final_pointer_map_parent']]);
        $t->same(['replacement-overflow-reused', 'replacement-overflow-appended', 'replacement-overflow-appended', 'replacement-overflow-appended'], array_slice(array_column($rows, 'status'), 0, 4));
        $t->same([false, true, true, true], array_slice(array_column($rows, 'appended_after_truncate'), 0, 4));
    };
}

return $tests;
