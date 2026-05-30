<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'plugin_alpha', 'option_value' => 'enabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 10],
    ['option_name' => 'plugin_alpha_child', 'option_value' => 'child', 'autoload' => 'no', 'parent_name' => 'plugin_alpha', 'priority' => 20],
];

$view = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-163-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-163-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['option_name'], 'plugin_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-163-next';
$nextView['trigger_source'] = 'main@trigger-cookie-163-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 1 && str_starts_with((string) $row['option_name'], 'plugin_');

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveChildSourceRelease(
    $rows,
    [['root_name' => 'siteurl']],
    [['root_name' => 'audit:current:siteurl:plugin_alpha']],
    $view,
    $nextView,
    ['option_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'release_next_source' => true,
        'savepoint' => 'wp_recursive_view_next163',
        'current_generation' => 'wp-import-current-163',
        'next_generation' => 'wp-import-next-163',
        'trigger_child_prefix' => 'plugin_generated',
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status'] ?? null) !== 'trigger-recursive-view-returning-snapshot-release-next163') {
        fwrite(STDERR, "unexpected recursive view RETURNING snapshot status\n");
        exit(1);
    }
    if (($summary['current_snapshot_guard']['reentrant_suppressed'] ?? null) !== true) {
        fwrite(STDERR, "trigger-generated rows re-entered the current recursive source\n");
        exit(1);
    }
    if (($summary['next_source_seed']['seeded_recursive_rows'] ?? 0) < 1) {
        fwrite(STDERR, "released next source did not see the trigger-generated seed row\n");
        exit(1);
    }
    echo "application-trigger-recursive-view-returning-current-source-next163 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next163',
    'applicationUse' => 'Copied wp_options imports through recursive INSTEAD OF view triggers materialize the current source before RETURNING rows are drained, so trigger-generated audit rows do not re-enter the current statement but can seed a released next source.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING source barriers and trigger row projection',
    'summary' => [
        'status' => $summary['status'],
        'snapshotSuppressed' => $summary['current_snapshot_guard']['reentrant_suppressed'],
        'generatedRows' => $summary['current_snapshot_guard']['generated_rows'],
        'seededNextRows' => $summary['next_source_seed']['seeded_recursive_rows'],
        'visibleReturningKeys' => $summary['returning_visibility']['visible'],
    ],
];
