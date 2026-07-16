<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage180 = static function (int $pageCount): string {
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

$putPointerMapEntry180 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database180 = static function () use ($makeFirstPage180, $putPointerMapEntry180): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage180(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next180', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(75 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry180($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan180 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database180;

    $database = $database180();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafWriterAdmissionFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next180-current-source-apply-', 50),
        3,
        true,
        $batchSize,
    );
};

$message180 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases180 = [
    'action label' => static fn (): mixed => $plan180()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan180()->applySummary()['status'],
    'leaf page' => static fn (): mixed => $plan180()->applySummary()['leaf_page'],
    'batch count' => static fn (): mixed => $plan180()->applySummary()['batch_count'],
    'apply pages' => static fn (): mixed => $plan180()->applyPages(),
    'pointer map write pages' => static fn (): mixed => $plan180()->pointerMapWritePages(),
    'fenced pages' => static fn (): mixed => $plan180()->applySummary()['fenced_pages'],
    'fenced apply pages' => static fn (): mixed => $plan180()->fencedApplyPages(),
    'apply errors' => static fn (): mixed => $plan180()->applyErrors(),
    'apply sequence count' => static fn (): mixed => $plan180()->applySummary()['apply_sequence_count'],
    'batch indexes' => static fn (): mixed => array_column($plan180()->applyRows(), 'batch_index'),
    'dependency write pages' => static fn (): mixed => array_column($plan180()->applyRows(), 'dependency_write_pages'),
    'page write pages' => static fn (): mixed => array_column($plan180()->applyRows(), 'page_write_pages'),
    'page write counts' => static fn (): mixed => array_column($plan180()->applyRows(), 'page_write_count'),
    'pointer map write counts' => static fn (): mixed => array_column($plan180()->applyRows(), 'pointer_map_write_count'),
    'pointer map precedes flags' => static fn (): mixed => array_column($plan180()->applyRows(), 'pointer_map_precedes_pages'),
    'contains fenced flags' => static fn (): mixed => array_column($plan180()->applyRows(), 'contains_fenced_page'),
    'deleted cell visibility flags' => static fn (): mixed => array_column($plan180()->applyRows(), 'deleted_cell_visible_to_next'),
    'first sequence kind' => static fn (): mixed => $plan180()->writeSequence()[0]['kind'],
    'first sequence page' => static fn (): mixed => $plan180()->writeSequence()[0]['page_number'],
    'second sequence kind' => static fn (): mixed => $plan180()->writeSequence()[1]['kind'],
    'second sequence page' => static fn (): mixed => $plan180()->writeSequence()[1]['page_number'],
    'fourth sequence kind' => static fn (): mixed => $plan180()->writeSequence()[3]['kind'],
    'fourth sequence page' => static fn (): mixed => $plan180()->writeSequence()[3]['page_number'],
    'sequence kinds' => static fn (): mixed => array_column($plan180()->writeSequence(), 'kind'),
    'sequence pages' => static fn (): mixed => array_column($plan180()->writeSequence(), 'page_number'),
    'summary final page count' => static fn (): mixed => $plan180()->applySummary()['final_database_page_count'],
    'summary dependencies' => static fn (): mixed => $plan180()->applySummary()['dependencies'],
    'dependency closure' => static fn (): mixed => str_contains($plan180()->applySummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan180()->applySummary()['non_overlap'], 'does not repeat next177'),
    'base replay pages' => static fn (): mixed => $plan180()->basePlan->replayPages(),
    'base fenced pages' => static fn (): mixed => $plan180()->basePlan->fencedPages(),
    'base pointer map dependency pages' => static fn (): mixed => $plan180()->basePlan->pointerMapDependencyPages(),
    'batch size three apply pages' => static fn (): mixed => $plan180(3)->applyPages(),
    'batch size three dependency rows' => static fn (): mixed => array_column($plan180(3)->applyRows(), 'dependency_write_pages'),
    'batch size three sequence pages' => static fn (): mixed => array_column($plan180(3)->writeSequence(), 'page_number'),
    'batch size three count' => static fn (): mixed => $plan180(3)->applySummary()['batch_count'],
    'apply signature length' => static fn (): mixed => strlen($plan180()->applySummary()['apply_signature']),
    'batch signature length' => static fn (): mixed => strlen($plan180()->applySummary()['batch_signature']),
    'bad batch size rejected' => static fn (): mixed => $message180(static fn () => $plan180(0)),
];

$expected180 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next180',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next180-ready',
    'leaf page' => 3,
    'batch count' => 3,
    'apply pages' => [1, 3, 105, 106, 107, 108],
    'pointer map write pages' => [2, 105],
    'fenced pages' => [109, 110],
    'fenced apply pages' => [],
    'apply errors' => [],
    'apply sequence count' => 9,
    'batch indexes' => [0, 1, 2],
    'dependency write pages' => [[2], [105], [105]],
    'page write pages' => [[1, 3], [105, 106], [107, 108]],
    'page write counts' => [2, 2, 2],
    'pointer map write counts' => [1, 1, 1],
    'pointer map precedes flags' => [true, true, true],
    'contains fenced flags' => [false, false, false],
    'deleted cell visibility flags' => [false, false, false],
    'first sequence kind' => 'pointer-map',
    'first sequence page' => 2,
    'second sequence kind' => 'page-image',
    'second sequence page' => 1,
    'fourth sequence kind' => 'pointer-map',
    'fourth sequence page' => 105,
    'sequence kinds' => ['pointer-map', 'page-image', 'page-image', 'pointer-map', 'page-image', 'page-image', 'pointer-map', 'page-image', 'page-image'],
    'sequence pages' => [2, 1, 3, 105, 105, 106, 105, 107, 108],
    'summary final page count' => 108,
    'summary dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next177', 'sqlite-current-source-next180'],
    'dependency closure' => true,
    'non overlap' => true,
    'base replay pages' => [1, 3, 105, 106, 107, 108],
    'base fenced pages' => [109, 110],
    'base pointer map dependency pages' => [2, 105],
    'batch size three apply pages' => [1, 3, 105, 106, 107, 108],
    'batch size three dependency rows' => [[2], [105]],
    'batch size three sequence pages' => [2, 1, 3, 105, 105, 106, 107, 108],
    'batch size three count' => 2,
    'apply signature length' => 64,
    'batch signature length' => 64,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases180 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next180 ' . $name] = static function (TestRunner $t) use ($callback, $expected180, $name): void {
        $t->same($expected180[$name], $callback());
    };
}

foreach (range(1, 50) as $index) {
    $tests['btree vacuum pointermap freeblock current source next180 apply invariant ' . $index] = static function (TestRunner $t) use ($plan180): void {
        $plan = $plan180();
        $rows = $plan->applyRows();
        $summary = $plan->applySummary();

        $t->same([], $plan->applyErrors());
        $t->same([], $plan->fencedApplyPages());
        $t->same([1, 3, 105, 106, 107, 108], $plan->applyPages());
        $t->same([2, 105], $plan->pointerMapWritePages());
        $t->same([109, 110], $summary['fenced_pages']);
        $t->same([true, true, true], array_column($rows, 'pointer_map_precedes_pages'));
        $t->same([1, 1, 1], array_column($rows, 'pointer_map_write_count'));
        $t->same([2, 2, 2], array_column($rows, 'page_write_count'));
        $t->same(9, $summary['apply_sequence_count']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next180-ready', $summary['status']);
    };
}

return $tests;
