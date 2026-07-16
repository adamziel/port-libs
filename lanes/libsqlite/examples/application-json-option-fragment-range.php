<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$jsonPath = $argv[2] ?? null;
$lower = $argv[3] ?? null;
$upper = $argv[4] ?? null;
$limit = isset($argv[5]) ? (int) $argv[5] : 100;
$upperInclusive = filter_var($argv[6] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($databasePath === null || $jsonPath === null || $lower === null || $upper === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-json-option-fragment-range.php path/to/application.sqlite json_path lower_json|- upper_json|- [limit] [upper_inclusive]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(option_value -> 'key'). JSON null is not a range bound in this bounded helper; use - to omit one side.\n");
    exit(1);
}

$parseBound = static function (string $value, string $label): mixed {
    if ($value === '-') {
        return null;
    }

    try {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "{$label} bound must be valid JSON or -: {$exception->getMessage()}\n");
        exit(1);
    }

    if ($decoded === null) {
        fwrite(STDERR, "{$label} bound cannot be JSON null; use - to omit that side of the range.\n");
        exit(1);
    }

    return $decoded;
};

$lowerBound = $parseBound($lower, 'Lower');
$upperBound = $parseBound($upper, 'Upper');
$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForJsonValueOperatorRangeLookup(
    'wp_options',
    'option_value',
    $jsonPath,
    $lowerBound,
    $upperBound,
    $upperInclusive,
);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedJsonFragmentRange($jsonPath, $lowerBound, $upperBound, $limit, $upperInclusive),
);

echo json_encode([
    'path' => $databasePath,
    'jsonPath' => $jsonPath,
    'lowerInclusive' => $lowerBound,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'wpOptionsJsonValueOperatorRangeIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
