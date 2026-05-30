<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonPathIndexedUpdatePlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => '{"plugin":{"enabled":false,"version":1}}'],
    ['option_id' => 2, 'option_name' => 'plugin_beta', 'option_value' => '{"plugin":{"enabled":true,"version":2}}'],
];

$plan = SQLiteJsonPathIndexedUpdatePlan::plan(
    $rows,
    [
        ['name' => 'idx_plugin_enabled', 'path' => '$.plugin.enabled'],
        ['name' => 'idx_plugin_version', 'path' => '$.plugin.version'],
    ],
    [
        ['rowid' => 1, 'mutations' => [
            ['function' => 'json_set', 'path' => '$.plugin.enabled', 'value' => true],
            ['function' => 'json_set', 'path' => '$.plugin.version', 'value' => 3],
        ]],
    ],
);

echo json_encode([
    'changes' => $plan['changes'],
    'index_updates' => $plan['index_updates'],
    'updated_value' => $plan['after'][0]['option_value'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
