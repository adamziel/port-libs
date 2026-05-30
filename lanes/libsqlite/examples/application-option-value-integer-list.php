<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$valueList = $argv[2] ?? null;
$limit = isset($argv[3]) ? (int) $argv[3] : 100;
if ($databasePath === null || $valueList === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-option-value-integer-list.php path/to/application.sqlite integer_value[,integer_value...] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(CAST(option_value AS INTEGER)).\n");
    exit(1);
}

$values = [];
foreach (explode(',', $valueList) as $value) {
    $value = trim($value);
    if ($value === '' || !preg_match('/^[+-]?\d+$/', $value)) {
        fwrite(STDERR, "Integer value lists may only contain base-10 integer literals separated by commas.\n");
        exit(1);
    }
    $values[] = (int) $value;
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForIntegerCastInLookup('wp_options', 'option_value', $values);
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRowsByIndexedIntegerOptionValues($values, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'integerValues' => $values,
    'wpOptionsOptionValueIntegerInListIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
