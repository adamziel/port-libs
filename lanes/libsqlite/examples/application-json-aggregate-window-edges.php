<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionRows = [
    ['siteurl', 'https://example.test', 'autoload', 1],
    ['home', 'https://example.test/home', 'autoload', 1],
    ['plugin_rules', new SQLiteJsonSubtypeValue('[{"plugin":"seo"},{"plugin":"cache"}]'), 'plugin', 1],
    ['plugin_summary', new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2, 'ok' => true])), 'plugin', 0],
    ['theme_mods', 'twentytwentyfive', 'theme', 1],
];

$arrayRows = [];
$objectRows = [];
foreach ($optionRows as [$name, $value, $bucket, $include]) {
    $arrayRows[] = [$value, $bucket, $include];
    $objectRows[] = [$name, $value, $bucket, $include];
}

$jsonbTies = SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsSqlFunction(
    'jsonb_group_array',
    $arrayRows,
    1,
    1,
    'TIES',
);

echo json_encode([
    'arrayWindowNoOthers' => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, 1),
    'arrayWindowExcludeGroup' => SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows($arrayRows, 1, 1, 'GROUP'),
    'arrayWindowExcludeTiesJsonbDecoded' => array_map(
        static fn (SQLiteBlobValue $frame): mixed => SQLiteJsonB::decode($frame->bytes),
        $jsonbTies,
    ),
    'objectWindowExcludeCurrent' => SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($objectRows, 1, 1, 'CURRENT ROW'),
    'objectWindowExcludeGroupJsonbDecoded' => array_map(
        static fn (SQLiteBlobValue $frame): mixed => SQLiteJsonB::decode($frame->bytes),
        SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsSqlFunction('JSONB_GROUP_OBJECT', $objectRows, 1, 1, 'GROUP'),
    ),
    'applicationUse' => 'Copied wp_options import summary using JSON aggregate window frames with FILTER-like include flags and SQLite EXCLUDE CURRENT ROW/GROUP/TIES peer handling without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
