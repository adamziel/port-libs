<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteRecursiveUpsertConflictYieldPlan.php';

use PortLibs\LibSqlite\SQLiteRecursiveUpsertConflictYieldPlan;

$plan = SQLiteRecursiveUpsertConflictYieldPlan::execute(
    [
        ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 2, 'depth' => 0, 'autoload' => 'yes'],
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'revision' => 5, 'depth' => 1, 'autoload' => 'no'],
    ],
    [
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'revision' => 3, 'depth' => 1, 'autoload' => 'yes'],
        ['option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'revision' => 1, 'depth' => 1, 'autoload' => 'no'],
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
    if ($plan['changes'] !== 6 || count($plan['returning']) !== 6 || $plan['max_depth_seen'] !== 2) {
        fwrite(STDERR, "application-trigger-upsert-returning-recursive-current-next51 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-upsert-returning-recursive-current-next51 self-test passed\n";
    exit(0);
}

echo json_encode([
    'changes' => $plan['changes'],
    'returned' => array_column($plan['returning'], 'option_name'),
    'depths' => array_column($plan['returning'], 'trigger_depth'),
    'sourceTriggers' => array_column($plan['returning'], 'source_trigger'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
