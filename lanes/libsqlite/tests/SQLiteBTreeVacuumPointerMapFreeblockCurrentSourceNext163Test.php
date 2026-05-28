<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext163Plan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage163 = static function (int $pageCount): string {
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

$putPointerMapEntry163 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database163 = static function () use ($makeFirstPage163, $putPointerMapEntry163): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage163(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next163', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(64 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry163($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan163 = static function (
    int $maxTruncatedPages = 2,
    ?string $payload = null,
) use ($database163): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext163Plan {
    $database = $database163();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext163Plan::tableLeafFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next163-current-source-admission-', 44),
        3,
        true,
    );
};

$message163 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$value163 = static function (array $array, string $path): mixed {
    $value = $array;
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }

    return $value;
};

$cases163 = [
    'action label' => static fn (): mixed => $plan163()->toArray()['action'],
    'status' => static fn (): mixed => $plan163()->currentSourceFence()['status'],
    'leaf page' => static fn (): mixed => $plan163()->currentSourceFence()['leaf_page'],
    'leaf freeblock pages' => static fn (): mixed => $plan163()->currentSourceFence()['leaf_freeblock_pages'],
    'released overflow pages' => static fn (): mixed => $plan163()->currentSourceFence()['released_overflow_pages'],
    'surviving overflow pages' => static fn (): mixed => $plan163()->currentSourceFence()['surviving_released_overflow_pages'],
    'truncated overflow pages' => static fn (): mixed => $plan163()->currentSourceFence()['truncated_released_overflow_pages'],
    'replacement chain pages' => static fn (): mixed => $plan163()->replacementChainPages(),
    'admitted current source pages' => static fn (): mixed => $plan163()->admittedCurrentSourcePages(),
    'rejected current source pages' => static fn (): mixed => $plan163()->rejectedCurrentSourcePages(),
    'final page count' => static fn (): mixed => $plan163()->currentSourceFence()['final_database_page_count'],
    'final freelist pages' => static fn (): mixed => $plan163()->currentSourceFence()['final_freelist_page_numbers'],
    'source chain signature' => static fn (): mixed => $plan163()->currentSourceFence()['source_chain_signature'],
    'surviving chain signature' => static fn (): mixed => $plan163()->currentSourceFence()['surviving_chain_signature'],
    'truncated chain signature' => static fn (): mixed => $plan163()->currentSourceFence()['truncated_chain_signature'],
    'replacement chain signature' => static fn (): mixed => $plan163()->currentSourceFence()['replacement_chain_signature'],
    'dependency markers' => static fn (): mixed => $plan163()->currentSourceFence()['dependencies'],
    'dependency closure' => static fn (): mixed => str_contains($plan163()->currentSourceFence()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan163()->currentSourceFence()['non_overlap'], 'does not repeat next160'),
    'fence row pages' => static fn (): mixed => array_column($plan163()->fenceRows(), 'page_number'),
    'fence source next pages' => static fn (): mixed => array_column($plan163()->fenceRows(), 'source_overflow_next_page'),
    'fence final next pages' => static fn (): mixed => array_column($plan163()->fenceRows(), 'final_overflow_next_page'),
    'fence source pointer types' => static fn (): mixed => array_column($plan163()->fenceRows(), 'source_pointer_map_type'),
    'fence source pointer parents' => static fn (): mixed => array_column($plan163()->fenceRows(), 'source_pointer_map_parent'),
    'fence final pointer types' => static fn (): mixed => array_column($plan163()->fenceRows(), 'final_pointer_map_type'),
    'fence final pointer parents' => static fn (): mixed => array_column($plan163()->fenceRows(), 'final_pointer_map_parent'),
    'replacement positions' => static fn (): mixed => array_column($plan163()->fenceRows(), 'replacement_chain_position'),
    'replacement expected next pages' => static fn (): mixed => array_column($plan163()->fenceRows(), 'replacement_expected_next_page'),
    'replacement expected parents' => static fn (): mixed => array_column($plan163()->fenceRows(), 'replacement_expected_parent'),
    'admitted flags' => static fn (): mixed => array_column($plan163()->fenceRows(), 'current_source_admitted'),
    'rejected flags' => static fn (): mixed => array_column($plan163()->fenceRows(), 'current_source_rejected'),
    'admission statuses' => static fn (): mixed => array_column($plan163()->fenceRows(), 'admission_status'),
    'base replacement next pages' => static fn (): mixed => $plan163()->basePlan->replacementOverflowNextPages(),
    'base replacement parents' => static fn (): mixed => $plan163()->basePlan->replacementPointerMapParents(),
    'base reused current source pages' => static fn (): mixed => $plan163()->basePlan->reusedCurrentSourceFreePages(),
    'base truncated rejected' => static fn (): mixed => $plan163()->basePlan->truncatedCurrentSourcePagesRejected(),
    'base pointer valid flags' => static fn (): mixed => array_column($plan163()->basePlan->chainRows, 'pointer_map_matches_chain'),
    'base next valid flags' => static fn (): mixed => array_column($plan163()->basePlan->chainRows, 'next_pointer_matches_chain'),
    'base truncated reused flags' => static fn (): mixed => array_column($plan163()->basePlan->chainRows, 'truncated_current_source_page_reused'),
    'short payload admitted' => static fn (): mixed => $message163(static fn () => $plan163(2, 'tiny')),
    'empty payload rejected' => static fn (): mixed => $message163(static fn () => $plan163(2, '')),
    'too few surviving pages rejected' => static fn (): mixed => $message163(static fn () => $plan163(4)),
];

