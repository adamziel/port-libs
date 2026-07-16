<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblock;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$largeValue = str_repeat('stale-cache-fragment:', 70);
$tablePayload = SQLiteRecord::encode([null, '_transient_large_cache', $largeValue, 'no']);
$tableOverflow = SQLiteTableLeafCell::encodeWithOverflowPages(2, $tablePayload, 5);
$tableOverflowPages = array_combine(range(5, 4 + count($tableOverflow['overflowPages'])), $tableOverflow['overflowPages']);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    $tableOverflow['cell'],
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
]);

$largeIndexKey = str_repeat('_transient_large_cache_', 30);
$indexRecord = SQLiteRecord::encode([$largeIndexKey, 2]);
$indexOverflow = SQLiteIndexCell::encodeWithOverflowPages($indexRecord, 9);
$indexOverflowPages = array_combine(range(9, 8 + count($indexOverflow['overflowPages'])), $indexOverflow['overflowPages']);
$indexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 3])),
    $indexOverflow['cell'],
    SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 1])),
]);

$overflowPageNumbers = static function (array $pages, int $firstOverflowPage, int $byteCount): array {
    $pageNumbers = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $page = $pages[$pageNumber] ?? null;
        if ($page === null) {
            throw new InvalidArgumentException('Fixture overflow page is missing');
        }
        $pageNumbers[] = $pageNumber;
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $remaining -= min($remaining, 508);
    }

    return $pageNumbers;
};
$indexOverflowReader = static function (int $firstOverflowPage, int $byteCount) use ($indexOverflowPages): string {
    $payload = '';
    $pageNumber = $firstOverflowPage;
    while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
        $page = $indexOverflowPages[$pageNumber] ?? null;
        if ($page === null) {
            throw new InvalidArgumentException('Fixture overflow page is missing');
        }
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $payload .= substr($page, 4);
    }

    return substr($payload, 0, $byteCount);
};

$tableDelete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
    $tablePage,
    2,
    static fn (int $firstOverflowPage, int $byteCount): array => $overflowPageNumbers($tableOverflowPages, $firstOverflowPage, $byteCount),
    secureDelete: true,
);
$indexDelete = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
    $indexPage,
    [$largeIndexKey, 2],
    static fn (int $firstOverflowPage, int $byteCount): array => $overflowPageNumbers($indexOverflowPages, $firstOverflowPage, $byteCount),
    secureDelete: true,
    overflowReader: $indexOverflowReader,
);

$tableHeader = SQLiteBTreePageHeader::parsePage($tableDelete['page'], 512);
$indexHeader = SQLiteBTreePageHeader::parsePage($indexDelete['page'], 512);

echo json_encode([
    'applicationUse' => 'Delete a large transient option and its option_name index entry while reporting obsolete overflow pages for the subsequent freelist release plan.',
    'deletedTableRowId' => $tableDelete['rowid'],
    'deletedIndexRecordPrefix' => substr($indexDelete['record_values'][0], 0, 24),
    'tableObsoleteOverflowPages' => $tableDelete['obsolete_overflow_page_numbers'],
    'indexObsoleteOverflowPages' => $indexDelete['obsolete_overflow_page_numbers'],
    'tableFreeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $tableHeader->freeblocks($tableDelete['page'])),
    'indexFreeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $indexHeader->freeblocks($indexDelete['page'])),
    'remainingTableRowIds' => array_map(
        static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
        SQLiteTableLeafCell::parsePageCells($tableDelete['page'], $tableHeader),
    ),
    'remainingIndexRecords' => array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($indexDelete['page'], $indexHeader),
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
