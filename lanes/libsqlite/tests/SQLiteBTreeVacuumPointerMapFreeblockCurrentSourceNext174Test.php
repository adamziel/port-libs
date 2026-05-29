<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage174 = static function (int $pageCount): string {
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

$putPointerMapEntry174 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database174 = static function () use ($makeFirstPage174, $putPointerMapEntry174): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage174(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next174', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(90 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry174($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan174 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database174;

    $database = $database174();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext174(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next174-reader-cursor-', 54),
        3,
        true,
        $batchSize,
    );
};

$message174 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases174 = [
    'action label' => static fn (): mixed => $plan174()->toArray()['action'],
    'status' => static fn (): mixed => $plan174()->cursorSummary()['status'],
    'leaf page' => static fn (): mixed => $plan174()->cursorSummary()['leaf_page'],
    'readable cursor pages' => static fn (): mixed => $plan174()->readableCursorPages(),
    'fenced cursor pages' => static fn (): mixed => $plan174()->fencedCursorPages(),
    'resume token count' => static fn (): mixed => count($plan174()->resumeTokens()),
    'cursor statuses' => static fn (): mixed => array_column($plan174()->cursorRows(), 'cursor_status'),
    'batch indexes' => static fn (): mixed => array_column($plan174()->cursorRows(), 'batch_index'),
    'positions in batch' => static fn (): mixed => array_column($plan174()->cursorRows(), 'position_in_batch'),
    'fenced resume tokens' => static fn (): mixed => array_values(array_map(
        static fn (array $row): mixed => $row['resume_token'],
        array_filter($plan174()->cursorRows(), static fn (array $row): bool => $row['cursor_status'] === 'fenced'),
    )),
    'readable hashes present' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => is_string($row['next_page_hash']) && strlen($row['next_page_hash']) === 64,
        array_filter($plan174()->cursorRows(), static fn (array $row): bool => $row['cursor_status'] === 'readable'),
    )),
    'fenced hashes absent' => static fn (): mixed => array_values(array_map(
        static fn (array $row): mixed => $row['next_page_hash'],
        array_filter($plan174()->cursorRows(), static fn (array $row): bool => $row['cursor_status'] === 'fenced'),
    )),
    'next pointer types' => static fn (): mixed => array_column($plan174()->cursorRows(), 'next_pointer_map_type'),
    'next pointer parents' => static fn (): mixed => array_column($plan174()->cursorRows(), 'next_pointer_map_parent'),
    'pointer changed flags' => static fn (): mixed => array_column($plan174()->cursorRows(), 'pointer_map_changed'),
    'deleted cell visibility' => static fn (): mixed => array_column($plan174()->cursorRows(), 'deleted_cell_visible_to_next'),
    'read statuses' => static fn (): mixed => array_column($plan174()->cursorRows(), 'read_status'),
    'batch size three indexes' => static fn (): mixed => array_column($plan174(3)->cursorRows(), 'batch_index'),
    'batch size three positions' => static fn (): mixed => array_column($plan174(3)->cursorRows(), 'position_in_batch'),
    'cursor signature' => static fn (): mixed => $plan174()->cursorSummary()['cursor_signature'],
    'readable signature' => static fn (): mixed => $plan174()->cursorSummary()['readable_signature'],
    'fenced signature' => static fn (): mixed => $plan174()->cursorSummary()['fenced_signature'],
    'final page count' => static fn (): mixed => $plan174()->cursorSummary()['final_database_page_count'],
    'dependencies' => static fn (): mixed => $plan174()->cursorSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => str_contains($plan174()->cursorSummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan174()->cursorSummary()['non_overlap'], 'does not repeat next170'),
    'bad batch size rejected' => static fn (): mixed => $message174(static fn () => $plan174(0)),
];

$expected174 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next174',
    'status' => 'btree-vacuum-pointermap-freeblock-current-source-next174-ready',
    'leaf page' => 3,
    'readable cursor pages' => [1, 3, 105, 106, 107, 108],
    'fenced cursor pages' => [109, 110],
    'resume token count' => 6,
    'cursor statuses' => ['readable', 'readable', 'readable', 'readable', 'readable', 'readable', 'fenced', 'fenced'],
    'batch indexes' => [0, 0, 1, 1, 2, 2, null, null],
    'positions in batch' => [0, 1, 0, 1, 0, 1, null, null],
    'fenced resume tokens' => [null, null],
    'readable hashes present' => [true, true, true, true, true, true],
    'fenced hashes absent' => [null, null],
    'next pointer types' => [null, 'root-page', null, 'overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'next pointer parents' => [null, 0, null, 108, 3, 107, null, null],
    'pointer changed flags' => [false, false, false, true, true, false, true, true],
    'deleted cell visibility' => [false, false, false, false, false, false, false, false],
    'read statuses' => ['next-source-readable', 'next-source-readable', 'next-source-readable', 'next-source-readable', 'next-source-readable', 'next-source-readable', 'rejected-truncated-source', 'rejected-truncated-source'],
    'batch size three indexes' => [0, 0, 0, 1, 1, 1, null, null],
    'batch size three positions' => [0, 1, 2, 0, 1, 2, null, null],
    'cursor signature' => hash('sha256', 'readable,readable,readable,readable,readable,readable,fenced,fenced'),
    'readable signature' => hash('sha256', '1,3,105,106,107,108'),
    'fenced signature' => hash('sha256', '109,110'),
    'final page count' => 108,
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next170', 'sqlite-current-source-next174'],
    'dependency closure' => true,
    'non overlap' => true,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases174 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next174 ' . $name] = static function (TestRunner $t) use ($callback, $expected174, $name): void {
        $t->same($expected174[$name], $callback());
    };
}

foreach (range(1, 55) as $index) {
    $tests['btree vacuum pointermap freeblock current source next174 cursor invariant ' . $index] = static function (TestRunner $t) use ($plan174): void {
        $plan = $plan174();
        $rows = $plan->cursorRows();

        $t->same([1, 3, 105, 106, 107, 108], $plan->readableCursorPages());
        $t->same([109, 110], $plan->fencedCursorPages());
        $t->same(6, count($plan->resumeTokens()));
        $t->same([], array_values(array_intersect($plan->readableCursorPages(), $plan->fencedCursorPages())));
        $t->same([0, 0, 1, 1, 2, 2, null, null], array_column($rows, 'batch_index'));
        $t->same([0, 1, 0, 1, 0, 1, null, null], array_column($rows, 'position_in_batch'));
        $t->same([false, false, false, true, true, false, true, true], array_column($rows, 'pointer_map_changed'));
        $t->same([false, false, false, false, false, false, false, false], array_column($rows, 'deleted_cell_visible_to_next'));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next174-ready', $plan->cursorSummary()['status']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next170-ready', $plan->basePlan->handoffSummary()['status']);
    };
}

return $tests;
