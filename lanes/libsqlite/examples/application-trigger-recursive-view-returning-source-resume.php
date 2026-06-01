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
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-195-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-195-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-receipt-195',
];
$nextView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-195-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-195-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-receipt-195',
];
$baseOptions = [
    'key' => 'key_name',
    'savepoint' => 'app_recursive_view_195',
    'max_depth' => 2,
    'page_size' => 2,
    'drained_current_pages' => 2,
    'savepoint_action' => 'release',
    'current_source_epoch' => 19,
    'snapshot_token' => 'app.recursive.view.returning.snapshot.195',
    'expected_snapshot_token' => 'app.recursive.view.returning.snapshot.195',
    'current_schema_cookie' => 195,
    'expected_current_schema_cookie' => 195,
    'current_source_generation' => 'app.recursive.view.current.195',
    'expected_current_source_generation' => 'app.recursive.view.current.195',
    'trigger_source_generation' => 'app.recursive.trigger.current.195',
    'expected_trigger_source_generation' => 'app.recursive.trigger.current.195',
    'returning_cursor_generation' => 'app.recursive.returning.cursor.195',
    'nested_epoch' => 'app.recursive.view.nested.195',
    'expected_nested_epoch' => 'app.recursive.view.nested.195',
    'required_nested_depths' => [1, 2],
    'drained_nested_depths' => [1, 2],
    'current_watermark' => 'app.recursive.view.current.watermark.195',
    'expected_current_watermark' => 'app.recursive.view.current.watermark.195',
    'fingerprint_salt' => 'app.recursive.view.returning.fingerprint.195',
    'expected_fingerprint_salt' => 'app.recursive.view.returning.fingerprint.195',
    'auto_ack_current_fingerprints' => true,
    'current_source_token_source_resume' => 'app.recursive.view.current.source.195',
    'expected_current_source_token_source_resume' => 'app.recursive.view.current.source.195',
    'next_resume_token_source_resume' => 'app.recursive.view.next.resume.195',
    'expected_next_resume_token_source_resume' => 'app.recursive.view.next.resume.195',
];

$plan = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceReceiptResumeFence(
    [
        ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
        ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
    ],
    [
        ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ],
    $currentView,
    $nextView,
    [
        'new.key_name',
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
    $options + $baseOptions,
);

$receipts = $plan()['required_current_source_receipts_source_resume'];
$released = $plan(['acknowledged_current_source_receipts_source_resume' => $receipts]);
$held = $plan(['acknowledged_current_source_receipts_source_resume' => array_slice($receipts, 0, -1)]);

if (
    $released['status_source_resume'] !== 'trigger-recursive-view-returning-current-source-receipts-released-source_resume'
    || $held['status_source_resume'] !== 'trigger-recursive-view-returning-current-source-receipts-held-source_resume'
    || $released['visible_row_count_source_resume'] !== 8
    || $held['held_next_row_count_source_resume'] !== 4
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-source_resume self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-source_resume self-test passed\n";
