<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 106;

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', $pageCount), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$leafPage = str_repeat("\xdd", $pageSize);
$leafPage[0] = "\x0d";
$leafPage = substr_replace($leafPage, pack('n', 360), 1, 2);
$leafPage = substr_replace($leafPage, pack('n', 1), 3, 2);
$leafPage = substr_replace($leafPage, pack('n', 340), 5, 2);
$leafPage[7] = chr(7);
$leafPage = substr_replace($leafPage, pack('n', 492), 8, 2);
$leafPage = substr_replace($leafPage, str_repeat('L', 12), 492, 12);
$leafPage = substr_replace($leafPage, pack('n', 376) . pack('n', 12), 360, 4);
$leafPage = substr_replace($leafPage, pack('n', 392) . pack('n', 10), 376, 4);
$leafPage = substr_replace($leafPage, pack('n', 406) . pack('n', 12), 392, 4);
$leafPage = substr_replace($leafPage, pack('n', 0) . pack('n', 14), 406, 4);

$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[3] = $leafPage;
$pages[104] = pack('N', 106) . str_repeat('O', $pageSize - 4);
$pages[106] = pack('N', 0) . str_repeat('P', $pageSize - 4);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pageNumber === 1 || $pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 42, SQLitePointerMapEntry::BTREE_PAGE, 3);
$putPointerMapEntry($pages, 104, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 42);
$putPointerMapEntry($pages, 106, SQLitePointerMapEntry::OVERFLOW_PAGE, 104);

$plan = SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNextPlan::fromDatabaseDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [[
        'source' => 'copied-wp-options-transient-tail-overflow-delete',
        'obsolete_overflow_page_numbers' => [104, 106],
        'rowids' => [1701],
    ]],
    8,
    true,
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $plan->nextDatabase->pageCount() !== 103
        || $plan->survivingFreelistPageNumbers() !== []
        || $plan->truncatedPageNumbers() !== [106, 105, 104]
        || $plan->truncatedPointerMapPages() !== [105]
        || array_column($plan->truncatedRows(), 'page_number') !== [104, 105, 106]
    ) {
        fwrite(STDERR, "wordpress-btree-freeblock-pointermap-vacuum-current-source-next self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-btree-freeblock-pointermap-vacuum-current-source-next self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'copied wp_options freeblock coalesce followed by pointer-map boundary vacuum current source next',
    'wordpressUse' => 'Preview a transient option delete where the leaf page keeps a coalesced reusable freeblock while obsolete tail overflow pages are removed by incremental vacuum across an auto-vacuum pointer-map boundary without ext/sqlite.',
    'coalescedFragmentBytes' => $plan->coalescePlan->coalescedFragmentBytes,
    'releasedOverflowPages' => $plan->vacuumPlan->releasedOverflowPages(),
    'truncatedPages' => $plan->truncatedPageNumbers(),
    'finalDatabasePageCount' => $plan->nextDatabase->pageCount(),
    'survivingFreelistPages' => $plan->survivingFreelistPageNumbers(),
    'updatedPageNumbers' => $plan->updatedPageNumbers(),
    'pointerMapTransitions' => $plan->vacuumPlan->pointerMapVacuumTransitions(),
    'truncatedPointerMapPages' => $plan->truncatedPointerMapPages(),
    'materializedRows' => array_column($plan->materializedRows(), 'page_number'),
    'truncatedRows' => array_column($plan->truncatedRows(), 'page_number'),
    'rows' => $plan->rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
