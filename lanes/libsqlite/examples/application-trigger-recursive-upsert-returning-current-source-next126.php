<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan;

require_once dirname(__DIR__) . '/../../tools/bootstrap.php';

$rows = [
    ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'load_policy' => 'yes'],
    ['key_name' => 'module_seed', 'key_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'load_policy' => 'no'],
];
$assignments = [
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
];
$triggers = [
    [
        'name' => 'app_settings_ai_recursive_child_126',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', ':child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
        ],
    ],
    [
        'name' => 'app_settings_au_recursive_child_126',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', ':child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
        ],
    ],
];
$returning = [
    'key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
];

$plan = SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan::execute(
    $rows,
    [
        ['key_name' => 'module_seed', 'key_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'load_policy' => 'yes'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no'],
    ],
    [
        ['key_name' => 'module_seed:child', 'key_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'load_policy' => 'yes'],
        ['key_name' => 'next_module', 'key_value' => 'next', 'revision' => 1, 'depth' => 1, 'load_policy' => 'yes'],
    ],
    ['key_name'],
    $assignments,
    $triggers,
    ['current_source' => 'main@cookie-126', 'next_source' => 'main@cookie-127', 'returning' => $returning],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'current-source-returning-drained-before-next-source');
    assert($plan['handoff']['returning_rows_drained'] === 6);
    assert($plan['handoff']['next_source_contains_all_returning_keys'] === true);
    assert(array_column($plan['next_source_rows'], 'key_name') === ['base_url', 'module_seed', 'module_seed:child', 'module_seed:child:child', 'fresh_module', 'fresh_module:child', 'fresh_module:child:child']);
    echo "application-trigger-recursive-upsert-returning-current-source-next126 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'handoff' => $plan['handoff'],
    'current_returning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'key_name'),
    'next_returning' => array_column(array_column($plan['next_returning_rows'], 'returning'), 'key_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
