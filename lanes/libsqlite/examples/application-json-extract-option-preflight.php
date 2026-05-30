<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonExtract;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$inputs = [
    'strict_plugin_settings' => '{"plugin":{"enabled":true,"title":"Cache","priority":7,"rules":[{"name":"seo"},{"name":"cache"}]}}',
    'json5_plugin_settings' => "{plugin:{enabled:false,title:'Cache',priority:+7,rules:[{name:'seo'},],},}",
    'jsonb_plugin_settings' => new SQLiteBlobValue(SQLiteJsonB::encode([
        'plugin' => [
            'enabled' => true,
            'title' => 'Cache',
            'priority' => 7,
            'rules' => [
                ['name' => 'seo'],
                ['name' => 'cache'],
            ],
        ],
    ])),
    'sql_null_option_value' => null,
];

$checks = [];
foreach ($inputs as $name => $value) {
    $jsonbSummary = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $value, '$.plugin.title', '$.plugin.priority', '$.plugin.missing');
    $checks[] = [
        'name' => $name,
        'enabled' => SQLiteJsonExtract::extractSqlFunction('json_extract', $value, '$.plugin.enabled'),
        'title' => SQLiteJsonExtract::extractSqlFunction('json_extract', $value, '$.plugin.title'),
        'lastRule' => SQLiteJsonExtract::extractSqlFunction('json_extract', $value, '$.plugin.rules[#-1]'),
        'summaryJson' => SQLiteJsonExtract::extractSqlFunction('json_extract', $value, '$.plugin.title', '$.plugin.priority', '$.plugin.missing'),
        'jsonbSummaryHex' => $jsonbSummary instanceof SQLiteBlobValue ? bin2hex($jsonbSummary->bytes) : null,
        'jsonbSummaryDecoded' => $jsonbSummary instanceof SQLiteBlobValue ? SQLiteJsonB::decode($jsonbSummary->bytes) : null,
    ];
}

echo json_encode([
    'checks' => $checks,
    'applicationUse' => 'Local-only wp_options option_value extraction that mirrors SQLite json_extract()/jsonb_extract() SQL-dispatch result typing for copied strict JSON, JSON5 text, JSONB blobs, missing paths, and SQL NULL before plugin settings are imported.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
