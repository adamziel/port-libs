<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows231 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView231 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-231-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-231-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-231',
];
$nextView231 = $currentView231;
$nextView231['source'] = 'main@view-cookie-231-next';
$nextView231['trigger_source'] = 'main@trigger-cookie-231-next';
$postResetView231 = $currentView231;
$postResetView231['source'] = 'main@view-cookie-231-post-reset';
$postResetView231['trigger_source'] = 'main@trigger-cookie-231-post-reset';
$followingView231 = $currentView231;
$followingView231['source'] = 'main@view-cookie-231-following';
$followingView231['trigger_source'] = 'main@trigger-cookie-231-following';
$currentInput231 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput231 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning231 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan231 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext231(
    $rows231,
    $currentInput231,
    $nextInput231,
    $currentView231,
    $nextView231,
    $returning231,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_231',
        'cursor_name' => 'wp_recursive_view_returning_cursor_231',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.231',
        'reset_generation' => 'wp-current-reset-231',
        'post_reset_current_source_token' => 'wp.current.source.postreset.231',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.231',
        'post_reset_view' => $postResetView231,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.231',
        'next_cursor' => 'wp.returning.next.cursor.231',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.231',
        'following_current_source_token' => 'wp.current.source.following.231',
        'following_cursor' => 'wp.returning.following.cursor.231',
        'following_current_view' => $followingView231,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-231',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.231',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.231',
        'recursive_child_generation' => 'wp-recursive-child-current-231',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.231',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.231',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.231',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.231',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.231',
        'current_view_cookie_next209' => 'main@view-cookie-231-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-231-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.231',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.231',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.231',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.231',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.231',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.231',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.231',
        'current_view_source_next222' => 'main@view-cookie-231-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-231-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.231',
        'current_source_close_token_next231' => 'wp.current.source.close.231',
        'current_view_cookie_next231' => 'main@view-cookie-231-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-231-current',
    ],
);

