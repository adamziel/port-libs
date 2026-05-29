<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeFreeblock.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteFreelistAllocationPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistFreePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTruncatePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteIndexLeafPage.php';
require_once __DIR__ . '/../src/SQLiteOverflowFreelistReleasePlan.php';
require_once __DIR__ . '/../src/SQLiteOverflowVacuumTruncatePlan.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteRecord.php';
require_once __DIR__ . '/../src/SQLiteTableLeafCell.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLiteVarint.php';

use PortLibs\LibSqlite\SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;
$makeFirstPage = static function () use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 310), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
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

$pages = array_fill(1, 310, str_repeat("\0", $pageSize));
$pages[1] = $makeFirstPage();
foreach ([306, 307, 308, 309, 310] as $index => $pageNumber) {
    $next = in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0;
    $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
}
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 42, SQLitePointerMapEntry::BTREE_PAGE, 4);
foreach ([306, 307, 308] as $index => $pageNumber) {
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $index === 0 ? 42 : $pageNumber - 1,
    );
}
foreach ([309, 310] as $index => $pageNumber) {
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $index === 0 ? 4 : 309,
    );
}

$plan = SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp_options-autoload-tail-overflow',
            'obsolete_overflow_page_numbers' => [306, 307, 308],
            'rowids' => [10401],
        ],
        [
            'source' => 'wp_options-option-name-index-tail-overflow',
            'obsolete_overflow_page_numbers' => [309, 310],
            'record_values' => [['_transient_next104', 10401]],
        ],
    ],
    3,
    2,
    42,
    [
        307 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(10410, SQLiteRecord::encode([null, '_transient_reused_after_vacuum', 'fresh', 'no'])),
        ]),
        306 => SQLiteIndexLeafPage::assemble([
            SQLiteRecord::encode(['_transient_reused_after_vacuum', 10410]),
        ]),
    ],
    true,
);

echo json_encode([
    'scenario' => 'wordpress-btree-freelist-vacuum-reuse-current-source-next104',
    'wordpressUse' => 'After copied wp_options overflow cleanup and incremental vacuum, reuse surviving free pages for new table/index B-tree pages while leaving truncated tail pages unavailable.',
    'vacuumSurvivors' => $plan->vacuumPlan->survivingFreedPointerMapPages(),
    'vacuumTruncatedPages' => $plan->vacuumPlan->truncatedFreedPointerMapPages(),
    'allocatedPages' => $plan->allocatedPageNumbers(),
    'reuseRows' => $plan->btreeFreelistVacuumReuseRows(),
    'finalFreelistPages' => $plan->databaseAfterReuse->freelistPageNumbers(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
