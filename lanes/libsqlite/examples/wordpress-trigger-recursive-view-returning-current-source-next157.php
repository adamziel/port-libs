<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null],
    ['option_name' => 'plugin_alpha', 'option_value' => 'enabled', 'autoload' => 'yes', 'parent_name' => 'siteurl'],
    ['option_name' => 'plugin_alpha_child', 'option_value' => 'child-on', 'autoload' => 'no', 'parent_name' => 'plugin_alpha'],
    ['option_name' => 'plugin_beta', 'option_value' => 'disabled', 'autoload' => 'yes', 'parent_name' => 'siteurl'],
    ['option_name' => 'plugin_next', 'option_value' => 'next-on', 'autoload' => 'yes', 'parent_name' => 'plugin_beta'],
];

$view = [
    'name' => 'wp_recursive_option_view',
    'source' => 'main@view-cookie-157-current',
    'trigger' => 'wp_recursive_option_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-157-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['option_name'], 'plugin_'),
    'order_by' => 'option_name',
];
$nextView = array_replace($view, [
    'source' => 'main@view-cookie-157-next',
    'trigger_source' => 'main@trigger-cookie-157-next',
]);

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext157(
    $rows,
    [['root_name' => 'siteurl']],
    [['root_name' => 'plugin_beta']],
    $view,
    $nextView,
    [
        'option_name',
        ['expr' => 'root', 'as' => 'root_name'],
        ['expr' => 'depth', 'as' => 'recursive_depth'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'trigger-recursive-view-returning-current-source-pinned-next157');
    assert(count($plan['current_returning_rows']) === 4);
    assert($plan['next_returning_rows'] === []);
    assert($plan['attempted_next_returning_rows'][0]['returning']['option_name'] === 'audit:next:plugin_beta:plugin_next');
    echo "wordpress-trigger-recursive-view-returning-current-source-next157 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next157',
    'status' => $plan['status'],
    'currentReturning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'option_name'),
    'attemptedNextReturning' => array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'option_name'),
    'yieldBoundary' => $plan['yield_boundary'],
], JSON_PRETTY_PRINT) . "\n";
