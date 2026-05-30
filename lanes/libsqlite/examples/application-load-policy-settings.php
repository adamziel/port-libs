<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$loadPolicy = $argv[2] ?? 'yes';
$limit = isset($argv[3]) ? (int) $argv[3] : 100;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-load-policy-settings.php path/to/application.sqlite [load_policy] [limit]\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForPointLookup('app_settings', 'load_policy', $loadPolicy);
$settings = array_map(
    static fn (SQLiteKeyValueRow $setting): array => $setting->toArray(),
    $database->keyValueRowsByIndexedLoadPolicy($loadPolicy, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'load_policy' => $loadPolicy,
    'appSettingsLoadPolicyIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'settings' => $settings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
