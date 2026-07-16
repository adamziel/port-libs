<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$jsonPath = $argv[2] ?? null;
$jsonValue = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
if ($databasePath === null || $jsonPath === null || $jsonValue === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-json-option-fragment.php path/to/application.sqlite json_path json_value [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(option_value -> 'key'). Pass json_value as JSON, for example '{\"mode\":\"dark\"}', '\"dark\"', true, or null.\n");
    exit(1);
}

try {
    $lookupValue = json_decode($jsonValue, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "json_value must be valid JSON: {$exception->getMessage()}\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForJsonValueOperatorPointLookup(
    'wp_options',
    'option_value',
    $jsonPath,
    $lookupValue,
);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedJsonFragment($jsonPath, $lookupValue, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'jsonPath' => $jsonPath,
    'lookupValue' => $lookupValue,
    'wpOptionsJsonValueOperatorIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
