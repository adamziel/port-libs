<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowAutoVacuumPointerMapPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount, bool $autoVacuum = true): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', $autoVacuum ? 3 : 0), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $stride = intdiv(512, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$makeCycleDatabase = static function (string $shape) use ($makeFirstPage, $putPointerMapEntry): SQLiteDatabase {
    $pages = array_fill(1, 112, str_repeat("\0", 512));
    $freelistCount = $shape === 'valid' ? 4 : 7;
    $pages[1] = $makeFirstPage(112, 4, $freelistCount);

    if ($shape === 'self') {
        $pages[4] = SQLiteFreelistTrunkPage::assemble(4, [5], 512);
    } elseif ($shape === 'two-trunk') {
        $pages[4] = SQLiteFreelistTrunkPage::assemble(106, [5], 512);
        $pages[106] = SQLiteFreelistTrunkPage::assemble(4, [107], 512);
    } elseif ($shape === 'later-trunk') {
        $pages[4] = SQLiteFreelistTrunkPage::assemble(106, [5], 512);
        $pages[106] = SQLiteFreelistTrunkPage::assemble(110, [107], 512);
        $pages[110] = SQLiteFreelistTrunkPage::assemble(106, [111], 512);
    } elseif ($shape === 'duplicate-leaf') {
        $pages[4] = SQLiteFreelistTrunkPage::assemble(106, [5], 512);
        $pages[106] = SQLiteFreelistTrunkPage::assemble(null, [5], 512);
        $pages[1] = $makeFirstPage(112, 4, 4);
    } else {
        $pages[4] = SQLiteFreelistTrunkPage::assemble(106, [5], 512);
        $pages[106] = SQLiteFreelistTrunkPage::assemble(null, [107], 512);
    }

    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    foreach ([4, 5, 106, 107, 110, 111] as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$payloadForPages = static function (int $pageCount): string {
    return str_repeat('w', (($pageCount - 1) * 508) + 1);
};

$tests = [];

foreach ([
    'self' => [4, [4, 4], [4], [5], [5, 4]],
    'two-trunk' => [4, [4, 106, 4], [4, 106], [5, 107], [5, 4, 107, 106]],
    'later-trunk' => [106, [106, 110, 106], [4, 106, 110], [5, 107, 111], [5, 4, 107, 106, 111, 110]],
] as $shape => [$cycleAtPage, $cyclePath, $trunks, $leaves, $allocationOrder]) {
    $tests['btree freelist trunk cycle current next71 reports ' . $shape . ' cycle'] = static function (TestRunner $t) use (
        $makeCycleDatabase,
        $shape,
        $cycleAtPage,
        $cyclePath,
        $trunks,
        $leaves,
        $allocationOrder,
    ): void {
        $plan = $makeCycleDatabase($shape)->freelistTraversalPlan();
        $summary = $plan->toArray();

        $t->same(false, $summary['valid']);
        $t->true($plan->hasCycle());
        $t->same($cycleAtPage, $summary['cycle_at_page']);
        $t->same($cyclePath, $summary['cycle_path']);
        $t->same($trunks, $summary['trunk_page_numbers']);
        $t->same($leaves, $summary['leaf_page_numbers']);
        $t->same($allocationOrder, $summary['allocation_order']);
        $t->same("SQLite freelist loops at page {$cycleAtPage}", $summary['errors'][0]);
    };
}

foreach ([
    'self',
    'two-trunk',
    'later-trunk',
] as $shape) {
    $tests['btree freelist trunk cycle current next71 allocation rejects ' . $shape] = static function (TestRunner $t) use ($makeCycleDatabase, $shape): void {
        $database = $makeCycleDatabase($shape);

        $t->throws(InvalidArgumentException::class, static function () use ($database): void {
            $database->planPageAllocation(1, false);
        });
    };

    $tests['btree freelist trunk cycle current next71 overflow allocation rejects ' . $shape] = static function (TestRunner $t) use (
        $makeCycleDatabase,
        $payloadForPages,
        $shape,
    ): void {
        $database = $makeCycleDatabase($shape);

        $t->throws(InvalidArgumentException::class, static function () use ($database, $payloadForPages): void {
            SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain($database, 3, $payloadForPages(2));
        });
    };
}

$tests['btree freelist trunk cycle current next71 valid chain remains allocatable'] = static function (TestRunner $t) use ($makeCycleDatabase): void {
    $database = $makeCycleDatabase('valid');
    $plan = $database->freelistTraversalPlan();
    $allocation = $database->planPageAllocation(4, false);

    $t->true($plan->isValid());
    $t->same(false, $plan->hasCycle());
    $t->same([4, 106], $plan->trunkPageNumbers);
    $t->same([5, 107], $plan->leafPageNumbers);
    $t->same([5, 4, 107, 106], $plan->allocationOrder);
    $t->same([5, 4, 107, 106], $allocation->allocatedPageNumbers);
    $t->same(0, $allocation->freelistPageCount);
};

$tests['btree freelist trunk cycle current next71 duplicate leaf is non cycle error'] = static function (TestRunner $t) use ($makeCycleDatabase): void {
    $plan = $makeCycleDatabase('duplicate-leaf')->freelistTraversalPlan();
    $summary = $plan->toArray();

    $t->same(false, $summary['valid']);
    $t->same(false, $plan->hasCycle());
    $t->same(null, $summary['cycle_at_page']);
    $t->same([], $summary['cycle_path']);
    $t->same('SQLite freelist page 5 appears more than once', $summary['errors'][0]);
};

foreach (range(1, 41) as $index) {
    $tests['btree freelist trunk cycle current next71 invariant ' . $index] = static function (TestRunner $t) use ($makeCycleDatabase, $index): void {
        $shape = $index % 3 === 0 ? 'later-trunk' : ($index % 2 === 0 ? 'two-trunk' : 'self');
        $plan = $makeCycleDatabase($shape)->freelistTraversalPlan();
        $summary = $plan->toArray();

        $t->same(false, $summary['valid']);
        $t->true($summary['cycle_at_page'] >= 4);
        $t->true(count($summary['cycle_path']) >= 2);
        $t->same($summary['cycle_at_page'], $summary['cycle_path'][count($summary['cycle_path']) - 1]);
        $t->true(str_contains($summary['errors'][0], 'SQLite freelist loops at page '));
    };
}

return $tests;
