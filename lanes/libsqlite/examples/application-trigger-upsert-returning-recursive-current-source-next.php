<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRecursiveUpsertConflictYieldPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan;

$plan = SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan::execute(
    [
        ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'autoload' => 'yes'],
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'autoload' => 'no'],
    ],
    [
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'autoload' => 'yes'],
        ['option_name' => 'fresh_plugin', 'option_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'no'],
    ],
    [
        ['option_name' => 'plugin_seed_child', 'option_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'autoload' => 'yes'],
        ['option_name' => 'fresh_plugin', 'option_value' => 'fresh-next', 'revision' => 5, 'depth' => 1, 'autoload' => 'yes'],
    ],
    ['option_name'],
    [
        'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
        'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
        'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
        'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    ],
    [
        [
            'name' => 'wp_options_after_upsert_recursive_child',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'upsert-parent',
            'when' => ['new.depth', '<', 3],
            'row' => [
                'option_name' => ['concat' => ['new.option_name', '_child']],
                'option_value' => ['concat' => ['new.option_value', ':child']],
                'revision' => 1,
                'depth' => ['add' => ['new.depth', 1]],
                'autoload' => 'new.autoload',
            ],
        ],
        [
            'name' => 'wp_options_after_insert_recursive_child',
            'timing' => 'after',
            'event' => 'insert',
            'action' => 'upsert-parent',
            'when' => ['new.depth', '<', 3],
            'row' => [
                'option_name' => ['concat' => ['new.option_name', '_child']],
                'option_value' => ['concat' => ['new.option_value', ':child']],
                'revision' => 1,
                'depth' => ['add' => ['new.depth', 1]],
                'autoload' => 'new.autoload',
            ],
        ],
    ],
    [
        'savepoint' => 'wp_import_recursive_145',
        'current_source' => 'main@cookie-145',
        'next_source' => 'main@cookie-146',
        'rollback_on_returning_key' => ['fresh_plugin_child'],
        'returning' => [
            'option_name',
            ['expr' => 'new.option_value', 'as' => 'value'],
            ['expr' => 'event', 'as' => 'event_name'],
            ['expr' => 'depth', 'as' => 'trigger_depth'],
        ],
    ],
);

$summary = [
    'scenario' => 'application-trigger-upsert-returning-recursive-current-source-next',
    'applicationUse' => 'Preview a copied wp_options import where recursive trigger UPSERT RETURNING rows are attempted in the current source, a savepoint barrier rolls them back, and the next source restarts from the saved image.',
    'status' => $plan['status'],
    'currentRolledBack' => $plan['current_rolled_back'],
    'rollbackKey' => $plan['rollback_barrier']['returning_key'] ?? null,
    'nextStartedFrom' => $plan['next_started_from'],
    'attemptedCurrentReturning' => array_column(array_column($plan['attempted_current_returning_rows'], 'returning'), 'option_name'),
    'committedReturning' => array_column(array_column($plan['returning_rows'], 'returning'), 'option_name'),
    'finalOptions' => array_column($plan['next_rows'], 'option_name'),
    'dependencyClosure' => 'no new support component needed; this composes native PHP recursive trigger UPSERT, RETURNING stream, and savepoint current-source handoff behavior',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'recursive-upsert-returning-current-source-rolled-back-next145');
    assert($summary['currentRolledBack'] === true);
    assert($summary['rollbackKey'] === 'fresh_plugin_child');
    assert($summary['nextStartedFrom'] === 'savepoint');
    assert($summary['committedReturning'] === ['plugin_seed_child', 'plugin_seed_child_child', 'fresh_plugin', 'fresh_plugin_child', 'fresh_plugin_child_child']);
    echo "application-trigger-upsert-returning-recursive-current-source-next self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
