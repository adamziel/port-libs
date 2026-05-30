<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$jsonPath = $argv[2] ?? '$[#-1]';
$value = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
if ($databasePath === null || $value === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-json-last-array-option-value.php path/to/application.sqlite '$[#-1]' json_scalar [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(json_extract(option_value, '$[#-1]')) or option_value ->> -1.\n");
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
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRowsByIndexedJsonOptionValue($jsonPath, $lookupValue, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'jsonPath' => $jsonPath,
    'lookupValue' => $lookupValue,
    'wpOptionsJsonLastArrayIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
