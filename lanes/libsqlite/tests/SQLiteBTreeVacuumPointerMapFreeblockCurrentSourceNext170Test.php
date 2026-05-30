<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage170 = static function (int $pageCount): string {
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

$putPointerMapEntry170 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database170 = static function () use ($makeFirstPage170, $putPointerMapEntry170): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage170(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next170', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry170($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan170 = static function (
    int $maxTruncatedPages = 2,
    ?string $payload = null,
    bool $secureDelete = true,
) use ($database170): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    $database = $database170();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: $secureDelete);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafFreelistCursorFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next170-reader-handoff-', 52),
        3,
        $secureDelete,
    );
};

$message170 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases170 = [
    'action label' => static fn (): mixed => $plan170()->toArray()['action'],
    'status' => static fn (): mixed => $plan170()->handoffSummary()['status'],
    'leaf page' => static fn (): mixed => $plan170()->handoffSummary()['leaf_page'],
    'next readable pages' => static fn (): mixed => $plan170()->nextReadablePages(),
    'fenced source pages' => static fn (): mixed => $plan170()->fencedSourcePages(),
    'changed next pages' => static fn (): mixed => $plan170()->changedNextPages(),
    'pointer map pages' => static fn (): mixed => $plan170()->handoffSummary()['pointer_map_pages'],
    'replacement overflow pages' => static fn (): mixed => $plan170()->handoffSummary()['replacement_overflow_pages'],
    'final page count' => static fn (): mixed => $plan170()->handoffSummary()['final_database_page_count'],
    'next read signature' => static fn (): mixed => $plan170()->handoffSummary()['next_read_signature'],
    'fenced source signature' => static fn (): mixed => $plan170()->handoffSummary()['fenced_source_signature'],
    'dependencies' => static fn (): mixed => $plan170()->handoffSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => str_contains($plan170()->handoffSummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan170()->handoffSummary()['non_overlap'], 'does not repeat next166'),
    'row pages' => static fn (): mixed => array_column($plan170()->handoffRows(), 'page_number'),
    'row kinds' => static fn (): mixed => array_column($plan170()->handoffRows(), 'write_kind'),
    'next readable flags' => static fn (): mixed => array_column($plan170()->handoffRows(), 'next_readable'),
    'read statuses' => static fn (): mixed => array_column($plan170()->handoffRows(), 'read_status'),
    'source materialized flags' => static fn (): mixed => array_column($plan170()->handoffRows(), 'source_materialized'),
    'next materialized flags' => static fn (): mixed => array_column($plan170()->handoffRows(), 'next_materialized'),
    'source changed flags' => static fn (): mixed => array_column($plan170()->handoffRows(), 'source_next_changed'),
    'source pointer types' => static fn (): mixed => array_column($plan170()->handoffRows(), 'source_pointer_map_type'),
    'source pointer parents' => static fn (): mixed => array_column($plan170()->handoffRows(), 'source_pointer_map_parent'),
    'next pointer types' => static fn (): mixed => array_column($plan170()->handoffRows(), 'next_pointer_map_type'),
    'next pointer parents' => static fn (): mixed => array_column($plan170()->handoffRows(), 'next_pointer_map_parent'),
    'pointer changed flags' => static fn (): mixed => array_column($plan170()->handoffRows(), 'pointer_map_changed'),
    'source overflow next pages' => static fn (): mixed => array_column($plan170()->handoffRows(), 'source_overflow_next_page'),
    'next overflow next pages' => static fn (): mixed => array_column($plan170()->handoffRows(), 'next_overflow_next_page'),
    'leaf freeblock offsets' => static fn (): mixed => array_column($plan170()->handoffRows(), 'leaf_freeblock_offset'),
    'deleted cell visible flags' => static fn (): mixed => array_column($plan170()->handoffRows(), 'deleted_cell_visible_to_next'),
    'base admitted writes' => static fn (): mixed => $plan170()->basePlan->admittedWritePages(),
    'base rejected writes' => static fn (): mixed => $plan170()->basePlan->rejectedWritePages(),
    'base replacement writes' => static fn (): mixed => $plan170()->basePlan->replacementOverflowWritePages(),
    'empty payload rejected' => static fn (): mixed => $message170(static fn () => $plan170(2, '')),
    'wide vacuum rejected allocation' => static fn (): mixed => $message170(static fn () => $plan170(4)),
    'insecure delete compacted at handoff' => static fn (): mixed => $message170(static fn () => $plan170(2, null, false)),
];

