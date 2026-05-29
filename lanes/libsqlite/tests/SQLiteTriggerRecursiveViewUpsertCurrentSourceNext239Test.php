<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows239 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView239 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-239-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-239-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-239',
];
$nextView239 = $currentView239;
$nextView239['source'] = 'main@view-cookie-239-next';
$nextView239['trigger_source'] = 'main@trigger-cookie-239-next';
$postResetView239 = $currentView239;
$postResetView239['source'] = 'main@view-cookie-239-post-reset';
$postResetView239['trigger_source'] = 'main@trigger-cookie-239-post-reset';
$followingView239 = $currentView239;
$followingView239['source'] = 'main@view-cookie-239-following';
$followingView239['trigger_source'] = 'main@trigger-cookie-239-following';
$currentInput239 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput239 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning239 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$baseOptions239 = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_239',
    'cursor_name' => 'wp_recursive_view_returning_cursor_239',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.239',
    'reset_generation' => 'wp-current-reset-239',
    'post_reset_current_source_token' => 'wp.current.source.postreset.239',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.239',
    'post_reset_view' => $postResetView239,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.239',
    'next_cursor' => 'wp.returning.next.cursor.239',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.239',
    'following_current_source_token' => 'wp.current.source.following.239',
    'following_cursor' => 'wp.returning.following.cursor.239',
    'following_current_view' => $followingView239,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-239',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.239',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.239',
    'recursive_child_generation' => 'wp-recursive-child-current-239',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.239',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.239',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.239',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.239',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.239',
    'current_view_cookie_next209' => 'main@view-cookie-239-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-239-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'wp.current.source.yield.239',
    'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.239',
    'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.239',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.239',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.239',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.239',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'wp.current.source.ticket.239',
    'current_view_source_next222' => 'main@view-cookie-239-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-239-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_source_close' => 'wp.returning.current.cursor.239',
    'current_source_close_token_source_close' => 'wp.current.source.close.239',
    'current_view_cookie_source_close' => 'main@view-cookie-239-current',
    'current_trigger_cookie_source_close' => 'main@trigger-cookie-239-current',
    'auto_ack_current_source_closures_source_close' => true,
    'current_source_upsert_target_next239' => 'option_name',
    'current_source_upsert_policy_next239' => 'do-update-returning',
    'current_source_upsert_cursor_next239' => 'wp.returning.upsert.cursor.239',
    'current_source_upsert_generation_next239' => 'wp.current.source.upsert.generation.239',
];

$plan239 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentTargetReceipt(
    $rows239,
    $currentInput239,
    $nextInput239,
    $currentView239,
    $nextView239,
    $returning239,
    $options + $baseOptions239,
);

