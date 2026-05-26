<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblock;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-page-freeblocks.php path/to/wordpress.sqlite [page-number]\n");
    exit(1);
}
$pageNumber = isset($argv[2]) ? (int) $argv[2] : 1;

$database = SQLiteDatabase::fromFile($databasePath);
$header = $database->pageHeader($pageNumber);
$page = $database->page($pageNumber);
$usableSize = $database->usablePageSize();

try {
    $freeblocks = array_map(
        static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(),
        $header->freeblocks($page, $usableSize),
    );
    $freeSpaceBytes = $header->freeSpaceBytes($page, $usableSize);
    $defragmentation = null;
    if ($header->pageType === 'table-leaf') {
        $compactedPage = SQLiteTableLeafPage::defragment($page, $database->header->pageSize, $header->headerOffset, $usableSize, true);
        $compactedHeader = SQLiteBTreePageHeader::parsePage($compactedPage, $database->header->pageSize, $header->headerOffset);
        $defragmentation = [
            'cellContentAreaStart' => $compactedHeader->cellContentAreaStart,
            'firstFreeblockOffset' => $compactedHeader->firstFreeblockOffset,
            'fragmentedFreeBytes' => $compactedHeader->fragmentedFreeBytes,
            'freeSpaceBytes' => $compactedHeader->freeSpaceBytes($compactedPage, $usableSize),
        ];
    } elseif ($header->pageType === 'index-leaf') {
        $compactedPage = SQLiteIndexLeafPage::defragment($page, $database->header->pageSize, $header->headerOffset, $usableSize, true);
        $compactedHeader = SQLiteBTreePageHeader::parsePage($compactedPage, $database->header->pageSize, $header->headerOffset);
        $defragmentation = [
            'cellContentAreaStart' => $compactedHeader->cellContentAreaStart,
            'firstFreeblockOffset' => $compactedHeader->firstFreeblockOffset,
            'fragmentedFreeBytes' => $compactedHeader->fragmentedFreeBytes,
            'freeSpaceBytes' => $compactedHeader->freeSpaceBytes($compactedPage, $usableSize),
        ];
    }
    $corruption = null;
} catch (InvalidArgumentException $exception) {
    $freeblocks = [];
    $freeSpaceBytes = null;
    $defragmentation = null;
    $corruption = $exception->getMessage();
}

echo json_encode([
    'path' => $databasePath,
    'page' => $pageNumber,
    'pageType' => $header->pageType,
    'usablePageSize' => $usableSize,
    'cellCount' => $header->cellCount,
    'cellContentAreaStart' => $header->cellContentAreaStart,
    'firstFreeblockOffset' => $header->firstFreeblockOffset,
    'fragmentedFreeBytes' => $header->fragmentedFreeBytes,
    'freeSpaceBytes' => $freeSpaceBytes,
    'freeblocks' => $freeblocks,
    'defragmentation' => $defragmentation,
    'corruption' => $corruption,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
