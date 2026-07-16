<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonExtract;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settingsJson = '{"plugin":{"enabled":true,"title":"Cache","rules":[{"name":"seo"},{"name":"cache"}]}}';
$settingsJsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => true,
        'title' => 'Cache',
        'rules' => [
            ['name' => 'seo'],
            ['name' => 'cache'],
        ],
    ],
]));

$jsonbRule = SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $settingsJsonb, '$.plugin.rules[#-1]');
$jsonbSummary = SQLiteJsonExtract::extractJsonArgumentSqlFunction(
    'jsonb_extract',
    $settingsJson,
    '$.plugin.title',
    '$.plugin.enabled',
    '$.plugin.missing',
);

echo json_encode([
    'checks' => [
        'jsonExtractRuleWrapped' => SQLiteJsonConstructor::jsonArray(
            SQLiteJsonExtract::extractJsonArgumentSqlFunction('json_extract', $settingsJson, '$.plugin.rules[#-1]'),
        ),
        'jsonbExtractRuleWrapped' => SQLiteJsonConstructor::jsonArray($jsonbRule),
        'jsonbExtractRuleHex' => $jsonbRule instanceof SQLiteBlobValue ? bin2hex($jsonbRule->bytes) : null,
        'jsonbExtractRuleDecoded' => $jsonbRule instanceof SQLiteBlobValue ? SQLiteJsonB::decode($jsonbRule->bytes) : null,
        'jsonbExtractSummaryWrapped' => SQLiteJsonConstructor::jsonArray($jsonbSummary),
        'jsonbExtractSummaryDecoded' => $jsonbSummary instanceof SQLiteBlobValue ? SQLiteJsonB::decode($jsonbSummary->bytes) : null,
        'scalarArgumentsWrapped' => SQLiteJsonConstructor::jsonArray(
            SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $settingsJson, '$.plugin.title'),
            SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $settingsJson, '$.plugin.enabled'),
            SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $settingsJson, '$.plugin.missing'),
        ),
    ],
    'applicationUse' => 'Local-only wp_options constructor diagnostics that preserve SQLite json_extract() JSON subtype arguments and jsonb_extract() JSONB blob arguments before copied plugin settings are imported.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
