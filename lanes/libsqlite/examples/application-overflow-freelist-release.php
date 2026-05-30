<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteOverflowFreelistReleasePlan;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

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
$firstPage = substr_replace($firstPage, pack('N', 40), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$tablePayload = SQLiteRecord::encode([null, '_transient_release_cache', str_repeat('table-release:', 75), 'no']);
$tableEncoded = SQLiteTableLeafCell::encodeWithOverflowPages(2, $tablePayload, 7);
$tableOverflowPages = array_combine(range(7, 6 + count($tableEncoded['overflowPages'])), $tableEncoded['overflowPages']);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    $tableEncoded['cell'],
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
]);
$tableOverflowNumbers = static function (int $firstOverflowPage, int $byteCount) use ($tableOverflowPages): array {
    $pageNumbers = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $page = $tableOverflowPages[$pageNumber] ?? null;
        if ($page === null) {
            throw new InvalidArgumentException('Fixture table overflow page is missing');
        }
        $pageNumbers[] = $pageNumber;
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $remaining -= min($remaining, 508);
    }

    return $pageNumbers;
};
$tableDelete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease($tablePage, 2, $tableOverflowNumbers, secureDelete: true);
$tableDelete['source'] = 'wp_options-table';

$indexKey = str_repeat('_transient_release_cache_', 30);
$indexEncoded = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode([$indexKey, 2]), 21);
$indexOverflowPages = array_combine(range(21, 20 + count($indexEncoded['overflowPages'])), $indexEncoded['overflowPages']);
$indexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 3])),
    $indexEncoded['cell'],
    SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 1])),
]);
$indexOverflowReader = static function (int $firstOverflowPage, int $byteCount) use ($indexOverflowPages): string {
    $payload = '';
    $pageNumber = $firstOverflowPage;
    while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
        $page = $indexOverflowPages[$pageNumber] ?? null;
        if ($page === null) {
            throw new InvalidArgumentException('Fixture index overflow page is missing');
        }
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $payload .= substr($page, 4);
    }

    return substr($payload, 0, $byteCount);
};
$indexOverflowNumbers = static function (int $firstOverflowPage, int $byteCount) use ($indexOverflowPages): array {
    $pageNumbers = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $page = $indexOverflowPages[$pageNumber] ?? null;
        if ($page === null) {
            throw new InvalidArgumentException('Fixture index overflow page is missing');
        }
        $pageNumbers[] = $pageNumber;
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $remaining -= min($remaining, 508);
    }

    return $pageNumbers;
};
$indexDelete = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
    $indexPage,
    [$indexKey, 2],
    $indexOverflowNumbers,
    secureDelete: true,
    overflowReader: $indexOverflowReader,
);
$indexDelete['source'] = 'wp_options-option-name-index';

$pages = [];
for ($pageNumber = 1; $pageNumber <= 40; $pageNumber++) {
    $pages[$pageNumber] = $emptyPage;
}
$pages[1] = $firstPage;
$pages[3] = $tablePage;
$pages[4] = $indexPage;
foreach ($tableOverflowPages + $indexOverflowPages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$release = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, [$tableDelete, $indexDelete], true);
foreach ($release->freePlan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'applicationUse' => 'Delete a copied wp_options transient row and option_name index entry, then connect their obsolete overflow chains to the SQLite freelist and auto-vacuum pointer-map free-page entries without ext/sqlite.',
    'releasePlan' => $release->toArray(),
    'freelistAfter' => $postDatabase->freelistPageNumbers(),
    'nextAllocationOrder' => $postDatabase->freelistAllocationOrder(),
    'pointerMapTypes' => array_map(
        static fn (int $pageNumber): string => $postDatabase->pointerMapEntryForPage($pageNumber)->typeName(),
        $release->releasedOverflowPages,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
