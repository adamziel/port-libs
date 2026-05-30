<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteRecursiveUpsertConflictYieldPlan.php';
require __DIR__ . '/../src/SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan;

$plan = SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan::execute(
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
        'returning' => [
            'option_name',
            ['expr' => 'new.option_value', 'as' => 'value'],
            ['expr' => 'event', 'as' => 'event_name'],
            ['expr' => 'depth', 'as' => 'trigger_depth'],
            ['expr' => 'trigger', 'as' => 'source_trigger'],
        ],
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $plan['changes'] !== 11
        || count($plan['current_returning']) !== 6
        || count($plan['next_returning']) !== 5
        || $plan['next_source_rows'][2]['option_name'] !== 'plugin_seed:child'
        || $plan['next_returning'][1]['option_name'] !== 'plugin_seed:child:child'
    ) {
        fwrite(STDERR, "application-trigger-returning-recursive-upsert-current-source-next118 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-returning-recursive-upsert-current-source-next118 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'changes' => $plan['changes'],
    'currentReturned' => array_column($plan['current_returning'], 'option_name'),
    'nextSource' => array_column($plan['next_source_rows'], 'option_name'),
    'nextReturned' => array_column($plan['next_returning'], 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
