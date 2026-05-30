<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningRecursiveViewPlan;

$parents = [
    ['option_id' => 2, 'option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'level' => 1, 'autoload' => 'no'],
];
$children = [
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'plugin'],
];
$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'level' => static fn (array $old, array $incoming): mixed => $incoming['level'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
];
$triggers = [
    [
        'name' => 'wp_options_bu_rekey_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 'new.option_id',
    ],
    [
        'name' => 'active_options_after_update_recursive_upsert',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.level', '<', 3],
        'row' => [
            'option_id' => 'new_increment.option_id',
            'option_name' => 'concat:new.option_name:child',
            'option_value' => 'concat:new.option_value:child',
            'level' => 'new_increment.level',
            'autoload' => 'new.autoload',
        ],
    ],
];

$plan = SQLiteTriggerUpsertReturningRecursiveViewPlan::execute(
    $parents,
    $children,
    [['option_id' => 20, 'option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'autoload' => 'yes', 'level' => 1, 'source' => 'wp-import-view']],
    ['option_name'],
    $assignments,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    $triggers,
    [
        'option_name',
        ['expr' => 'current.option_id', 'as' => 'current_id'],
        ['expr' => 'view.source', 'as' => 'source'],
    ],
    ['view_name' => 'active_options', 'savepoint' => 'wp-current-next53'],
);

echo json_encode([
    'returning' => $plan['returning_rows'],
    'current_next_trace' => $plan['current_next_trace'],
    'parent_names' => array_column($plan['parent'], 'option_name'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
