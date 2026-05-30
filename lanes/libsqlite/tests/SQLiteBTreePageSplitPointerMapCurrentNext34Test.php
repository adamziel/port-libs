<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteTableRow;
use PortLibs\LibSqlite\SQLiteKeyValueRowReplacementPlan;

$makeFirstPage = static function (int $pageSize = 512, int $databaseSizePages = 3): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$setPointerMapEntry = static function (string $pointerMapPage, int $pageNumber, int $type, int $parentPageNumber): string {
    return substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$buildRootSplitFixture = static function (string $replacementValue, string $loadPolicy) use ($makeFirstPage, $setPointerMapEntry): array {
    $pageSize = 512;
    $firstPage = $makeFirstPage($pageSize, 3);
    $pointerMapPage = $setPointerMapEntry(str_repeat("\0", $pageSize), 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $schemaPage = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
            'table',
            'app_settings',
            'app_settings',
            3,
            'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)',
        ])),
    ], $pageSize, 100, $firstPage);
    $rootLeafPage = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'site_name', 'Stale Site', 'yes'])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'migration_lock', 'old-lock', 'no'])),
    ], $pageSize);
    $database = SQLiteDatabase::fromBytes($schemaPage . $pointerMapPage . $rootLeafPage);
    $plan = $database->planKeyValueRowReplace('site_name', $replacementValue, $loadPolicy);
    $postPages = [];
    for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
        $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
            ? $database->page($pageNumber)
            : str_repeat("\0", $pageSize);
    }
    foreach ($plan->pageImages() as $pageNumber => $page) {
        $postPages[$pageNumber] = $page;
    }
    ksort($postPages);

    return [$database, $plan, SQLiteDatabase::fromBytes(implode('', $postPages))];
};

$assertRootSplitPointerMap = static function (
    TestRunner $t,
    SQLiteDatabase $database,
    SQLiteKeyValueRowReplacementPlan $plan,
    SQLiteDatabase $postDatabase,
    string $replacementValue,
    string $loadPolicy,
): void {
    $rootHeader = $postDatabase->pageHeader(3);
    $rootCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(3), $rootHeader);
    $leftChildPage = $rootCells[0]->leftChildPage;
    $rightChildPage = $rootHeader->rightMostPointer;
    $leftRows = $postDatabase->tableRows($leftChildPage);
    $rightRows = $postDatabase->tableRows($rightChildPage);
    $setting = $postDatabase->tableRowByRowIdByName('app_settings', 2);
    $summary = $plan->toArray();

    $t->same(SQLiteKeyValueRowReplacementPlan::class, get_class($plan));
    $t->same([1, 2, 3, 4, 5], array_keys($plan->pageImages()));
    $t->same(5, $plan->databasePageCount);
    $t->same(3, $plan->tableRootPage);
    $t->same(2, $plan->rowId);
    $t->same('root-page', $database->pointerMapEntryForPage(3)->typeName());
    $t->same(0, $database->pointerMapEntryForPage(3)->parentPageNumber);
    $t->same('root-page', $postDatabase->pointerMapEntryForPage(3)->typeName());
    $t->same(0, $postDatabase->pointerMapEntryForPage(3)->parentPageNumber);
    $t->same('table-interior', $rootHeader->pageType);
    $t->same(1, $rootHeader->cellCount);
    $t->same([1], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $rootCells));
    $t->same(4, $leftChildPage);
    $t->same(5, $rightChildPage);
    $t->same('btree-page', $postDatabase->pointerMapEntryForPage($leftChildPage)->typeName());
    $t->same(3, $postDatabase->pointerMapEntryForPage($leftChildPage)->parentPageNumber);
    $t->same('btree-page', $postDatabase->pointerMapEntryForPage($rightChildPage)->typeName());
    $t->same(3, $postDatabase->pointerMapEntryForPage($rightChildPage)->parentPageNumber);
    $t->same([1], array_map(static fn (SQLiteTableRow $row): int => $row->rowId, $leftRows));
    $t->same([2, 3], array_map(static fn (SQLiteTableRow $row): int => $row->rowId, $rightRows));
    $t->same(['siteurl'], array_map(static fn (SQLiteTableRow $row): mixed => $row->values()[1] ?? null, $leftRows));
    $t->same(['site_name', 'migration_lock'], array_map(static fn (SQLiteTableRow $row): mixed => $row->values()[1] ?? null, $rightRows));
    $t->true($setting !== null);
    $t->same([null, 'site_name', $replacementValue, $loadPolicy], $setting?->values());
    $t->same(strlen(SQLiteRecord::encode([null, 'site_name', $replacementValue, $loadPolicy])), $plan->localPayloadLength);
    $t->same([], $plan->overflowPageNumbers);
    $t->same([], $plan->obsoleteOverflowPageNumbers);
    $t->same([1, 2, 3, 4, 5], $summary['updated_page_numbers']);
    $t->same(5, $summary['database_page_count']);
};

$tests = [
    'splits auto-vacuum table root and rewrites pointer-map child ownership' => static function (TestRunner $t) use ($buildRootSplitFixture, $assertRootSplitPointerMap): void {
        $replacementValue = str_repeat('expanded-cache-', 28);
        [$database, $plan, $postDatabase] = $buildRootSplitFixture($replacementValue, 'no');

        $assertRootSplitPointerMap($t, $database, $plan, $postDatabase, $replacementValue, 'no');
    },
];

$cases = [];
foreach (range(0, 48) as $index) {
    $cases['variant ' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)] = [
        str_repeat('expanded-cache-', 28),
        $index % 3 === 0 ? 'yes' : 'no',
    ];
}

foreach ($cases as $label => [$replacementValue, $loadPolicy]) {
    $tests['preserves split pointer-map current/next pages for ' . $label] = static function (TestRunner $t) use (
        $buildRootSplitFixture,
        $assertRootSplitPointerMap,
        $replacementValue,
        $loadPolicy,
    ): void {
        [$database, $plan, $postDatabase] = $buildRootSplitFixture($replacementValue, $loadPolicy);

        $assertRootSplitPointerMap($t, $database, $plan, $postDatabase, $replacementValue, $loadPolicy);
    };
}

return $tests;