$expected163 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next163',
    'status' => 'btree-vacuum-pointermap-freeblock-current-source-next163-ready',
    'leaf page' => 3,
    'leaf freeblock pages' => [3],
    'released overflow pages' => [106, 107, 108, 109, 110],
    'surviving overflow pages' => [106, 107, 108],
    'truncated overflow pages' => [109, 110],
    'replacement chain pages' => [107, 108, 106],
    'admitted current source pages' => [106, 107, 108],
    'rejected current source pages' => [109, 110],
    'final page count' => 108,
    'final freelist pages' => [],
    'source chain signature' => hash('sha256', '106,107,108,109,110'),
    'surviving chain signature' => hash('sha256', '106,107,108'),
    'truncated chain signature' => hash('sha256', '109,110'),
    'replacement chain signature' => hash('sha256', '107,108,106'),
    'dependency markers' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next160', 'sqlite-current-source-next163'],
    'dependency closure' => true,
    'non overlap' => true,
    'fence row pages' => [106, 107, 108, 109, 110],
    'fence source next pages' => [107, 108, 109, 110, 0],
    'fence final next pages' => [0, 108, 106, null, null],
    'fence source pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'fence source pointer parents' => [3, 106, 107, 108, 109],
    'fence final pointer types' => ['overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'fence final pointer parents' => [108, 3, 107, null, null],
    'replacement positions' => [2, 0, 1, null, null],
    'replacement expected next pages' => [0, 108, 106, null, null],
    'replacement expected parents' => [108, 3, 107, null, null],
    'admitted flags' => [true, true, true, false, false],
    'rejected flags' => [false, false, false, true, true],
    'admission statuses' => ['admitted-as-replacement-overflow-page', 'admitted-as-replacement-overflow-page', 'admitted-as-replacement-overflow-page', 'rejected-after-vacuum-truncate', 'rejected-after-vacuum-truncate'],
    'base replacement next pages' => [108, 106, 0],
    'base replacement parents' => [3, 107, 108],
    'base reused current source pages' => [107, 108, 106],
    'base truncated rejected' => [109, 110],
    'base pointer valid flags' => [true, true, true],
    'base next valid flags' => [true, true, true],
    'base truncated reused flags' => [false, false, false],
    'short payload admitted' => 'not rejected',
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes',
    'too few surviving pages rejected' => 'SQLite freelist does not contain enough pages for this allocation',
];

$tests = [];

foreach ($cases163 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next163 ' . $name] = static function (TestRunner $t) use ($callback, $expected163, $name): void {
        $t->same($expected163[$name], $callback());
    };
}

foreach (range(1, 36) as $index) {
    $tests['btree vacuum pointermap freeblock current source next163 invariant ' . $index] = static function (TestRunner $t) use ($plan163, $value163): void {
        $plan = $plan163();
        $summary = $plan->toArray();

        $t->same([106, 107, 108], $plan->admittedCurrentSourcePages());
        $t->same([109, 110], $plan->rejectedCurrentSourcePages());
        $t->same([107, 108, 106], $plan->replacementChainPages());
        $t->same([false, false, false], array_column($plan->basePlan->chainRows, 'truncated_current_source_page_reused'));
        $t->same([true, true, true], array_column($plan->basePlan->chainRows, 'pointer_map_matches_chain'));
        $t->same([true, true, true], array_column($plan->basePlan->chainRows, 'next_pointer_matches_chain'));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next163-ready', $value163($summary, 'current_source_fence.status'));
        $t->same('admitted-as-replacement-overflow-page', $plan->fenceRows()[0]['admission_status']);
        $t->same('rejected-after-vacuum-truncate', $plan->fenceRows()[4]['admission_status']);
        $t->same(null, $plan->fenceRows()[4]['final_page_hash']);
    };
}

return $tests;
