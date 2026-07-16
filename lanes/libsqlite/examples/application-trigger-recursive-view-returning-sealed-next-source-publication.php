<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-193-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-193-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-193',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-193-next';
$nextView['trigger_source'] = 'main@trigger-cookie-193-next';
$nextView['audit_label'] = 'next-recursive-view-trigger-193';
$postResetView = $currentView;
$postResetView['source'] = 'main@view-cookie-193-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-193-post-reset';
$postResetView['audit_label'] = 'post-reset-recursive-view-trigger-193';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSealedNextSourcePublication(
    [
        ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
        ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
    ],
    [
        ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
    ],
    [
        ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
    ],
    $currentView,
    $nextView,
    [
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
    [
        'savepoint' => 'app_recursive_view_193',
        'cursor_name' => 'app_recursive_view_returning_cursor_193',
        'admit_next_source' => true,
        'reset_generation' => 'app-current-reset-193',
        'post_reset_current_source_token' => 'app.current.source.postreset.193',
        'post_reset_cursor' => 'app.returning.postreset.cursor.193',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes'],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no'],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.193',
        'next_cursor' => 'app.returning.next.cursor.193',
        'handoff_token' => 'app.recursive.view.returning.handoff.193',
    ],
);

if (
    $summary['status_next193'] !== 'trigger-recursive-view-returning-current-source-next193-published'
    || $summary['published_next_source_row_count_next193'] !== 2
    || array_column($summary['published_next_source_payloads_next193'], 'name') !== ['landing_url', 'next_module']
    || $summary['current_source_returning_handoff_next193']['decision'] !== 'publish-sealed-next-source-after-current-drain'
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-sealed-next-source-publication self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-sealed-next-source-publication self-test passed\n";
