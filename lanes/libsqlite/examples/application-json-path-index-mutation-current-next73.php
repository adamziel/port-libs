<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonPathIndexedUpdatePlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'module_alpha_settings', 'key_value' => '{"load_policy":true}', 'payload' => '{"module":{"slug":"alpha","enabled":false,"rank":1,"tags":["cache"],"meta":{"channel":"stable"}}}'],
    ['setting_id' => 2, 'key_name' => 'module_beta_settings', 'key_value' => '{"load_policy":true}', 'payload' => '{"module":{"slug":"beta","enabled":true,"rank":2,"tags":["seo"],"meta":{"channel":"beta"}}}'],
];

$plan = SQLiteJsonPathIndexedUpdatePlan::plan(
    $rows,
    [
        ['name' => 'idx_module_payload_slug', 'column' => 'payload', 'path' => '$.module.slug', 'unique' => true],
        ['name' => 'idx_module_payload_enabled', 'column' => 'payload', 'path' => '$.module.enabled'],
        ['name' => 'idx_module_payload_rank', 'column' => 'payload', 'path' => '$.module.rank'],
        ['name' => 'idx_module_payload_channel', 'column' => 'payload', 'path' => '$.module.meta.channel'],
        ['name' => 'idx_setting_load_policy', 'column' => 'key_value', 'path' => '$.load_policy'],
    ],
    [
        ['rowid' => 1, 'column' => 'payload', 'mutations' => [
            ['function' => 'json_set', 'path' => '$.module.enabled', 'value' => true],
            ['function' => 'json_set', 'path' => '$.module.rank', 'value' => 10],
            ['function' => 'json_set', 'path' => '$.module.meta.channel', 'value' => 'published'],
        ]],
    ],
);

echo json_encode([
    'changes' => $plan['changes'],
    'index_updates' => array_map(
        static fn (array $update): array => [
            'index' => $update['index'],
            'current' => $update['current'],
            'next' => $update['next'],
            'delete' => $update['delete'],
            'insert' => $update['insert'],
        ],
        $plan['index_updates'],
    ),
    'payload' => $plan['after'][0]['payload'],
    'key_value_unchanged' => $plan['after'][0]['key_value'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
