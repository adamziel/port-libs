<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertSavepointCurrentNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'level' => 0],
    ['setting_id' => 2, 'key_name' => 'module_registry', 'key_value' => 'a:0:{}', 'load_policy' => 'yes', 'level' => 0],
];
$incoming = [
    ['setting_id' => 20, 'key_name' => 'base_url', 'key_value' => 'https://new.test', 'load_policy' => 'yes', 'level' => 0],
    ['setting_id' => 30, 'key_name' => 'module_seed', 'key_value' => 'seed', 'load_policy' => 'no', 'level' => 0],
    ['setting_id' => 40, 'key_name' => 'bad_module', 'key_value' => 'bad', 'load_policy' => 'no', 'level' => 0],
];
$assignments = [
    'setting_id' => static fn (array $old, array $new): mixed => $new['setting_id'],
    'key_value' => static fn (array $old, array $new): mixed => $new['key_value'],
    'load_policy' => static fn (array $old, array $new): mixed => $new['load_policy'],
    'level' => static fn (array $old, array $new): mixed => $new['level'],
];
$triggers = [
    [
        'name' => 'app_settings_bu_base_url_suffix',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.key_name', '=', 'base_url'],
        'set' => ['key_value' => 'concat:new.key_value:/settings'],
    ],
    [
        'name' => 'app_settings_ai_module_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-row',
        'when' => ['new.level', '<', 1],
        'row' => [
            'setting_id' => 'new_plus.setting_id',
            'key_name' => 'concat:new.key_name:_meta',
            'key_value' => 'concat:new.key_value::meta',
            'load_policy' => 'new.load_policy',
            'level' => 'new_plus.level',
        ],
    ],
    [
        'name' => 'app_settings_bi_block_bad_module',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'raise',
        'when' => ['new.key_name', '=', 'bad_module'],
        'raise' => 'abort',
        'reason' => 'blocked-module-setting',
    ],
];

$plan = SQLiteTriggerUpsertSavepointCurrentNextPlan::execute(
    $rows,
    $incoming,
    ['key_name'],
    $assignments,
    $triggers,
    ['savepoint' => 'app-import-row', 'wal_frame' => 12],
);

if (($argv[1] ?? '') === '--self-test') {
    assert(array_column($plan['rows'], 'key_name') === ['base_url', 'module_registry', 'module_seed', 'module_seed_meta']);
    assert($plan['next_wal_frame'] === 14);
    assert($plan['row_results'][2]['status'] === 'rolled-back');
    echo "application-trigger-upsert-savepoint-current-next73 self-test passed\n";
    return;
}

echo json_encode([
    'rows' => array_column($plan['rows'], 'key_name'),
    'rowResults' => $plan['row_results'],
    'yielded' => array_column($plan['yielded'], 'key_name'),
    'currentWalFrame' => $plan['current_wal_frame'],
    'nextWalFrame' => $plan['next_wal_frame'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
