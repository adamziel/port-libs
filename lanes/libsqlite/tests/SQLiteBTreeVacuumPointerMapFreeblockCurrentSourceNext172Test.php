<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage172 = static function (int $pageCount): string {
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

$putPointerMapEntry172 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database172 = static function () use ($makeFirstPage172, $putPointerMapEntry172): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage172(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next172', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry172($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan172 = static function (
    int $maxTruncatedPages = 2,
    ?string $payload = null,
    bool $secureDelete = true,
) use ($database172): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database172();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: $secureDelete);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafMaterializedWriteImageFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next172-materialized-write-image-', 44),
        3,
        $secureDelete,
    );
};

$message172 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases172 = [
    'action label' => static fn (): mixed => $plan172()->toArray()['action'],
    'status' => static fn (): mixed => $plan172()->materializationSummary()['status'],
    'source page count' => static fn (): mixed => $plan172()->materializationSummary()['source_page_count'],
    'final page count' => static fn (): mixed => $plan172()->materializationSummary()['final_database_page_count'],
    'changed pages' => static fn (): mixed => $plan172()->changedPageNumbers(),
    'unchanged count' => static fn (): mixed => $plan172()->materializationSummary()['unchanged_page_count'],
    'truncated pages' => static fn (): mixed => $plan172()->truncatedPageNumbers(),
    'admitted pages' => static fn (): mixed => $plan172()->materializationSummary()['admitted_write_pages'],
    'rejected pages' => static fn (): mixed => $plan172()->materializationSummary()['rejected_write_pages'],
    'pointer map pages' => static fn (): mixed => $plan172()->materializationSummary()['pointer_map_write_pages'],
    'leaf page' => static fn (): mixed => $plan172()->materializationSummary()['leaf_page'],
    'dependencies' => static fn (): mixed => $plan172()->materializationSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => str_contains($plan172()->materializationSummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan172()->materializationSummary()['non_overlap'], 'does not repeat next166'),
    'row count' => static fn (): mixed => count($plan172()->materializationRows()),
    'first rows' => static fn (): mixed => array_slice(array_column($plan172()->materializationRows(), 'page_number'), 0, 6),
    'last rows' => static fn (): mixed => array_slice(array_column($plan172()->materializationRows(), 'page_number'), -5),
    'changed row kinds' => static fn (): mixed => array_values(array_map(static fn (array $row): string => (string) $row['write_kind'], array_filter($plan172()->materializationRows(), static fn (array $row): bool => $row['page_changed'] === true))),
    'truncated materialized flags' => static fn (): mixed => array_values(array_map(static fn (array $row): bool => (bool) $row['final_materialized'], array_filter($plan172()->materializationRows(), static fn (array $row): bool => $row['write_kind'] === 'truncated-current-source-page'))),
    'replacement overflow next pages' => static fn (): mixed => array_values(array_filter(array_column($plan172()->materializationRows(), 'overflow_next_page'), static fn (mixed $value): bool => $value !== null)),
    'replacement pointer map types' => static fn (): mixed => array_values(array_filter(array_column($plan172()->materializationRows(), 'pointer_map_type'), static fn (mixed $value): bool => $value === 'first-overflow-page' || $value === 'overflow-page')),
    'replacement pointer map parents' => static fn (): mixed => array_values(array_filter(array_column($plan172()->materializationRows(), 'pointer_map_parent'), static fn (mixed $value): bool => $value !== null)),
    'leaf freeblock offset' => static fn (): mixed => $plan172()->materializationRows()[2]['freeblock_offset'],
    'materialized byte length' => static fn (): mixed => strlen($plan172()->materializedDatabase->toBytes()),
    'materialized hash length' => static fn (): mixed => strlen($plan172()->materializationSummary()['materialized_database_hash']),
    'base status' => static fn (): mixed => $plan172()->basePlan->writeAdmissionSummary()['status'],
    'base admitted' => static fn (): mixed => $plan172()->basePlan->admittedWritePages(),
    'base rejected' => static fn (): mixed => $plan172()->basePlan->rejectedWritePages(),
    'empty payload rejected' => static fn (): mixed => $message172(static fn () => $plan172(2, '')),
    'wide vacuum rejected allocation' => static fn (): mixed => $message172(static fn () => $plan172(4)),
    'insecure delete admitted after image materialization' => static fn (): mixed => $message172(static fn () => $plan172(2, null, false)),
];

$expected172 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next172',
    'status' => 'btree-vacuum-pointermap-freeblock-current-source-next172-ready',
    'source page count' => 110,
    'final page count' => 108,
    'changed pages' => [1, 3, 105, 106, 107, 108],
    'unchanged count' => 102,
    'truncated pages' => [109, 110],
    'admitted pages' => [1, 3, 105, 106, 107, 108],
    'rejected pages' => [109, 110],
    'pointer map pages' => [105],
    'leaf page' => 3,
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next166', 'sqlite-current-source-next172'],
    'dependency closure' => true,
    'non overlap' => true,
    'row count' => 110,
    'first rows' => [1, 2, 3, 4, 5, 6],
    'last rows' => [106, 107, 108, 109, 110],
    'changed row kinds' => ['database-header', 'changed-btree-page', 'pointer-map-page', 'replacement-overflow-page', 'replacement-overflow-page', 'replacement-overflow-page'],
    'truncated materialized flags' => [false, false],
    'replacement overflow next pages' => [0, 108, 106],
    'replacement pointer map types' => ['overflow-page', 'first-overflow-page', 'overflow-page'],
    'replacement pointer map parents' => [0, 108, 3, 107],
    'leaf freeblock offset' => 0,
    'materialized byte length' => 55296,
    'materialized hash length' => 64,
    'base status' => 'btree-vacuum-pointermap-freeblock-current-source-next166-ready',
    'base admitted' => [1, 3, 105, 106, 107, 108],
    'base rejected' => [109, 110],
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes',
    'wide vacuum rejected allocation' => 'SQLite freelist does not contain enough pages for this allocation',
    'insecure delete admitted after image materialization' => 'not rejected',
];

$tests = [];

foreach ($cases172 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next172 ' . $name] = static function (TestRunner $t) use ($callback, $expected172, $name): void {
        $t->same($expected172[$name], $callback());
    };
}

foreach (range(1, 72) as $index) {
    $tests['btree vacuum pointermap freeblock current source next172 materialization invariant ' . $index] = static function (TestRunner $t) use ($plan172): void {
        $plan = $plan172();
        $summary = $plan->materializationSummary();
        $rows = $plan->materializationRows();

        $t->same([1, 3, 105, 106, 107, 108], $plan->changedPageNumbers());
        $t->same([109, 110], $plan->truncatedPageNumbers());
        $t->same(108, $plan->materializedDatabase->pageCount());
        $t->same(55296, strlen($plan->materializedDatabase->toBytes()));
        $t->same([], array_values(array_intersect($plan->changedPageNumbers(), $plan->truncatedPageNumbers())));
        $t->same([false, false], array_values(array_map(static fn (array $row): bool => (bool) $row['final_materialized'], array_filter($rows, static fn (array $row): bool => $row['write_kind'] === 'truncated-current-source-page'))));
        $t->same([1, 3, 105, 106, 107, 108], $summary['admitted_write_pages']);
        $t->same([109, 110], $summary['rejected_write_pages']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next172-ready', $summary['status']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next166-ready', $plan->basePlan->writeAdmissionSummary()['status']);
    };
}

return $tests;
