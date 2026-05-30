<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$makeFragmentedLeaf = static function (): string {
    $page = str_repeat("\xdd", 512);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', 392), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 376), 5, 2);
    $page[7] = chr(9);
    $page = substr_replace($page, pack('n', 500), 8, 2);
    $page = substr_replace($page, str_repeat('W', 8), 500, 8);
    $page = substr_replace($page, pack('n', 407) . pack('n', 10), 392, 4);
    $page = substr_replace($page, pack('n', 421) . pack('n', 13), 407, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', 18), 421, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pointerMapPage = 2;
    if ($pageNumber === $pointerMapPage) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$pages = array_fill(1, 6, str_repeat("\0", 512));
$pages[1] = $makeFirstPage(6);
$pages[2] = str_repeat("\0", 512);
$pages[3] = $makeFragmentedLeaf();
$pages[5] = pack('N', 6) . str_repeat('A', 508);
$pages[6] = pack('N', 0) . str_repeat('B', 508);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 5, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::OVERFLOW_PAGE, 5);

$plan = SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::currentSourceOverflowFreeblockFromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [[
        'source' => 'wp_options-current-source-large-autoload-value',
        'first_page' => 5,
        'overflow_payload_bytes' => 700,
    ]],
    [[
        'source' => 'wp_options-delete-large-autoload-value',
        'obsolete_overflow_page_numbers' => [5, 6],
        'rowids' => [13801],
    ]],
    3,
    str_repeat('next138-replacement-wp-option-', 27),
    true,
);

echo json_encode([
    'action' => $plan->toArray()['action'],
    'current_source_pages' => array_column($plan->currentSourceRows(), 'page_number'),
    'current_source_next_pages' => array_column($plan->currentSourceRows(), 'current_next_page'),
    'allocated_pages' => $plan->allocatedOverflowPages(),
    'transition_replacement_next_pages' => array_column($plan->transitionRows(), 'replacement_next_page'),
    'final_freelist_pages' => $plan->databaseAfterAllocation->freelistPageNumbers(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
