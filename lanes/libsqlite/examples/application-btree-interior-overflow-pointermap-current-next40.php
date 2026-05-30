<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorMergeApplicationPlan;
use PortLibs\LibSqlite\SQLiteBTreeInteriorMergePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pages = array_fill(1, 18, str_repeat("\0", $pageSize));
$pages[1] = str_repeat("\0", $pageSize);
$pages[1] = substr_replace($pages[1], "SQLite format 3\0", 0, 16);
$pages[1] = substr_replace($pages[1], pack('n', $pageSize), 16, 2);
$pages[1][18] = "\x01";
$pages[1][19] = "\x01";
$pages[1][20] = "\x00";
$pages[1][21] = "\x40";
$pages[1][22] = "\x20";
$pages[1][23] = "\x20";
$pages[1] = substr_replace($pages[1], pack('N', 18), 28, 4);
$pages[1] = substr_replace($pages[1], pack('N', 2), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
    $pointerMapPage = 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$leftPayload = SQLiteRecord::encode(['no', '_transient_left', 10]);
$dividerPayload = SQLiteRecord::encode(['no', '_transient_timeout_merge', 20]);
$overflowPayload = SQLiteRecord::encode(['yes', '_transient_plugin_settings', str_repeat('widget-cache:', 85), 30]);
$overflowCell = SQLiteIndexCell::encodeWithOverflowPages($overflowPayload, 14, $pageSize, $pageSize, 12);
$overflowPages = array_combine(range(14, 13 + count($overflowCell['overflowPages'])), $overflowCell['overflowPages']);

$pages[3] = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode($dividerPayload, $pageSize, null, 7),
], 8, $pageSize);
$pages[7] = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode($leftPayload, $pageSize, null, 10),
], 11, $pageSize);
$pages[8] = SQLiteIndexInteriorPage::assemble([$overflowCell['cell']], 13, $pageSize);
foreach ($overflowPages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 7 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 10 => [SQLitePointerMapEntry::BTREE_PAGE, 7], 11 => [SQLitePointerMapEntry::BTREE_PAGE, 7], 12 => [SQLitePointerMapEntry::BTREE_PAGE, 8], 13 => [SQLitePointerMapEntry::BTREE_PAGE, 8], 14 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 8], 15 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 14]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pageNumber, $type, $parent);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$overflowReader = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): string {
    $payload = '';
    $pageNumber = $firstOverflowPage;
    while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
        $page = $overflowPages[$pageNumber] ?? '';
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $payload .= substr($page, 4);
    }

    return substr($payload, 0, $byteCount);
};
$overflowPageNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
    $numbers = [];
    $pageNumber = $firstOverflowPage;
    while ($pageNumber !== 0 && $byteCount > 0) {
        $page = $overflowPages[$pageNumber] ?? '';
        $numbers[] = $pageNumber;
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $byteCount -= 508;
    }

    return $numbers;
};

$merge = SQLiteBTreeInteriorMergePlan::indexInterior(
    $database->page(7),
    $database->page(8),
    7,
    8,
    3,
    $dividerPayload,
    $pageSize,
    $pageSize,
    0,
    0,
    $overflowReader,
    $overflowPageNumbers,
);
$apply = SQLiteBTreeInteriorMergeApplicationPlan::apply($database, $merge, true);

echo json_encode([
    'applicationUse' => 'Merge an underfilled copied wp_options option_name index interior sibling while preserving an overflow-backed separator payload and retargeting auto-vacuum pointer-map ownership to the merged page.',
    'summary' => $merge->toArray(),
    'applied' => $apply->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
