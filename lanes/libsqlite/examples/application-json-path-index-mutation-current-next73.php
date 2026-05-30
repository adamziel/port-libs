<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonPathIndexedUpdatePlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha_settings', 'option_value' => '{"autoload":true}', 'payload' => '{"plugin":{"slug":"alpha","enabled":false,"rank":1,"tags":["cache"],"meta":{"channel":"stable"}}}'],
    ['option_id' => 2, 'option_name' => 'plugin_beta_settings', 'option_value' => '{"autoload":true}', 'payload' => '{"plugin":{"slug":"beta","enabled":true,"rank":2,"tags":["seo"],"meta":{"channel":"beta"}}}'],
];

$plan = SQLiteJsonPathIndexedUpdatePlan::plan(
    $rows,
    [
        ['name' => 'idx_plugin_payload_slug', 'column' => 'payload', 'path' => '$.plugin.slug', 'unique' => true],
        ['name' => 'idx_plugin_payload_enabled', 'column' => 'payload', 'path' => '$.plugin.enabled'],
        ['name' => 'idx_plugin_payload_rank', 'column' => 'payload', 'path' => '$.plugin.rank'],
        ['name' => 'idx_plugin_payload_channel', 'column' => 'payload', 'path' => '$.plugin.meta.channel'],
        ['name' => 'idx_option_autoload', 'column' => 'option_value', 'path' => '$.autoload'],
    ],
    [
        ['rowid' => 1, 'column' => 'payload', 'mutations' => [
            ['function' => 'json_set', 'path' => '$.plugin.enabled', 'value' => true],
            ['function' => 'json_set', 'path' => '$.plugin.rank', 'value' => 10],
            ['function' => 'json_set', 'path' => '$.plugin.meta.channel', 'value' => 'published'],
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
    'option_value_unchanged' => $plan['after'][0]['option_value'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
