<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 4096;
$makeFirstPage = static function (int $databasePageCount) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databasePageCount), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode(
    $rowId,
    SQLiteRecord::encode($values),
);
$indexCell = static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values));
$textPath = static fn (string $expression): ?string => SQLiteCreateIndex::firstJsonTextOperatorExpression(
    'CREATE INDEX fixture ON app_settings(' . $expression . ') WHERE key_value IS NOT NULL',
)?->path;

$schemaPage = SQLiteTableLeafPage::assemble([
    $schemaCell([
        'table',
        'app_settings',
        'app_settings',
        2,
        'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)',
    ], 1),
    $schemaCell([
        'index',
        'app_settings_json_min_cache',
        'app_settings',
        3,
        "CREATE INDEX app_settings_json_min_cache ON app_settings(key_value ->> min('search', 'cache')) WHERE key_value IS NOT NULL",
    ], 2),
    $schemaCell([
        'index',
        'app_settings_json_max_module_enabled',
        'app_settings',
        4,
        "CREATE INDEX app_settings_json_max_module_enabled ON app_settings(key_value ->> max('module.enabled', 'module.disabled')) WHERE key_value IS NOT NULL",
    ], 3),
    $schemaCell([
        'index',
        'app_settings_json_min_slot',
        'app_settings',
        5,
        'CREATE INDEX app_settings_json_min_slot ON app_settings(key_value ->> min(2, 1)) WHERE key_value IS NOT NULL',
    ], 4),
], $pageSize, 100, $makeFirstPage(5));

$tablePage = SQLiteTableLeafPage::assemble([
    $schemaCell([null, 'module_min_cache_settings', '{"cache":"hit"}', 'no'], 1),
    $schemaCell([null, 'module_max_enabled_settings', '{"module.enabled":"yes"}', 'no'], 2),
    $schemaCell([null, 'module_min_slot_settings', '["zero","one","two"]', 'no'], 3),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . SQLiteIndexLeafPage::assemble([$indexCell(['hit', 1])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['yes', 2])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['one', 3])], $pageSize),
);

$matches = [
    'minCache' => $database->keyValueRowsByIndexedJsonValue('$.cache', 'hit'),
    'maxModuleEnabled' => $database->keyValueRowsByIndexedJsonValue('$."module.enabled"', 'yes'),
    'minSlot' => $database->keyValueRowsByIndexedJsonValue('$[1]', 'one'),
];

echo json_encode([
    'applicationUse' => 'Preflight copied app_settings JSON operator indexes whose RHS constants use reduced SQLite min()/max() string or numeric literals without requiring the SQLite extension.',
    'normalizedExpressionPaths' => [
        'minCache' => $textPath("key_value ->> min('search', 'cache')"),
        'maxModuleEnabled' => $textPath("key_value ->> max('module.enabled', 'module.disabled')"),
        'minSlot' => $textPath('key_value ->> min(2, 1)'),
        'mixedTypeUnsupported' => $textPath("key_value ->> min('1', 2)"),
        'singleArgumentUnsupported' => $textPath('key_value ->> max(2)'),
    ],
    'nativeRootPages' => [
        'minCache' => $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$.cache', 'hit'),
        'maxModuleEnabled' => $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$."module.enabled"', 'yes'),
        'minSlot' => $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$[1]', 'one'),
    ],
    'matches' => array_map(
        static fn (array $settings): array => array_map(static fn (SQLiteKeyValueRow $setting): string => $setting->keyName, $settings),
        $matches,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
