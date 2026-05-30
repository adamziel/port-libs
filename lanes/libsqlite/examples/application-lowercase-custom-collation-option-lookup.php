<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$optionName = $argv[2] ?? null;
$collationName = strtoupper($argv[3] ?? 'WPSLUG');
if ($databasePath === null || $optionName === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-lowercase-custom-collation-option-lookup.php path/to/application.sqlite option_name [WPSLUG]\n");
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
$options = $database->keyValueRowsByIndexedLowercaseNameWithCollation(
    $optionName,
    $collationName,
    $collations[$collationName],
);

echo json_encode([
    'path' => $databasePath,
    'optionName' => $optionName,
    'collationName' => $collationName,
    'indexShape' => 'CREATE INDEX ... ON wp_options(lower(option_name) COLLATE ' . $collationName . ')',
    'options' => array_map(
        static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
        $options,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
