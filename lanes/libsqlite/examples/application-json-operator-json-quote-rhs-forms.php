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
        'app_settings_json_quote_null',
        'app_settings',
        3,
        'CREATE INDEX app_settings_json_quote_null ON app_settings(key_value ->> json_quote(NULL)) WHERE key_value IS NOT NULL',
    ], 2),
    $schemaCell([
        'index',
        'app_settings_json_quote_integer',
        'app_settings',
        4,
        'CREATE INDEX app_settings_json_quote_integer ON app_settings(key_value ->> json_quote(123)) WHERE key_value IS NOT NULL',
    ], 3),
    $schemaCell([
        'index',
        'app_settings_json_quote_real',
        'app_settings',
        5,
        'CREATE INDEX app_settings_json_quote_real ON app_settings(key_value ->> json_quote(1.25)) WHERE key_value IS NOT NULL',
    ], 4),
], $pageSize, 100, $makeFirstPage(5));

$tablePage = SQLiteTableLeafPage::assemble([
    $schemaCell([null, 'module_json_quote_null_settings', '{"null":"json-null"}', 'no'], 1),
    $schemaCell([null, 'module_json_quote_integer_settings', '{"123":"integer-label"}', 'no'], 2),
    $schemaCell([null, 'module_json_quote_real_settings', '{"1.25":"real-label"}', 'no'], 3),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . SQLiteIndexLeafPage::assemble([$indexCell(['json-null', 1])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['integer-label', 2])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['real-label', 3])], $pageSize),
);

$matches = [
    'jsonNull' => $database->keyValueRowsByIndexedJsonValue('$.null', 'json-null'),
    'jsonInteger' => $database->keyValueRowsByIndexedJsonValue('$."123"', 'integer-label'),
    'jsonReal' => $database->keyValueRowsByIndexedJsonValue('$."1.25"', 'real-label'),
];

echo json_encode([
    'applicationUse' => 'Preflight copied app_settings JSON operator indexes whose RHS constants use SQLite json_quote() numeric and NULL rendering without requiring the SQLite extension.',
    'normalizedExpressionPaths' => [
        'jsonNull' => $textPath('key_value ->> json_quote(NULL)'),
        'jsonInteger' => $textPath('key_value ->> json_quote(123)'),
        'jsonReal' => $textPath('key_value ->> json_quote(1.25)'),
        'directQuotedTextUnsupported' => $textPath("key_value ->> json_quote('module')"),
        'rawBlobUnsupported' => $textPath("key_value ->> json_quote(X'414243')"),
        'invalidArityUnsupported' => $textPath('key_value ->> json_quote(1, 2)'),
    ],
    'nativeRootPages' => [
        'jsonNull' => $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$.null', 'json-null'),
        'jsonInteger' => $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$."123"', 'integer-label'),
        'jsonReal' => $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$."1.25"', 'real-label'),
    ],
    'matches' => array_map(
        static fn (array $settings): array => array_map(static fn (SQLiteKeyValueRow $setting): string => $setting->keyName, $settings),
        $matches,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
