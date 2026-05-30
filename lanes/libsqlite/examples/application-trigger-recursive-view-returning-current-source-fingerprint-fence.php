<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-191-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-191-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-fingerprint-191',
];
$nextView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-191-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-191-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-fingerprint-191',
];
$baseOptions = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_191',
    'max_depth' => 2,
    'page_size' => 2,
    'drained_current_pages' => 2,
    'savepoint_action' => 'release',
    'current_source_epoch' => 19,
    'snapshot_token' => 'wp.recursive.view.returning.snapshot.191',
    'expected_snapshot_token' => 'wp.recursive.view.returning.snapshot.191',
    'current_schema_cookie' => 191,
    'expected_current_schema_cookie' => 191,
    'current_source_generation' => 'wp.recursive.view.current.191',
    'expected_current_source_generation' => 'wp.recursive.view.current.191',
    'trigger_source_generation' => 'wp.recursive.trigger.current.191',
    'expected_trigger_source_generation' => 'wp.recursive.trigger.current.191',
    'returning_cursor_generation' => 'wp.recursive.returning.cursor.191',
    'nested_epoch' => 'wp.recursive.view.nested.191',
    'expected_nested_epoch' => 'wp.recursive.view.nested.191',
    'required_nested_depths' => [1, 2],
    'drained_nested_depths' => [1, 2],
    'current_watermark' => 'wp.recursive.view.current.watermark.191',
    'expected_current_watermark' => 'wp.recursive.view.current.watermark.191',
    'fingerprint_salt' => 'wp.recursive.view.returning.fingerprint.191',
    'expected_fingerprint_salt' => 'wp.recursive.view.returning.fingerprint.191',
];

$plan = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceFingerprintFence(
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

$fingerprints = $plan()['required_current_fingerprints_next191'];
$released = $plan(['acknowledged_current_fingerprints' => $fingerprints]);
$held = $plan(['acknowledged_current_fingerprints' => array_slice($fingerprints, 0, -1)]);

if (
    $released['status_next191'] !== 'trigger-recursive-view-returning-current-source-fingerprints-released-next191'
    || $held['status_next191'] !== 'trigger-recursive-view-returning-current-source-fingerprints-held-next191'
    || $released['visible_row_count_next191'] !== 8
    || $held['held_next_row_count_next191'] !== 4
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-fingerprint-fence self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-fingerprint-fence self-test passed\n";
