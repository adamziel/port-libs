<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$autoload = $argv[2] ?? 'no';
$bound = static fn (?string $value, ?string $default): ?string => $value === null ? $default : ($value === '-' ? null : $value);
$lowerInclusive = $bound($argv[3] ?? null, '_transient_');
$upperBound = $bound($argv[4] ?? null, '_transient`');
$limit = isset($argv[5]) ? (int) $argv[5] : 100;
$upperInclusive = filter_var($argv[6] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-autoloaded-option-name-range.php path/to/application.sqlite [autoload] [lower_inclusive|-] [upper_bound|-] [limit] [upper_inclusive]\n");
    fwrite(STDERR, "At least one option_name range bound is required; use - to omit only one side.\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForPrefixRangeLookup(
    'wp_options',
    ['autoload' => $autoload],
    'option_name',
    $lowerInclusive,
    $upperBound,
    $upperInclusive,
);
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRowsByIndexedAutoloadAndNameRange($autoload, $lowerInclusive, $upperBound, $limit, $upperInclusive),
);

echo json_encode([
    'path' => $databasePath,
    'autoload' => $autoload,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'wpOptionsAutoloadOptionNameRangeIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
