<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$jsonPath = $argv[2] ?? null;
$value = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
if ($databasePath === null || $jsonPath === null || $value === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-json-escaped-label-option-value.php path/to/application.sqlite json_path json_scalar [limit]\n");
    fwrite(STDERR, "Reads JSON expression indexes for option_value keys that require SQLite JSON path escaping, such as quoted labels, embedded quotes, or hex/backslash escapes.\n");
    exit(1);
}

$lookupValue = match (strtolower($value)) {
    'true' => true,
    'false' => false,
    'null' => null,
    default => preg_match('/^[+-]?\d+$/', $value) === 1 ? (int) $value : $value,
};

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', $jsonPath, $lookupValue);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedJsonValue($jsonPath, $lookupValue, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'jsonPath' => $jsonPath,
    'lookupValue' => $lookupValue,
    'wpOptionsJsonExtractIndexRootPage' => $indexRootPage,
    'jsonPathInput' => 'SQLite JSON path with escaped object-label support',
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
