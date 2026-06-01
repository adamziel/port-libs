<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonPathIndexedUpdatePlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'module_alpha', 'key_value' => '{"module":{"enabled":false,"version":1}}'],
    ['setting_id' => 2, 'key_name' => 'module_beta', 'key_value' => '{"module":{"enabled":true,"version":2}}'],
];

$plan = SQLiteJsonPathIndexedUpdatePlan::plan(
    $rows,
    [
        ['name' => 'idx_module_enabled', 'path' => '$.module.enabled'],
        ['name' => 'idx_module_version', 'path' => '$.module.version'],
    ],
    [
        ['rowid' => 1, 'mutations' => [
            ['function' => 'json_set', 'path' => '$.module.enabled', 'value' => true],
            ['function' => 'json_set', 'path' => '$.module.version', 'value' => 3],
        ]],
    ],
);

echo json_encode([
    'changes' => $plan['changes'],
    'index_updates' => $plan['index_updates'],
    'updated_value' => $plan['after'][0]['key_value'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
