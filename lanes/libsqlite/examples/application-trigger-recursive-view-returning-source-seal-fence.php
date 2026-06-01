<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRecursiveViewReturningPlan.php';
foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-211-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-211-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-211',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-211-next';
$nextView['trigger_source'] = 'main@trigger-cookie-211-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-211-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-211-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-211-following';
$followingView['trigger_source'] = 'main@trigger-cookie-211-following';

$currentInput = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];
$options = [
    'key' => 'key_name',
    'savepoint' => 'app_recursive_view_211',
    'cursor_name' => 'app_recursive_view_returning_cursor_211',
    'admit_next_source' => true,
    'rollback_token' => 'app.rollback.current.211',
    'reset_generation' => 'app-current-reset-211',
    'post_reset_current_source_token' => 'app.current.source.postreset.211',
    'post_reset_cursor' => 'app.returning.postreset.cursor.211',
    'post_reset_view' => $postResetView,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'app.next.source.211',
    'next_cursor' => 'app.returning.next.cursor.211',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'app.returning.next.cursor.211',
    'following_current_source_token' => 'app.current.source.following.211',
    'following_cursor' => 'app.returning.following.cursor.211',
    'following_current_view' => $followingView,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'app-following-current-211',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'app.current.source.recursive.child.211',
    'recursive_child_cursor' => 'app.returning.recursive.child.cursor.211',
    'recursive_child_generation' => 'app-recursive-child-current-211',
    'current_generation_next203' => 'app.current.recursive.returning.generation.211',
    'expected_current_generation_next203' => 'app.current.recursive.returning.generation.211',
    'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.211',
    'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.211',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'app.current.source.drain.211',
    'current_view_cookie_next209' => 'main@view-cookie-211-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-211-current',
    'auto_ack_current_source_watermarks_next209' => true,
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::currentSourceSealFence($rows, $currentInput, $nextInput, $view, $nextView, $returning, $options);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status_next211'] === 'trigger-recursive-view-returning-current-source-next211-source-sealed');
    assert($plan['next_source_visible_after_current_source_seal_next211'] === true);
    assert(array_column($plan['visible_returning_payloads_next211'], 'name') === ['app_summary_child', 'template_child', 'landing_url', 'next_module']);
    assert($plan['blocked_reasons_next211'] === []);
    echo "application-trigger-recursive-view-returning-source-seal-fence self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next211'],
    'visible' => array_column($plan['visible_returning_payloads_next211'], 'name'),
    'sourceSeal' => $plan['current_source_seal_next211'],
    'decision' => $plan['current_source_seal_plan_next211']['decision'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
