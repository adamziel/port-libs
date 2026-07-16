<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;

$inputs = [
    'strict_settings_text' => '{"plugin":{"rules":[{"name":"seo"},{"name":"cache"}],"enabled":true}}',
    'json5_settings_text' => "{plugin:{rules:['seo','cache',],enabled:true,},}",
    'jsonb_settings_blob' => new SQLiteBlobValue(SQLiteJsonB::encode([
        'plugin' => [
            'rules' => [
                ['name' => 'seo'],
                ['name' => 'cache'],
            ],
            'enabled' => true,
        ],
    ])),
    'constructor_subtype' => new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject(
        'plugin',
        new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject(
            'rules',
            new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonArray('seo', 'cache')),
            'enabled',
            true,
        )),
    )),
];

$reports = [];
foreach ($inputs as $name => $value) {
    $normalEachRows = SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [$value, '$.plugin']);
    $normalTreeRows = SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$value, '$.plugin']);

    $reports[] = [
        'name' => $name,
        'normalEachRowCount' => count($normalEachRows),
        'normalTreeRowCount' => count($normalTreeRows),
        'nullPathEachRows' => SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [$value, null]),
        'nullPathTreeRows' => SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$value, null]),
    ];
}

echo json_encode([
    'scenario' => 'application-json-table-null-path',
    'reports' => $reports,
    'applicationUse' => 'Local-only wp_options diagnostics preserve SQLite JSON table-valued NULL-path behavior: json_each(option_value, NULL) and json_tree(option_value, NULL) return no rows instead of expanding copied plugin settings or throwing before import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
