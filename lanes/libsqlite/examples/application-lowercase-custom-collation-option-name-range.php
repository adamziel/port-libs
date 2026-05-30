<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$bound = static fn (?string $value, ?string $default): ?string => $value === null ? $default : ($value === '-' ? null : $value);
$lowerInclusive = $bound($argv[2] ?? null, 'plugin-');
$upperBound = $bound($argv[3] ?? null, 'plugin.');
$collationName = strtoupper($argv[4] ?? 'WPSLUG');
$limit = isset($argv[5]) ? (int) $argv[5] : 100;
$upperInclusive = filter_var($argv[6] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-lowercase-custom-collation-option-name-range.php path/to/application.sqlite [lower_inclusive|-] [upper_bound|-] [WPSLUG] [limit] [upper_inclusive]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(lower(option_name) COLLATE WPSLUG).\n");
    exit(1);
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
        $normalize = static fn (string $value): string => str_replace('_', '-', $asciiLower($value));

        return strcmp($normalize($left), $normalize($right));
    },
];

if (!isset($collations[$collationName])) {
    fwrite(STDERR, "Unknown example collation: {$collationName}\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$options = $database->optionRowsByIndexedLowercaseNameRangeWithCollation(
    $lowerInclusive,
    $upperBound,
    $collationName,
    $collations[$collationName],
    $limit,
    $upperInclusive,
);

echo json_encode([
    'path' => $databasePath,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'collationName' => $collationName,
    'indexShape' => 'CREATE INDEX ... ON wp_options(lower(option_name) COLLATE ' . $collationName . ')',
    'limit' => $limit,
    'options' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $options,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
