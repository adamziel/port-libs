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
use PortLibs\LibSqlite\SQLiteOptionRow;

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
    'CREATE INDEX fixture ON wp_options(' . $expression . ') WHERE option_value IS NOT NULL',
)?->path;
$extractPath = static fn (string $path): ?string => SQLiteCreateIndex::firstJsonExtractExpression(
    "CREATE INDEX fixture ON wp_options(json_extract(option_value, '{$path}')) WHERE option_value IS NOT NULL",
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
        'wp_options_json_empty_label',
        'wp_options',
        3,
        'CREATE INDEX wp_options_json_empty_label ON wp_options(option_value ->> \'$.""\') WHERE option_value IS NOT NULL',
    ], 2),
    $schemaCell([
        'index',
        'wp_options_json_bad_reverse',
        'wp_options',
        4,
        'CREATE INDEX wp_options_json_bad_reverse ON wp_options(option_value ->> \'$.plugin[#-]\') WHERE option_value IS NOT NULL',
    ], 3),
    $schemaCell([
        'index',
        'wp_options_json_bad_extract',
        'wp_options',
        5,
        'CREATE INDEX wp_options_json_bad_extract ON wp_options(json_extract(option_value, \'$.\')) WHERE option_value IS NOT NULL',
    ], 4),
], $pageSize, 100, $makeFirstPage(5));

$tablePage = SQLiteTableLeafPage::assemble([
    $schemaCell([null, 'plugin_empty_label_settings', '{"":"empty-label","plugin":["bad"]}', 'no'], 1),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . SQLiteIndexLeafPage::assemble([$indexCell(['empty-label', 1])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['bad', 1])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['bad-extract', 1])], $pageSize),
);

$matches = $database->optionRowsByIndexedJsonOptionValue('$.""', 'empty-label');

echo json_encode([
    'applicationUse' => 'Preflight copied wp_options JSON expression indexes and skip malformed SQLite JSON paths before trusting root pages.',
    'pathSyntax' => [
        'emptyQuotedLabel' => SQLiteJsonPath::isWellFormed('$.""'),
        'emptyBareLabel' => SQLiteJsonPath::isWellFormed('$.'),
        'badReverseIndex' => SQLiteJsonPath::isWellFormed('$.plugin[#-]'),
        'badHashDigits' => SQLiteJsonPath::isWellFormed('$.plugin[#9]'),
    ],
    'normalizedExpressionPaths' => [
        'emptyQuotedLabel' => $textPath('option_value ->> \'$.""\''),
        'badReverseIndex' => $textPath('option_value ->> \'$.plugin[#-]\''),
        'badJsonExtractPath' => $extractPath('$.'),
    ],
    'nativeRootPages' => [
        'emptyQuotedLabel' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.""', 'empty-label'),
        'malformedPluginPathSkipped' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.plugin', 'bad'),
    ],
    'matches' => array_map(static fn (SQLiteOptionRow $option): string => $option->optionName, $matches),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
