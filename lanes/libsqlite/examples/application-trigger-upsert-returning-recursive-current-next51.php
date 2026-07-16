<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteRecursiveUpsertConflictYieldPlan.php';

use PortLibs\LibSqlite\SQLiteRecursiveUpsertConflictYieldPlan;

$plan = SQLiteRecursiveUpsertConflictYieldPlan::execute(
    [
        ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 2, 'depth' => 0, 'load_policy' => 'yes'],
        ['key_name' => 'module_seed', 'key_value' => 'seed-old', 'revision' => 5, 'depth' => 1, 'load_policy' => 'no'],
    ],
    [
        ['key_name' => 'module_seed', 'key_value' => 'seed-new', 'revision' => 3, 'depth' => 1, 'load_policy' => 'yes'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no'],
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
    ],
    [
        'returning' => [
            'key_name',
            ['expr' => 'new.key_value', 'as' => 'value'],
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
    'returned' => array_column($plan['returning'], 'key_name'),
    'depths' => array_column($plan['returning'], 'trigger_depth'),
    'sourceTriggers' => array_column($plan['returning'], 'source_trigger'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
