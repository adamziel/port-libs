<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$arrayPath = $argv[2] ?? null;
$value = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
if ($databasePath === null || $arrayPath === null || $value === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-json-array-option-value.php path/to/application.sqlite '$.rules[0].enabled' json_scalar [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(json_extract(option_value, '$.rules[0].enabled')).\n");
    exit(1);
}

$lookupValue = match (strtolower($value)) {
    'true' => true,
    'false' => false,
    'null' => null,
    default => preg_match('/^[+-]?\d+$/', $value) === 1 ? (int) $value : $value,
};

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', $arrayPath, $lookupValue);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedJsonValue($arrayPath, $lookupValue, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'jsonPath' => $arrayPath,
    'lookupValue' => $lookupValue,
    'wpOptionsJsonArrayIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
