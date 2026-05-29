<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage178 = static function (int $pageCount): string {
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

$putPointerMapEntry178 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage178 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database178 = static function () use ($makeFirstPage178, $putPointerMapEntry178, $overflowPage178): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage178(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next178', str_repeat('cache:', 20)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage178(107, 'A');
    $pages[107] = $overflowPage178(108, 'B');
    $pages[108] = $overflowPage178(109, 'C');
    $pages[109] = $overflowPage178(110, 'D');
    $pages[110] = $overflowPage178(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry178($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan178 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
) use ($database178): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database178();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafPublicationReceiptFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next178-current-source-publication-receipt-', 40),
        3,
        true,
    );
};

$message178 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases178 = [
    'action label' => static fn (): mixed => $plan178()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan178()->publicationSummary()['status'],
    'publication errors' => static fn (): mixed => $plan178()->publicationErrors(),
    'published pages' => static fn (): mixed => $plan178()->publishedCurrentSourcePages(),
    'blocked pages' => static fn (): mixed => $plan178()->blockedCurrentSourcePages(),
    'freeblock receipts' => static fn (): mixed => $plan178()->freeblockReceiptPages(),
    'pointer map receipts' => static fn (): mixed => $plan178()->pointerMapReceiptPages(),
    'summary published pages' => static fn (): mixed => $plan178()->publicationSummary()['published_current_source_pages'],
    'summary blocked pages' => static fn (): mixed => $plan178()->publicationSummary()['blocked_current_source_pages'],
    'summary freeblock receipts' => static fn (): mixed => $plan178()->publicationSummary()['freeblock_receipt_pages'],
    'summary pointer map receipts' => static fn (): mixed => $plan178()->publicationSummary()['pointer_map_receipt_pages'],
    'dependencies' => static fn (): mixed => $plan178()->publicationSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan178()->publicationSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan178()->publicationSummary()['non_overlap'], 'does not repeat next175 admission fencing'),
    'row pages' => static fn (): mixed => array_column($plan178()->publicationRows(), 'page_number'),
    'row sequences' => static fn (): mixed => array_column($plan178()->publicationRows(), 'sequence'),
    'row statuses' => static fn (): mixed => array_column($plan178()->publicationRows(), 'status'),
    'publication states' => static fn (): mixed => array_column($plan178()->publicationRows(), 'publication_state'),
    'publish flags' => static fn (): mixed => array_column($plan178()->publicationRows(), 'publish_to_next_current_source'),
    'block reasons' => static fn (): mixed => array_column($plan178()->publicationRows(), 'block_reason'),
    'receipt kinds' => static fn (): mixed => array_column($plan178()->publicationRows(), 'receipt_kind'),
    'freeblock receipt flags' => static fn (): mixed => array_column($plan178()->publicationRows(), 'freeblock_receipt_required'),
    'pointer map receipt flags' => static fn (): mixed => array_column($plan178()->publicationRows(), 'pointer_map_receipt_required'),
    'next pointer receipt flags' => static fn (): mixed => array_column($plan178()->publicationRows(), 'next_pointer_receipt_required'),
    'source materialized flags' => static fn (): mixed => array_column($plan178()->publicationRows(), 'source_materialized'),
    'final materialized flags' => static fn (): mixed => array_column($plan178()->publicationRows(), 'final_materialized'),
    'source next pages' => static fn (): mixed => array_column($plan178()->publicationRows(), 'source_next_page'),
    'final next pages' => static fn (): mixed => array_column($plan178()->publicationRows(), 'final_next_page'),
    'final pointer map types' => static fn (): mixed => array_column($plan178()->publicationRows(), 'final_pointer_map_type'),
    'final pointer map parents' => static fn (): mixed => array_column($plan178()->publicationRows(), 'final_pointer_map_parent'),
    'receipt signature length' => static fn (): mixed => strlen($plan178()->publicationRows()[0]['receipt_signature']),
    'publication signature length' => static fn (): mixed => strlen($plan178()->publicationSummary()['publication_signature']),
    'current source token length' => static fn (): mixed => strlen($plan178()->publicationSummary()['current_source_token']),
    'base action label' => static fn (): mixed => $plan178()->basePlan->toArray()['action'],
    'base admitted pages' => static fn (): mixed => $plan178()->basePlan->admittedCurrentSourcePages(),
    'base rejected pages' => static fn (): mixed => $plan178()->basePlan->rejectedCurrentSourcePages(),
    'wide published pages' => static fn (): mixed => $plan178(null, 6)->publishedCurrentSourcePages(),
    'wide blocked pages' => static fn (): mixed => $plan178(null, 6)->blockedCurrentSourcePages(),
    'wide errors' => static fn (): mixed => $plan178(null, 6)->publicationErrors(),
    'small replacement rejected' => static fn (): mixed => $message178(static fn () => $plan178(str_repeat('small', 20))),
    'empty replacement rejected' => static fn (): mixed => $message178(static fn () => $plan178('')),
];

