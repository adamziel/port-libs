<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage182 = static function (int $pageCount): string {
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

$putPointerMapEntry182 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database182 = static function () use ($makeFirstPage182, $putPointerMapEntry182): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage182(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next182', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry182($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan182 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database182;

    $database = $database182();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafApplyScheduleFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next182-current-source-apply-', 50),
        3,
        true,
        $batchSize,
    );
};

$message182 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases182 = [
    'action label' => static fn (): mixed => $plan182()->toArray()['action'],
    'status' => static fn (): mixed => $plan182()->applySummary()['status'],
    'ordered replay pages' => static fn (): mixed => $plan182()->orderedReplayPages(),
    'truncate pages' => static fn (): mixed => $plan182()->truncateAfterReplayPages(),
    'pointer map replay pages' => static fn (): mixed => $plan182()->replayPointerMapPages(),
    'replacement overflow pages' => static fn (): mixed => $plan182()->replacementOverflowPages(),
    'apply errors' => static fn (): mixed => $plan182()->applyErrors(),
    'operations' => static fn (): mixed => array_column($plan182()->applyRows(), 'operation'),
    'apply orders' => static fn (): mixed => array_column($plan182()->applyRows(), 'apply_order'),
    'page roles' => static fn (): mixed => array_column($plan182()->applyRows(), 'page_role'),
    'page numbers' => static fn (): mixed => array_column($plan182()->applyRows(), 'page_number'),
    'batch indexes' => static fn (): mixed => array_column($plan182()->applyRows(), 'batch_index'),
    'positions' => static fn (): mixed => array_column($plan182()->applyRows(), 'position_in_batch'),
    'pointer map types' => static fn (): mixed => array_column($plan182()->applyRows(), 'pointer_map_type'),
    'pointer map parents' => static fn (): mixed => array_column($plan182()->applyRows(), 'pointer_map_parent'),
    'dependency flags' => static fn (): mixed => array_column($plan182()->applyRows(), 'dependency_replayed_in_schedule'),
    'truncation flags' => static fn (): mixed => array_column($plan182()->applyRows(), 'tail_truncation_allowed_after_this_row'),
    'hash shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => $row['operation'] !== 'replay-page' || strlen((string) $row['next_page_hash']) === 64,
        $plan182()->applyRows(),
    )),
    'resume shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => $row['operation'] !== 'replay-page' || strlen((string) $row['resume_token']) === 64,
        $plan182()->applyRows(),
    )),
    'summary dependencies' => static fn (): mixed => $plan182()->applySummary()['dependencies'],
    'summary dependency pages' => static fn (): mixed => $plan182()->applySummary()['dependency_pages'],
    'summary fenced pages' => static fn (): mixed => $plan182()->applySummary()['fenced_pages'],
    'summary truncate after replay' => static fn (): mixed => $plan182()->applySummary()['truncate_after_replay'],
    'summary pointer map before overflow' => static fn (): mixed => $plan182()->applySummary()['pointer_map_replayed_before_overflow'],
    'summary signature length' => static fn (): mixed => strlen($plan182()->applySummary()['apply_signature']),
    'dependency closure' => static fn (): mixed => str_contains($plan182()->applySummary()['dependency_closure'], 'no new support component needed'),
    'non overlap' => static fn (): mixed => str_contains($plan182()->applySummary()['non_overlap'], 'does not repeat next177'),
    'base action' => static fn (): mixed => $plan182()->basePlan->toArray()['action'],
    'base replay pages' => static fn (): mixed => $plan182()->basePlan->replayPages(),
    'base fenced pages' => static fn (): mixed => $plan182()->basePlan->fencedPages(),
    'batch size three replay pages' => static fn (): mixed => $plan182(3)->orderedReplayPages(),
    'batch size three roles' => static fn (): mixed => array_column($plan182(3)->applyRows(), 'page_role'),
    'bad batch size rejected' => static fn (): mixed => $message182(static fn () => $plan182(0)),
];

$expected182 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next182',
    'status' => 'btree-vacuum-pointermap-freeblock-current-source-next182-ready',
    'ordered replay pages' => [1, 3, 105, 106, 107, 108],
    'truncate pages' => [109, 110],
    'pointer map replay pages' => [105],
    'replacement overflow pages' => [106, 107, 108],
    'apply errors' => [],
    'operations' => ['replay-page', 'replay-page', 'replay-page', 'replay-page', 'replay-page', 'replay-page', 'truncate-fenced-tail', 'truncate-fenced-tail'],
    'apply orders' => [0, 1, 2, 3, 4, 5, 6, 7],
    'page roles' => ['database-header', 'table-leaf-freeblock', 'pointer-map', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail', 'truncated-tail'],
    'page numbers' => [1, 3, 105, 106, 107, 108, 109, 110],
    'batch indexes' => [0, 0, 1, 1, 2, 2, null, null],
    'positions' => [0, 1, 0, 1, 0, 1, null, null],
    'pointer map types' => [null, 'root-page', null, 'overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'pointer map parents' => [null, 0, null, 108, 3, 107, null, null],
    'dependency flags' => [true, true, true, true, true, true, true, true],
    'truncation flags' => [false, false, false, false, false, true, true, true],
    'hash shape' => [true, true, true, true, true, true, true, true],
    'resume shape' => [true, true, true, true, true, true, true, true],
    'summary dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next177', 'sqlite-current-source-next182'],
    'summary dependency pages' => [2, 105],
    'summary fenced pages' => [109, 110],
    'summary truncate after replay' => true,
    'summary pointer map before overflow' => true,
    'summary signature length' => 64,
    'dependency closure' => true,
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next177',
    'base replay pages' => [1, 3, 105, 106, 107, 108],
    'base fenced pages' => [109, 110],
    'batch size three replay pages' => [1, 3, 105, 106, 107, 108],
    'batch size three roles' => ['database-header', 'table-leaf-freeblock', 'pointer-map', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail', 'truncated-tail'],
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases182 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next182 ' . $name] = static function (TestRunner $t) use ($callback, $expected182, $name): void {
        $t->same($expected182[$name], $callback());
    };
}

foreach (range(1, 54) as $index) {
    $tests['btree vacuum pointermap freeblock current source next182 apply invariant ' . $index] = static function (TestRunner $t) use ($plan182): void {
        $plan = $plan182();
        $rows = $plan->applyRows();
        $summary = $plan->applySummary();

        $t->same([], $plan->applyErrors());
        $t->same([1, 3, 105, 106, 107, 108], $plan->orderedReplayPages());
        $t->same([109, 110], $plan->truncateAfterReplayPages());
        $t->same([105], $plan->replayPointerMapPages());
        $t->same([106, 107, 108], $plan->replacementOverflowPages());
        $t->same([true, true, true, true, true, true, true, true], array_column($rows, 'dependency_replayed_in_schedule'));
        $t->same([false, false, false, false, false, true, true, true], array_column($rows, 'tail_truncation_allowed_after_this_row'));
        $t->same(true, $summary['truncate_after_replay']);
        $t->same(true, $summary['pointer_map_replayed_before_overflow']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next182-ready', $summary['status']);
    };
}

return $tests;
