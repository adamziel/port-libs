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
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$indexCell = static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values));
$textPath = static fn (string $expression): ?string => SQLiteCreateIndex::firstJsonTextOperatorExpression(
    'CREATE INDEX fixture ON wp_options(' . $expression . ') WHERE option_value IS NOT NULL',
)?->path;
$valuePath = static fn (string $expression): ?string => SQLiteCreateIndex::firstJsonValueOperatorExpression(
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
        'wp_options_json_cache',
        'wp_options',
        3,
        "CREATE INDEX wp_options_json_cache ON wp_options(option_value ->> ('cache' COLLATE nocase)) WHERE option_value IS NOT NULL",
    ], 2),
    $schemaCell([
        'index',
        'wp_options_json_plugin_enabled',
        'wp_options',
        4,
        "CREATE INDEX wp_options_json_plugin_enabled ON wp_options(option_value ->> ('plugin.enabled' COLLATE binary)) WHERE option_value IS NOT NULL",
    ], 3),
], $pageSize, 100, $makeFirstPage(4));

$tablePage = SQLiteTableLeafPage::assemble([
    $schemaCell([null, 'plugin_cache_settings', '{"cache":"hit"}', 'no'], 1),
    $schemaCell([null, 'plugin_enabled_settings', '{"plugin.enabled":"yes"}', 'no'], 2),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . SQLiteIndexLeafPage::assemble([$indexCell(['hit', 1])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['yes', 2])], $pageSize),
);

$matches = [
    'cache' => $database->keyValueRowsByIndexedJsonValue('$.cache', 'hit'),
    'pluginEnabled' => $database->keyValueRowsByIndexedJsonValue('$."plugin.enabled"', 'yes'),
];

echo json_encode([
    'scenario' => 'application-json-operator-collate-rhs',
    'applicationUse' => 'Preflight copied wp_options JSON expression indexes whose -> and ->> RHS constants carry their own COLLATE clause, while the outer index collation remains separate, without requiring ext/sqlite.',
    'normalizedExpressionPaths' => [
        'cache' => $textPath("option_value ->> ('cache' COLLATE nocase)"),
        'pluginEnabled' => $textPath("option_value ->> ('plugin.enabled' COLLATE binary)"),
        'valueOperatorMetadata' => $valuePath("option_value -> ('plugin.enabled' COLLATE binary)"),
        'badMissingCollationName' => $textPath("option_value ->> ('cache' COLLATE)"),
    ],
    'nativeRootPages' => [
        'cache' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.cache', 'hit'),
        'pluginEnabled' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."plugin.enabled"', 'yes'),
    ],
    'matches' => array_map(
        static fn (array $options): array => array_map(static fn (SQLiteKeyValueRow $option): string => $option->optionName, $options),
        $matches,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
