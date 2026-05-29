<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowPointerMapRebalanceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageSize, int $pageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
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

$payload = static fn (string $name, string $value = 'yes'): string => SQLiteRecord::encode([null, $name, $value, 'yes']);

$fixture = static function (string $replacementPayload = '') use ($makeFirstPage, $payload): array {
    $pageSize = 512;
    $largePayload = SQLiteRecord::encode([null, '_transient_rebalance_delete_118', str_repeat('delete-current-next118:', 62), 'no']);
    $large = SQLiteTableLeafCell::encodeWithOverflowPages(10, $largePayload, 7, $pageSize);
    $leftLeaf = SQLiteTableLeafPage::assemble([
        $large['cell'],
        SQLiteTableLeafCell::encode(20, $payload('_transient_keep_118'), $pageSize),
    ], $pageSize);
    $rightLeaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(30, $payload('autoload_next_118_30'), $pageSize),
        SQLiteTableLeafCell::encode(40, $payload('autoload_next_118_40'), $pageSize),
        SQLiteTableLeafCell::encode(50, $payload('autoload_next_118_50'), $pageSize),
        SQLiteTableLeafCell::encode(60, $payload('autoload_next_118_60'), $pageSize),
    ], $pageSize);
    $tailLeaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(9000, $payload('tail_balance_118'), $pageSize),
    ], $pageSize);
    $parent = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(4, 20),
        SQLiteTableInteriorCell::encode(5, 8999),
    ], 6, $pageSize);

    $pointerMapPage = str_repeat("\0", $pageSize);
    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
    ] as $pageNumber => [$type, $parentPageNumber]) {
        $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
    }

    $database = SQLiteDatabase::fromBytes(
        $makeFirstPage($pageSize, 8)
        . $pointerMapPage
        . $parent
        . $leftLeaf
        . $rightLeaf
        . $tailLeaf
        . implode('', $large['overflowPages']),
    );
    $overflowPages = array_combine(range(7, 6 + count($large['overflowPages'])), $large['overflowPages']);
    $overflowPageNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
        $pageNumbers = [];
        $pageNumber = $firstOverflowPage;
        $remaining = $byteCount;
        while ($pageNumber !== 0 && $remaining > 0) {
            $page = $overflowPages[$pageNumber] ?? null;
            if ($page === null) {
                throw new InvalidArgumentException('Fixture overflow page is missing');
            }
            $pageNumbers[] = $pageNumber;
            $pageNumber = unpack('N', substr($page, 0, 4))[1];
            $remaining -= min($remaining, 508);
        }

        return $pageNumbers;
    };
    $replacementPayload = $replacementPayload !== '' ? $replacementPayload : str_repeat('replacement-current-next118:', 42);
    $plan = SQLiteBTreeOverflowPointerMapRebalanceCurrentSourceNextPlan::tableDeleteRebalanceThenReplaceOverflow(
        $database,
        3,
        4,
        5,
        0,
        10,
        $overflowPageNumbers,
        $replacementPayload,
        true,
        true,
    );

    return [$database, $plan, $replacementPayload];
};

$transitionRows = static fn (array $fx): array => $fx[1]->pointerMapTransitionRows();
$summary = static fn (array $fx): array => $fx[1]->toArray();

