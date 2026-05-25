<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$copiedOptions = [
    ['siteurl', 'https://example.test', 'yes'],
    ['blogname', 'Port Fixture', 'yes'],
    ['plugin_rules', new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'no'],
    ['plugin_queue', new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2, 'ok' => true])), 'no'],
    ['empty_option', null, 'no'],
];

$optionValues = [];
$autoloadSummary = [];
foreach ($copiedOptions as [$name, $value, $autoload]) {
    $optionValues[] = $value;
    $autoloadSummary[] = [$name, $autoload === 'yes'];
}

echo json_encode([
    'optionValueArray' => SQLiteJsonAggregate::jsonGroupArray($optionValues),
    'autoloadMap' => SQLiteJsonAggregate::jsonGroupObject($autoloadSummary),
    'wordpressUse' => 'Local-only wp_options import summary that mirrors SQLite json_group_array() and json_group_object() row aggregation for copied option values, JSON subtype fragments, JSONB blobs, booleans, and NULLs without requiring the SQLite extension.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
