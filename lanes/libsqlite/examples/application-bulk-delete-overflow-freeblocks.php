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

$tablePageSize = 2048;
$tableLarge = SQLiteTableLeafCell::encodeWithOverflowPages(
    2,
    SQLiteRecord::encode([null, '_transient_bulk_cache', str_repeat('stale-cache-fragment:', 120), 'no']),
    7,
    $tablePageSize,
);
$tableTimeout = SQLiteTableLeafCell::encodeWithOverflowPages(
    3,
    SQLiteRecord::encode([null, '_transient_timeout_bulk_cache', str_repeat('1700000000:', 250), 'no']),
    13,
    $tablePageSize,
);
$tableOverflowPages = array_combine(range(7, 6 + count($tableLarge['overflowPages'])), $tableLarge['overflowPages'])
    + array_combine(range(13, 12 + count($tableTimeout['overflowPages'])), $tableTimeout['overflowPages']);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    $tableLarge['cell'],
    $tableTimeout['cell'],
    SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
], $tablePageSize);

$indexLargeKey = str_repeat('_transient_bulk_cache_', 30);
$indexTimeoutKey = str_repeat('_transient_timeout_bulk_cache_', 22);
$indexLarge = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode([$indexLargeKey, 2]), 21);
$indexTimeout = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode([$indexTimeoutKey, 3]), 31);
$indexOverflowPages = array_combine(range(21, 20 + count($indexLarge['overflowPages'])), $indexLarge['overflowPages'])
    + array_combine(range(31, 30 + count($indexTimeout['overflowPages'])), $indexTimeout['overflowPages']);
$indexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 4])),
    $indexLarge['cell'],
    $indexTimeout['cell'],
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

$tableDelete = SQLiteTableLeafPage::deleteCellsByRowIdsWithOverflowRelease(
    $tablePage,
    [2, 3],
    static fn (int $firstOverflowPage, int $byteCount): array => $overflowPageNumbers($tableOverflowPages, $firstOverflowPage, $byteCount),
    $tablePageSize,
    secureDelete: true,
);
$indexDelete = SQLiteIndexLeafPage::deleteCellsByRecordValuesWithOverflowRelease(
    $indexPage,
    [[$indexLargeKey, 2], [$indexTimeoutKey, 3]],
    static fn (int $firstOverflowPage, int $byteCount): array => $overflowPageNumbers($indexOverflowPages, $firstOverflowPage, $byteCount),
    secureDelete: true,
    overflowReader: $indexOverflowReader,
);
$tableHeader = SQLiteBTreePageHeader::parsePage($tableDelete['page'], $tablePageSize);
$indexHeader = SQLiteBTreePageHeader::parsePage($indexDelete['page'], 512);

echo json_encode([
    'applicationUse' => 'Delete a transient option plus timeout partner from wp_options and the autoload/option_name index, returning coalesced freeblocks and obsolete overflow pages for freelist release.',
    'deletedTableRowIds' => $tableDelete['rowids'],
    'deletedIndexRecordPrefixes' => array_map(
        static fn (array $record): array => [substr((string) $record[0], 0, 28), $record[1]],
        $indexDelete['record_values'],
    ),
    'tableObsoleteOverflowPages' => $tableDelete['obsolete_overflow_page_numbers'],
    'indexObsoleteOverflowPages' => $indexDelete['obsolete_overflow_page_numbers'],
    'tableFreeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $tableHeader->freeblocks($tableDelete['page'])),
    'indexFreeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $indexHeader->freeblocks($indexDelete['page'])),
    'tableFreeblockIntegrity' => $tableHeader->freeblockIntegrityReport($tableDelete['page']),
    'indexFreeblockIntegrity' => $indexHeader->freeblockIntegrityReport($indexDelete['page']),
    'tableSecureDelete' => $tableHeader->freeblockSecureDeleteReport($tableDelete['page'])['secure_delete_payload_zeroed'],
    'indexSecureDelete' => $indexHeader->freeblockSecureDeleteReport($indexDelete['page'])['secure_delete_payload_zeroed'],
    'remainingTableRowIds' => array_map(
        static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
        SQLiteTableLeafCell::parsePageCells($tableDelete['page'], $tableHeader),
    ),
    'remainingIndexRecords' => array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($indexDelete['page'], $indexHeader),
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
