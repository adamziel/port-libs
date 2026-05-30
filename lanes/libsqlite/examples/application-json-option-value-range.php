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
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-json-option-value-range.php path/to/application.sqlite json_path lower_json_scalar|- upper_json_scalar|- [limit] [upper_inclusive]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(json_extract(option_value, '$.key')).\n");
    fwrite(STDERR, "Use - to omit one side of the range. JSON null is not a range bound in this bounded helper.\n");
    exit(1);
}

$parseBound = static function (string $value, string $label): mixed {
    if ($value === '-') {
        return null;
    }

    return match (strtolower($value)) {
        'true' => true,
        'false' => false,
        'null' => (static function () use ($label): never {
            fwrite(STDERR, "{$label} bound cannot be JSON null; use - to omit that side of the range.\n");
            exit(1);
        })(),
        default => preg_match('/^[+-]?\d+$/', $value) === 1
            ? (int) $value
            : (preg_match('/^[+-]?(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?$/', $value) === 1 ? (float) $value : $value),
    };
};

$lowerBound = $parseBound($lower, 'Lower');
$upperBound = $parseBound($upper, 'Upper');
$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForJsonExtractRangeLookup(
    'wp_options',
    'option_value',
    $jsonPath,
    $lowerBound,
    $upperBound,
    $upperInclusive,
);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedJsonValueRange($jsonPath, $lowerBound, $upperBound, $limit, $upperInclusive),
);

echo json_encode([
    'path' => $databasePath,
    'jsonPath' => $jsonPath,
    'lowerInclusive' => $lowerBound,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'wpOptionsJsonExtractRangeIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
