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
        'wp_options_json_min_cache',
        'wp_options',
        3,
        "CREATE INDEX wp_options_json_min_cache ON wp_options(option_value ->> min('seo', 'cache')) WHERE option_value IS NOT NULL",
    ], 2),
    $schemaCell([
        'index',
        'wp_options_json_max_plugin_enabled',
        'wp_options',
        4,
        "CREATE INDEX wp_options_json_max_plugin_enabled ON wp_options(option_value ->> max('plugin.enabled', 'plugin.disabled')) WHERE option_value IS NOT NULL",
    ], 3),
    $schemaCell([
        'index',
        'wp_options_json_min_slot',
        'wp_options',
        5,
        'CREATE INDEX wp_options_json_min_slot ON wp_options(option_value ->> min(2, 1)) WHERE option_value IS NOT NULL',
    ], 4),
], $pageSize, 100, $makeFirstPage(5));

$tablePage = SQLiteTableLeafPage::assemble([
    $schemaCell([null, 'plugin_min_cache_settings', '{"cache":"hit"}', 'no'], 1),
    $schemaCell([null, 'plugin_max_enabled_settings', '{"plugin.enabled":"yes"}', 'no'], 2),
    $schemaCell([null, 'plugin_min_slot_settings', '["zero","one","two"]', 'no'], 3),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . SQLiteIndexLeafPage::assemble([$indexCell(['hit', 1])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['yes', 2])], $pageSize)
    . SQLiteIndexLeafPage::assemble([$indexCell(['one', 3])], $pageSize),
);

$matches = [
    'minCache' => $database->optionRowsByIndexedJsonOptionValue('$.cache', 'hit'),
    'maxPluginEnabled' => $database->optionRowsByIndexedJsonOptionValue('$."plugin.enabled"', 'yes'),
    'minSlot' => $database->optionRowsByIndexedJsonOptionValue('$[1]', 'one'),
];

echo json_encode([
    'applicationUse' => 'Preflight copied wp_options JSON operator indexes whose RHS constants use reduced SQLite min()/max() string or numeric literals without requiring the SQLite extension.',
    'normalizedExpressionPaths' => [
        'minCache' => $textPath("option_value ->> min('seo', 'cache')"),
        'maxPluginEnabled' => $textPath("option_value ->> max('plugin.enabled', 'plugin.disabled')"),
        'minSlot' => $textPath('option_value ->> min(2, 1)'),
        'mixedTypeUnsupported' => $textPath("option_value ->> min('1', 2)"),
        'singleArgumentUnsupported' => $textPath('option_value ->> max(2)'),
    ],
    'nativeRootPages' => [
        'minCache' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.cache', 'hit'),
        'maxPluginEnabled' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."plugin.enabled"', 'yes'),
        'minSlot' => $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$[1]', 'one'),
    ],
    'matches' => array_map(
        static fn (array $options): array => array_map(static fn (SQLiteOptionRow $option): string => $option->optionName, $options),
        $matches,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
