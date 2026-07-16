<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$loadPolicy = $argv[2] ?? 'no';
$bound = static fn (?string $value, ?string $default): ?string => $value === null ? $default : ($value === '-' ? null : $value);
$lowerInclusive = $bound($argv[3] ?? null, '_cache_');
$upperBound = $bound($argv[4] ?? null, '_cache`');
$limit = isset($argv[5]) ? (int) $argv[5] : 100;
$upperInclusive = filter_var($argv[6] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-load-policy-setting-key-range.php path/to/application.sqlite [load_policy] [lower_inclusive|-] [upper_bound|-] [limit] [upper_inclusive]\n");
    fwrite(STDERR, "At least one key_name range bound is required; use - to omit only one side.\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForPrefixRangeLookup(
    'app_settings',
    ['load_policy' => $loadPolicy],
    'key_name',
    $lowerInclusive,
    $upperBound,
    $upperInclusive,
);
$settings = array_map(
    static fn (SQLiteKeyValueRow $setting): array => $setting->toArray(),
    $database->keyValueRowsByIndexedLoadPolicyAndNameRange($loadPolicy, $lowerInclusive, $upperBound, $limit, $upperInclusive),
);

echo json_encode([
    'path' => $databasePath,
    'load_policy' => $loadPolicy,
    'lowerInclusive' => $lowerInclusive,
    'upperBound' => $upperBound,
    'upperInclusive' => $upperInclusive,
    'appSettingsLoadPolicyKeyNameRangeIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'settings' => $settings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
