<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblock;
use PortLibs\LibSqlite\SQLiteDatabase;

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
    $corruption = null;
} catch (InvalidArgumentException $exception) {
    $freeblocks = [];
    $freeSpaceBytes = null;
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
    'corruption' => $corruption,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
