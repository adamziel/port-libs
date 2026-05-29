<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'plugin_alpha', 'option_value' => 'enabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 10],
    ['option_name' => 'plugin_beta', 'option_value' => 'disabled', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 15],
    ['option_name' => 'plugin_beta_child', 'option_value' => 'queued', 'autoload' => 'no', 'parent_name' => 'plugin_beta', 'priority' => 30],
];

$view = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-source-generation-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-source-generation-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['option_name'], 'plugin_'),
    'order_by' => 'priority',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-source-generation-next';
$nextView['trigger_source'] = 'main@trigger-cookie-source-generation-next';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSourceGenerationBarrier(
    $rows,
    [['root_name' => 'siteurl']],
    [['root_name' => 'plugin_beta']],
    $view,
    $nextView,
    ['option_name', ['expr' => 'root', 'as' => 'root_name'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    ['savepoint' => 'wp_recursive_view_source-generation', 'current_generation' => 'wp-import-current-source-generation', 'next_generation' => 'wp-import-next-source-generation'],
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
    echo "wordpress-trigger-recursive-view-returning-current-source-source-generation self-test passed\n";
}

return [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-source-generation',
    'wordpressUse' => 'Copied wp_options imports through an INSTEAD OF recursive view trigger can drain RETURNING rows from the current source while a next schema/source generation remains attempted-only until the savepoint releases it.',
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