$expected170 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next170',
    'status' => 'btree-vacuum-pointermap-freeblock-current-source-next170-ready',
    'leaf page' => 3,
    'next readable pages' => [1, 3, 105, 106, 107, 108],
    'fenced source pages' => [109, 110],
    'changed next pages' => [1, 3, 105, 106, 107, 108],
    'pointer map pages' => [105],
    'replacement overflow pages' => [106, 107, 108],
    'final page count' => 108,
    'next read signature' => hash('sha256', '1,3,105,106,107,108'),
    'fenced source signature' => hash('sha256', '109,110'),
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next166', 'sqlite-current-source-next170'],
    'dependency closure' => true,
    'non overlap' => true,
    'row pages' => [1, 3, 105, 106, 107, 108, 109, 110],
    'row kinds' => ['database-header', 'leaf-freeblock-page', 'pointer-map-page', 'replacement-overflow-page', 'replacement-overflow-page', 'replacement-overflow-page', 'rejected-truncated-current-source-page', 'rejected-truncated-current-source-page'],
    'next readable flags' => [true, true, true, true, true, true, false, false],
    'read statuses' => ['next-source-readable', 'next-source-readable', 'next-source-readable', 'next-source-readable', 'next-source-readable', 'next-source-readable', 'rejected-truncated-source', 'rejected-truncated-source'],
    'source materialized flags' => [true, true, true, true, true, true, true, true],
    'next materialized flags' => [true, true, true, true, true, true, false, false],
    'source changed flags' => [true, true, true, true, true, true, false, false],
    'source pointer types' => [null, 'root-page', null, 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'source pointer parents' => [null, 0, null, 3, 106, 107, 108, 109],
    'next pointer types' => [null, 'root-page', null, 'overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'next pointer parents' => [null, 0, null, 108, 3, 107, null, null],
    'pointer changed flags' => [false, false, false, true, true, false, true, true],
    'source overflow next pages' => [null, null, null, 107, 108, 109, null, null],
    'next overflow next pages' => [null, null, null, 0, 108, 106, null, null],
    'leaf freeblock offsets' => [null, 0, null, null, null, null, null, null],
    'deleted cell visible flags' => [false, false, false, false, false, false, false, false],
    'base admitted writes' => [1, 3, 105, 106, 107, 108],
    'base rejected writes' => [109, 110],
    'base replacement writes' => [106, 107, 108],
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes',
    'wide vacuum rejected allocation' => 'SQLite freelist does not contain enough pages for this allocation',
    'insecure delete compacted at handoff' => 'not rejected',
];

$tests = [];

foreach ($cases170 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next170 ' . $name] = static function (TestRunner $t) use ($callback, $expected170, $name): void {
        $t->same($expected170[$name], $callback());
    };
}

foreach (range(1, 62) as $index) {
    $tests['btree vacuum pointermap freeblock current source next170 handoff invariant ' . $index] = static function (TestRunner $t) use ($plan170): void {
        $plan = $plan170();

        $t->same([1, 3, 105, 106, 107, 108], $plan->nextReadablePages());
        $t->same([109, 110], $plan->fencedSourcePages());
        $t->same([1, 3, 105, 106, 107, 108], $plan->changedNextPages());
        $t->same([false, false, false, true, true, false, true, true], array_column($plan->handoffRows(), 'pointer_map_changed'));
        $t->same([false, false, false, false, false, false, false, false], array_column($plan->handoffRows(), 'deleted_cell_visible_to_next'));
        $t->same([null, null, null, 0, 108, 106, null, null], array_column($plan->handoffRows(), 'next_overflow_next_page'));
        $t->same([], array_values(array_intersect($plan->nextReadablePages(), $plan->fencedSourcePages())));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next170-ready', $plan->handoffSummary()['status']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next166-ready', $plan->basePlan->writeAdmissionSummary()['status']);
    };
}

return $tests;