$expected178 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next178',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next178-ready',
    'publication errors' => [],
    'published pages' => [3, 106, 107, 108, 109],
    'blocked pages' => [110],
    'freeblock receipts' => [3],
    'pointer map receipts' => [110],
    'summary published pages' => [3, 106, 107, 108, 109],
    'summary blocked pages' => [110],
    'summary freeblock receipts' => [3],
    'summary pointer map receipts' => [110],
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next175', 'sqlite-current-source-next178'],
    'dependency closure' => 'no new support component needed; next178 reuses native next175 current-source admission rows, secure-delete leaf freeblock receipts, overflow next-pointer fencing, and auto-vacuum pointer-map metadata',
    'non overlap' => true,
    'row pages' => [3, 106, 107, 108, 109, 110],
    'row sequences' => [1, 2, 3, 4, 5, 6],
    'row statuses' => ['stable-leaf-freeblock', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail-page'],
    'publication states' => ['published-current-source', 'published-current-source', 'published-current-source', 'published-current-source', 'published-current-source', 'blocked-truncated-tail'],
    'publish flags' => [true, true, true, true, true, false],
    'block reasons' => [null, null, null, null, null, 'truncated-tail-page-not-materialized'],
    'receipt kinds' => ['leaf-freeblock-receipt', 'overflow-chain-receipt', 'overflow-chain-receipt', 'overflow-chain-receipt', 'overflow-tail-rewrite-receipt', 'truncated-tail-fence-receipt'],
    'freeblock receipt flags' => [true, false, false, false, false, false],
    'pointer map receipt flags' => [false, false, false, false, false, true],
    'next pointer receipt flags' => [false, false, false, false, true, true],
    'source materialized flags' => [true, true, true, true, true, true],
    'final materialized flags' => [true, true, true, true, true, false],
    'source next pages' => [null, 107, 108, 109, 110, 0],
    'final next pages' => [null, 107, 108, 109, 0, null],
    'final pointer map types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', null],
    'final pointer map parents' => [0, 3, 106, 107, 108, null],
    'receipt signature length' => 64,
    'publication signature length' => 64,
    'current source token length' => 64,
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next175',
    'base admitted pages' => [3, 106, 107, 108, 109],
    'base rejected pages' => [110],
    'wide published pages' => [3, 106, 107, 108, 109],
    'wide blocked pages' => [110],
    'wide errors' => [],
    'small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
    'empty replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases178 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next178 ' . $name] = static function (TestRunner $t) use ($callback, $expected178, $name): void {
        $t->same($expected178[$name], $callback());
    };
}

foreach (range(1, 50) as $index) {
    $tests['btree vacuum pointermap freeblock current source next178 publication invariant ' . $index] = static function (TestRunner $t) use ($plan178): void {
        $plan = $plan178();
        $rows = $plan->publicationRows();

        $t->same([], $plan->publicationErrors());
        $t->same([3, 106, 107, 108, 109], $plan->publishedCurrentSourcePages());
        $t->same([110], $plan->blockedCurrentSourcePages());
        $t->same([3], $plan->freeblockReceiptPages());
        $t->same([110], $plan->pointerMapReceiptPages());
        $t->same(false, $rows[5]['publish_to_next_current_source']);
        $t->same('truncated-tail-page-not-materialized', $rows[5]['block_reason']);
        $t->same('overflow-tail-rewrite-receipt', $rows[4]['receipt_kind']);
        $t->same(true, $rows[4]['next_pointer_receipt_required']);
        $t->same(true, $rows[5]['pointer_map_receipt_required']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next178-ready', $plan->publicationSummary()['status']);
    };
}

return $tests;
