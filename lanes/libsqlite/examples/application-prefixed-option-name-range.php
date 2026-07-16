<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$prefixJson = $argv[2] ?? null;
$bound = static fn (?string $value, ?string $default): ?string => $value === null ? $default : ($value === '-' ? null : $value);
$lowerInclusive = $bound($argv[3] ?? null, '_transient_');
$upperBound = $bound($argv[4] ?? null, '_transient`');
$limit = isset($argv[5]) ? (int) $argv[5] : 100;
$upperInclusive = filter_var($argv[6] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($databasePath === null || $prefixJson === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-prefixed-option-name-range.php path/to/application.sqlite '{\"autoload\":\"no\",\"option_value\":\"cached-feed\"}' [lower_inclusive|-] [upper_bound|-] [limit] [upper_inclusive]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(prefix_column..., option_name).\n");
    exit(1);
}

try {
    $equalityPrefix = json_decode($prefixJson, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "Equality prefix must be a JSON object: {$exception->getMessage()}\n");
    exit(1);
}
if (!is_array($equalityPrefix) || array_is_list($equalityPrefix) || $equalityPrefix === []) {
    fwrite(STDERR, "Equality prefix must be a non-empty JSON object.\n");
    exit(1);
}
foreach ($equalityPrefix as $columnName => $_value) {
    if (!is_string($columnName) || $columnName === '') {
        fwrite(STDERR, "Equality prefix column names must be non-empty strings.\n");
        exit(1);
    }
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForPrefixRangeLookup(
    'wp_options',
    $equalityPrefix,
    'option_name',
    $lowerInclusive,
    $upperBound,
    $upperInclusive,
);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedNameRangeWithPrefix($equalityPrefix, $lowerInclusive, $upperBound, $limit, $upperInclusive),
);

echo json_encode([
    'path' => $databasePath,
    'equalityPrefix' => $equalityPrefix,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'wpOptionsPrefixedNameRangeIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
