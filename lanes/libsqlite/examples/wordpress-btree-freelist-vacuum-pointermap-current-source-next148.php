<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistFreePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTruncatePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistAllocationPlan.php';
require_once __DIR__ . '/../src/SQLiteOverflowPage.php';
require_once __DIR__ . '/../src/SQLiteOverflowFreelistReleasePlan.php';
require_once __DIR__ . '/../src/SQLiteOverflowVacuumTruncatePlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$pageSize = 512;
$pageCount = 313;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = str_repeat("\0", $pageSize);
$pages[1] = substr_replace($pages[1], "SQLite format 3\0", 0, 16);
$pages[1] = substr_replace($pages[1], pack('n', $pageSize), 16, 2);
$pages[1][18] = "\x01";
$pages[1][19] = "\x01";
$pages[1][20] = "\x00";
$pages[1][21] = "\x40";
$pages[1][22] = "\x20";
$pages[1][23] = "\x20";
$pages[1] = substr_replace($pages[1], pack('N', $pageCount), 28, 4);
$pages[1] = substr_replace($pages[1], pack('N', 0), 32, 4);
$pages[1] = substr_replace($pages[1], pack('N', 0), 36, 4);
$pages[1] = substr_replace($pages[1], pack('N', 4), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);
$pages[311] = str_repeat('P', $pageSize);

$putPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$putPointerMap(4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMap(42, SQLitePointerMapEntry::BTREE_PAGE, 4);
foreach ([309, 310, 312, 313] as $index => $pageNumber) {
    $first = $index === 0 || $index === 2;
    $putPointerMap(
        $pageNumber,
        $first ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $first ? 42 : $pageNumber - 1,
    );
    $pages[$pageNumber] = pack('N', in_array($pageNumber, [309, 312], true) ? $pageNumber + 1 : 0)
        . str_repeat(chr(65 + $index), $pageSize - 4);
}

$plan = SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan::next148FromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp_options-autoload-index-old-value',
            'obsolete_overflow_page_numbers' => [309, 310],
            'record_values' => [['autoload', 'yes', 'theme_mods']],
        ],
        [
            'source' => 'wp_options-autoload-tail-value',
            'obsolete_overflow_page_numbers' => [312, 313],
            'rowids' => [14801],
        ],
    ],
    3,
    str_repeat('replacement-autoload-option-', 22),
    42,
    true,
);

$report = [
    'scenario' => 'wordpress-btree-freelist-vacuum-pointermap-current-source-next148',
    'wordpressUse' => 'Replacing an oversized autoloaded option after incremental vacuum crosses an auto-vacuum pointer-map boundary without reusing the pointer-map page as overflow.',
    'truncatedPointerMapPages' => $plan->truncatedPointerMapPages(),
    'truncatedFreelistPages' => $plan->truncatedFreelistPages(),
    'allocatedOverflowPages' => $plan->basePlan->allocatedOverflowPages(),
    'rejectedBoundaryAllocations' => $plan->rejectedBoundaryAllocations(),
    'finalDatabasePageCount' => $plan->basePlan->databaseAfterAllocation->pageCount(),
    'boundaryStatuses' => array_column($plan->boundaryRows(), 'boundary_status'),
];

if ($report['truncatedPointerMapPages'] !== [311] || $report['allocatedOverflowPages'] !== [310, 309]) {
    fwrite(STDERR, "wordpress-btree-freelist-vacuum-pointermap-current-source-next148 self-test failed\n");
    exit(1);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
