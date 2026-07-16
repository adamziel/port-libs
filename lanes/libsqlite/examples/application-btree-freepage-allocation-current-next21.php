<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$emptyPage = str_repeat("\0", $pageSize);
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 9), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $emptyPage
    . $emptyPage
    . $emptyPage
    . SQLiteFreelistTrunkPage::assemble(8, [3, 7], $pageSize)
    . $emptyPage
    . $emptyPage
    . SQLiteFreelistTrunkPage::assemble(null, [4], $pageSize)
    . $emptyPage,
);

$plan = $database->planPageAllocation(6, true);
$postHeader = SQLiteHeader::parse($plan->firstPage);

echo json_encode([
    'applicationUse' => 'Allocate reusable pages for a copied wp_options b-tree write by draining the current freelist trunk, following its next-trunk pointer, and appending only after the freelist is depleted without requiring ext/sqlite.',
    'allocatedPages' => $plan->allocatedPageNumbers,
    'appendedPages' => $plan->appendedPageNumbers,
    'allocationSteps' => $plan->allocationSteps(),
    'headerAfter' => [
        'database_page_count' => $postHeader->databaseSizePages,
        'first_freelist_trunk_page' => $postHeader->firstFreelistTrunkPage,
        'freelist_page_count' => $postHeader->freelistPageCount,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
