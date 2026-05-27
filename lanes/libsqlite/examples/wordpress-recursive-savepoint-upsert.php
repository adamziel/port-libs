<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveSavepointUpsertPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
$foreignKey = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
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
        'name' => 'wp_options_after_upsert_recursive_child',
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

$committed = SQLiteRecursiveSavepointUpsertPlan::execute(
    'wp-option-import',
    $parents,
    $children,
    [['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'level' => 1, 'autoload' => 'no']],
    ['option_name'],
    $assignments,
    $foreignKey,
    $triggers,
);

$rollbackTriggers = $triggers;
$rollbackTriggers[] = [
    'name' => 'wp_options_abort_second_child',
    'timing' => 'after',
    'event' => 'insert',
    'action' => 'raise',
    'when' => ['new.option_name', '=', 'fresh_plugin:child:child'],
    'raise' => 'rollback',
    'reason' => 'recursive-child-conflict',
    'values' => ['name' => 'new.option_name'],
];

$rolledBack = SQLiteRecursiveSavepointUpsertPlan::execute(
    'wp-option-import',
    $parents,
    $children,
    [['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'level' => 1, 'autoload' => 'no']],
    ['option_name'],
    $assignments,
    $foreignKey,
    $rollbackTriggers,
);

echo json_encode([
    'committedNames' => array_column($committed['parent'], 'option_name'),
    'committedChanges' => $committed['changes'],
    'committedYielded' => array_column($committed['yielded'], 'option_name'),
    'rolledBackNames' => array_column($rolledBack['parent'], 'option_name'),
    'rolledBackAttemptedNames' => array_column($rolledBack['attempted_parent'], 'option_name'),
    'rollbackScope' => $rolledBack['rollback_scope'],
    'rollbackReason' => $rolledBack['rollback_reason'],
    'savepointPreserved' => $rolledBack['savepoint_preserved'],
    'dependencies' => $rolledBack['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
