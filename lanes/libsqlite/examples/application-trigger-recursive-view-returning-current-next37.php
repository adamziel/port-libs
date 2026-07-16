<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRecursiveViewReturningPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'level' => 0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'level' => 1, 'autoload' => 'no'],
];
$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
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
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'autoload', 'meta_value' => 'new.autoload'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
    [
        'name' => 'active_options_after_insert_recursive_upsert',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.level', '<', 3],
        'row' => [
            'option_id' => 'new_increment.option_id',
            'option_name' => 'concat:new.option_name:child',
            'option_value' => 'concat:new.option_value:child',
            'level' => 'new_increment.level',
            'autoload' => 'new.autoload',
        ],
        'values' => ['name' => 'new.option_name', 'level' => 'new.level'],
    ],
];

$plan = SQLiteRecursiveViewReturningPlan::execute(
    $parents,
    $children,
    [
        ['option_id' => 20, 'option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'autoload' => 'yes', 'level' => 1, 'source' => 'wp-import'],
        ['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'autoload' => 'no', 'level' => 1, 'source' => 'wp-import'],
    ],
    ['option_name'],
    $assignments,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    $triggers,
    [
        'option_name',
        ['expr' => 'view.source', 'as' => 'source'],
        ['expr' => 'yield.event', 'as' => 'event'],
        ['expr' => 'current.option_id', 'as' => 'current_option_id'],
    ],
    ['view_name' => 'active_options', 'savepoint' => 'wp-active-view-import'],
);

echo json_encode([
    'view' => $plan['view'],
    'savepoint' => $plan['savepoint'],
    'changes' => $plan['changes'],
    'returningRows' => $plan['returning_rows'],
    'parentNames' => array_column($plan['parent'], 'option_name'),
    'childOptionIds' => array_column($plan['child'], 'option_id'),
    'dependencies' => $plan['view_dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
