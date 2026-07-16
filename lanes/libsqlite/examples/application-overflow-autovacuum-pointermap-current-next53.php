<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowAutoVacuumPointerMapPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 107), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 107, str_repeat("\0", 512));
$pages[1] = $firstPage;
$pages[4] = SQLiteFreelistTrunkPage::assemble(106, [5], 512);
$pages[106] = SQLiteFreelistTrunkPage::assemble(null, [107], 512);

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
    $pointerMapPage = $pageNumber < 105 ? 2 : 105;
    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

$putPointerMapEntry(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
foreach ([4, 5, 106, 107] as $pageNumber) {
    $putPointerMapEntry($pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain(
    $database,
    3,
    str_repeat('copied-wp-options-transient-payload;', 48),
);

echo json_encode([
    'scenario' => 'application-overflow-autovacuum-pointermap-current-next53',
    'applicationUse' => 'Allocate a copied wp_options overflow payload from a current freelist trunk and its next trunk while materializing auto-vacuum pointer-map ownership across both pointer-map pages without ext/sqlite.',
    'summary' => $plan->toArray(),
], JSON_PRETTY_PRINT) . "\n";
