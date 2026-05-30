<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$lower = $argv[2] ?? null;
$upper = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
$upperInclusive = isset($argv[5]) && in_array(strtolower($argv[5]), ['1', 'true', 'yes', 'inclusive'], true);
if ($databasePath === null || $lower === null || $upper === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-option-value-integer-range.php path/to/application.sqlite lower_integer|- upper_integer|- [limit] [upper_inclusive]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(CAST(option_value AS INTEGER)).\n");
    exit(1);
}

$parseBound = static function (string $value, string $label): ?int {
    if ($value === '-') {
        return null;
    }
    if (!preg_match('/^[+-]?\d+$/', $value)) {
        fwrite(STDERR, "{$label} bound must be '-' or a base-10 integer literal.\n");
        exit(1);
    }

    return (int) $value;
};

$lowerBound = $parseBound($lower, 'Lower');
$upperBound = $parseBound($upper, 'Upper');
$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForIntegerCastRangeLookup(
    'wp_options',
    'option_value',
    $lowerBound,
    $upperBound,
    $upperInclusive,
);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedIntegerValueRange($lowerBound, $upperBound, $limit, $upperInclusive),
);

echo json_encode([
    'path' => $databasePath,
    'lowerInclusive' => $lowerBound,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'wpOptionsOptionValueIntegerRangeIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
