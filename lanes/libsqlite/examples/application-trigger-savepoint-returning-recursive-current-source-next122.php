<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteRecursiveUpsertConflictYieldPlan.php';
require __DIR__ . '/../src/SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan.php';
require __DIR__ . '/../src/SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan;

$plan = SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan::execute(
    [
        ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 2, 'depth' => 0, 'load_policy' => 'yes'],
        ['key_name' => 'module_seed', 'key_value' => 'seed-old', 'revision' => 5, 'depth' => 1, 'load_policy' => 'no'],
    ],
    [
        ['key_name' => 'module_seed', 'key_value' => 'seed-current', 'revision' => 3, 'depth' => 1, 'load_policy' => 'yes'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no'],
    ],
    [
        ['key_name' => 'module_seed:child', 'key_value' => 'seed-child-next', 'revision' => 4, 'depth' => 2, 'load_policy' => 'yes'],
        ['key_name' => 'next_module', 'key_value' => 'next', 'revision' => 1, 'depth' => 1, 'load_policy' => 'yes'],
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
            'name' => 'app_settings_ai_recursive_child',
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
            'name' => 'app_settings_au_recursive_child',
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
    ],
    [
        'savepoint' => 'app_trigger_batch',
        'returning' => [
            'key_name',
            ['expr' => 'new.key_value', 'as' => 'value'],
            ['expr' => 'event', 'as' => 'event_name'],
            ['expr' => 'depth', 'as' => 'trigger_depth'],
        ],
    ],
);

if (in_array('--self-test', $argv, true)) {
    if (
        $plan['rows'][1]['key_value'] !== 'seed-old'
        || count($plan['attempted_returning_rows']) !== 11
        || $plan['discarded_returning_count'] !== 11
        || $plan['yield_stream'][6]['phase'] !== 'next'
    ) {
        fwrite(STDERR, "application-trigger-savepoint-returning-recursive-current-source-next122 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-savepoint-returning-recursive-current-source-next122 self-test passed\n";
}

echo json_encode([
    'scenario' => 'application-trigger-savepoint-returning-recursive-current-source-next122',
    'applicationUse' => 'Preview a copied app_settings import where recursive triggers produce RETURNING diagnostics from current and next sources, then ROLLBACK TO restores the savepoint image while retaining attempted RETURNING evidence for importer diagnostics.',
    'savepoint' => $plan['savepoint'],
    'rolledBack' => $plan['rolled_back'],
    'finalRows' => array_column($plan['rows'], 'key_name'),
    'attemptedRows' => array_column($plan['attempted_rows'], 'key_name'),
    'attemptedReturning' => array_column($plan['attempted_returning_rows'], 'key_name'),
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'yieldPhases' => array_column($plan['yield_stream'], 'phase'),
    'dependencyClosure' => 'no new support component needed; composes existing native PHP recursive trigger RETURNING and savepoint rollback semantics',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
