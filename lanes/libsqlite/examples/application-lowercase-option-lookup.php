<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$optionName = $argv[2] ?? null;
if ($databasePath === null || $optionName === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-lowercase-option-lookup.php path/to/application.sqlite option_name\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForLowercasePointLookup('wp_options', 'option_name', $optionName);
$option = $database->optionRowByIndexedLowercaseName($optionName);

echo json_encode([
    'path' => $databasePath,
    'optionName' => $optionName,
    'wpOptionsLowerOptionNameIndexRootPage' => $indexRootPage,
    'option' => $option?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
