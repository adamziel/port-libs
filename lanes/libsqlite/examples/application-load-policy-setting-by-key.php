<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$loadPolicy = $argv[2] ?? null;
$keyName = $argv[3] ?? null;
if ($databasePath === null || $loadPolicy === null || $keyName === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-load-policy-setting-by-key.php path/to/application.sqlite load_policy key_name\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$compositeIndexRootPage = $database->indexRootPageForPointLookupColumns('app_settings', [
    'load_policy' => $loadPolicy,
    'key_name' => $keyName,
]);
$partialKeyNameIndexRootPage = $database->indexRootPageForPointLookupWithConstraints(
    'app_settings',
    'key_name',
    $keyName,
    ['load_policy' => $loadPolicy],
);

if ($compositeIndexRootPage !== null) {
    $lookupMode = 'composite-load_policy-key_name';
    $setting = $database->keyValueRowByIndexedLoadPolicyAndName($loadPolicy, $keyName);
} elseif ($partialKeyNameIndexRootPage !== null) {
    $lookupMode = 'partial-key_name-load_policy-equality';
    $setting = $database->keyValueRowByIndexedNameForLoadPolicy($keyName, $loadPolicy);
} else {
    throw new InvalidArgumentException('SQLite app_settings load_policy+key_name index is not present');
}

echo json_encode([
    'path' => $databasePath,
    'load_policy' => $loadPolicy,
    'keyName' => $keyName,
    'lookupMode' => $lookupMode,
    'appSettingsLoadPolicyKeyNameIndexRootPage' => $compositeIndexRootPage,
    'appSettingsPartialKeyNameLoadPolicyIndexRootPage' => $partialKeyNameIndexRootPage,
    'setting' => $setting?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