$cases = [
    'action label' => static fn (array $fx): mixed => $summary($fx)['action'],
    'deleted rowid' => static fn (array $fx): mixed => $summary($fx)['deleted_rowid'],
    'obsolete pages' => static fn (array $fx): mixed => $summary($fx)['obsolete_overflow_pages'],
    'replacement pages' => static fn (array $fx): mixed => $summary($fx)['replacement_overflow_pages'],
    'reused obsolete pages' => static fn (array $fx): mixed => $summary($fx)['reused_obsolete_overflow_pages'],
    'appended pages' => static fn (array $fx): mixed => $summary($fx)['appended_page_numbers'],
    'transition row count' => static fn (array $fx): mixed => count($transitionRows($fx)),
    'transition page numbers' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'page_number'),
    'current type names' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'current_type_name'),
    'current parents' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'current_parent_page_number'),
    'release type names' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'release_type_name'),
    'release parents' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'release_parent_page_number'),
    'next type names' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'next_type_name'),
    'next parents' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'next_parent_page_number'),
    'freelist roles' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'freelist_role'),
    'allocation sources' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'allocation_source'),
    'allocation positions' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'allocation_position'),
    'rebalance roles' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'rebalance_role'),
    'secure delete flags' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'secure_deleted_before_reuse'),
    'pointer map pages' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'pointer_map_page'),
    'next overflow page pointers' => static fn (array $fx): mixed => array_column($transitionRows($fx), 'next_overflow_page'),
    'page seven current first overflow' => static fn (array $fx): mixed => $transitionRows($fx)[0]['current_type_name'],
    'page seven released free page' => static fn (array $fx): mixed => $transitionRows($fx)[0]['release_type_name'],
    'page seven next overflow parent points to page eight' => static fn (array $fx): mixed => $transitionRows($fx)[0]['next_parent_page_number'],
    'page seven allocation source trunk' => static fn (array $fx): mixed => $transitionRows($fx)[0]['allocation_source'],
    'page seven role obsolete reused' => static fn (array $fx): mixed => $transitionRows($fx)[0]['rebalance_role'],
    'page eight current overflow parent seven' => static fn (array $fx): mixed => $transitionRows($fx)[1]['current_parent_page_number'],
    'page eight released as leaf' => static fn (array $fx): mixed => $transitionRows($fx)[1]['freelist_role'],
    'page eight next first overflow parent left leaf' => static fn (array $fx): mixed => $transitionRows($fx)[1]['next_parent_page_number'],
    'page eight secure deleted before reuse' => static fn (array $fx): mixed => $transitionRows($fx)[1]['secure_deleted_before_reuse'],
    'page nine appended replacement' => static fn (array $fx): mixed => $transitionRows($fx)[2]['rebalance_role'],
    'page nine has no current pointer map type' => static fn (array $fx): mixed => $transitionRows($fx)[2]['current_type_name'],
    'page nine next overflow parent seven' => static fn (array $fx): mixed => $transitionRows($fx)[2]['next_parent_page_number'],
    'summary rows match method' => static fn (array $fx): mixed => $summary($fx)['pointer_map_transition_rows'] === $transitionRows($fx),
    'updated pointer map pages' => static fn (array $fx): mixed => $summary($fx)['updated_pointer_map_page_numbers'],
    'updated page numbers' => static fn (array $fx): mixed => $summary($fx)['updated_page_numbers'],
    'replacement chain current pages' => static fn (array $fx): mixed => array_column($summary($fx)['replacement_chain_links'], 'current_page'),
    'replacement chain next pages' => static fn (array $fx): mixed => array_column($summary($fx)['replacement_chain_links'], 'next_page'),
    'database after page seven type' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->database->pointerMapEntryForPage(7)->typeName(),
    'database after page eight type' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->database->pointerMapEntryForPage(8)->typeName(),
    'database after page nine type' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->database->pointerMapEntryForPage(9)->typeName(),
    'database after page seven parent' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->database->pointerMapEntryForPage(7)->parentPageNumber,
    'database after page eight parent' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->database->pointerMapEntryForPage(8)->parentPageNumber,
    'database after page nine parent' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->database->pointerMapEntryForPage(9)->parentPageNumber,
    'release plan freed pages' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->freePlan->freedPageNumbers,
    'allocation plan allocated pages' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->allocationPlan->allocatedPageNumbers,
    'allocation plan database page count' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->allocationPlan->databasePageCount,
    'replacement payload page count' => static fn (array $fx): mixed => count($fx[1]->rebalancePlan->replacementChainLinks),
    'source page count stays before append' => static fn (array $fx): mixed => $fx[0]->pageCount(),
    'post page count includes appended page' => static fn (array $fx): mixed => $fx[1]->rebalancePlan->database->pageCount(),
];

