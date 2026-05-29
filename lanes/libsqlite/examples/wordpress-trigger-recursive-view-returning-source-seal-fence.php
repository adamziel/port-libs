<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRecursiveViewReturningPlan.php';
foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-211-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-211-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
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
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];
$options = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_211',
    'cursor_name' => 'wp_recursive_view_returning_cursor_211',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.211',
    'reset_generation' => 'wp-current-reset-211',
    'post_reset_current_source_token' => 'wp.current.source.postreset.211',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.211',
    'post_reset_view' => $postResetView,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.211',
    'next_cursor' => 'wp.returning.next.cursor.211',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.211',
    'following_current_source_token' => 'wp.current.source.following.211',
    'following_cursor' => 'wp.returning.following.cursor.211',
    'following_current_view' => $followingView,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-211',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.211',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.211',
    'recursive_child_generation' => 'wp-recursive-child-current-211',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.211',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.211',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.211',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.211',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.211',
    'current_view_cookie_next209' => 'main@view-cookie-211-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-211-current',
    'auto_ack_current_source_watermarks_next209' => true,
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::currentSourceSealFence($rows, $currentInput, $nextInput, $view, $nextView, $returning, $options);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status_next211'] === 'trigger-recursive-view-returning-current-source-next211-source-sealed');
    assert($plan['next_source_visible_after_current_source_seal_next211'] === true);
    assert(array_column($plan['visible_returning_payloads_next211'], 'name') === ['blogdescription_child', 'template_child', 'home', 'next_plugin']);
    assert($plan['blocked_reasons_next211'] === []);
    echo "wordpress-trigger-recursive-view-returning-source-seal-fence self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next211'],
    'visible' => array_column($plan['visible_returning_payloads_next211'], 'name'),
    'sourceSeal' => $plan['current_source_seal_next211'],
    'decision' => $plan['current_source_seal_plan_next211']['decision'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
