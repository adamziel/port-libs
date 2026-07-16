<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistFreePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistAllocationPlan.php';
require_once __DIR__ . '/../src/SQLiteOverflowPage.php';
require_once __DIR__ . '/../src/SQLiteOverflowFreelistReleasePlan.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
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

$overflowPage = static fn (?int $nextPage, string $payload): string => str_pad(pack('N', $nextPage ?? 0) . $payload, 512, "\0");

$pages = array_fill(1, 8, str_repeat("\0", 512));
$pages[1] = $firstPage(8, 8, 1);
$pages[3] = SQLiteTableLeafPage::assemble([]);
$pages[4] = SQLiteTableLeafPage::assemble([]);
$pages[5] = SQLiteTableLeafPage::assemble([]);
$pages[6] = $overflowPage(7, str_repeat('O', 508));
$pages[7] = $overflowPage(null, str_repeat('P', 192));
$pages[8] = SQLiteFreelistTrunkPage::assemble(null, [], 512);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
$putPointerMapEntry($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 3);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
$putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FREE_PAGE, 0);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan::fromOverflowChains(
    $database,
    [[
        'source' => 'wp-options-large-transient-rewrite',
        'first_page' => 6,
        'overflow_payload_bytes' => 700,
        'rowids' => [12501],
    ]],
    700,
    3,
    str_repeat('N', 508) . str_repeat('Q', 192),
);

$summary = [
    'scenario' => 'application-btree-overflow-pointermap-freelist-current-source-next125',
    'application_use' => 'Rewrite a large wp_options transient value so obsolete overflow pages first become freelist leaves, then are immediately reused as the next overflow chain with pointer-map parentage restored.',
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'allocated_overflow_pages' => $plan->allocatedOverflowPages(),
    'reused_overflow_pages' => $plan->reusedOverflowPages(),
    'final_freelist_pages' => $plan->databaseAfterAllocation->freelistPageNumbers(),
    'pointer_map_transitions' => $plan->transitionRows(),
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    $ok = $summary['released_overflow_pages'] === [6, 7]
        && $summary['allocated_overflow_pages'] === [6, 7]
        && array_column($summary['pointer_map_transitions'], 'free_pointer_map_type') === ['free-page', 'free-page']
        && array_column($summary['pointer_map_transitions'], 'next_pointer_map_type') === ['first-overflow-page', 'overflow-page']
        && array_column($summary['pointer_map_transitions'], 'next_overflow_next_page') === [7, 0];
    if (!$ok) {
        fwrite(STDERR, "application-btree-overflow-pointermap-freelist-current-source-next125 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-btree-overflow-pointermap-freelist-current-source-next125 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