$expected = [
    'action label' => 'btree-overflow-pointermap-rebalance-current-source-next118',
    'deleted rowid' => 10,
    'obsolete pages' => [7, 8],
    'replacement pages' => [8, 7, 9],
    'reused obsolete pages' => [8, 7],
    'appended pages' => [9],
    'transition row count' => 3,
    'transition page numbers' => [7, 8, 9],
    'current type names' => ['first-overflow-page', 'overflow-page', null],
    'current parents' => [4, 7, null],
    'release type names' => ['free-page', 'free-page', null],
    'release parents' => [0, 0, null],
    'next type names' => ['overflow-page', 'first-overflow-page', 'overflow-page'],
    'next parents' => [8, 4, 7],
    'freelist roles' => ['freelist-trunk', 'freelist-leaf', null],
    'allocation sources' => ['freelist-trunk', 'freelist-leaf', 'append'],
    'allocation positions' => [1, 0, 2],
    'rebalance roles' => ['obsolete-reused', 'obsolete-reused', 'replacement-appended'],
    'secure delete flags' => [false, true, false],
    'pointer map pages' => [2, 2, 2],
    'next overflow page pointers' => [9, 7, 0],
    'page seven current first overflow' => 'first-overflow-page',
    'page seven released free page' => 'free-page',
    'page seven next overflow parent points to page eight' => 8,
    'page seven allocation source trunk' => 'freelist-trunk',
    'page seven role obsolete reused' => 'obsolete-reused',
    'page eight current overflow parent seven' => 7,
    'page eight released as leaf' => 'freelist-leaf',
    'page eight next first overflow parent left leaf' => 4,
    'page eight secure deleted before reuse' => true,
    'page nine appended replacement' => 'replacement-appended',
    'page nine has no current pointer map type' => null,
    'page nine next overflow parent seven' => 7,
    'summary rows match method' => true,
    'updated pointer map pages' => [2],
    'updated page numbers' => [1, 2, 3, 4, 5, 7, 8, 9],
    'replacement chain current pages' => [8, 7, 9],
    'replacement chain next pages' => [7, 9, 0],
    'database after page seven type' => 'overflow-page',
    'database after page eight type' => 'first-overflow-page',
    'database after page nine type' => 'overflow-page',
    'database after page seven parent' => 8,
    'database after page eight parent' => 4,
    'database after page nine parent' => 7,
    'release plan freed pages' => [7, 8],
    'allocation plan allocated pages' => [8, 7, 9],
    'allocation plan database page count' => 9,
    'replacement payload page count' => 3,
    'source page count stays before append' => 8,
    'post page count includes appended page' => 9,
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree overflow pointermap rebalance current source next118 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

$tests['btree overflow pointermap rebalance current source next118 reads replacement payload back'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan, $payload] = $fixture();
    $readBack = '';
    $pageNumber = 8;
    while ($pageNumber !== 0 && strlen($readBack) < strlen($payload)) {
        $page = $plan->rebalancePlan->database->page($pageNumber);
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $readBack .= substr($page, 4);
    }

    $t->same($payload, substr($readBack, 0, strlen($payload)));
};

$tests['btree overflow pointermap rebalance current source next118 rejects empty replacement payload'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowPointerMapRebalanceCurrentSourceNextPlan::tableDeleteRebalanceThenReplaceOverflow(
        $database,
        3,
        4,
        5,
        0,
        10,
        static fn (): array => [7, 8],
        '',
    ));
};

$tests['btree overflow pointermap rebalance current source next118 rejects missing delete row'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowPointerMapRebalanceCurrentSourceNextPlan::tableDeleteRebalanceThenReplaceOverflow(
        $database,
        3,
        4,
        5,
        0,
        999,
        static fn (): array => [],
        str_repeat('replacement-current-next118:', 42),
    ));
};

return $tests;
