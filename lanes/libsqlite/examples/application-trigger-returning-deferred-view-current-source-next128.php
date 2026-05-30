<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'autoload' => 'yes', 'parent_name' => null],
    ['option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'autoload' => 'no', 'parent_name' => 'siteurl'],
    ['option_name' => 'orphan_option', 'option_value' => 'orphan-old', 'revision' => 1, 'depth' => 0, 'autoload' => 'no', 'parent_name' => 'missing_parent'],
];

$assignments = [
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'parent_name' => static fn (array $old, array $incoming): mixed => $incoming['parent_name'] ?? ($old['parent_name'] ?? null),
];

$triggers = [
    [
        'name' => 'wp_options_ai_child_128',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 2],
        'row' => [
            'option_name' => ['concat' => ['new.option_name', ':child']],
            'option_value' => ['concat' => ['new.option_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'autoload' => 'new.autoload',
            'parent_name' => 'new.option_name',
        ],
        'values' => ['name' => 'new.option_name'],
    ],
    [
        'name' => 'wp_options_au_child_128',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 2],
        'row' => [
            'option_name' => ['concat' => ['new.option_name', ':child']],
            'option_value' => ['concat' => ['new.option_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'autoload' => 'new.autoload',
            'parent_name' => 'new.option_name',
        ],
        'values' => ['name' => 'new.option_name'],
    ],
];

$plan = SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan::execute(
    $rows,
    [
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'autoload' => 'yes', 'parent_name' => 'siteurl'],
        ['option_name' => 'fresh_plugin', 'option_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes', 'parent_name' => 'siteurl'],
        ['option_name' => 'missing_parent', 'option_value' => 'repair-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'no', 'parent_name' => 'siteurl'],
    ],
    [
        ['option_name' => 'plugin_seed:child', 'option_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'autoload' => 'yes', 'parent_name' => 'plugin_seed'],
        ['option_name' => 'next_plugin', 'option_value' => 'next', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes', 'parent_name' => 'siteurl'],
    ],
    ['option_name'],
    $assignments,
    $triggers,
    ['parent_key' => 'option_name', 'child_key' => 'parent_name', 'deferred' => true],
    [
        'name' => 'wp_autoloaded_options_128',
        'columns' => ['option_name', 'option_value', 'parent_name', 'autoload'],
        'where' => static fn (array $row): bool => ($row['autoload'] ?? null) === 'yes',
        'order_by' => 'option_name',
    ],
    [
        'current_source' => 'main@cookie-128',
        'next_source' => 'main@cookie-129',
        'returning' => ['option_name', ['expr' => 'new.option_value', 'as' => 'value'], ['expr' => 'event', 'as' => 'event_name'], 'parent_name'],
    ],
);

$summary = [
    'scenario' => 'application-trigger-returning-deferred-view-current-source-next128',
    'applicationUse' => 'A copied Application wp_options import can yield recursive trigger RETURNING rows, materialize an autoloaded-options view from that current source, then check deferred parent-option references before advancing the next source.',
    'status' => $plan['status'],
    'viewRows' => array_column($plan['view_rows'], 'option_name'),
    'currentReturning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'option_name'),
    'nextReturning' => array_column(array_column($plan['next_returning_rows'], 'returning'), 'option_name'),
    'deferredViolations' => $plan['foreign_key_violations'],
    'dependencyClosure' => 'no new support component needed; reuses existing recursive trigger RETURNING, view projection, and deferred foreign-key plan primitives',
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'view-current-source-drained-before-next-source'
        || $summary['viewRows'] !== ['fresh_plugin', 'fresh_plugin:child', 'plugin_seed', 'plugin_seed:child', 'siteurl']
        || $summary['deferredViolations'] !== []
    ) {
        fwrite(STDERR, "application-trigger-returning-deferred-view-current-source-next128 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-returning-deferred-view-current-source-next128 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
