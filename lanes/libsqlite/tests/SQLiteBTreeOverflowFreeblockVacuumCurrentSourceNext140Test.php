<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage140 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry140 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage140 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database140 = static function () use ($makeFirstPage140, $putPointerMapEntry140, $overflowPage140): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage140(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next140', str_repeat('t', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'template', 'twentytwentysix'])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage140(107, 'A');
    $pages[107] = $overflowPage140(108, 'B');
    $pages[108] = $overflowPage140(109, 'C');
    $pages[109] = $overflowPage140(110, 'D');
    $pages[110] = $overflowPage140(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry140($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan140 = static function (int $maxTruncatedPages = 4) use ($database140): SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan {
    $database = $database140();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan::next140TableLeafFromCurrentSourceDeleteResult(
        $database,
        3,
        [[
            'source' => 'wp_options-current-source-transient-next140',
            'first_page' => 106,
            'overflow_payload_bytes' => 2540,
        ]],
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        true,
    );
};

$throwsMessage140 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$currentRows140 = static fn (): array => $plan140()->currentSourceRows();
$vacuumRows140 = static fn (): array => $plan140()->vacuumRows();
$leafHeader140 = static fn (): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($plan140()->basePlan->basePlan->deletePlan->leafPageImage, 512);

$cases140 = [
    'action label' => static fn (): mixed => $plan140()->toArray()['action'],
    'base action label' => static fn (): mixed => $plan140()->basePlan->toArray()['action'],
    'leaf page' => static fn (): mixed => $plan140()->toArray()['leaf_page'],
    'leaf page type' => static fn (): mixed => $plan140()->toArray()['leaf_page_type'],
    'current row pages' => static fn (): mixed => array_column($currentRows140(), 'page_number'),
    'current chain positions' => static fn (): mixed => array_column($currentRows140(), 'chain_position'),
    'current next pages' => static fn (): mixed => array_column($currentRows140(), 'current_next_page'),
    'current terminal flags' => static fn (): mixed => array_column($currentRows140(), 'current_terminal'),
    'current payload bytes' => static fn (): mixed => array_column($currentRows140(), 'current_payload_bytes'),
    'current pointer types' => static fn (): mixed => array_column($currentRows140(), 'current_pointer_map_type'),
    'current pointer parents' => static fn (): mixed => array_column($currentRows140(), 'current_pointer_map_parent'),
    'released overflow pages' => static fn (): mixed => $plan140()->toArray()['released_overflow_pages'],
    'surviving current source pages' => static fn (): mixed => $plan140()->survivingCurrentSourcePages(),
    'truncated current source pages' => static fn (): mixed => $plan140()->truncatedCurrentSourcePages(),
    'vacuum row pages' => static fn (): mixed => array_column($vacuumRows140(), 'page_number'),
    'vacuum row chain positions' => static fn (): mixed => array_column($vacuumRows140(), 'chain_position'),
    'vacuum row statuses' => static fn (): mixed => array_column($vacuumRows140(), 'vacuum_status'),
    'vacuum row materialized flags' => static fn (): mixed => array_column($vacuumRows140(), 'materialized_after_vacuum'),
    'vacuum row truncated flags' => static fn (): mixed => array_column($vacuumRows140(), 'truncated_after_vacuum'),
    'vacuum row freelist roles' => static fn (): mixed => array_column($vacuumRows140(), 'freelist_role'),
    'vacuum row next pointer types' => static fn (): mixed => array_column($vacuumRows140(), 'next_pointer_map_type'),
    'vacuum row next pointer parents' => static fn (): mixed => array_column($vacuumRows140(), 'next_pointer_map_parent'),
    'vacuum row freeblock statuses' => static fn (): mixed => array_values(array_unique(array_column($vacuumRows140(), 'leaf_freeblock_status'))),
    'final page count' => static fn (): mixed => $plan140()->toArray()['final_database_page_count'],
    'final first freelist trunk' => static fn (): mixed => $plan140()->toArray()['final_first_freelist_trunk_page'],
    'final freelist count' => static fn (): mixed => $plan140()->toArray()['final_freelist_page_count'],
    'final freelist pages' => static fn (): mixed => $plan140()->toArray()['final_freelist_page_numbers'],
    'updated pages' => static fn (): mixed => $plan140()->toArray()['updated_page_numbers'],
    'materialized apply omitted pages' => static fn (): mixed => $plan140()->basePlan->materializedApplySummary()['omitted_truncated_page_numbers'],
    'materialized apply byte length' => static fn (): mixed => $plan140()->basePlan->basePlan->materializedApplySummary()['byte_length'],
    'leaf freeblock integrity' => static fn (): mixed => $leafHeader140()->freeblockIntegrityReport($plan140()->basePlan->basePlan->deletePlan->leafPageImage)['status'],
    'leaf secure delete zeroed' => static fn (): mixed => $leafHeader140()->freeblockSecureDeleteReport($plan140()->basePlan->basePlan->deletePlan->leafPageImage)['secure_delete_payload_zeroed'],
    'summary current rows' => static fn (): mixed => array_column($plan140()->toArray()['current_source_overflow_chain_rows'], 'page_number'),
    'summary vacuum rows' => static fn (): mixed => array_column($plan140()->toArray()['btree_overflow_freeblock_vacuum_current_source_next140'], 'page_number'),
    'summary surviving pages' => static fn (): mixed => $plan140()->toArray()['surviving_current_source_pages'],
    'summary truncated pages' => static fn (): mixed => $plan140()->toArray()['truncated_current_source_pages'],
    'partial vacuum keeps page 106' => static fn (): mixed => $plan140()->basePlan->basePlan->nextDatabase->pointerMapEntryForPage(106)->typeName(),
    'partial vacuum omits page 107' => static function () use ($plan140): string {
        try {
            $plan140()->basePlan->basePlan->nextDatabase->page(107);
        } catch (Throwable) {
            return 'omitted';
        }

        return 'present';
    },
    'single page vacuum leaves all freelist pages' => static fn (): mixed => $plan140(1)->toArray()['final_freelist_page_numbers'],
    'single page vacuum truncates only tail' => static fn (): mixed => $plan140(1)->truncatedCurrentSourcePages(),
    'bad truncation limit rejected' => static fn (): mixed => $throwsMessage140(static fn () => $plan140(0)),
    'bad current chain rejected' => static function () use ($database140, $throwsMessage140): mixed {
        return $throwsMessage140(static fn () => SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan::next140TableLeafFromCurrentSourceDeleteResult(
            $database140(),
            3,
            [['source' => 'bad', 'first_page' => 106, 'overflow_payload_bytes' => 508]],
            [
                'page' => SQLiteTableLeafPage::deleteCellByRowId($database140()->page(3), 2, secureDelete: true),
                'rowid' => 2,
                'obsolete_overflow_page_numbers' => [106],
            ],
            1,
            true,
        ));
    },
];

$expected140 = [
    'action label' => 'btree-overflow-freeblock-vacuum-current-source-next140',
    'base action label' => 'btree-pointermap-freeblock-vacuum-current-source-next135',
    'leaf page' => 3,
    'leaf page type' => 'table-leaf',
    'current row pages' => [106, 107, 108, 109, 110],
    'current chain positions' => [0, 1, 2, 3, 4],
    'current next pages' => [107, 108, 109, 110, 0],
    'current terminal flags' => [false, false, false, false, true],
    'current payload bytes' => [508, 508, 508, 508, 508],
    'current pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'current pointer parents' => [3, 106, 107, 108, 109],
    'released overflow pages' => [106, 107, 108, 109, 110],
    'surviving current source pages' => [106],
    'truncated current source pages' => [107, 108, 109, 110],
    'vacuum row pages' => [106, 107, 108, 109, 110],
    'vacuum row chain positions' => [0, 1, 2, 3, 4],
    'vacuum row statuses' => ['survives-as-free-page', 'truncated', 'truncated', 'truncated', 'truncated'],
    'vacuum row materialized flags' => [true, false, false, false, false],
    'vacuum row truncated flags' => [false, true, true, true, true],
    'vacuum row freelist roles' => ['freelist-trunk', null, null, null, null],
    'vacuum row next pointer types' => ['free-page', null, null, null, null],
    'vacuum row next pointer parents' => [0, null, null, null, null],
    'vacuum row freeblock statuses' => ['ok'],
    'final page count' => 106,
    'final first freelist trunk' => 106,
    'final freelist count' => 1,
    'final freelist pages' => [106],
    'updated pages' => [1, 3, 105, 106],
    'materialized apply omitted pages' => [110, 109, 108, 107],
    'materialized apply byte length' => 54272,
    'leaf freeblock integrity' => 'ok',
    'leaf secure delete zeroed' => true,
    'summary current rows' => [106, 107, 108, 109, 110],
    'summary vacuum rows' => [106, 107, 108, 109, 110],
    'summary surviving pages' => [106],
    'summary truncated pages' => [107, 108, 109, 110],
    'partial vacuum keeps page 106' => 'free-page',
    'partial vacuum omits page 107' => 'omitted',
    'single page vacuum leaves all freelist pages' => [106, 107, 108, 109],
    'single page vacuum truncates only tail' => [110],
    'bad truncation limit rejected' => 'SQLite pointer-map vacuum freeblock next127 requires a positive truncation limit',
    'bad current chain rejected' => 'SQLite overflow chain has trailing pages beyond the expected payload length',
];

$tests = [];

foreach ($cases140 as $name => $callback) {
    $tests['btree overflow freeblock vacuum current source next140 ' . $name] = static function (TestRunner $t) use ($callback, $expected140, $name): void {
        $t->same($expected140[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree overflow freeblock vacuum current source next140 invariant ' . $index] = static function (TestRunner $t) use ($plan140): void {
        $plan = $plan140();

        $t->same([106, 107, 108, 109, 110], array_column($plan->currentSourceRows(), 'page_number'));
        $t->same([107, 108, 109, 110, 0], array_column($plan->currentSourceRows(), 'current_next_page'));
        $t->same([106], $plan->survivingCurrentSourcePages());
        $t->same([107, 108, 109, 110], $plan->truncatedCurrentSourcePages());
        $t->same(['survives-as-free-page', 'truncated', 'truncated', 'truncated', 'truncated'], array_column($plan->vacuumRows(), 'vacuum_status'));
        $t->same([106], $plan->basePlan->basePlan->nextDatabase->freelistPageNumbers());
        $t->same('free-page', $plan->basePlan->basePlan->nextDatabase->pointerMapEntryForPage(106)->typeName());
    };
}

return $tests;
