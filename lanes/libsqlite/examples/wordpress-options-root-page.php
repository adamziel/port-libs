<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-options-root-page.php path/to/wordpress.sqlite\n");
    exit(1);
}

$handle = fopen($databasePath, 'rb');
if ($handle === false) {
    fwrite(STDERR, "Unable to open SQLite database: {$databasePath}\n");
    exit(1);
}

$prefix = fread($handle, 100);
if ($prefix === false) {
    fclose($handle);
    fwrite(STDERR, "Unable to read SQLite database header: {$databasePath}\n");
    exit(1);
}

$databaseHeader = SQLiteHeader::parse($prefix);
$remaining = $databaseHeader->pageSize - strlen($prefix);
$pageRemainder = $remaining > 0 ? fread($handle, $remaining) : '';
fclose($handle);

if ($pageRemainder === false || strlen($prefix . $pageRemainder) < $databaseHeader->pageSize) {
    fwrite(STDERR, "Unable to read the complete first SQLite page: {$databasePath}\n");
    exit(1);
}

$rootPage = SQLiteBTreePageHeader::parseFirstPage($prefix . $pageRemainder);

echo json_encode([
    'path' => $databasePath,
    'pageSize' => $databaseHeader->pageSize,
    'databaseSizePages' => $databaseHeader->databaseSizePages,
    'textEncoding' => $databaseHeader->textEncoding,
    'schemaRootPage' => [
        'type' => $rootPage->pageType,
        'cells' => $rootPage->cellCount,
        'cellContentAreaStart' => $rootPage->cellContentAreaStart,
        'fragmentedFreeBytes' => $rootPage->fragmentedFreeBytes,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
