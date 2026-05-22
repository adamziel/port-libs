<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteWordPressOption;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$names = $argv[2] ?? null;
$limit = isset($argv[3]) ? (int) $argv[3] : null;
if ($databasePath === null || $names === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-options-by-name-list.php path/to/wordpress.sqlite option_name[,option_name...] [limit]\n");
    exit(1);
}

$optionNames = array_values(array_filter(
    array_map(trim(...), explode(',', $names)),
    static fn (string $name): bool => $name !== '',
));

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForInLookup('wp_options', 'option_name', $optionNames);
$options = array_map(
    static fn (SQLiteWordPressOption $option): array => $option->toArray(),
    $database->wordpressOptionsByIndexedNames($optionNames, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'optionNames' => $optionNames,
    'wpOptionsOptionNameInListIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
