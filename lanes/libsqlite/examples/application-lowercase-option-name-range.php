<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$bound = static fn (?string $value, ?string $default): ?string => $value === null ? $default : ($value === '-' ? null : $value);
$lowerInclusive = $bound($argv[2] ?? null, '_transient_');
$upperBound = $bound($argv[3] ?? null, '_transient`');
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
$upperInclusive = filter_var($argv[5] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-lowercase-option-name-range.php path/to/application.sqlite [lower_inclusive|-] [upper_bound|-] [limit] [upper_inclusive]\n");
    fwrite(STDERR, "At least one lower(option_name) range bound is required; use - to omit only one side.\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForLowercaseRangeLookup('wp_options', 'option_name', $lowerInclusive, $upperBound, $upperInclusive);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedLowercaseNameRange($lowerInclusive, $upperBound, $limit, $upperInclusive),
);

echo json_encode([
    'path' => $databasePath,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'wpOptionsLowerOptionNameRangeIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
