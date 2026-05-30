<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$names = $argv[2] ?? null;
$limit = isset($argv[3]) ? (int) $argv[3] : null;
if ($databasePath === null || $names === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-options-by-name-list.php path/to/application.sqlite option_name[,option_name...] [limit]\n");
    exit(1);
}

$optionNames = array_values(array_filter(
    array_map(trim(...), explode(',', $names)),
    static fn (string $name): bool => $name !== '',
));

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForInLookup('wp_options', 'option_name', $optionNames);
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRowsByIndexedNames($optionNames, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'optionNames' => $optionNames,
    'wpOptionsOptionNameInListIndexRootPage' => $indexRootPage,
    'plannerBehavior' => 'option_name IN-list lookups can use a partial option_name IN (...) index when all requested non-null names are covered by that partial predicate.',
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
