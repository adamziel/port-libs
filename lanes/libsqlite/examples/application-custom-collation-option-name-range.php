<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$lowerInclusive = $argv[2] ?? null;
$upperBound = $argv[3] ?? null;
$collationName = strtoupper($argv[4] ?? 'WPSLUG');
$upperInclusive = in_array('--inclusive', $argv, true);
if ($databasePath === null || ($lowerInclusive === null && $upperBound === null)) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-custom-collation-option-name-range.php path/to/application.sqlite [lower|null] [upper|null] [WPSLUG|WPCASE|BACKWARDS] [--inclusive]\n");
    exit(1);
}

$lowerInclusive = $lowerInclusive === 'null' ? null : $lowerInclusive;
$upperBound = $upperBound === 'null' ? null : $upperBound;

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
$options = $database->keyValueRowsByIndexedNameRangeWithCollation(
    $lowerInclusive,
    $upperBound,
    $collationName,
    $collations[$collationName],
    null,
    $upperInclusive,
);

echo json_encode([
    'path' => $databasePath,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'collationName' => $collationName,
    'options' => array_map(
        static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
        $options,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
