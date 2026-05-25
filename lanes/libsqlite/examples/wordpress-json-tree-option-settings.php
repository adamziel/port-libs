<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonbSettings = SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => true,
        'rules' => [
            ['name' => 'seo', 'enabled' => true],
            ['name' => 'cache', 'enabled' => false],
        ],
        'dotted.key' => 'quoted',
    ],
]);

$inputs = [
    'strict_settings_text' => '{"plugin":{"enabled":true,"title":"Cache","rules":[{"name":"seo"},{"name":"cache"}]},"priority":7}',
    'json5_settings_text' => "{plugin:{enabled:false,title:'Cache',rules:['seo','cache',],},priority:+7}",
    'jsonb_settings_blob' => new SQLiteBlobValue($jsonbSettings),
    'sql_null_option_value' => null,
];

$reports = [];
foreach ($inputs as $name => $value) {
    $reports[] = [
        'name' => $name,
        'rootRows' => SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $value),
        'pluginRows' => SQLiteJsonTree::jsonTree($value, '$.plugin'),
        'rulesRows' => SQLiteJsonTree::jsonTree($value, '$.plugin.rules'),
    ];
}

echo json_encode([
    'reports' => $reports,
    'wordpressUse' => 'Local-only wp_options option_value recursive expansion that mirrors bounded SQLite json_tree() rows for strict JSON, JSON5 text, JSONB blobs, missing paths, and SQL NULL before copied plugin settings are imported.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
