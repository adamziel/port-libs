<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$names = $argv[2] ?? null;
$limit = isset($argv[3]) ? (int) $argv[3] : null;
if ($databasePath === null || $names === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-lowercase-options-by-name-list.php path/to/application.sqlite option_name[,option_name...] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(lower(option_name)).\n");
    exit(1);
}

$optionNames = array_values(array_filter(
    array_map(trim(...), explode(',', $names)),
    static fn (string $name): bool => $name !== '',
));

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForLowercaseInLookup('wp_options', 'option_name', $optionNames);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedLowercaseNames($optionNames, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'optionNames' => $optionNames,
    'wpOptionsLowerOptionNameInListIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
