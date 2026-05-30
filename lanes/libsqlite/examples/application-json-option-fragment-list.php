<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$jsonPath = $argv[2] ?? null;
$jsonValues = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
if ($databasePath === null || $jsonPath === null || $jsonValues === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-json-option-fragment-list.php path/to/application.sqlite json_path json_values_array [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(option_value -> 'key'). Pass json_values_array as JSON, for example '[{\"mode\":\"dark\"},\"dark\",null]'.\n");
    exit(1);
}

try {
    $lookupValues = json_decode($jsonValues, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "json_values_array must be valid JSON: {$exception->getMessage()}\n");
    exit(1);
}
if (!is_array($lookupValues) || !array_is_list($lookupValues)) {
    fwrite(STDERR, "json_values_array must decode to a JSON array.\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForJsonValueOperatorInLookup(
    'wp_options',
    'option_value',
    $jsonPath,
    $lookupValues,
);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedJsonFragments($jsonPath, $lookupValues, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'jsonPath' => $jsonPath,
    'lookupValues' => $lookupValues,
    'wpOptionsJsonValueOperatorInListIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
