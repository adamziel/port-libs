<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$value = $argv[2] ?? null;
$limit = isset($argv[3]) ? (int) $argv[3] : 100;
if ($databasePath === null || $value === null || !preg_match('/^[+-]?\d+$/', $value)) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-option-value-integer.php path/to/application.sqlite integer_value [limit]\n");
    exit(1);
}

$integerValue = (int) $value;
$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForIntegerCastPointLookup('wp_options', 'option_value', $integerValue);
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRowsByIndexedIntegerOptionValue($integerValue, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'integerValue' => $integerValue,
    'wpOptionsOptionValueIntegerIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
