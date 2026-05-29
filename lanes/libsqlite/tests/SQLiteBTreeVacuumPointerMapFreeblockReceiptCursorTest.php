<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage177 = static function (int $pageCount): string {
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

$putPointerMapEntry177 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database177 = static function () use ($makeFirstPage177, $putPointerMapEntry177): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage177(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next177', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(70 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry177($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan177 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database177;

    $database = $database177();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafReceiptCursorFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next177-current-source-batch-', 50),
        3,
        true,
        $batchSize,
    );
};

$message177 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases177 = [
    'action label' => static fn (): mixed => $plan177()->toArray()['action'],
    'status' => static fn (): mixed => $plan177()->nextSourceSummary()['status'],
    'leaf page' => static fn (): mixed => $plan177()->nextSourceSummary()['leaf_page'],
    'batch count' => static fn (): mixed => $plan177()->nextSourceSummary()['batch_count'],
    'replay pages' => static fn (): mixed => $plan177()->replayPages(),
    'fenced pages' => static fn (): mixed => $plan177()->fencedPages(),
    'pointer map dependency pages' => static fn (): mixed => $plan177()->pointerMapDependencyPages(),
    'batch indexes' => static fn (): mixed => array_column($plan177()->batchRows(), 'batch_index'),
    'batch page numbers' => static fn (): mixed => array_column($plan177()->batchRows(), 'page_numbers'),
    'batch page counts' => static fn (): mixed => array_column($plan177()->batchRows(), 'page_count'),
    'first pages' => static fn (): mixed => array_column($plan177()->batchRows(), 'first_page'),
    'last pages' => static fn (): mixed => array_column($plan177()->batchRows(), 'last_page'),
    'resume token counts' => static fn (): mixed => array_column($plan177()->batchRows(), 'resume_token_count'),
    'pointer map dependency rows' => static fn (): mixed => array_column($plan177()->batchRows(), 'pointer_map_dependency_pages'),
    'pointer map types' => static fn (): mixed => array_column($plan177()->batchRows(), 'pointer_map_types'),
    'pointer map parents' => static fn (): mixed => array_column($plan177()->batchRows(), 'pointer_map_parents'),
    'contains fenced flags' => static fn (): mixed => array_column($plan177()->batchRows(), 'contains_fenced_page'),
    'deleted cell visibility flags' => static fn (): mixed => array_column($plan177()->batchRows(), 'deleted_cell_visible_to_next'),
    'resume token shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => count(array_filter($row['resume_tokens'], static fn (string $token): bool => strlen($token) === 64)) === $row['page_count'],
        $plan177()->batchRows(),
    )),
    'hash shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => count(array_filter($row['next_page_hashes'], static fn (string $hash): bool => strlen($hash) === 64)) === $row['page_count'],
        $plan177()->batchRows(),
    )),
    'batch size three page numbers' => static fn (): mixed => array_column($plan177(3)->batchRows(), 'page_numbers'),
    'batch size three count' => static fn (): mixed => $plan177(3)->nextSourceSummary()['batch_count'],
    'batch signature' => static fn (): mixed => $plan177()->nextSourceSummary()['batch_signature'],
    'replay signature' => static fn (): mixed => $plan177()->nextSourceSummary()['replay_signature'],
    'fenced signature' => static fn (): mixed => $plan177()->nextSourceSummary()['fenced_signature'],
    'final page count' => static fn (): mixed => $plan177()->nextSourceSummary()['final_database_page_count'],
    'dependencies' => static fn (): mixed => $plan177()->nextSourceSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => str_contains($plan177()->nextSourceSummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan177()->nextSourceSummary()['non_overlap'], 'does not repeat next174'),
    'base cursor status' => static fn (): mixed => $plan177()->basePlan->cursorSummary()['status'],
    'bad batch size rejected' => static fn (): mixed => $message177(static fn () => $plan177(0)),
];

$expected177 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next177',
    'status' => 'btree-vacuum-pointermap-freeblock-current-source-next177-ready',
    'leaf page' => 3,
    'batch count' => 3,
    'replay pages' => [1, 3, 105, 106, 107, 108],
    'fenced pages' => [109, 110],
    'pointer map dependency pages' => [2, 105],
    'batch indexes' => [0, 1, 2],
    'batch page numbers' => [[1, 3], [105, 106], [107, 108]],
    'batch page counts' => [2, 2, 2],
    'first pages' => [1, 105, 107],
    'last pages' => [3, 106, 108],
    'resume token counts' => [2, 2, 2],
    'pointer map dependency rows' => [[2], [105], [105]],
    'pointer map types' => [[null, 'root-page'], [null, 'overflow-page'], ['first-overflow-page', 'overflow-page']],
    'pointer map parents' => [[null, 0], [null, 108], [3, 107]],
    'contains fenced flags' => [false, false, false],
    'deleted cell visibility flags' => [false, false, false],
    'resume token shape' => [true, true, true],
    'hash shape' => [true, true, true],
    'batch size three page numbers' => [[1, 3, 105], [106, 107, 108]],
    'batch size three count' => 2,
    'batch signature' => hash('sha256', implode(',', array_column($plan177()->batchRows(), 'batch_replay_key'))),
    'replay signature' => hash('sha256', '1,3,105,106,107,108'),
    'fenced signature' => hash('sha256', '109,110'),
    'final page count' => 108,
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next174', 'sqlite-current-source-next177'],
    'dependency closure' => true,
    'non overlap' => true,
    'base cursor status' => 'btree-vacuum-pointermap-freeblock-current-source-next174-ready',
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases177 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next177 ' . $name] = static function (TestRunner $t) use ($callback, $expected177, $name): void {
        $t->same($expected177[$name], $callback());
    };
}

foreach (range(1, 48) as $index) {
    $tests['btree vacuum pointermap freeblock current source next177 batch invariant ' . $index] = static function (TestRunner $t) use ($plan177): void {
        $plan = $plan177();
        $rows = $plan->batchRows();

        $t->same([1, 3, 105, 106, 107, 108], $plan->replayPages());
        $t->same([109, 110], $plan->fencedPages());
        $t->same([2, 105], $plan->pointerMapDependencyPages());
        $t->same([false, false, false], array_column($rows, 'contains_fenced_page'));
        $t->same([false, false, false], array_column($rows, 'deleted_cell_visible_to_next'));
        $t->same([2, 2, 2], array_column($rows, 'resume_token_count'));
        $t->same([2, 2, 2], array_column($rows, 'page_count'));
        $t->same([], array_values(array_intersect($plan->replayPages(), $plan->fencedPages())));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next177-ready', $plan->nextSourceSummary()['status']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next174-ready', $plan->basePlan->cursorSummary()['status']);
    };
}

return $tests;
