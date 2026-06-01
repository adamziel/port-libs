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
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-236-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-236-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-236',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-236-next';
$nextView['trigger_source'] = 'main@trigger-cookie-236-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-236-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-236-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-236-following';
$followingView['trigger_source'] = 'main@trigger-cookie-236-following';

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentRowImageReceipt(
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
    $view,
    $nextView,
    [
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'savepoint' => 'app_recursive_view_236',
        'cursor_name' => 'app_recursive_view_returning_cursor_236',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.236',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.236',
        'next_cursor' => 'app.returning.next.cursor.236',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.236',
        'following_current_source_token' => 'app.current.source.following.236',
        'following_cursor' => 'app.returning.following.cursor.236',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.236',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.236',
        'recursive_child_generation' => 'app-recursive-child-current-236',
        'current_generation_next203' => 'app.current.recursive.returning.generation.236',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.236',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.236',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.236',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.236',
        'current_view_cookie_next209' => 'main@view-cookie-236-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-236-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.236',
        'auto_ack_current_source_epochs_next218' => true,
        'auto_ack_current_returning_source_seals_next224' => true,
        'current_returning_source_generation_next229' => 'app.current.returning.source.generation.236',
        'current_returning_view_generation_next229' => 'main@view-cookie-236-current',
        'current_returning_trigger_generation_next229' => 'main@trigger-cookie-236-current',
        'auto_ack_current_returning_generation_seals_next229' => true,
        'current_upsert_source_token_next233' => 'app.current.upsert.source.236',
        'current_upsert_view_source_next233' => 'main@view-cookie-236-current',
        'current_upsert_trigger_source_next233' => 'main@trigger-cookie-236-current',
        'current_upsert_conflict_target_next233' => ['key_name'],
        'current_upsert_update_columns_next233' => ['key_value', 'load_policy'],
        'auto_ack_current_upsert_seals_next233' => true,
        'current_upsert_row_image_token_next236' => 'app.current.upsert.row.image.236',
        'current_upsert_row_image_view_source_next236' => 'main@view-cookie-236-current',
        'current_upsert_row_image_trigger_source_next236' => 'main@trigger-cookie-236-current',
        'auto_ack_current_upsert_row_image_receipts_next236' => true,
    ],
);

if (
    $summary['status_next236'] !== 'trigger-recursive-view-upsert-current-source-next236-row-image-released'
    || $summary['current_upsert_row_image_plan_next236']['decision'] !== 'publish-next-source-after-current-view-upsert-row-images'
    || $summary['current_upsert_row_images_next236']['names'] !== ['app_summary_child', 'template_child']
    || array_column($summary['visible_returning_payloads_next236'], 'name') !== ['app_summary_child', 'template_child', 'landing_url', 'next_module']
    || $summary['held_next_source_rows_next236'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next236 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next236 self-test passed\n";
