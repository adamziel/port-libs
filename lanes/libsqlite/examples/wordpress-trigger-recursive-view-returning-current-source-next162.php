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
    'source' => 'main@view-cookie-162-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-162-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_contains((string) $row['option_name'], '_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-162-next';
$nextView['trigger_source'] = 'main@trigger-cookie-162-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['option_name'], '_');

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext162(
    $rows,
    [['root_name' => 'siteurl']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'plugin_root']],
    $view,
    $nextView,
    ['option_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'savepoint' => 'wp_recursive_view_next162',
        'current_generation' => 'wp-import-current-162',
        'first_next_generation' => 'wp-import-next-162-a',
        'second_next_generation' => 'wp-import-next-162-b',
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status'] ?? null) !== 'trigger-recursive-view-returning-current-source-queue-held-next162') {
        fwrite(STDERR, "unexpected recursive view RETURNING next162 queue status\n");
        exit(1);
    }
    if (($summary['returning_visibility']['suppressed_count'] ?? null) !== 3) {
        fwrite(STDERR, "staged next-source RETURNING rows were not held at the queue\n");
        exit(1);
    }
    echo "wordpress-trigger-recursive-view-returning-current-source-next162 self-test passed\n";
}

return [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next162',
    'wordpressUse' => 'Copied wp_options imports through an INSTEAD OF recursive view trigger can drain current-source RETURNING rows while multiple staged next-source trigger yields remain queued and invisible until release order admits them.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING current-source barriers',
    'summary' => [
        'status' => $summary['status'],
        'visibleGeneration' => $summary['visible_generation'],
        'stagedGenerations' => $summary['staged_generations'],
        'visibleReturning' => $summary['returning_visibility']['visible_count'],
        'suppressedReturning' => $summary['returning_visibility']['suppressed_count'],
        'queue' => $summary['next_source_queue'],
    ],
];
