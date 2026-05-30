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
    'CREATE INDEX fixture ON wp_options(' . $expression . ') WHERE option_value IS NOT NULL',
)?->path;

$schemaPage = SQLiteTableLeafPage::assemble([
    $schemaCell([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ], 1),
    $schemaCell([
        'index',
        'wp_options_json_quote_null',
        'wp_options',
        3,
        'CREATE INDEX wp_options_json_quote_null ON wp_options(option_value ->> json_quote(NULL)) WHERE option_value IS NOT NULL',
    ], 2),
    $schemaCell([
        'index',
        'wp_options_json_quote_integer',
        'wp_options',
        4,
        'CREATE INDEX wp_options_json_quote_integer ON wp_options(option_value ->> json_quote(123)) WHERE option_value IS NOT NULL',
    ], 3),
    $schemaCell([
        'index',
        'wp_options_json_quote_real',
        'wp_options',
        5,
        'CREATE INDEX wp_options_json_quote_real ON wp_options(option_value ->> json_quote(1.25)) WHERE option_value IS NOT NULL',
    ], 4),
], $pageSize, 100, $makeFirstPage(5));

$tablePage = SQLiteTableLeafPage::assemble([
    $schemaCell([null, 'plugin_json_quote_null_settings', '{"null":"json-null"}', 'no'], 1),
    $schemaCell([null, 'plugin_json_quote_integer_settings', '{"123":"integer-label"}', 'no'], 2),
    $schemaCell([null, 'plugin_json_quote_real_settings', '{"1.25":"real-label"}', 'no'], 3),
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
    'applicationUse' => 'Preflight copied wp_options JSON operator indexes whose RHS constants use SQLite json_quote() numeric and NULL rendering without requiring the SQLite extension.',
    'normalizedExpressionPaths' => [
        'jsonNull' => $textPath('option_value ->> json_quote(NULL)'),
        'jsonInteger' => $textPath('option_value ->> json_quote(123)'),
        'jsonReal' => $textPath('option_value ->> json_quote(1.25)'),
        'directQuotedTextUnsupported' => $textPath("option_value ->> json_quote('plugin')"),
        'rawBlobUnsupported' => $textPath("option_value ->> json_quote(X'414243')"),
        'invalidArityUnsupported' => $textPath('option_value ->> json_quote(1, 2)'),
    ],
    'nativeRootPages' => [
        'jsonNull' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.null', 'json-null'),
        'jsonInteger' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."123"', 'integer-label'),
        'jsonReal' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."1.25"', 'real-label'),
    ],
    'matches' => array_map(
        static fn (array $options): array => array_map(static fn (SQLiteKeyValueRow $option): string => $option->optionName, $options),
        $matches,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
