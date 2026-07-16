<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['key_name' => 'module_alpha', 'key_value' => 'enabled', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 10],
    ['key_name' => 'module_beta', 'key_value' => 'disabled', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 15],
    ['key_name' => 'module_beta_child', 'key_value' => 'queued', 'load_policy' => 'no', 'parent_name' => 'module_beta', 'priority' => 30],
];

$view = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-source-generation-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-source-generation-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['key_name'], 'module_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-source-generation-next';
$nextView['trigger_source'] = 'main@trigger-cookie-source-generation-next';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSourceGenerationBarrier(
    $rows,
    [['root_name' => 'base_url']],
    [['root_name' => 'module_beta']],
    $view,
    $nextView,
    ['key_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    ['savepoint' => 'app_recursive_view_source-generation', 'current_generation' => 'app-import-current-source-generation', 'next_generation' => 'app-import-next-source-generation'],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status'] ?? null) !== 'trigger-recursive-view-returning-current-source-barrier-source-generation') {
        fwrite(STDERR, "unexpected recursive view RETURNING barrier status\n");
        exit(1);
    }
    if (($summary['source_barrier']['next_returning_visible'] ?? null) !== 0) {
        fwrite(STDERR, "next-source RETURNING rows leaked before release\n");
        exit(1);
    }
    echo "application-trigger-recursive-view-returning-current-source-source-generation self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-source-generation',
    'applicationUse' => 'Copied app_settings imports through an INSTEAD OF recursive view trigger can drain RETURNING rows from the current source while a next schema/source generation remains attempted-only until the savepoint releases it.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING execution and current-source savepoint modeling',
    'summary' => [
        'status' => $summary['status'],
        'visibleSource' => $summary['source_barrier']['visible_source_after_release'],
        'currentReturning' => $summary['source_barrier']['current_returning_drained'],
        'attemptedNextReturning' => $summary['source_barrier']['next_returning_attempted'],
        'visibleReturningKeys' => $summary['returning_visibility']['visible'],
        'suppressedReturningKeys' => $summary['returning_visibility']['suppressed'],
    ],
];
