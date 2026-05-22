<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$autoload = $argv[2] ?? null;
$optionName = $argv[3] ?? null;
if ($databasePath === null || $autoload === null || $optionName === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-autoloaded-option-by-name.php path/to/wordpress.sqlite autoload option_name\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForPointLookupColumns('wp_options', [
    'autoload' => $autoload,
    'option_name' => $optionName,
]);
$option = $database->wordpressOptionByIndexedAutoloadAndName($autoload, $optionName);

echo json_encode([
    'path' => $databasePath,
    'autoload' => $autoload,
    'optionName' => $optionName,
    'wpOptionsAutoloadOptionNameIndexRootPage' => $indexRootPage,
    'option' => $option?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
