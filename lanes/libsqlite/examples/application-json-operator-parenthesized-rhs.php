<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteOptionRow;

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
$fragmentPath = static fn (string $expression): ?string => SQLiteCreateIndex::firstJsonValueOperatorExpression(
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
        'wp_options_json_parenthesized_cache',
        'wp_options',
        3,
        "CREATE INDEX wp_options_json_parenthesized_cache ON wp_options(option_value ->> ('cache')) WHERE option_value IS NOT NULL",
    ], 2),
    $schemaCell([
        'index',
        'wp_options_json_parenthesized_slot',
        'wp_options',
        4,
        'CREATE INDEX wp_options_json_parenthesized_slot ON wp_options(option_value ->> (1)) WHERE option_value IS NOT NULL',
    ], 3),
    $schemaCell([
        'index',
        'wp_options_json_parenthesized_fragment',
        'wp_options',
        5,
        "CREATE INDEX wp_options_json_parenthesized_fragment ON wp_options(option_value -> ('settings.v1')) WHERE option_value IS NOT NULL",
    ], 4),
], $pageSize, 100, $makeFirstPage(5));

$tablePage = SQLiteTableLeafPage::assemble([
    $schemaCell([null, 'plugin_parenthesized_cache', '{"cache":"hit"}', 'no'], 1),
    $schemaCell([null, 'plugin_parenthesized_slot', '["zero","one"]', 'no'], 2),
    $schemaCell([null, 'plugin_parenthesized_fragment', '{"settings.v1":{"mode":"dark"}}', 'no'], 3),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . SQLiteIndexLeafPage::assemble([$indexCell(['hit', 1])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['one', 2])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['{"mode":"dark"}', 3])], $pageSize),
);

$matches = [
    'cache' => $database->optionRowsByIndexedJsonOptionValue('$.cache', 'hit'),
    'slot' => $database->optionRowsByIndexedJsonOptionValue('$[1]', 'one'),
    'fragment' => $database->optionRowsByIndexedJsonOptionFragment('$."settings.v1"', ['mode' => 'dark']),
];

echo json_encode([
    'applicationUse' => 'Preflight copied wp_options JSON operator indexes whose RHS scalar constants are wrapped in SQL parentheses without requiring the SQLite extension.',
    'normalizedExpressionPaths' => [
        'cache' => $textPath("option_value ->> ('cache')"),
        'slot' => $textPath('option_value ->> (1)'),
        'fragment' => $fragmentPath("option_value -> ('settings.v1')"),
        'nestedMin' => $textPath("option_value ->> ((min('seo', 'cache')))"),
        'expressionUnsupported' => $textPath('option_value ->> (1 + 1)'),
    ],
    'nativeRootPages' => [
        'cache' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.cache', 'hit'),
        'slot' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$[1]', 'one'),
        'fragment' => $database->indexRootPageForJsonValueOperatorPointLookup('wp_options', 'option_value', '$."settings.v1"', ['mode' => 'dark']),
    ],
    'matches' => array_map(
        static fn (array $options): array => array_map(static fn (SQLiteOptionRow $option): string => $option->optionName, $options),
        $matches,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
