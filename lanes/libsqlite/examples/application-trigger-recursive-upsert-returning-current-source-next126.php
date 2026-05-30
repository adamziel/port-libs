<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan;

require_once dirname(__DIR__) . '/../../tools/bootstrap.php';

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'autoload' => 'yes'],
    ['option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'autoload' => 'no'],
];
$assignments = [
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
];
$triggers = [
    [
        'name' => 'wp_options_ai_recursive_child_126',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'option_name' => ['concat' => ['new.option_name', ':child']],
            'option_value' => ['concat' => ['new.option_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'autoload' => 'new.autoload',
        ],
    ],
    [
        'name' => 'wp_options_au_recursive_child_126',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'option_name' => ['concat' => ['new.option_name', ':child']],
            'option_value' => ['concat' => ['new.option_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'autoload' => 'new.autoload',
        ],
    ],
];
$returning = [
    'option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
];

$plan = SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan::execute(
    $rows,
    [
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'autoload' => 'yes'],
        ['option_name' => 'fresh_plugin', 'option_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'no'],
    ],
    [
        ['option_name' => 'plugin_seed:child', 'option_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'autoload' => 'yes'],
        ['option_name' => 'next_plugin', 'option_value' => 'next', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes'],
    ],
    ['option_name'],
    $assignments,
    $triggers,
    ['current_source' => 'main@cookie-126', 'next_source' => 'main@cookie-127', 'returning' => $returning],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'current-source-returning-drained-before-next-source');
    assert($plan['handoff']['returning_rows_drained'] === 6);
    assert($plan['handoff']['next_source_contains_all_returning_keys'] === true);
    assert(array_column($plan['next_source_rows'], 'option_name') === ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']);
    echo "application-trigger-recursive-upsert-returning-current-source-next126 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'handoff' => $plan['handoff'],
    'current_returning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'option_name'),
    'next_returning' => array_column(array_column($plan['next_returning_rows'], 'returning'), 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
