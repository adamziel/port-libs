<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows212 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView212 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-212-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-212-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-212',
];
$nextView212 = $currentView212;
$nextView212['source'] = 'main@view-cookie-212-next';
$nextView212['trigger_source'] = 'main@trigger-cookie-212-next';
$postResetView212 = $currentView212;
$postResetView212['source'] = 'main@view-cookie-212-post-reset';
$postResetView212['trigger_source'] = 'main@trigger-cookie-212-post-reset';
$followingView212 = $currentView212;
$followingView212['source'] = 'main@view-cookie-212-following';
$followingView212['trigger_source'] = 'main@trigger-cookie-212-following';
$currentInput212 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput212 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$postResetInput212 = [
    ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$followingInput212 = [
    ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
];
$returning212 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan212 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::currentSourceYieldFence(
    $rows212,
    $currentInput212,
    $nextInput212,
    $currentView212,
    $nextView212,
    $returning212,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_212',
        'cursor_name' => 'app_recursive_view_returning_cursor_212',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.212',
        'reset_generation' => 'app-current-reset-212',
        'post_reset_current_source_token' => 'app.current.source.postreset.212',
        'post_reset_cursor' => 'app.returning.postreset.cursor.212',
        'post_reset_view' => $postResetView212,
        'post_reset_input' => $postResetInput212,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.212',
        'next_cursor' => 'app.returning.next.cursor.212',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.212',
        'following_current_source_token' => 'app.current.source.following.212',
        'following_cursor' => 'app.returning.following.cursor.212',
        'following_current_view' => $followingView212,
        'following_current_input' => $followingInput212,
        'following_generation' => 'app-following-current-212',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.212',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.212',
        'recursive_child_generation' => 'app-recursive-child-current-212',
        'current_generation_next203' => 'app.current.recursive.returning.generation.212',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.212',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.212',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.212',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.212',
        'current_view_cookie_next209' => 'main@view-cookie-212-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-212-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.212',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.212',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.212',
    ],
);

