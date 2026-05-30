<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteRecursiveUpsertConflictYieldPlan.php';
require __DIR__ . '/../src/SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan.php';
require __DIR__ . '/../src/SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan;

$plan = SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan::execute(
    [
        ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 2, 'depth' => 0, 'autoload' => 'yes'],
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'revision' => 5, 'depth' => 1, 'autoload' => 'no'],
    ],
    [
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-current', 'revision' => 3, 'depth' => 1, 'autoload' => 'yes'],
        ['option_name' => 'fresh_plugin', 'option_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'no'],
    ],
    [
        ['option_name' => 'plugin_seed:child', 'option_value' => 'seed-child-next', 'revision' => 4, 'depth' => 2, 'autoload' => 'yes'],
        ['option_name' => 'next_plugin', 'option_value' => 'next', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes'],
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
            'name' => 'wp_options_ai_recursive_child',
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
            'name' => 'wp_options_au_recursive_child',
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
    ],
    [
        'savepoint' => 'wp_trigger_batch',
        'returning' => [
            'option_name',
            ['expr' => 'new.option_value', 'as' => 'value'],
            ['expr' => 'event', 'as' => 'event_name'],
            ['expr' => 'depth', 'as' => 'trigger_depth'],
        ],
    ],
);

if (in_array('--self-test', $argv, true)) {
    if (
        $plan['rows'][1]['option_value'] !== 'seed-old'
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
    'applicationUse' => 'Preview a copied wp_options import where recursive triggers produce RETURNING diagnostics from current and next sources, then ROLLBACK TO restores the savepoint image while retaining attempted RETURNING evidence for importer diagnostics.',
    'savepoint' => $plan['savepoint'],
    'rolledBack' => $plan['rolled_back'],
    'finalRows' => array_column($plan['rows'], 'option_name'),
    'attemptedRows' => array_column($plan['attempted_rows'], 'option_name'),
    'attemptedReturning' => array_column($plan['attempted_returning_rows'], 'option_name'),
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'yieldPhases' => array_column($plan['yield_stream'], 'phase'),
    'dependencyClosure' => 'no new support component needed; composes existing native PHP recursive trigger RETURNING and savepoint rollback semantics',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
