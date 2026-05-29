<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'theme_root', 'option_value' => 'theme', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 10],
    ['option_name' => 'theme_child', 'option_value' => 'theme-child', 'autoload' => 'no', 'parent_name' => 'theme_root', 'priority' => 20],
    ['option_name' => 'plugin_root', 'option_value' => 'plugin', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 15],
    ['option_name' => 'plugin_child', 'option_value' => 'plugin-child', 'autoload' => 'no', 'parent_name' => 'plugin_root', 'priority' => 30],
    ['option_name' => 'network_root', 'option_value' => 'network', 'autoload' => 'yes', 'parent_name' => 'plugin_child', 'priority' => 40],
];

$view = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-165-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-165-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['option_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-165-next';
$nextView['trigger_source'] = 'main@trigger-cookie-165-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['option_name'], '_');

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentDrainBeforeStagedSources(
    $rows,
    [['root_name' => 'siteurl']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'plugin_root']],
    $view,
    $nextView,
    ['option_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'savepoint' => 'wp_recursive_view_next165',
        'cursor_name' => 'wp_recursive_view_returning_cursor_165',
        'current_generation' => 'wp-import-current-165',
        'first_next_generation' => 'wp-import-next-165-a',
        'second_next_generation' => 'wp-import-next-165-b',
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
    echo "wordpress-trigger-recursive-view-returning-current-source-next165 self-test passed\n";
}

return [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next165',
    'wordpressUse' => 'Copied wp_options imports through an INSTEAD OF recursive view trigger can drain current-source RETURNING rows through the cursor next step while staged next source rows stay invisible until the import savepoint explicitly releases them.',
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