$closures231 = static fn (): array => $plan231()['required_current_source_closures_next231'];
$released231 = static fn (): array => $plan231(['auto_ack_current_source_closures_next231' => true]);
$missing231 = static fn (): array => $plan231(['acknowledged_current_source_closures_next231' => array_slice($closures231(), 0, 1)]);
$unexpected231 = static fn (): array => $plan231(['acknowledged_current_source_closures_next231' => array_merge($closures231(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'])]);
$reversed231 = static fn (): array => $plan231(['acknowledged_current_source_closures_next231' => array_reverse($closures231())]);
$unordered231 = static fn (): array => $plan231(['require_current_source_close_order_next231' => false, 'acknowledged_current_source_closures_next231' => array_reverse($closures231())]);
$closeMismatch231 = static fn (): array => $plan231(['auto_ack_current_source_closures_next231' => true, 'expected_current_source_close_token_next231' => 'wp.current.source.close.stale.231']);
$baseHeld231 = static fn (): array => $plan231(['auto_ack_current_source_closures_next231' => true, 'auto_ack_current_source_tickets_next222' => false]);
$custom231 = static fn (): array => $plan231([
    'auto_ack_current_source_closures_next231' => true,
    'current_source_cursor_next231' => 'wp.returning.current.cursor.custom.231',
    'current_source_close_token_next231' => 'wp.current.source.close.custom.231',
    'current_view_cookie_next231' => 'main@view-cookie-231-custom',
    'current_trigger_cookie_next231' => 'main@trigger-cookie-231-custom',
]);

$cases231 = [
    'released status' => [static fn (): mixed => $released231()['status_next231'], 'trigger-recursive-view-returning-current-source-next231-cursor-close-released'],
    'missing status' => [static fn (): mixed => $missing231()['status_next231'], 'trigger-recursive-view-returning-current-source-next231-cursor-close-held'],
    'unexpected status' => [static fn (): mixed => $unexpected231()['status_next231'], 'trigger-recursive-view-returning-current-source-next231-cursor-close-held'],
    'reversed status' => [static fn (): mixed => $reversed231()['status_next231'], 'trigger-recursive-view-returning-current-source-next231-cursor-close-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered231()['status_next231'], 'trigger-recursive-view-returning-current-source-next231-cursor-close-released'],
    'close mismatch status' => [static fn (): mixed => $closeMismatch231()['status_next231'], 'trigger-recursive-view-returning-current-source-next231-close-token-held'],
    'base held status' => [static fn (): mixed => $baseHeld231()['status_next231'], 'trigger-recursive-view-returning-current-source-next231-base-held'],
    'savepoint retained' => [static fn (): mixed => $released231()['savepoint'], 'wp_recursive_view_231'],
    'base next222 released' => [static fn (): mixed => $released231()['base']['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-ticket-released'],
    'base next222 held' => [static fn (): mixed => $baseHeld231()['base']['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-ticket-held'],
    'base visible released' => [static fn (): mixed => $released231()['base_next_source_visible_next231'], true],
    'base visible held' => [static fn (): mixed => $baseHeld231()['base_next_source_visible_next231'], false],
    'cursor retained' => [static fn (): mixed => $released231()['current_source_cursor_next231'], 'wp.returning.current.cursor.231'],
    'custom cursor retained' => [static fn (): mixed => $custom231()['current_source_cursor_next231'], 'wp.returning.current.cursor.custom.231'],
    'close token retained' => [static fn (): mixed => $released231()['current_source_close_token_next231'], 'wp.current.source.close.231'],
    'custom close token retained' => [static fn (): mixed => $custom231()['current_source_close_token_next231'], 'wp.current.source.close.custom.231'],
    'view cookie retained' => [static fn (): mixed => $released231()['current_view_cookie_next231'], 'main@view-cookie-231-current'],
    'custom view cookie retained' => [static fn (): mixed => $custom231()['current_view_cookie_next231'], 'main@view-cookie-231-custom'],
    'trigger cookie retained' => [static fn (): mixed => $released231()['current_trigger_cookie_next231'], 'main@trigger-cookie-231-current'],
    'custom trigger cookie retained' => [static fn (): mixed => $custom231()['current_trigger_cookie_next231'], 'main@trigger-cookie-231-custom'],
    'close matches released' => [static fn (): mixed => $released231()['current_source_close_matches_next231'], true],
    'close mismatch detected' => [static fn (): mixed => $closeMismatch231()['current_source_close_matches_next231'], false],
    'required closure count' => [static fn (): mixed => count($released231()['required_current_source_closures_next231']), 2],
    'closures are forty eight hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{48}$/', $v), $released231()['required_current_source_closures_next231']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released231()['acknowledged_current_source_closures_next231'], $closures231()],
    'missing acknowledged count' => [static fn (): mixed => count($missing231()['acknowledged_current_source_closures_next231']), 1],
    'missing closure recorded' => [static fn (): mixed => $missing231()['missing_current_source_closures_next231'], [array_slice($closures231(), -1)[0]]],
    'unexpected closure recorded' => [static fn (): mixed => $unexpected231()['unexpected_current_source_closures_next231'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef']],
    'released missing empty' => [static fn (): mixed => $released231()['missing_current_source_closures_next231'], []],
    'released unexpected empty' => [static fn (): mixed => $released231()['unexpected_current_source_closures_next231'], []],
    'require order default' => [static fn (): mixed => $released231()['require_current_source_close_order_next231'], true],
    'order matches released' => [static fn (): mixed => $released231()['current_source_close_order_matches_next231'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed231()['current_source_close_order_matches_next231'], false],
    'unordered disables order' => [static fn (): mixed => $unordered231()['require_current_source_close_order_next231'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered231()['current_source_close_order_matches_next231'], true],
    'close complete released' => [static fn (): mixed => $released231()['current_source_close_complete_next231'], true],
    'close incomplete missing' => [static fn (): mixed => $missing231()['current_source_close_complete_next231'], false],
    'close incomplete unexpected' => [static fn (): mixed => $unexpected231()['current_source_close_complete_next231'], false],
    'close incomplete reversed' => [static fn (): mixed => $reversed231()['current_source_close_complete_next231'], false],
    'close incomplete mismatch' => [static fn (): mixed => $closeMismatch231()['current_source_close_complete_next231'], false],
    'next visible released' => [static fn (): mixed => $released231()['next_source_visible_after_current_source_close_next231'], true],
    'next denied missing' => [static fn (): mixed => $missing231()['next_source_visible_after_current_source_close_next231'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected231()['next_source_visible_after_current_source_close_next231'], false],
    'next denied reversed' => [static fn (): mixed => $reversed231()['next_source_visible_after_current_source_close_next231'], false],
    'next denied close mismatch' => [static fn (): mixed => $closeMismatch231()['next_source_visible_after_current_source_close_next231'], false],
    'current row count' => [static fn (): mixed => $released231()['current_source_row_count_next231'], 2],
    'attempted next row count' => [static fn (): mixed => $released231()['attempted_next_source_row_count_next231'], 2],
    'visible released count' => [static fn (): mixed => $released231()['visible_row_count_next231'], 4],
    'held released count' => [static fn (): mixed => $released231()['held_next_row_count_next231'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing231()['visible_row_count_next231'], 2],
    'held missing count next only' => [static fn (): mixed => $missing231()['held_next_row_count_next231'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released231()['current_source_rows_next231'], 'source_close_phase_next231'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released231()['attempted_next_source_rows_next231'], 'source_close_phase_next231'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing231()['current_source_rows_next231'], 'visible_after_current_source_close_next231'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released231()['attempted_next_source_rows_next231'], 'visible_after_current_source_close_next231'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing231()['attempted_next_source_rows_next231'], 'visible_after_current_source_close_next231'))), [false]],
    'current closures tagged' => [static fn (): mixed => array_column($released231()['current_source_rows_next231'], 'current_source_close_receipt_next231'), $closures231()],
    'next closures null' => [static fn (): mixed => array_values(array_unique(array_column($released231()['attempted_next_source_rows_next231'], 'current_source_close_receipt_next231'))), [null]],
    'current cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released231()['current_source_rows_next231'], 'current_source_cursor_next231'))), ['wp.returning.current.cursor.231']],
    'next close token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released231()['attempted_next_source_rows_next231'], 'current_source_close_token_next231'))), ['wp.current.source.close.231']],
    'current view cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($released231()['current_source_rows_next231'], 'current_view_cookie_next231'))), ['main@view-cookie-231-current']],
    'next trigger cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($released231()['attempted_next_source_rows_next231'], 'current_trigger_cookie_next231'))), ['main@trigger-cookie-231-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released231()['visible_returning_payloads_next231'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing231()['held_next_returning_payloads_next231'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing231()['blocked_reasons_next231'], ['current-source-close-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected231()['blocked_reasons_next231'], ['current-source-close-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed231()['blocked_reasons_next231'], ['current-source-close-order-mismatch']],
    'blocked reasons close mismatch' => [static fn (): mixed => $closeMismatch231()['blocked_reasons_next231'], ['current-source-close-token-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld231()['blocked_reasons_next231'], ['current-source-ticket-missing']],
    'released reasons empty' => [static fn (): mixed => $released231()['blocked_reasons_next231'], []],
    'plan decision released' => [static fn (): mixed => $released231()['current_source_close_plan_next231']['decision'], 'publish-next-source-after-current-returning-cursor-close'],
    'plan decision missing' => [static fn (): mixed => $missing231()['current_source_close_plan_next231']['decision'], 'hold-next-source-until-current-returning-cursor-close'],
    'plan base visible' => [static fn (): mixed => $released231()['current_source_close_plan_next231']['base_next_source_visible'], true],
    'plan required echoed' => [static fn (): mixed => $released231()['current_source_close_plan_next231']['required_closures'], $closures231()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing231()['current_source_close_plan_next231']['acknowledged_closures'], array_slice($closures231(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released231()['current_source_close_plan_next231']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released231()['yield_boundary_next231'], 'recursive-view-returning-next231-current-cursor-close-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing231()['yield_boundary_next231'], 'recursive-view-returning-next231-current-cursor-close-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released231()['dependency_closure_next231'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-close-handoff'],
    'dependency includes next231' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next231', $released231()['dependencies_next231'], true), true],
    'dependency includes close receipt' => [static fn (): mixed => in_array('sqlite-returning-current-source-cursor-close-handoff', $released231()['dependencies_next231'], true), true],
    'dependency includes next222' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next222', $released231()['dependencies_next231'], true), true],
    'non overlap mentions next222' => [static fn (): mixed => str_contains($released231()['non_overlap_next231'], 'next222 source-ticket handoff'), true],
    'bad cursor rejected' => [static fn (): mixed => $plan231(['current_source_cursor_next231' => 'bad cursor']), InvalidArgumentException::class],
    'bad close token rejected' => [static fn (): mixed => $plan231(['current_source_close_token_next231' => 'bad token']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $plan231(['current_view_cookie_next231' => 'bad cookie']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $plan231(['current_trigger_cookie_next231' => 'bad cookie']), InvalidArgumentException::class],
    'bad closure list rejected' => [static fn (): mixed => $plan231(['acknowledged_current_source_closures_next231' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef']]), InvalidArgumentException::class],
    'bad short closure rejected' => [static fn (): mixed => $plan231(['acknowledged_current_source_closures_next231' => ['abc']]), InvalidArgumentException::class],
    'bad non hex closure rejected' => [static fn (): mixed => $plan231(['acknowledged_current_source_closures_next231' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases231 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next231 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
