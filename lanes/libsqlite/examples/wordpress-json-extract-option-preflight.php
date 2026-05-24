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
    $checks[] = [
        'name' => $name,
        'enabled' => SQLiteJsonExtract::extract($value, '$.plugin.enabled'),
        'title' => SQLiteJsonExtract::extract($value, '$.plugin.title'),
        'lastRule' => SQLiteJsonExtract::extract($value, '$.plugin.rules[#-1]'),
        'summaryJson' => SQLiteJsonExtract::extract($value, '$.plugin.title', '$.plugin.priority', '$.plugin.missing'),
    ];
}

echo json_encode([
    'checks' => $checks,
    'wordpressUse' => 'Local-only wp_options option_value extraction that mirrors SQLite json_extract() SQL-result typing for copied strict JSON, JSON5 text, JSONB blobs, missing paths, and SQL NULL before plugin settings are imported.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
