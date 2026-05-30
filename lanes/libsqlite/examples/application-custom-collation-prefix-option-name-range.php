<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$prefixJson = $argv[2] ?? null;
$bound = static fn (?string $value, ?string $default): ?string => $value === null ? $default : ($value === '-' ? null : $value);
$lowerInclusive = $bound($argv[3] ?? null, '_transient_');
$upperBound = $bound($argv[4] ?? null, '_transient`');
$collationName = strtoupper($argv[5] ?? 'WPSLUG');
$limit = isset($argv[6]) ? (int) $argv[6] : 100;
$upperInclusive = filter_var($argv[7] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($databasePath === null || $prefixJson === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-custom-collation-prefix-option-name-range.php path/to/application.sqlite '{\"option_value\":\"plugin_core\"}' [lower_inclusive|-] [upper_bound|-] [WPSLUG|WPCASE|BACKWARDS] [limit] [upper_inclusive]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(prefix_column COLLATE CUSTOM, option_name).\n");
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

$asciiLower = static function (string $value): string {
    $bytes = $value;
    $length = strlen($bytes);
    for ($i = 0; $i < $length; $i++) {
        $ord = ord($bytes[$i]);
        if ($ord >= 0x41 && $ord <= 0x5a) {
            $bytes[$i] = chr($ord + 0x20);
        }
    }

    return $bytes;
};

$collations = [
    'WPSLUG' => static function (string $left, string $right) use ($asciiLower): int {
        return strcmp(str_replace('_', '-', $asciiLower($left)), str_replace('_', '-', $asciiLower($right)));
    },
    'WPCASE' => static function (string $left, string $right) use ($asciiLower): int {
        return strcmp($asciiLower($left), $asciiLower($right));
    },
    'BACKWARDS' => static fn (string $left, string $right): int => strcmp(strrev($left), strrev($right)),
];

if (!isset($collations[$collationName])) {
    fwrite(STDERR, "Unknown example collation: {$collationName}\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$customCollations = [$collationName => $collations[$collationName]];
$indexRootPage = $database->indexRootPageForPrefixRangeLookupWithCollations(
    'wp_options',
    $equalityPrefix,
    'option_name',
    $lowerInclusive,
    $upperBound,
    $customCollations,
    $upperInclusive,
);
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRowsByIndexedNameRangeWithPrefixCollations(
        $equalityPrefix,
        $lowerInclusive,
        $upperBound,
        $customCollations,
        $limit,
        $upperInclusive,
    ),
);

echo json_encode([
    'path' => $databasePath,
    'equalityPrefix' => $equalityPrefix,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'collationName' => $collationName,
    'wpOptionsCustomPrefixNameRangeIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
