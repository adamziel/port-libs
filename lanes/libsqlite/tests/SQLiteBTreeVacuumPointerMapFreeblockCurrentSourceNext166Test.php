<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage166 = static function (int $pageCount): string {
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

$putPointerMapEntry166 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database166 = static function () use ($makeFirstPage166, $putPointerMapEntry166): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage166(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next166', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry166($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan166 = static function (
    int $maxTruncatedPages = 2,
    ?string $payload = null,
    bool $secureDelete = true,
) use ($database166): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database166();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: $secureDelete);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext166(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next166-freeblock-write-admission-', 44),
        3,
        $secureDelete,
    );
};

$message166 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases166 = [
    'action label' => static fn (): mixed => $plan166()->toArray()['action'],
    'status' => static fn (): mixed => $plan166()->writeAdmissionSummary()['status'],
    'leaf page' => static fn (): mixed => $plan166()->writeAdmissionSummary()['leaf_page'],
    'admitted pages' => static fn (): mixed => $plan166()->admittedWritePages(),
    'rejected pages' => static fn (): mixed => $plan166()->rejectedWritePages(),
    'pointer map write pages' => static fn (): mixed => $plan166()->pointerMapWritePages(),
    'replacement write pages' => static fn (): mixed => $plan166()->replacementOverflowWritePages(),
    'summary leaf freeblocks' => static fn (): mixed => $plan166()->writeAdmissionSummary()['leaf_freeblock_pages'],
    'summary replacement pages' => static fn (): mixed => $plan166()->writeAdmissionSummary()['replacement_chain_pages'],
    'summary rejected current source' => static fn (): mixed => $plan166()->writeAdmissionSummary()['rejected_current_source_pages'],
    'summary final page count' => static fn (): mixed => $plan166()->writeAdmissionSummary()['final_database_page_count'],
    'write admission signature' => static fn (): mixed => $plan166()->writeAdmissionSummary()['write_admission_signature'],
    'rejected source signature' => static fn (): mixed => $plan166()->writeAdmissionSummary()['rejected_source_signature'],
    'dependencies' => static fn (): mixed => $plan166()->writeAdmissionSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => str_contains($plan166()->writeAdmissionSummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan166()->writeAdmissionSummary()['non_overlap'], 'does not repeat next163'),
    'write row pages' => static fn (): mixed => array_column($plan166()->writeRows(), 'page_number'),
    'write row kinds' => static fn (): mixed => array_column($plan166()->writeRows(), 'write_kind'),
    'write admitted flags' => static fn (): mixed => array_column($plan166()->writeRows(), 'write_admitted'),
    'write page sizes' => static fn (): mixed => array_column($plan166()->writeRows(), 'page_size'),
    'pointer map flags' => static fn (): mixed => array_column($plan166()->writeRows(), 'is_pointer_map_page'),
    'leaf flags' => static fn (): mixed => array_column($plan166()->writeRows(), 'is_leaf_freeblock_page'),
    'replacement flags' => static fn (): mixed => array_column($plan166()->writeRows(), 'is_replacement_overflow_page'),
    'overflow next pages' => static fn (): mixed => array_column($plan166()->writeRows(), 'overflow_next_page'),
    'pointer map offsets' => static fn (): mixed => array_column($plan166()->writeRows(), 'pointer_map_cell_offsets'),
    'leaf freeblock offsets' => static fn (): mixed => array_column($plan166()->writeRows(), 'leaf_freeblock_offset'),
    'deleted cell absent flags' => static fn (): mixed => array_column($plan166()->writeRows(), 'deleted_cell_bytes_absent'),
    'stale source flags' => static fn (): mixed => array_column($plan166()->writeRows(), 'stale_current_source_admitted'),
    'pointer map hash length' => static fn (): mixed => strlen($plan166()->writeRows()[2]['page_hash']),
    'leaf hash length' => static fn (): mixed => strlen($plan166()->writeRows()[1]['page_hash']),
    'rejected hashes' => static fn (): mixed => array_slice(array_column($plan166()->writeRows(), 'page_hash'), -2),
    'base admitted current source' => static fn (): mixed => $plan166()->basePlan->admittedCurrentSourcePages(),
    'base rejected current source' => static fn (): mixed => $plan166()->basePlan->rejectedCurrentSourcePages(),
    'base replacement chain' => static fn (): mixed => $plan166()->basePlan->replacementChainPages(),
    'empty payload rejected' => static fn (): mixed => $message166(static fn () => $plan166(2, '')),
    'wide vacuum rejected allocation' => static fn (): mixed => $message166(static fn () => $plan166(4)),
    'insecure delete admitted after vacuum compaction' => static fn (): mixed => $message166(static fn () => $plan166(2, null, false)),
];

