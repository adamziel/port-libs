<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$jsonPath = $argv[2] ?? null;
$valueList = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
if ($databasePath === null || $jsonPath === null || $valueList === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-json-option-value-list.php path/to/application.sqlite json_path json_scalar[,json_scalar...] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(json_extract(option_value, '$.key')).\n");
    exit(1);
}

$values = array_values(array_filter(
    array_map(trim(...), explode(',', $valueList)),
    static fn (string $value): bool => $value !== '',
));
$lookupValues = array_map(
    static fn (string $value): mixed => match (strtolower($value)) {
        'true' => true,
        'false' => false,
        'null' => null,
        default => preg_match('/^[+-]?\d+$/', $value) === 1 ? (int) $value : $value,
    },
    $values,
);

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForJsonExtractInLookup('wp_options', 'option_value', $jsonPath, $lookupValues);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedJsonValues($jsonPath, $lookupValues, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'jsonPath' => $jsonPath,
    'lookupValues' => $lookupValues,
    'wpOptionsJsonExtractInListIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