$receipts212 = static fn (): array => $plan212()['required_current_source_yields_next212'];
$released212 = static fn (): array => $plan212(['auto_ack_current_source_yields_next212' => true]);
$missing212 = static fn (): array => $plan212(['acknowledged_current_source_yields_next212' => array_slice($receipts212(), 0, 1)]);
$unexpected212 = static fn (): array => $plan212(['acknowledged_current_source_yields_next212' => array_merge($receipts212(), ['abcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed212 = static fn (): array => $plan212(['acknowledged_current_source_yields_next212' => array_reverse($receipts212())]);
$unordered212 = static fn (): array => $plan212(['require_current_source_yield_order_next212' => false, 'acknowledged_current_source_yields_next212' => array_reverse($receipts212())]);
$baseHeld212 = static fn (): array => $plan212(['auto_ack_current_source_yields_next212' => true, 'auto_ack_current_source_watermarks_next209' => false]);
$custom212 = static fn (): array => $plan212([
    'auto_ack_current_source_yields_next212' => true,
    'current_source_yield_token_next212' => 'app.current.source.yield.custom.212',
    'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.custom.212',
    'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.custom.212',
]);

$cases212 = [
    'released status' => [static fn (): mixed => $released212()['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-released'],
    'missing status' => [static fn (): mixed => $missing212()['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-held'],
    'unexpected status' => [static fn (): mixed => $unexpected212()['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-held'],
    'reversed status' => [static fn (): mixed => $reversed212()['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered212()['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-released'],
    'base held status' => [static fn (): mixed => $baseHeld212()['status_next212'], 'trigger-recursive-view-returning-current-source-next212-base-held'],
    'savepoint retained' => [static fn (): mixed => $released212()['savepoint'], 'app_recursive_view_212'],
    'base next209 released' => [static fn (): mixed => $released212()['base']['status_next209'], 'trigger-recursive-view-returning-current-source-next209-drain-released'],
    'base next209 held' => [static fn (): mixed => $baseHeld212()['base']['status_next209'], 'trigger-recursive-view-returning-current-source-next209-drain-held'],
    'base visible released' => [static fn (): mixed => $released212()['base_next_source_visible_next212'], true],
    'base visible held' => [static fn (): mixed => $baseHeld212()['base_next_source_visible_next212'], false],
    'yield token retained' => [static fn (): mixed => $released212()['current_source_yield_token_next212'], 'app.current.source.yield.212'],
    'custom yield token retained' => [static fn (): mixed => $custom212()['current_source_yield_token_next212'], 'app.current.source.yield.custom.212'],
    'view yield cursor retained' => [static fn (): mixed => $released212()['current_view_yield_cursor_next212'], 'app.returning.view.yield.cursor.212'],
    'custom view yield cursor retained' => [static fn (): mixed => $custom212()['current_view_yield_cursor_next212'], 'app.returning.view.yield.cursor.custom.212'],
    'trigger yield cursor retained' => [static fn (): mixed => $released212()['current_trigger_yield_cursor_next212'], 'app.returning.trigger.yield.cursor.212'],
    'custom trigger yield cursor retained' => [static fn (): mixed => $custom212()['current_trigger_yield_cursor_next212'], 'app.returning.trigger.yield.cursor.custom.212'],
    'required yield count' => [static fn (): mixed => count($released212()['required_current_source_yields_next212']), 2],
    'yield receipts are 34 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{34}$/', $v), $released212()['required_current_source_yields_next212']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released212()['acknowledged_current_source_yields_next212'], $receipts212()],
    'missing acknowledged count' => [static fn (): mixed => count($missing212()['acknowledged_current_source_yields_next212']), 1],
    'missing yield recorded' => [static fn (): mixed => $missing212()['missing_current_source_yields_next212'], [array_slice($receipts212(), -1)[0]]],
    'unexpected yield recorded' => [static fn (): mixed => $unexpected212()['unexpected_current_source_yields_next212'], ['abcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released212()['missing_current_source_yields_next212'], []],
    'released unexpected empty' => [static fn (): mixed => $released212()['unexpected_current_source_yields_next212'], []],
    'require order default' => [static fn (): mixed => $released212()['require_current_source_yield_order_next212'], true],
    'order matches released' => [static fn (): mixed => $released212()['current_source_yield_order_matches_next212'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed212()['current_source_yield_order_matches_next212'], false],
    'unordered disables order' => [static fn (): mixed => $unordered212()['require_current_source_yield_order_next212'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered212()['current_source_yield_order_matches_next212'], true],
    'yield complete released' => [static fn (): mixed => $released212()['current_source_yield_complete_next212'], true],
    'yield incomplete missing' => [static fn (): mixed => $missing212()['current_source_yield_complete_next212'], false],
    'yield incomplete unexpected' => [static fn (): mixed => $unexpected212()['current_source_yield_complete_next212'], false],
    'yield incomplete reversed' => [static fn (): mixed => $reversed212()['current_source_yield_complete_next212'], false],
    'next visible released' => [static fn (): mixed => $released212()['next_source_visible_after_current_source_yield_next212'], true],
    'next denied missing' => [static fn (): mixed => $missing212()['next_source_visible_after_current_source_yield_next212'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected212()['next_source_visible_after_current_source_yield_next212'], false],
    'next denied reversed' => [static fn (): mixed => $reversed212()['next_source_visible_after_current_source_yield_next212'], false],
    'next denied base held' => [static fn (): mixed => $baseHeld212()['next_source_visible_after_current_source_yield_next212'], false],
    'current row count' => [static fn (): mixed => $released212()['current_source_row_count_next212'], 2],
    'attempted next row count' => [static fn (): mixed => $released212()['attempted_next_source_row_count_next212'], 2],
    'visible released count' => [static fn (): mixed => $released212()['visible_row_count_next212'], 4],
    'held released count' => [static fn (): mixed => $released212()['held_next_row_count_next212'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing212()['visible_row_count_next212'], 2],
    'held missing count next only' => [static fn (): mixed => $missing212()['held_next_row_count_next212'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released212()['current_source_rows_next212'], 'source_yield_phase_next212'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released212()['attempted_next_source_rows_next212'], 'source_yield_phase_next212'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing212()['current_source_rows_next212'], 'visible_after_current_source_yield_next212'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released212()['attempted_next_source_rows_next212'], 'visible_after_current_source_yield_next212'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing212()['attempted_next_source_rows_next212'], 'visible_after_current_source_yield_next212'))), [false]],
    'current yield receipts tagged' => [static fn (): mixed => array_column($released212()['current_source_rows_next212'], 'current_source_yield_receipt_next212'), $receipts212()],
    'next yield receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released212()['attempted_next_source_rows_next212'], 'current_source_yield_receipt_next212'))), [null]],
    'current yield token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released212()['current_source_rows_next212'], 'current_source_yield_token_next212'))), ['app.current.source.yield.212']],
    'next yield token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released212()['attempted_next_source_rows_next212'], 'current_source_yield_token_next212'))), ['app.current.source.yield.212']],
    'current view cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released212()['current_source_rows_next212'], 'current_view_yield_cursor_next212'))), ['app.returning.view.yield.cursor.212']],
    'next trigger cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released212()['attempted_next_source_rows_next212'], 'current_trigger_yield_cursor_next212'))), ['app.returning.trigger.yield.cursor.212']],
    'visible payload names released' => [static fn (): mixed => array_column($released212()['visible_returning_payloads_next212'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'held payload names missing' => [static fn (): mixed => array_column($missing212()['held_next_returning_payloads_next212'], 'name'), ['landing_url', 'next_module']],
    'blocked reasons missing' => [static fn (): mixed => $missing212()['blocked_reasons_next212'], ['current-source-yield-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected212()['blocked_reasons_next212'], ['current-source-yield-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed212()['blocked_reasons_next212'], ['current-source-yield-order-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld212()['blocked_reasons_next212'], ['current-source-watermark-missing']],
    'released reasons empty' => [static fn (): mixed => $released212()['blocked_reasons_next212'], []],
    'plan decision released' => [static fn (): mixed => $released212()['current_source_yield_plan_next212']['decision'], 'publish-next-source-after-current-source-yield'],
    'plan decision missing' => [static fn (): mixed => $missing212()['current_source_yield_plan_next212']['decision'], 'hold-next-source-until-current-source-yield'],
    'plan base visible' => [static fn (): mixed => $released212()['current_source_yield_plan_next212']['base_next_source_visible'], true],
    'plan base held' => [static fn (): mixed => $baseHeld212()['current_source_yield_plan_next212']['base_next_source_visible'], false],
    'plan required echoed' => [static fn (): mixed => $released212()['current_source_yield_plan_next212']['required_yields'], $receipts212()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing212()['current_source_yield_plan_next212']['acknowledged_yields'], array_slice($receipts212(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released212()['current_source_yield_plan_next212']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released212()['yield_boundary_next212'], 'recursive-view-returning-next212-current-source-yield-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing212()['yield_boundary_next212'], 'recursive-view-returning-next212-current-source-yield-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released212()['dependency_closure_next212'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-yield-receipts'],
    'dependency includes next212' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next212', $released212()['dependencies_next212'], true), true],
    'dependency includes yield receipt' => [static fn (): mixed => in_array('sqlite-returning-current-source-yield-receipt', $released212()['dependencies_next212'], true), true],
    'dependency includes next209' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next209', $released212()['dependencies_next212'], true), true],
    'non overlap mentions next209' => [static fn (): mixed => str_contains($released212()['non_overlap_next212'], 'next209 drain watermarks'), true],
    'bad yield token rejected' => [static fn (): mixed => $plan212(['current_source_yield_token_next212' => 'bad token']), InvalidArgumentException::class],
    'bad view yield cursor rejected' => [static fn (): mixed => $plan212(['current_view_yield_cursor_next212' => 'bad cursor']), InvalidArgumentException::class],
    'bad trigger yield cursor rejected' => [static fn (): mixed => $plan212(['current_trigger_yield_cursor_next212' => 'bad cursor']), InvalidArgumentException::class],
    'bad yield list rejected' => [static fn (): mixed => $plan212(['acknowledged_current_source_yields_next212' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short yield rejected' => [static fn (): mixed => $plan212(['acknowledged_current_source_yields_next212' => ['abc']]), InvalidArgumentException::class],
    'bad non hex yield rejected' => [static fn (): mixed => $plan212(['acknowledged_current_source_yields_next212' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases212 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next212 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
