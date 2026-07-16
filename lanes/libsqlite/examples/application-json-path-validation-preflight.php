<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteJsonPath;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 1024;
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
$extractPath = static fn (string $path): ?string => SQLiteCreateIndex::firstJsonExtractExpression(
    "CREATE INDEX fixture ON app_settings(json_extract(key_value, '{$path}')) WHERE key_value IS NOT NULL",
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
        'app_settings_json_empty_label',
        'app_settings',
        3,
        'CREATE INDEX app_settings_json_empty_label ON app_settings(key_value ->> \'$.""\') WHERE key_value IS NOT NULL',
    ], 2),
    $schemaCell([
        'index',
        'app_settings_json_bad_reverse',
        'app_settings',
        4,
        'CREATE INDEX app_settings_json_bad_reverse ON app_settings(key_value ->> \'$.module[#-]\') WHERE key_value IS NOT NULL',
    ], 3),
    $schemaCell([
        'index',
        'app_settings_json_bad_extract',
        'app_settings',
        5,
        'CREATE INDEX app_settings_json_bad_extract ON app_settings(json_extract(key_value, \'$.\')) WHERE key_value IS NOT NULL',
    ], 4),
], $pageSize, 100, $makeFirstPage(5));

$tablePage = SQLiteTableLeafPage::assemble([
    $schemaCell([null, 'module_empty_label_settings', '{"":"empty-label","module":["bad"]}', 'no'], 1),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . SQLiteIndexLeafPage::assemble([$indexCell(['empty-label', 1])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['bad', 1])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['bad-extract', 1])], $pageSize),
);

$matches = $database->keyValueRowsByIndexedJsonValue('$.""', 'empty-label');

echo json_encode([
    'applicationUse' => 'Preflight application settings JSON expression indexes and skip malformed SQLite JSON paths before trusting root pages.',
    'pathSyntax' => [
        'emptyQuotedLabel' => SQLiteJsonPath::isWellFormed('$.""'),
        'emptyBareLabel' => SQLiteJsonPath::isWellFormed('$.'),
        'badReverseIndex' => SQLiteJsonPath::isWellFormed('$.module[#-]'),
        'badHashDigits' => SQLiteJsonPath::isWellFormed('$.module[#9]'),
    ],
    'normalizedExpressionPaths' => [
        'emptyQuotedLabel' => $textPath('key_value ->> \'$.""\''),
        'badReverseIndex' => $textPath('key_value ->> \'$.module[#-]\''),
        'badJsonExtractPath' => $extractPath('$.'),
    ],
    'nativeRootPages' => [
        'emptyQuotedLabel' => $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$.""', 'empty-label'),
        'malformedModulePathSkipped' => $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$.module', 'bad'),
    ],
    'matches' => array_map(static fn (SQLiteKeyValueRow $setting): string => $setting->keyName, $matches),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
