<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$autoload = $argv[2] ?? null;
$optionName = $argv[3] ?? null;
if ($databasePath === null || $autoload === null || $optionName === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-autoloaded-option-by-name.php path/to/application.sqlite autoload option_name\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$compositeIndexRootPage = $database->indexRootPageForPointLookupColumns('wp_options', [
    'autoload' => $autoload,
    'option_name' => $optionName,
]);
$partialOptionNameIndexRootPage = $database->indexRootPageForPointLookupWithConstraints(
    'wp_options',
    'option_name',
    $optionName,
    ['autoload' => $autoload],
);

if ($compositeIndexRootPage !== null) {
    $lookupMode = 'composite-autoload-option_name';
    $option = $database->optionRowByIndexedAutoloadAndName($autoload, $optionName);
} elseif ($partialOptionNameIndexRootPage !== null) {
    $lookupMode = 'partial-option_name-autoload-equality';
    $option = $database->optionRowByIndexedNameForAutoload($optionName, $autoload);
} else {
    throw new InvalidArgumentException('SQLite wp_options autoload+option_name index is not present');
}

echo json_encode([
    'path' => $databasePath,
    'autoload' => $autoload,
    'optionName' => $optionName,
    'lookupMode' => $lookupMode,
    'wpOptionsAutoloadOptionNameIndexRootPage' => $compositeIndexRootPage,
    'wpOptionsPartialOptionNameAutoloadIndexRootPage' => $partialOptionNameIndexRootPage,
    'option' => $option?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
