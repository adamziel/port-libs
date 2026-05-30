<?php

declare(strict_types=1);

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

$integrity = $header->freeblockIntegrityReport($page, $usableSize);
$secureDelete = $header->freeblockSecureDeleteReport($page, $usableSize);
$currentNextFragments = $header->freeblockFragmentReport($page, $usableSize);
$freeblocks = $integrity['freeblocks'];
$freeSpaceBytes = $integrity['free_space_bytes'];

try {
    if ($integrity['status'] !== 'ok') {
        throw new InvalidArgumentException((string) $integrity['error']);
    }
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
    'freeblockIntegrity' => $integrity,
    'freeblockSecureDelete' => $secureDelete,
    'freeblockCurrentNextFragments' => $currentNextFragments,
    'freeSpaceBytes' => $freeSpaceBytes,
    'freeblocks' => $freeblocks,
    'defragmentation' => $defragmentation,
    'corruption' => $corruption,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
