<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRecursiveUpsertConflictYieldPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan;

$plan = SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan::execute(
    [
        ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'load_policy' => 'yes'],
        ['key_name' => 'module_seed', 'key_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'load_policy' => 'no'],
    ],
    [
        ['key_name' => 'module_seed', 'key_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'load_policy' => 'yes'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no'],
    ],
    [
        ['key_name' => 'module_seed_child', 'key_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'load_policy' => 'yes'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh-next', 'revision' => 5, 'depth' => 1, 'load_policy' => 'yes'],
    ],
    ['key_name'],
    [
        'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
        'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
        'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
        'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
    ],
    [
        [
            'name' => 'app_settings_after_upsert_recursive_child',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'upsert-parent',
            'when' => ['new.depth', '<', 3],
            'row' => [
                'key_name' => ['concat' => ['new.key_name', '_child']],
                'key_value' => ['concat' => ['new.key_value', ':child']],
                'revision' => 1,
                'depth' => ['add' => ['new.depth', 1]],
                'load_policy' => 'new.load_policy',
            ],
        ],
        [
            'name' => 'app_settings_after_insert_recursive_child',
            'timing' => 'after',
            'event' => 'insert',
            'action' => 'upsert-parent',
            'when' => ['new.depth', '<', 3],
            'row' => [
                'key_name' => ['concat' => ['new.key_name', '_child']],
                'key_value' => ['concat' => ['new.key_value', ':child']],
                'revision' => 1,
                'depth' => ['add' => ['new.depth', 1]],
                'load_policy' => 'new.load_policy',
            ],
        ],
    ],
    [
        'savepoint' => 'app_import_recursive_145',
        'current_source' => 'main@cookie-145',
        'next_source' => 'main@cookie-146',
        'rollback_on_returning_key' => ['fresh_module_child'],
        'returning' => [
            'key_name',
            ['expr' => 'new.key_value', 'as' => 'value'],
            ['expr' => 'event', 'as' => 'event_name'],
            ['expr' => 'depth', 'as' => 'trigger_depth'],
        ],
    ],
);

$summary = [
    'scenario' => 'application-trigger-upsert-returning-recursive-current-source-next',
    'applicationUse' => 'Preview a copied app_settings import where recursive trigger UPSERT RETURNING rows are attempted in the current source, a savepoint barrier rolls them back, and the next source restarts from the saved image.',
    'status' => $plan['status'],
    'currentRolledBack' => $plan['current_rolled_back'],
    'rollbackKey' => $plan['rollback_barrier']['returning_key'] ?? null,
    'nextStartedFrom' => $plan['next_started_from'],
    'attemptedCurrentReturning' => array_column(array_column($plan['attempted_current_returning_rows'], 'returning'), 'key_name'),
    'committedReturning' => array_column(array_column($plan['returning_rows'], 'returning'), 'key_name'),
    'finalOptions' => array_column($plan['next_rows'], 'key_name'),
    'dependencyClosure' => 'no new support component needed; this composes native PHP recursive trigger UPSERT, RETURNING stream, and savepoint current-source handoff behavior',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'recursive-upsert-returning-current-source-rolled-back-next145');
    assert($summary['currentRolledBack'] === true);
    assert($summary['rollbackKey'] === 'fresh_module_child');
    assert($summary['nextStartedFrom'] === 'savepoint');
    assert($summary['committedReturning'] === ['module_seed_child', 'module_seed_child_child', 'fresh_module', 'fresh_module_child', 'fresh_module_child_child']);
    echo "application-trigger-upsert-returning-recursive-current-source-next self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
