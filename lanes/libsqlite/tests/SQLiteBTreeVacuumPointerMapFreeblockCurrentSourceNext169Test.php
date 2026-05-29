<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage169 = static function (int $pageCount): string {
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

$putPointerMapEntry169 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database169 = static function () use ($makeFirstPage169, $putPointerMapEntry169): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage169(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next169', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(48 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry169($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan169 = static function (array $observedCurrentPages = []) use ($database169): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database169();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext169(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next169-current-source-gate-', 46),
        3,
        true,
        $observedCurrentPages,
    );
};

$stalePlan169 = static function () use ($database169, $plan169): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database169();

    return $plan169([
        107 => substr_replace($database->page(107), 'X', 16, 1),
        108 => substr_replace($database->page(108), 'Y', 24, 1),
    ]);
};

$message169 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$tests = [
    'btree vacuum pointermap freeblock current source next169 status' => static function (TestRunner $t) use ($plan169): void {
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next169-ready', $plan169()->currentSourceGate()['status']);
    },
    'btree vacuum pointermap freeblock current source next169 action' => static function (TestRunner $t) use ($plan169): void {
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next169', $plan169()->toArray()['action']);
    },
    'btree vacuum pointermap freeblock current source next169 admitted pages' => static function (TestRunner $t) use ($plan169): void {
        $t->same([1, 3, 105, 106, 107, 108], $plan169()->admittedCurrentSourcePages());
    },
    'btree vacuum pointermap freeblock current source next169 changed pages' => static function (TestRunner $t) use ($plan169): void {
        $t->same([1, 3, 105, 106, 107, 108], $plan169()->changedAdmittedPages());
    },
    'btree vacuum pointermap freeblock current source next169 vacuum rejected pages' => static function (TestRunner $t) use ($plan169): void {
        $t->same([109, 110], $plan169()->vacuumRejectedPages());
    },
    'btree vacuum pointermap freeblock current source next169 stale pages absent by default' => static function (TestRunner $t) use ($plan169): void {
        $t->same([], $plan169()->staleCurrentSourcePages());
    },
    'btree vacuum pointermap freeblock current source next169 stale pages rejected' => static function (TestRunner $t) use ($stalePlan169): void {
        $t->same([107, 108], $stalePlan169()->staleCurrentSourcePages());
    },
    'btree vacuum pointermap freeblock current source next169 stale pages removed from admitted set' => static function (TestRunner $t) use ($stalePlan169): void {
        $t->same([1, 3, 105, 106], $stalePlan169()->admittedCurrentSourcePages());
    },
    'btree vacuum pointermap freeblock current source next169 stale changed pages removed from write list' => static function (TestRunner $t) use ($stalePlan169): void {
        $t->same([1, 3, 105, 106], $stalePlan169()->changedAdmittedPages());
    },
    'btree vacuum pointermap freeblock current source next169 replacement chain' => static function (TestRunner $t) use ($plan169): void {
        $gate = $plan169()->currentSourceGate();
        $t->same([107, 108, 106], $gate['replacement_overflow_pages']);
        $t->same([108, 106, 0], $gate['replacement_overflow_next_pages']);
        $t->same([3, 107, 108], $gate['replacement_pointer_map_parents']);
    },
    'btree vacuum pointermap freeblock current source next169 dependency and non overlap' => static function (TestRunner $t) use ($plan169): void {
        $gate = $plan169()->currentSourceGate();
        $t->same(['sqlite-btree-vacuum-pointermap-freeblock-current-source-next165', 'sqlite-current-source-next169'], $gate['dependencies']);
        $t->true(str_contains($gate['dependency_closure'], 'no new support component needed'));
        $t->true(str_contains($gate['non_overlap'], 'does not repeat next165'));
    },
    'btree vacuum pointermap freeblock current source next169 all row pages' => static function (TestRunner $t) use ($plan169): void {
        $t->same([1, 3, 105, 106, 107, 108, 109, 110], array_column($plan169()->writeGateRows(), 'page_number'));
    },
    'btree vacuum pointermap freeblock current source next169 gate statuses' => static function (TestRunner $t) use ($plan169): void {
        $t->same([
            'admitted-current-source-match',
            'admitted-current-source-match',
            'admitted-current-source-match',
            'admitted-current-source-match',
            'admitted-current-source-match',
            'admitted-current-source-match',
            'rejected-after-vacuum-truncate',
            'rejected-after-vacuum-truncate',
        ], array_column($plan169()->writeGateRows(), 'gate_status'));
    },
    'btree vacuum pointermap freeblock current source next169 stale gate statuses' => static function (TestRunner $t) use ($stalePlan169): void {
        $t->same([
            'admitted-current-source-match',
            'admitted-current-source-match',
            'admitted-current-source-match',
            'admitted-current-source-match',
            'rejected-stale-current-source',
            'rejected-stale-current-source',
            'rejected-after-vacuum-truncate',
            'rejected-after-vacuum-truncate',
        ], array_column($stalePlan169()->writeGateRows(), 'gate_status'));
    },
    'btree vacuum pointermap freeblock current source next169 pointer map transitions' => static function (TestRunner $t) use ($plan169): void {
        $rows = $plan169()->writeGateRows();
        $t->same([null, 'root-page', null, 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'], array_column($rows, 'current_pointer_map_type'));
        $t->same([null, 'root-page', null, 'overflow-page', 'first-overflow-page', 'overflow-page', null, null], array_column($rows, 'next_pointer_map_type'));
        $t->same([null, 0, null, 3, 106, 107, 108, 109], array_column($rows, 'current_pointer_map_parent'));
        $t->same([null, 0, null, 108, 3, 107, null, null], array_column($rows, 'next_pointer_map_parent'));
    },
    'btree vacuum pointermap freeblock current source next169 overflow next transitions' => static function (TestRunner $t) use ($plan169): void {
        $rows = $plan169()->writeGateRows();
        $t->same([null, null, null, 107, 108, 109, null, null], array_column($rows, 'current_overflow_next_page'));
        $t->same([null, null, null, 0, 108, 106, null, null], array_column($rows, 'next_overflow_next_page'));
    },
    'btree vacuum pointermap freeblock current source next169 row hash comparisons' => static function (TestRunner $t) use ($stalePlan169): void {
        $rows = $stalePlan169()->writeGateRows();
        $t->same(true, $rows[3]['current_source_matches']);
        $t->same(false, $rows[4]['current_source_matches']);
        $t->same(false, $rows[5]['current_source_matches']);
        $t->same(false, $rows[6]['write_admitted']);
        $t->same(false, $rows[7]['write_admitted']);
    },
    'btree vacuum pointermap freeblock current source next169 rejects fully stale write gate' => static function (TestRunner $t) use ($database169, $message169, $plan169): void {
        $database = $database169();
        $observed = [];
        foreach ([1, 3, 105, 106, 107, 108] as $pageNumber) {
            $observed[$pageNumber] = substr_replace($database->page($pageNumber), chr($pageNumber % 255), 32, 1);
        }

        $t->same('SQLite b-tree vacuum pointer-map freeblock current-source next169 has no admitted writable pages', $message169(static fn () => $plan169($observed)));
    },
];

foreach (range(1, 30) as $index) {
    $tests['btree vacuum pointermap freeblock current source next169 invariant ' . $index] = static function (TestRunner $t) use ($plan169, $stalePlan169): void {
        $plan = $plan169();
        $stale = $stalePlan169();
        $summary = $plan->toArray();

        $t->same('btree-vacuum-pointermap-freeblock-current-source-next169-ready', $summary['current_source_gate']['status']);
        $t->same([109, 110], $summary['current_source_gate']['vacuum_rejected_pages']);
        $t->same([107, 108], $stale->staleCurrentSourcePages());
        $t->same([], $plan->staleCurrentSourcePages());
        $t->true($plan->currentSourceGate()['write_gate_signature'] !== $stale->currentSourceGate()['write_gate_signature']);
    };
}

return $tests;
