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
    'source' => 'main@view-cookie-162-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-162-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_contains((string) $row['key_name'], '_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-162-next';
$nextView['trigger_source'] = 'main@trigger-cookie-162-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeStagedReturningSourceQueue(
    $rows,
    [['root_name' => 'base_url']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'module_root']],
    $view,
    $nextView,
    ['key_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'savepoint' => 'app_recursive_view_next162',
        'current_generation' => 'app-import-current-162',
        'first_next_generation' => 'app-import-next-162-a',
        'second_next_generation' => 'app-import-next-162-b',
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
    echo "application-trigger-recursive-view-returning-current-source-next162 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next162',
    'applicationUse' => 'Copied app_settings imports through an INSTEAD OF recursive view trigger can drain current-source RETURNING rows while multiple staged next-source trigger yields remain queued and invisible until release order admits them.',
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
