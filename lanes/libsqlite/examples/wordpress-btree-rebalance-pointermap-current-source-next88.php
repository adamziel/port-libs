<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeRebalancePointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 12), 28, 4);
    $page = substr_replace($page, pack('N', 11), 32, 4);
    $page = substr_replace($page, pack('N', 1), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$overflowReaderFor = static function (array $overflowPages): callable {
    return static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): string {
        $payload = '';
        $pageNumber = $firstOverflowPage;
        while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
            $page = $overflowPages[$pageNumber];
            $pageNumber = unpack('N', substr($page, 0, 4))[1];
            $payload .= substr($page, 4);
        }

        return substr($payload, 0, $byteCount);
    };
};

$overflowNumbersFor = static function (array $overflowPages): callable {
    return static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
        $pages = [];
        $pageNumber = $firstOverflowPage;
        $remaining = $byteCount;
        while ($pageNumber !== 0 && $remaining > 0) {
            $page = $overflowPages[$pageNumber];
            $pages[] = $pageNumber;
            $pageNumber = unpack('N', substr($page, 0, 4))[1];
            $remaining -= min($remaining, 508);
        }

        return $pages;
    };
};

$pages = array_fill(1, 12, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);
$pages[11] = SQLiteFreelistTrunkPage::assemble(null, [], 512);

$deletedValues = ['no', '_transient_rebalance_next88', str_repeat('transient-payload:', 64), 10];
$deleted = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($deletedValues), 7, 512);
$pages[3] = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_alpha_next88', 30]), leftChildPage: 4),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_divider_next88', 800]), leftChildPage: 5),
], 6);
$pages[4] = SQLiteIndexLeafPage::assemble([
    $deleted['cell'],
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_keep_next88', 20])),
]);
$pages[5] = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_beta_next88', 40])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_gamma_next88', 50])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_omega_next88', 60])),
]);
$pages[6] = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_next88', 900])),
]);
$overflowPages = array_combine(range(7, 6 + count($deleted['overflowPages'])), $deleted['overflowPages']);
foreach ($overflowPages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
    10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
    11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parentPageNumber);
}

$plan = SQLiteBTreeRebalancePointerMapCurrentSourceNextPlan::indexDeleteRebalanceCurrentSource(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    4,
    5,
    0,
    $deletedValues,
    $overflowNumbersFor($overflowPages),
    true,
    $overflowReaderFor($overflowPages),
);

echo json_encode([
    'scenario' => 'wordpress-btree-rebalance-pointermap-current-source-next88',
    'wordpressUse' => 'Delete an overflow-backed wp_options option_name index entry, rebalance sibling leaves from the current source image, then release obsolete overflow pages with auto-vacuum pointer-map entries rewritten to free-page.',
    'changed_pointer_map_pages' => $plan->changedPointerMapPageNumbers(),
    'current_pointer_map_types' => array_column($plan->pointerMapTransitions, 'current_type_name'),
    'next_pointer_map_types' => array_column($plan->pointerMapTransitions, 'next_type_name'),
    'freed_pages' => $plan->rebalanceFreelistPlan->freePlan->freedPageNumbers,
    'freelist_pages_after' => $plan->nextDatabase->freelistPageNumbers(),
    'updated_pages' => $plan->updatedPageNumbers(),
], JSON_PRETTY_PRINT) . PHP_EOL;
