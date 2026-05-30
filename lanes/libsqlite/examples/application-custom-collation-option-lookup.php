<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$optionName = $argv[2] ?? null;
$collationName = strtoupper($argv[3] ?? 'WPCASE');
if ($databasePath === null || $optionName === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-custom-collation-option-lookup.php path/to/application.sqlite option_name [WPCASE|BACKWARDS]\n");
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
$options = $database->optionRowsByIndexedNameWithCollation(
    $optionName,
    $collationName,
    $collations[$collationName],
);

echo json_encode([
    'path' => $databasePath,
    'optionName' => $optionName,
    'collationName' => $collationName,
    'options' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $options,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
