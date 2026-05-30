<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$autoload = $argv[2] ?? 'no';
$lowerInclusive = $argv[3] ?? '_transient_';
$upperBound = $argv[4] ?? '_transient`';
$collationName = strtoupper($argv[5] ?? 'WPSLUG');
$limit = isset($argv[6]) ? (int) $argv[6] : 100;
$upperInclusive = in_array('--inclusive', $argv, true);
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-custom-collation-autoload-option-name-range.php path/to/application.sqlite [autoload] [lower|null] [upper|null] [WPSLUG|WPCASE|BACKWARDS] [limit] [--inclusive]\n");
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
$options = $database->keyValueRowsByIndexedNameRangeWithPrefixAndCollation(
    ['autoload' => $autoload],
    $lowerInclusive,
    $upperBound,
    $collationName,
    $collations[$collationName],
    $limit,
    $upperInclusive,
);

echo json_encode([
    'path' => $databasePath,
    'autoload' => $autoload,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'collationName' => $collationName,
    'limit' => $limit,
    'options' => array_map(
        static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
        $options,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
