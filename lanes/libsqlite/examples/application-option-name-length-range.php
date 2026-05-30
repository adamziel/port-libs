<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$lower = $argv[2] ?? null;
$upper = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
$upperInclusive = isset($argv[5]) && in_array(strtolower($argv[5]), ['1', 'true', 'yes', 'inclusive'], true);
if ($databasePath === null || $lower === null || $upper === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-option-name-length-range.php path/to/application.sqlite lower_length|- upper_length|- [limit] [upper_inclusive]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(length(option_name)).\n");
    exit(1);
}

$parseBound = static function (string $value, string $label): ?int {
    if ($value === '-') {
        return null;
    }
    if (!preg_match('/^\d+$/', $value)) {
        fwrite(STDERR, "{$label} bound must be '-' or a non-negative base-10 integer literal.\n");
        exit(1);
    }

    return (int) $value;
};

$lowerBound = $parseBound($lower, 'Lower');
$upperBound = $parseBound($upper, 'Upper');
$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForLengthRangeLookup(
    'wp_options',
    'option_name',
    $lowerBound,
    $upperBound,
    $upperInclusive,
);
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRowsByIndexedNameLengthRange($lowerBound, $upperBound, $limit, $upperInclusive),
);

echo json_encode([
    'path' => $databasePath,
    'lowerInclusive' => $lowerBound,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'wpOptionsLengthOptionNameRangeIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
