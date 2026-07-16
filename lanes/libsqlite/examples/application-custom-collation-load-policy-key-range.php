<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$loadPolicy = $argv[2] ?? 'no';
$lowerInclusive = $argv[3] ?? '_cache_';
$upperBound = $argv[4] ?? '_cache`';
$collationName = strtoupper($argv[5] ?? 'APPSLUG');
$limit = isset($argv[6]) ? (int) $argv[6] : 100;
$upperInclusive = in_array('--inclusive', $argv, true);
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-custom-collation-load-policy-key-range.php path/to/application.sqlite [load_policy] [lower|null] [upper|null] [APPSLUG|APPCASE|BACKWARDS] [limit] [--inclusive]\n");
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
    'APPSLUG' => static function (string $left, string $right) use ($asciiLower): int {
        return strcmp(str_replace('_', '-', $asciiLower($left)), str_replace('_', '-', $asciiLower($right)));
    },
    'APPCASE' => static function (string $left, string $right) use ($asciiLower): int {
        return strcmp($asciiLower($left), $asciiLower($right));
    },
    'BACKWARDS' => static fn (string $left, string $right): int => strcmp(strrev($left), strrev($right)),
];

if (!isset($collations[$collationName])) {
    fwrite(STDERR, "Unknown example collation: {$collationName}\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$settings = $database->keyValueRowsByIndexedNameRangeWithPrefixAndCollation(
    ['load_policy' => $loadPolicy],
    $lowerInclusive,
    $upperBound,
    $collationName,
    $collations[$collationName],
    $limit,
    $upperInclusive,
);

echo json_encode([
    'path' => $databasePath,
    'load_policy' => $loadPolicy,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'collationName' => $collationName,
    'limit' => $limit,
    'settings' => array_map(
        static fn (SQLiteKeyValueRow $setting): array => $setting->toArray(),
        $settings,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
