<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'load_policy' => 'yes', 'parent_name' => null],
    ['key_name' => 'module_seed', 'key_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'load_policy' => 'no', 'parent_name' => 'base_url'],
    ['key_name' => 'orphan_setting', 'key_value' => 'orphan-old', 'revision' => 1, 'depth' => 0, 'load_policy' => 'no', 'parent_name' => 'missing_parent'],
];

$assignments = [
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
    'parent_name' => static fn (array $old, array $incoming): mixed => $incoming['parent_name'] ?? ($old['parent_name'] ?? null),
];

$triggers = [
    [
        'name' => 'app_settings_ai_child_128',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 2],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', ':child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
            'parent_name' => 'new.key_name',
        ],
        'values' => ['name' => 'new.key_name'],
    ],
    [
        'name' => 'app_settings_au_child_128',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 2],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', ':child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
            'parent_name' => 'new.key_name',
        ],
        'values' => ['name' => 'new.key_name'],
    ],
];

$plan = SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan::execute(
    $rows,
    [
        ['key_name' => 'module_seed', 'key_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'load_policy' => 'yes', 'parent_name' => 'base_url'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'yes', 'parent_name' => 'base_url'],
        ['key_name' => 'missing_parent', 'key_value' => 'repair-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no', 'parent_name' => 'base_url'],
    ],
    [
        ['key_name' => 'module_seed:child', 'key_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'load_policy' => 'yes', 'parent_name' => 'module_seed'],
        ['key_name' => 'next_module', 'key_value' => 'next', 'revision' => 1, 'depth' => 1, 'load_policy' => 'yes', 'parent_name' => 'base_url'],
    ],
    ['key_name'],
    $assignments,
    $triggers,
    ['parent_key' => 'key_name', 'child_key' => 'parent_name', 'deferred' => true],
    [
        'name' => 'app_loadable_settings_128',
        'columns' => ['key_name', 'key_value', 'parent_name', 'load_policy'],
        'where' => static fn (array $row): bool => ($row['load_policy'] ?? null) === 'yes',
        'order_by' => 'key_name',
    ],
    [
        'current_source' => 'main@cookie-128',
        'next_source' => 'main@cookie-129',
        'returning' => ['key_name', ['expr' => 'new.key_value', 'as' => 'value'], ['expr' => 'event', 'as' => 'event_name'], 'parent_name'],
    ],
);

$summary = [
    'scenario' => 'application-trigger-returning-deferred-view-current-source-next128',
    'applicationUse' => 'A copied application app_settings import can yield recursive trigger RETURNING rows, materialize a loadable-settings view from that current source, then check deferred parent-key references before advancing the next source.',
    'status' => $plan['status'],
    'viewRows' => array_column($plan['view_rows'], 'key_name'),
    'currentReturning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'key_name'),
    'nextReturning' => array_column(array_column($plan['next_returning_rows'], 'returning'), 'key_name'),
    'deferredViolations' => $plan['foreign_key_violations'],
    'dependencyClosure' => 'no new support component needed; reuses existing recursive trigger RETURNING, view projection, and deferred foreign-key plan primitives',
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'view-current-source-drained-before-next-source'
        || $summary['viewRows'] !== ['base_url', 'fresh_module', 'fresh_module:child', 'module_seed', 'module_seed:child']
        || $summary['deferredViolations'] !== []
    ) {
        fwrite(STDERR, "application-trigger-returning-deferred-view-current-source-next128 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-returning-deferred-view-current-source-next128 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