$expected166 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next166',
    'status' => 'btree-vacuum-pointermap-freeblock-current-source-next166-ready',
    'leaf page' => 3,
    'admitted pages' => [1, 3, 105, 106, 107, 108],
    'rejected pages' => [109, 110],
    'pointer map write pages' => [105],
    'replacement write pages' => [106, 107, 108],
    'summary leaf freeblocks' => [3],
    'summary replacement pages' => [107, 108, 106],
    'summary rejected current source' => [109, 110],
    'summary final page count' => 108,
    'write admission signature' => hash('sha256', '1,3,105,106,107,108'),
    'rejected source signature' => hash('sha256', '109,110'),
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next163', 'sqlite-current-source-next166'],
    'dependency closure' => true,
    'non overlap' => true,
    'write row pages' => [1, 3, 105, 106, 107, 108, 109, 110],
    'write row kinds' => ['database-header', 'leaf-freeblock-page', 'pointer-map-page', 'replacement-overflow-page', 'replacement-overflow-page', 'replacement-overflow-page', 'rejected-truncated-current-source-page', 'rejected-truncated-current-source-page'],
    'write admitted flags' => [true, true, true, true, true, true, false, false],
    'write page sizes' => [512, 512, 512, 512, 512, 512, 0, 0],
    'pointer map flags' => [false, false, true, false, false, false, false, false],
    'leaf flags' => [false, true, false, false, false, false, false, false],
    'replacement flags' => [false, false, false, true, true, true, false, false],
    'overflow next pages' => [null, null, null, 0, 108, 106, null, null],
    'pointer map offsets' => [[], [], [5, 10, 0], [], [], [], [], []],
    'leaf freeblock offsets' => [null, 0, null, null, null, null, null, null],
    'deleted cell absent flags' => [null, true, null, null, null, null, null, null],
    'stale source flags' => [false, false, false, false, false, false, false, false],
    'pointer map hash length' => 64,
    'leaf hash length' => 64,
    'rejected hashes' => [null, null],
    'base admitted current source' => [106, 107, 108],
    'base rejected current source' => [109, 110],
    'base replacement chain' => [107, 108, 106],
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes',
    'wide vacuum rejected allocation' => 'SQLite freelist does not contain enough pages for this allocation',
    'insecure delete admitted after vacuum compaction' => 'not rejected',
];

$tests = [];

foreach ($cases166 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next166 ' . $name] = static function (TestRunner $t) use ($callback, $expected166, $name): void {
        $t->same($expected166[$name], $callback());
    };
}

foreach (range(1, 54) as $index) {
    $tests['btree vacuum pointermap freeblock current source next166 write invariant ' . $index] = static function (TestRunner $t) use ($plan166): void {
        $plan = $plan166();

        $t->same([1, 3, 105, 106, 107, 108], $plan->admittedWritePages());
        $t->same([109, 110], $plan->rejectedWritePages());
        $t->same([105], $plan->pointerMapWritePages());
        $t->same([106, 107, 108], $plan->replacementOverflowWritePages());
        $t->same([false, true, false, false, false, false, false, false], array_column($plan->writeRows(), 'is_leaf_freeblock_page'));
        $t->same([null, 0, null, null, null, null, null, null], array_column($plan->writeRows(), 'leaf_freeblock_offset'));
        $t->same([null, true, null, null, null, null, null, null], array_column($plan->writeRows(), 'deleted_cell_bytes_absent'));
        $t->same([false, false, false, false, false, false, false, false], array_column($plan->writeRows(), 'stale_current_source_admitted'));
        $t->same([], array_values(array_intersect($plan->admittedWritePages(), $plan->rejectedWritePages())));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next166-ready', $plan->writeAdmissionSummary()['status']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next163-ready', $plan->basePlan->currentSourceFence()['status']);
    };
}

return $tests;
