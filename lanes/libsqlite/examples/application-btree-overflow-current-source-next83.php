<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$pageSize = 512;
$pageCount = 211;
$currentPayload = str_repeat('current-plugin-setting:', 58);
$nextPayload = str_repeat('next-plugin-setting:', 61);
$chainPages = [209, 210, 211];

$firstPage = static function (int $pageSize, int $pageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 42), 52, 4);

    return $page;
};

$putPointerMap = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
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

$currentPages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$nextPages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$currentPages[1] = $firstPage($pageSize, $pageCount);
$nextPages[1] = $firstPage($pageSize, $pageCount);

foreach (SQLiteOverflowPage::encodeChainAtPages($currentPayload, $chainPages, $pageSize) as $pageNumber => $pageImage) {
    $currentPages[$pageNumber] = $pageImage;
}
foreach (SQLiteOverflowPage::encodeChainAtPages($nextPayload, $chainPages, $pageSize) as $pageNumber => $pageImage) {
    $nextPages[$pageNumber] = $pageImage;
}
foreach ($chainPages as $index => $pageNumber) {
    $type = $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE;
    $putPointerMap($currentPages, $pageNumber, $type, $index === 0 ? 42 : $chainPages[$index - 1]);
    $putPointerMap($nextPages, $pageNumber, $type, $index === 0 ? 44 : $chainPages[$index - 1]);
}

$plan = SQLiteBTreeOverflowCurrentSourcePlan::compareCurrentAndNext(
    SQLiteDatabase::fromBytes(implode('', $currentPages)),
    SQLiteDatabase::fromBytes(implode('', $nextPages)),
    209,
    strlen($currentPayload),
    42,
);

echo json_encode($plan->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
