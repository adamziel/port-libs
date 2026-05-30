<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertSavepointCurrentNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'level' => 0],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes', 'level' => 0],
];
$incoming = [
    ['option_id' => 20, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'level' => 0],
    ['option_id' => 30, 'option_name' => 'plugin_seed', 'option_value' => 'seed', 'autoload' => 'no', 'level' => 0],
    ['option_id' => 40, 'option_name' => 'bad_plugin', 'option_value' => 'bad', 'autoload' => 'no', 'level' => 0],
];
$assignments = [
    'option_id' => static fn (array $old, array $new): mixed => $new['option_id'],
    'option_value' => static fn (array $old, array $new): mixed => $new['option_value'],
    'autoload' => static fn (array $old, array $new): mixed => $new['autoload'],
    'level' => static fn (array $old, array $new): mixed => $new['level'],
];
$triggers = [
    [
        'name' => 'wp_options_bu_siteurl_suffix',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.option_name', '=', 'siteurl'],
        'set' => ['option_value' => 'concat:new.option_value:/wp'],
    ],
    [
        'name' => 'wp_options_ai_plugin_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-row',
        'when' => ['new.level', '<', 1],
        'row' => [
            'option_id' => 'new_plus.option_id',
            'option_name' => 'concat:new.option_name:_meta',
            'option_value' => 'concat:new.option_value::meta',
            'autoload' => 'new.autoload',
            'level' => 'new_plus.level',
        ],
    ],
    [
        'name' => 'wp_options_bi_block_bad_plugin',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'raise',
        'when' => ['new.option_name', '=', 'bad_plugin'],
        'raise' => 'abort',
        'reason' => 'blocked-plugin-option',
    ],
];

$plan = SQLiteTriggerUpsertSavepointCurrentNextPlan::execute(
    $rows,
    $incoming,
    ['option_name'],
    $assignments,
    $triggers,
    ['savepoint' => 'wp-import-row', 'wal_frame' => 12],
);

if (($argv[1] ?? '') === '--self-test') {
    assert(array_column($plan['rows'], 'option_name') === ['siteurl', 'active_plugins', 'plugin_seed', 'plugin_seed_meta']);
    assert($plan['next_wal_frame'] === 14);
    assert($plan['row_results'][2]['status'] === 'rolled-back');
    echo "application-trigger-upsert-savepoint-current-next73 self-test passed\n";
    return;
}

echo json_encode([
    'rows' => array_column($plan['rows'], 'option_name'),
    'rowResults' => $plan['row_results'],
    'yielded' => array_column($plan['yielded'], 'option_name'),
    'currentWalFrame' => $plan['current_wal_frame'],
    'nextWalFrame' => $plan['next_wal_frame'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
