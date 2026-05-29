<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage168 = static function (int $pageCount): string {
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

$putPointerMapEntry168 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage168 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database168 = static function () use ($makeFirstPage168, $putPointerMapEntry168, $overflowPage168): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage168(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next168', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage168(107, 'A');
    $pages[107] = $overflowPage168(108, 'B');
    $pages[108] = $overflowPage168(109, 'C');
    $pages[109] = $overflowPage168(110, 'D');
    $pages[110] = $overflowPage168(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry168($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan168 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
) use ($database168): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database168();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext168(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next168-current-source-replacement-chain-', 38),
        3,
        true,
    );
};

$message168 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases168 = [
    'action label' => static fn (): mixed => $plan168()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan168()->toArray()['released_overflow_pages'],
    'allocated overflow pages' => static fn (): mixed => $plan168()->toArray()['allocated_overflow_pages'],
    'appended overflow pages' => static fn (): mixed => $plan168()->toArray()['appended_overflow_pages'],
    'stable leaf pages' => static fn (): mixed => $plan168()->stableLeafPages(),
    'leaf errors' => static fn (): mixed => $plan168()->leafErrors(),
    'leaf row count' => static fn (): mixed => count($plan168()->leafRows()),
    'leaf page number' => static fn (): mixed => $plan168()->leafRows()[0]['page_number'],
    'leaf source cell count' => static fn (): mixed => $plan168()->leafRows()[0]['source_cell_count'],
    'leaf deleted cell count' => static fn (): mixed => $plan168()->leafRows()[0]['deleted_cell_count'],
    'leaf final cell count' => static fn (): mixed => $plan168()->leafRows()[0]['final_cell_count'],
    'leaf source freeblock count' => static fn (): mixed => $plan168()->leafRows()[0]['source_freeblock_count'],
    'leaf deleted freeblock count' => static fn (): mixed => $plan168()->leafRows()[0]['deleted_freeblock_count'],
    'leaf final freeblock count' => static fn (): mixed => $plan168()->leafRows()[0]['final_freeblock_count'],
    'leaf deleted status' => static fn (): mixed => $plan168()->leafRows()[0]['deleted_freeblock_status'],
    'leaf final status' => static fn (): mixed => $plan168()->leafRows()[0]['final_freeblock_status'],
    'leaf source hash differs from delete' => static fn (): mixed => $plan168()->leafRows()[0]['source_hash'] !== $plan168()->leafRows()[0]['deleted_hash'],
    'leaf final hash matches delete' => static fn (): mixed => $plan168()->leafRows()[0]['final_hash'] === $plan168()->leafRows()[0]['deleted_hash'],
    'leaf pointer map type' => static fn (): mixed => $plan168()->leafRows()[0]['final_pointer_map_type'],
    'leaf pointer map parent' => static fn (): mixed => $plan168()->leafRows()[0]['final_pointer_map_parent'],
    'leaf final database page count' => static fn (): mixed => $plan168()->leafRows()[0]['final_database_page_count'],
    'leaf freeblock shape matches final' => static fn (): mixed => $plan168()->leafRows()[0]['deleted_freeblocks'] === $plan168()->leafRows()[0]['final_freeblocks'],
    'wide truncation stable leaf pages' => static fn (): mixed => $plan168(null, 6)->stableLeafPages(),
    'wide truncation leaf errors' => static fn (): mixed => $plan168(null, 6)->leafErrors(),
    'too small replacement rejected' => static fn (): mixed => $message168(static fn () => $plan168(str_repeat('small', 20))),
    'empty replacement rejected' => static fn (): mixed => $message168(static fn () => $plan168('')),
];

$expected168 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next168',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'allocated overflow pages' => [106, 107, 108, 109],
    'appended overflow pages' => [107, 108, 109],
    'stable leaf pages' => [3],
    'leaf errors' => [],
    'leaf row count' => 1,
    'leaf page number' => 3,
    'leaf source cell count' => 3,
    'leaf deleted cell count' => 2,
    'leaf final cell count' => 2,
    'leaf source freeblock count' => 0,
    'leaf deleted freeblock count' => 0,
    'leaf final freeblock count' => 0,
    'leaf deleted status' => 'ok',
    'leaf final status' => 'ok',
    'leaf source hash differs from delete' => true,
    'leaf final hash matches delete' => true,
    'leaf pointer map type' => 'root-page',
    'leaf pointer map parent' => 0,
    'leaf final database page count' => 109,
    'leaf freeblock shape matches final' => true,
    'wide truncation stable leaf pages' => [3],
    'wide truncation leaf errors' => [],
    'too small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
    'empty replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases168 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next168 ' . $name] = static function (TestRunner $t) use ($callback, $expected168, $name): void {
        $t->same($expected168[$name], $callback());
    };
}

foreach (range(1, 42) as $index) {
    $tests['btree vacuum pointermap freeblock current source next168 invariant ' . $index] = static function (TestRunner $t) use ($plan168): void {
        $plan = $plan168();
        $row = $plan->leafRows()[0];

        $t->same([], $plan->leafErrors());
        $t->same([3], $plan->stableLeafPages());
        $t->same('ok', $row['deleted_freeblock_status']);
        $t->same('ok', $row['final_freeblock_status']);
        $t->same($row['deleted_freeblock_bytes'], $row['final_freeblock_bytes']);
        $t->same($row['deleted_freeblocks'], $row['final_freeblocks']);
        $t->same('root-page', $row['final_pointer_map_type']);
        $t->same(0, $row['final_pointer_map_parent']);
    };
}

return $tests;
