<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan;

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
$orphanTriggers = [
    [
        'name' => 'wp_options_after_insert_orphan_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 999, 'meta_key' => 'autoload', 'meta_value' => 'new.autoload'],
        'values' => ['name' => 'new.option_name'],
    ],
    [
        'name' => 'wp_options_after_insert_recursive_upsert',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.level', '<', 2],
        'row' => [
            'option_id' => 'new_increment.option_id',
            'option_name' => 'concat:new.option_name:child',
            'option_value' => 'concat:new.option_value:child',
            'level' => 'new_increment.level',
            'autoload' => 'new.autoload',
        ],
        'values' => ['name' => 'new.option_name'],
    ],
];
$validTriggers = [[
    'name' => 'wp_options_after_insert_meta',
    'timing' => 'after',
    'event' => 'insert',
    'action' => 'insert-child',
    'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'autoload', 'meta_value' => 'new.autoload'],
    'values' => ['name' => 'new.option_name'],
]];

$plan = SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute(
    'wp_import',
    $parents,
    $children,
    [['option_id' => 30, 'option_name' => 'broken_plugin', 'option_value' => 'broken', 'level' => 1, 'autoload' => 'no']],
    [['option_id' => 40, 'option_name' => 'fixed_plugin', 'option_value' => 'fixed', 'level' => 1, 'autoload' => 'yes']],
    ['option_name'],
    $assignments,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    $orphanTriggers,
    $validTriggers,
    [
        'option_id',
        'option_name',
        ['expr' => 'new.option_value', 'as' => 'value_after'],
        ['expr' => 'yield.depth', 'as' => 'trigger_depth'],
    ],
);

if (($argv[1] ?? null) === '--self-test') {
    if ($plan['status'] !== 'current-returned-then-rolled-back-next-applied' || $plan['current_returning_rows'] === [] || $plan['next_returning_rows'][0]['option_name'] !== 'fixed_plugin') {
        fwrite(STDERR, "application-trigger-fk-returning-upsert-savepoint-current-next75 self-test failed\n");
        exit(1);
    }
    echo "application-trigger-fk-returning-upsert-savepoint-current-next75 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'copied wp_options UPSERT RETURNING deferred FK savepoint current-next75',
    'status' => $plan['status'],
    'releaseStatus' => $plan['release_status'],
    'attemptedReturningBeforeReleaseFailure' => $plan['current_returning_rows'],
    'fkViolationsAtRelease' => count($plan['current_fk_violations']),
    'preservedSavepointNames' => array_column($plan['parent'], 'option_name'),
    'retryNames' => array_column($plan['next_parent'], 'option_name'),
    'retryReturning' => $plan['next_returning_rows'],
    'committedChanges' => $plan['committed_changes'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
