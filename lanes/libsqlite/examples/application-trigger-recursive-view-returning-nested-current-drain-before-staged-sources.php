<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['key_name' => 'theme_root', 'key_value' => 'theme', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 10],
    ['key_name' => 'theme_child', 'key_value' => 'theme-child', 'load_policy' => 'no', 'parent_name' => 'theme_root', 'priority' => 20],
    ['key_name' => 'module_root', 'key_value' => 'module', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 15],
    ['key_name' => 'module_child', 'key_value' => 'module-child', 'load_policy' => 'no', 'parent_name' => 'module_root', 'priority' => 30],
    ['key_name' => 'group_root', 'key_value' => 'group', 'load_policy' => 'yes', 'parent_name' => 'module_child', 'priority' => 40],
];

$view = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-169-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-169-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['key_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-169-next';
$nextView['trigger_source'] = 'main@trigger-cookie-169-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNestedCurrentDrainBeforeStagedSources(
    $rows,
    [['root_name' => 'base_url']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'module_root']],
    $view,
    $nextView,
    ['key_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'savepoint' => 'app_recursive_view_next169',
        'cursor_name' => 'app_recursive_view_returning_cursor_169',
        'current_generation' => 'app-import-current-169',
        'nested_generation' => 'app-import-current-169-nested',
        'first_next_generation' => 'app-import-next-169-a',
        'second_next_generation' => 'app-import-next-169-b',
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status'] ?? null) !== 'trigger-recursive-view-returning-current-source-nested-held-next169') {
        fwrite(STDERR, "unexpected recursive view RETURNING next169 status\n");
        exit(1);
    }
    if (($summary['source_next_plan']['nested_drained_before_staged'] ?? null) !== true) {
        fwrite(STDERR, "nested current-source RETURNING rows did not drain before staged next source\n");
        exit(1);
    }
    if (($summary['source_next_plan']['visible_steps'] ?? null) !== 5) {
        fwrite(STDERR, "unexpected visible current-source RETURNING count\n");
        exit(1);
    }
    echo "application-trigger-recursive-view-returning-current-source-next169 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next169',
    'applicationUse' => 'Copied app_settings imports through an INSTEAD OF recursive view trigger can run a re-entrant current-source RETURNING segment and drain it before staged next-source rows are exposed.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING current-source cursor modeling',
    'summary' => [
        'status' => $summary['status'],
        'cursor' => $summary['cursor'],
        'currentSourceSteps' => $summary['source_next_plan']['current_source_steps'],
        'nestedCurrentSourceSteps' => $summary['source_next_plan']['nested_current_source_steps'],
        'stagedSourceSteps' => $summary['source_next_plan']['staged_source_steps'],
        'visibleSteps' => $summary['source_next_plan']['visible_steps'],
        'heldSteps' => $summary['source_next_plan']['held_steps'],
    ],
];
