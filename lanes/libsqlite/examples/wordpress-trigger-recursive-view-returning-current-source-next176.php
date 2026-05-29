<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-176-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-176-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-page-acks-176',
];
$nextView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-176-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-176-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-page-acks-176',
];
$currentInput = [
    ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
];
$nextInput = [
    ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
];
$returning = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext176(
    $rows,
    $currentInput,
    $nextInput,
    $currentView,
    $nextView,
    $returning,
    [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_176',
        'max_depth' => 2,
        'page_size' => 2,
        'admit_next_source' => true,
        'acknowledged_current_page_indexes' => [0, 1],
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['next_source_admitted_next176'] === true);
    assert($plan['current_page_acknowledgements_valid_next176'] === true);
    assert(array_column($plan['visible_returning_pages_next173'], 'phase') === ['current', 'current', 'next', 'next']);
    echo "wordpress trigger recursive view returning current source next176 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next176'],
    'admitted' => $plan['next_source_admitted_next176'],
    'acknowledged_current_page_indexes' => $plan['acknowledged_current_page_indexes_next176'],
    'visible_page_phases' => array_column($plan['visible_returning_pages_next173'], 'phase'),
    'dependency_closure' => 'no-new-support-component',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
