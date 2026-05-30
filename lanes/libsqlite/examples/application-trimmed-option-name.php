<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$optionName = $argv[2] ?? null;
$functionName = $argv[3] ?? 'trim';
$characters = $argv[4] ?? null;
if ($databasePath === null || $optionName === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-trimmed-option-name.php path/to/application.sqlite option_name [trim|ltrim|rtrim] [characters]\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForTrimmedPointLookup(
    'wp_options',
    'option_name',
    $optionName,
    $functionName,
    $characters,
);
$option = $database->optionRowByIndexedTrimmedName($optionName, $functionName, $characters);

echo json_encode([
    'path' => $databasePath,
    'optionName' => $optionName,
    'functionName' => $functionName,
    'characters' => $characters,
    'wpOptionsTrimOptionNameIndexRootPage' => $indexRootPage,
    'option' => $option?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
