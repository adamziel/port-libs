<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowVacuumFreepagePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 260;
$firstTrunk = 8;
$existingLeaves = range(130, 249);
$releasedPages = [20, 21, 22, 106, 107, 108];
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
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
$firstPage = substr_replace($firstPage, pack('N', $firstTrunk), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 1 + count($existingLeaves)), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$pages[1] = $firstPage;
$pages[$firstTrunk] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    if ($pageNumber === 1) {
        return;
    }

    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::ROOT_PAGE, 0], $firstTrunk => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}
foreach ($existingLeaves as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
foreach ($releasedPages as $index => $pageNumber) {
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $index === 0 || $index === 3 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $index === 0 ? 4 : ($index === 3 ? 5 : $releasedPages[$index - 1]),
    );
    $pages[$pageNumber] = pack('N', 0) . str_repeat(chr(65 + $index), $pageSize - 4);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreeOverflowVacuumFreepagePlan::fromDeleteResults(
    $database,
    [
        [
            'source' => 'wp_options-transient-table-overflow',
            'obsolete_overflow_page_numbers' => [20, 21, 22],
            'rowids' => [44],
        ],
        [
            'source' => 'wp_options-option-name-index-overflow',
            'obsolete_overflow_page_numbers' => [106, 107, 108],
            'record_values' => [['_transient_timeout_current_next26', 44]],
        ],
    ],
    true,
    8,
);

echo json_encode([
    'applicationUse' => 'Vacuum copied wp_options transient table/index overflow pages into current freelist page images, then report the next SQLite freepage allocation order without ext/sqlite.',
    'summary' => $plan->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
