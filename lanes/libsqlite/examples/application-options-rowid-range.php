<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-options-rowid-range.php path/to/application.sqlite [lower|-] [upper|-] [limit] [upper_inclusive]\n");
    exit(1);
}

$parseOptionalInt = static function (?string $value): ?int {
    if ($value === null || $value === '-' || strcasecmp($value, 'null') === 0) {
        return null;
    }
    if (!preg_match('/^-?\d+$/', $value)) {
        throw new InvalidArgumentException("Expected integer rowid bound or '-': {$value}");
    }

    return (int) $value;
};

$lowerInclusive = $parseOptionalInt($argv[2] ?? null);
$upperBound = $parseOptionalInt($argv[3] ?? null);
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
$upperInclusive = isset($argv[5]) && in_array(strtolower($argv[5]), ['1', 'true', 'yes', 'inclusive'], true);

$database = SQLiteDatabase::fromFile($databasePath);
$optionsRootPage = $database->tableRootPage('wp_options');
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByRowIdRange($lowerInclusive, $upperBound, $limit, $upperInclusive),
);

echo json_encode([
    'path' => $databasePath,
    'wpOptionsRootPage' => $optionsRootPage,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
