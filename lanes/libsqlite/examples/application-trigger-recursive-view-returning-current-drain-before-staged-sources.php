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
    'source' => 'main@view-cookie-165-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-165-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['key_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-165-next';
$nextView['trigger_source'] = 'main@trigger-cookie-165-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentDrainBeforeStagedSources(
    $rows,
    [['root_name' => 'base_url']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'module_root']],
    $view,
    $nextView,
    ['key_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'savepoint' => 'app_recursive_view_next165',
        'cursor_name' => 'app_recursive_view_returning_cursor_165',
        'current_generation' => 'app-import-current-165',
        'first_next_generation' => 'app-import-next-165-a',
        'second_next_generation' => 'app-import-next-165-b',
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status'] ?? null) !== 'trigger-recursive-view-returning-current-source-next-cursor-held-next165') {
        fwrite(STDERR, "unexpected recursive view RETURNING next-cursor status\n");
        exit(1);
    }
    if (($summary['source_next_plan']['current_drained_before_staged'] ?? null) !== true) {
        fwrite(STDERR, "current-source RETURNING rows did not drain before staged next source\n");
        exit(1);
    }
    if (($summary['source_next_plan']['held_steps'] ?? null) !== 3) {
        fwrite(STDERR, "unexpected staged next-source visibility count\n");
        exit(1);
    }
    echo "application-trigger-recursive-view-returning-current-source-next165 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next165',
    'applicationUse' => 'Copied app_settings imports through an INSTEAD OF recursive view trigger can drain current-source RETURNING rows through the cursor next step while staged next source rows stay invisible until the import savepoint explicitly releases them.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING current-source queue and cursor modeling',
    'summary' => [
        'status' => $summary['status'],
        'cursor' => $summary['cursor'],
        'currentSourceSteps' => $summary['source_next_plan']['current_source_steps'],
        'stagedSourceSteps' => $summary['source_next_plan']['staged_source_steps'],
        'heldSteps' => $summary['source_next_plan']['held_steps'],
        'visibleKeys' => $summary['source_next_plan']['visible_keys'],
        'heldKeys' => $summary['source_next_plan']['held_keys'],
    ],
];
