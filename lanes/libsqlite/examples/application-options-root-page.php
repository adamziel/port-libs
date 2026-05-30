<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-options-root-page.php path/to/application.sqlite [limit]\n");
    exit(1);
}
$limit = isset($argv[2]) ? (int) $argv[2] : 5;

$database = SQLiteDatabase::fromFile($databasePath);
$databaseHeader = $database->header;
$rootPage = $database->pageHeader(1);
$optionsRootPage = $database->tableRootPage('wp_options');
$optionsPageHeader = $optionsRootPage === null ? null : $database->pageHeader($optionsRootPage);
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRows($limit),
);

echo json_encode([
    'path' => $databasePath,
    'pageSize' => $databaseHeader->pageSize,
    'databaseSizePages' => $databaseHeader->databaseSizePages,
    'availablePages' => $database->pageCount(),
    'textEncoding' => $databaseHeader->textEncoding,
    'schemaRootPage' => [
        'type' => $rootPage->pageType,
        'cells' => $rootPage->cellCount,
        'cellContentAreaStart' => $rootPage->cellContentAreaStart,
        'fragmentedFreeBytes' => $rootPage->fragmentedFreeBytes,
    ],
    'wp_options' => [
        'rootPage' => $optionsRootPage,
        'rootPageType' => $optionsPageHeader?->pageType,
        'cells' => $optionsPageHeader?->cellCount,
        'sampleLimit' => $limit,
        'sampleOptions' => $options,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
