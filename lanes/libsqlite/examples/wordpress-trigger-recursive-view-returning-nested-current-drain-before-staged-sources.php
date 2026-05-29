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
    'source' => 'main@view-cookie-169-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-169-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['option_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-169-next';
$nextView['trigger_source'] = 'main@trigger-cookie-169-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['option_name'], '_');

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNestedCurrentDrainBeforeStagedSources(
    $rows,
    [['root_name' => 'siteurl']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'plugin_root']],
    $view,
    $nextView,
    ['option_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'savepoint' => 'wp_recursive_view_next169',
        'cursor_name' => 'wp_recursive_view_returning_cursor_169',
        'current_generation' => 'wp-import-current-169',
        'nested_generation' => 'wp-import-current-169-nested',
        'first_next_generation' => 'wp-import-next-169-a',
        'second_next_generation' => 'wp-import-next-169-b',
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
    echo "wordpress-trigger-recursive-view-returning-current-source-next169 self-test passed\n";
}

return [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next169',
    'wordpressUse' => 'Copied wp_options imports through an INSTEAD OF recursive view trigger can run a re-entrant current-source RETURNING segment and drain it before staged next-source rows are exposed.',
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