$targets239 = static fn (): array => $plan239()['required_current_source_upsert_targets_next239'];
$released239 = static fn (): array => $plan239(['auto_ack_current_source_upsert_targets_next239' => true]);
$missing239 = static fn (): array => $plan239(['acknowledged_current_source_upsert_targets_next239' => array_slice($targets239(), 0, 1)]);
$unexpected239 = static fn (): array => $plan239(['acknowledged_current_source_upsert_targets_next239' => array_merge($targets239(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed239 = static fn (): array => $plan239(['acknowledged_current_source_upsert_targets_next239' => array_reverse($targets239())]);
$unordered239 = static fn (): array => $plan239(['require_current_source_upsert_order_next239' => false, 'acknowledged_current_source_upsert_targets_next239' => array_reverse($targets239())]);
$generationHeld239 = static fn (): array => $plan239(['auto_ack_current_source_upsert_targets_next239' => true, 'expected_current_source_upsert_generation_next239' => 'wp.current.source.upsert.generation.stale.239']);
$baseHeld239 = static fn (): array => $plan239(['auto_ack_current_source_upsert_targets_next239' => true, 'auto_ack_current_source_closures_source_close' => false]);
$custom239 = static fn (): array => $plan239([
    'auto_ack_current_source_upsert_targets_next239' => true,
    'current_source_upsert_target_next239' => 'option_name_autoload',
    'current_source_upsert_policy_next239' => 'do-nothing-returning',
    'current_source_upsert_cursor_next239' => 'wp.returning.upsert.cursor.custom.239',
    'current_source_upsert_generation_next239' => 'wp.current.source.upsert.generation.custom.239',
]);

$cases239 = [
    'released status' => [static fn (): mixed => $released239()['status_next239'], 'trigger-recursive-view-upsert-current-source-next239-targets-released'],
    'missing status' => [static fn (): mixed => $missing239()['status_next239'], 'trigger-recursive-view-upsert-current-source-next239-targets-held'],
    'unexpected status' => [static fn (): mixed => $unexpected239()['status_next239'], 'trigger-recursive-view-upsert-current-source-next239-targets-held'],
    'reversed status' => [static fn (): mixed => $reversed239()['status_next239'], 'trigger-recursive-view-upsert-current-source-next239-target-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered239()['status_next239'], 'trigger-recursive-view-upsert-current-source-next239-targets-released'],
    'generation mismatch status' => [static fn (): mixed => $generationHeld239()['status_next239'], 'trigger-recursive-view-upsert-current-source-next239-generation-held'],
    'base held status' => [static fn (): mixed => $baseHeld239()['status_next239'], 'trigger-recursive-view-upsert-current-source-next239-base-held'],
    'savepoint retained' => [static fn (): mixed => $released239()['savepoint'], 'wp_recursive_view_239'],
    'base next231 released' => [static fn (): mixed => $released239()['base']['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-cursor-close-released'],
    'base next231 held' => [static fn (): mixed => $baseHeld239()['base']['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-cursor-close-held'],
    'base visible released' => [static fn (): mixed => $released239()['base_next_source_visible_next239'], true],
    'base visible held' => [static fn (): mixed => $baseHeld239()['base_next_source_visible_next239'], false],
    'target retained' => [static fn (): mixed => $released239()['current_source_upsert_target_next239'], 'option_name'],
    'custom target retained' => [static fn (): mixed => $custom239()['current_source_upsert_target_next239'], 'option_name_autoload'],
    'policy retained' => [static fn (): mixed => $released239()['current_source_upsert_policy_next239'], 'do-update-returning'],
    'custom policy retained' => [static fn (): mixed => $custom239()['current_source_upsert_policy_next239'], 'do-nothing-returning'],
    'cursor retained' => [static fn (): mixed => $released239()['current_source_upsert_cursor_next239'], 'wp.returning.upsert.cursor.239'],
    'custom cursor retained' => [static fn (): mixed => $custom239()['current_source_upsert_cursor_next239'], 'wp.returning.upsert.cursor.custom.239'],
    'generation retained' => [static fn (): mixed => $released239()['current_source_upsert_generation_next239'], 'wp.current.source.upsert.generation.239'],
    'custom generation retained' => [static fn (): mixed => $custom239()['current_source_upsert_generation_next239'], 'wp.current.source.upsert.generation.custom.239'],
    'generation matches released' => [static fn (): mixed => $released239()['current_source_upsert_generation_matches_next239'], true],
    'generation mismatch detected' => [static fn (): mixed => $generationHeld239()['current_source_upsert_generation_matches_next239'], false],
    'required target count' => [static fn (): mixed => count($released239()['required_current_source_upsert_targets_next239']), 2],
    'targets are forty hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{40}$/', $v), $released239()['required_current_source_upsert_targets_next239']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released239()['acknowledged_current_source_upsert_targets_next239'], $targets239()],
    'missing acknowledged count' => [static fn (): mixed => count($missing239()['acknowledged_current_source_upsert_targets_next239']), 1],
    'missing target recorded' => [static fn (): mixed => $missing239()['missing_current_source_upsert_targets_next239'], [array_slice($targets239(), -1)[0]]],
    'unexpected target recorded' => [static fn (): mixed => $unexpected239()['unexpected_current_source_upsert_targets_next239'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released239()['missing_current_source_upsert_targets_next239'], []],
    'released unexpected empty' => [static fn (): mixed => $released239()['unexpected_current_source_upsert_targets_next239'], []],
    'require order default' => [static fn (): mixed => $released239()['require_current_source_upsert_order_next239'], true],
    'order matches released' => [static fn (): mixed => $released239()['current_source_upsert_order_matches_next239'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed239()['current_source_upsert_order_matches_next239'], false],
    'unordered disables order' => [static fn (): mixed => $unordered239()['require_current_source_upsert_order_next239'], false],
    'upsert complete released' => [static fn (): mixed => $released239()['current_source_upsert_complete_next239'], true],
    'upsert incomplete missing' => [static fn (): mixed => $missing239()['current_source_upsert_complete_next239'], false],
    'upsert incomplete unexpected' => [static fn (): mixed => $unexpected239()['current_source_upsert_complete_next239'], false],
    'upsert incomplete reversed' => [static fn (): mixed => $reversed239()['current_source_upsert_complete_next239'], false],
    'upsert incomplete generation' => [static fn (): mixed => $generationHeld239()['current_source_upsert_complete_next239'], false],
    'next visible released' => [static fn (): mixed => $released239()['next_source_visible_after_current_source_upsert_next239'], true],
    'next denied missing' => [static fn (): mixed => $missing239()['next_source_visible_after_current_source_upsert_next239'], false],
    'current row count' => [static fn (): mixed => $released239()['current_source_row_count_next239'], 2],
    'attempted next row count' => [static fn (): mixed => $released239()['attempted_next_source_row_count_next239'], 2],
    'visible released count' => [static fn (): mixed => $released239()['visible_row_count_next239'], 4],
    'held released count' => [static fn (): mixed => $released239()['held_next_row_count_next239'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing239()['visible_row_count_next239'], 2],
    'held missing count next only' => [static fn (): mixed => $missing239()['held_next_row_count_next239'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released239()['current_source_rows_next239'], 'upsert_target_phase_next239'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released239()['attempted_next_source_rows_next239'], 'upsert_target_phase_next239'))), ['next']],
    'current targets tagged' => [static fn (): mixed => array_column($released239()['current_source_rows_next239'], 'current_source_upsert_target_receipt_next239'), $targets239()],
    'next target receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released239()['attempted_next_source_rows_next239'], 'current_source_upsert_target_receipt_next239'))), [null]],
    'target stamped' => [static fn (): mixed => array_values(array_unique(array_column($released239()['current_source_rows_next239'], 'current_source_upsert_target_next239'))), ['option_name']],
    'policy stamped on next' => [static fn (): mixed => array_values(array_unique(array_column($released239()['attempted_next_source_rows_next239'], 'current_source_upsert_policy_next239'))), ['do-update-returning']],
    'visible payload names released' => [static fn (): mixed => array_column($released239()['visible_returning_payloads_next239'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing239()['held_next_returning_payloads_next239'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing239()['blocked_reasons_next239'], ['current-source-upsert-target-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected239()['blocked_reasons_next239'], ['current-source-upsert-target-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed239()['blocked_reasons_next239'], ['current-source-upsert-target-order-mismatch']],
    'blocked reasons generation' => [static fn (): mixed => $generationHeld239()['blocked_reasons_next239'], ['current-source-upsert-generation-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld239()['blocked_reasons_next239'], ['current-source-close-missing']],
    'released reasons empty' => [static fn (): mixed => $released239()['blocked_reasons_next239'], []],
    'plan decision released' => [static fn (): mixed => $released239()['current_source_upsert_plan_next239']['decision'], 'publish-next-source-after-current-upsert-targets'],
    'plan decision missing' => [static fn (): mixed => $missing239()['current_source_upsert_plan_next239']['decision'], 'hold-next-source-until-current-upsert-targets'],
    'plan target echoed' => [static fn (): mixed => $released239()['current_source_upsert_plan_next239']['target'], 'option_name'],
    'plan required echoed' => [static fn (): mixed => $released239()['current_source_upsert_plan_next239']['required_targets'], $targets239()],
    'yield boundary released' => [static fn (): mixed => $released239()['yield_boundary_next239'], 'recursive-view-upsert-next239-current-targets-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing239()['yield_boundary_next239'], 'recursive-view-upsert-next239-current-targets-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released239()['dependency_closure_next239'], 'no-new-support-component-reuses-native-recursive-view-returning-source_close-and-adds-current-source-upsert-target-admission'],
    'dependency includes next239' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next239', $released239()['dependencies_next239'], true), true],
    'dependency includes upsert receipts' => [static fn (): mixed => in_array('sqlite-upsert-current-source-target-receipts', $released239()['dependencies_next239'], true), true],
    'dependency includes next231' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-source_close', $released239()['dependencies_next239'], true), true],
    'non overlap mentions next231' => [static fn (): mixed => str_contains($released239()['non_overlap_next239'], 'source_close cursor close'), true],
    'bad target rejected' => [static fn (): mixed => $plan239(['current_source_upsert_target_next239' => 'bad target']), InvalidArgumentException::class],
    'bad policy rejected' => [static fn (): mixed => $plan239(['current_source_upsert_policy_next239' => 'bad policy']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan239(['current_source_upsert_cursor_next239' => 'bad cursor']), InvalidArgumentException::class],
    'bad generation rejected' => [static fn (): mixed => $plan239(['current_source_upsert_generation_next239' => 'bad generation']), InvalidArgumentException::class],
    'bad target list rejected' => [static fn (): mixed => $plan239(['acknowledged_current_source_upsert_targets_next239' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan239(['acknowledged_current_source_upsert_targets_next239' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan239(['acknowledged_current_source_upsert_targets_next239' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases239 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next239 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
