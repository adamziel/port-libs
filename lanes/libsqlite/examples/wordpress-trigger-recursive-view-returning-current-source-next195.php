<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-195-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-195-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-receipt-195',
];
$nextView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-195-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-195-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-receipt-195',
];
$baseOptions = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_195',
    'max_depth' => 2,
    'page_size' => 2,
    'drained_current_pages' => 2,
    'savepoint_action' => 'release',
    'current_source_epoch' => 19,
    'snapshot_token' => 'wp.recursive.view.returning.snapshot.195',
    'expected_snapshot_token' => 'wp.recursive.view.returning.snapshot.195',
    'current_schema_cookie' => 195,
    'expected_current_schema_cookie' => 195,
    'current_source_generation' => 'wp.recursive.view.current.195',
    'expected_current_source_generation' => 'wp.recursive.view.current.195',
    'trigger_source_generation' => 'wp.recursive.trigger.current.195',
    'expected_trigger_source_generation' => 'wp.recursive.trigger.current.195',
    'returning_cursor_generation' => 'wp.recursive.returning.cursor.195',
    'nested_epoch' => 'wp.recursive.view.nested.195',
    'expected_nested_epoch' => 'wp.recursive.view.nested.195',
    'required_nested_depths' => [1, 2],
    'drained_nested_depths' => [1, 2],
    'current_watermark' => 'wp.recursive.view.current.watermark.195',
    'expected_current_watermark' => 'wp.recursive.view.current.watermark.195',
    'fingerprint_salt' => 'wp.recursive.view.returning.fingerprint.195',
    'expected_fingerprint_salt' => 'wp.recursive.view.returning.fingerprint.195',
    'auto_ack_current_fingerprints' => true,
    'current_source_token_next195' => 'wp.recursive.view.current.source.195',
    'expected_current_source_token_next195' => 'wp.recursive.view.current.source.195',
    'next_resume_token_next195' => 'wp.recursive.view.next.resume.195',
    'expected_next_resume_token_next195' => 'wp.recursive.view.next.resume.195',
];

$plan = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext195(
    [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ],
    [
        ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ],
    $currentView,
    $nextView,
    [
        'new.option_name',
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
    $options + $baseOptions,
);

$receipts = $plan()['required_current_source_receipts_next195'];
$released = $plan(['acknowledged_current_source_receipts_next195' => $receipts]);
$held = $plan(['acknowledged_current_source_receipts_next195' => array_slice($receipts, 0, -1)]);

if (
    $released['status_next195'] !== 'trigger-recursive-view-returning-current-source-receipts-released-next195'
    || $held['status_next195'] !== 'trigger-recursive-view-returning-current-source-receipts-held-next195'
    || $released['visible_row_count_next195'] !== 8
    || $held['held_next_row_count_next195'] !== 4
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next195 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next195 self-test passed\n";
