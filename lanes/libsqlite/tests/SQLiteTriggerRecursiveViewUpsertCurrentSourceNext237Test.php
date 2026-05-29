<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows237 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView237 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-237-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-237-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-237',
];
$nextView237 = $currentView237;
$nextView237['source'] = 'main@view-cookie-237-next';
$nextView237['trigger_source'] = 'main@trigger-cookie-237-next';
$postResetView237 = $currentView237;
$postResetView237['source'] = 'main@view-cookie-237-post-reset';
$postResetView237['trigger_source'] = 'main@trigger-cookie-237-post-reset';
$followingView237 = $currentView237;
$followingView237['source'] = 'main@view-cookie-237-following';
$followingView237['trigger_source'] = 'main@trigger-cookie-237-following';
$currentInput237 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput237 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning237 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan237 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentActionReceipt(
    $rows237,
    $currentInput237,
    $nextInput237,
    $currentView237,
    $nextView237,
    $returning237,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_237',
        'cursor_name' => 'wp_recursive_view_returning_cursor_237',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.237',
        'reset_generation' => 'wp-current-reset-237',
        'post_reset_current_source_token' => 'wp.current.source.postreset.237',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.237',
        'post_reset_view' => $postResetView237,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.237',
        'next_cursor' => 'wp.returning.next.cursor.237',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.237',
        'following_current_source_token' => 'wp.current.source.following.237',
        'following_cursor' => 'wp.returning.following.cursor.237',
        'following_current_view' => $followingView237,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-237',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.237',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.237',
        'recursive_child_generation' => 'wp-recursive-child-current-237',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.237',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.237',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.237',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.237',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.237',
        'current_view_cookie_next209' => 'main@view-cookie-237-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-237-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.237',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.237',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.237',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.237',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.237',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.237',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.237',
        'current_view_source_next222' => 'main@view-cookie-237-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-237-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.237',
        'current_source_close_token_next231' => 'wp.current.source.close.237',
        'current_view_cookie_next231' => 'main@view-cookie-237-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-237-current',
        'auto_ack_current_source_closures_next231' => true,
        'current_source_upsert_token_next234' => 'wp.current.source.upsert.237',
        'current_upsert_view_cookie_next234' => 'main@view-cookie-237-current',
        'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-237-current',
        'auto_ack_current_source_upserts_next234' => true,
        'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.237',
        'current_upsert_action_view_cookie_next237' => 'main@view-cookie-237-current',
        'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-237-current',
    ],
);

$receipts237 = static fn (): array => $plan237()['required_current_source_upsert_action_receipts_next237'];
$released237 = static fn (): array => $plan237(['auto_ack_current_source_upsert_actions_next237' => true]);
$missing237 = static fn (): array => $plan237(['acknowledged_current_source_upsert_action_receipts_next237' => array_slice($receipts237(), 0, 1)]);
$unexpected237 = static fn (): array => $plan237(['acknowledged_current_source_upsert_action_receipts_next237' => array_merge($receipts237(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed237 = static fn (): array => $plan237(['acknowledged_current_source_upsert_action_receipts_next237' => array_reverse($receipts237())]);
$unordered237 = static fn (): array => $plan237(['require_current_source_upsert_action_order_next237' => false, 'acknowledged_current_source_upsert_action_receipts_next237' => array_reverse($receipts237())]);
$tokenMismatch237 = static fn (): array => $plan237(['auto_ack_current_source_upsert_actions_next237' => true, 'expected_current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.stale.237']);
$baseHeld237 = static fn (): array => $plan237(['auto_ack_current_source_upsert_actions_next237' => true, 'auto_ack_current_source_upserts_next234' => false]);
$custom237 = static fn (): array => $plan237([
    'auto_ack_current_source_upsert_actions_next237' => true,
    'current_source_upsert_actions_next237' => [
        'blogdescription_child' => 'do-update',
        'template_child' => 'do-nothing',
    ],
]);

$cases237 = [
    'released status' => [static fn (): mixed => $released237()['status_next237'], 'trigger-recursive-view-upsert-current-source-next237-actions-released'],
    'missing status' => [static fn (): mixed => $missing237()['status_next237'], 'trigger-recursive-view-upsert-current-source-next237-actions-held'],
    'unexpected status' => [static fn (): mixed => $unexpected237()['status_next237'], 'trigger-recursive-view-upsert-current-source-next237-actions-held'],
    'reversed status' => [static fn (): mixed => $reversed237()['status_next237'], 'trigger-recursive-view-upsert-current-source-next237-action-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered237()['status_next237'], 'trigger-recursive-view-upsert-current-source-next237-actions-released'],
    'token mismatch status' => [static fn (): mixed => $tokenMismatch237()['status_next237'], 'trigger-recursive-view-upsert-current-source-next237-action-token-held'],
    'base held status' => [static fn (): mixed => $baseHeld237()['status_next237'], 'trigger-recursive-view-upsert-current-source-next237-base-held'],
    'savepoint retained' => [static fn (): mixed => $released237()['savepoint'], 'wp_recursive_view_237'],
    'base next234 released' => [static fn (): mixed => $released237()['base']['status_next234'], 'trigger-recursive-view-upsert-current-source-next234-upsert-released'],
    'base next234 held' => [static fn (): mixed => $baseHeld237()['base']['status_next234'], 'trigger-recursive-view-upsert-current-source-next234-upsert-held'],
    'base visible released' => [static fn (): mixed => $released237()['base_next_source_visible_next237'], true],
    'base visible held' => [static fn (): mixed => $baseHeld237()['base_next_source_visible_next237'], false],
    'action token retained' => [static fn (): mixed => $released237()['current_source_upsert_action_token_next237'], 'wp.current.source.upsert.action.237'],
    'expected action token defaults' => [static fn (): mixed => $released237()['expected_current_source_upsert_action_token_next237'], 'wp.current.source.upsert.action.237'],
    'view cookie retained' => [static fn (): mixed => $released237()['current_upsert_action_view_cookie_next237'], 'main@view-cookie-237-current'],
    'trigger cookie retained' => [static fn (): mixed => $released237()['current_upsert_action_trigger_cookie_next237'], 'main@trigger-cookie-237-current'],
    'token matches released' => [static fn (): mixed => $released237()['current_source_upsert_action_token_matches_next237'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenMismatch237()['current_source_upsert_action_token_matches_next237'], false],
    'default action count' => [static fn (): mixed => count($released237()['current_source_upsert_actions_next237']), 2],
    'default action names' => [static fn (): mixed => array_column($released237()['current_source_upsert_actions_next237'], 'name'), ['blogdescription_child', 'template_child']],
    'default action values' => [static fn (): mixed => array_column($released237()['current_source_upsert_actions_next237'], 'action'), ['insert-recursive', 'insert-recursive']],
    'default action conflicts' => [static fn (): mixed => array_column($released237()['current_source_upsert_actions_next237'], 'conflict'), [0, 0]],
    'custom action values' => [static fn (): mixed => array_column($custom237()['current_source_upsert_actions_next237'], 'action'), ['do-update', 'do-nothing']],
    'custom receipts differ' => [static fn (): mixed => $custom237()['required_current_source_upsert_action_receipts_next237'] === $released237()['required_current_source_upsert_action_receipts_next237'], false],
    'required receipt count' => [static fn (): mixed => count($released237()['required_current_source_upsert_action_receipts_next237']), 2],
    'receipts are forty six hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{46}$/', $v), $released237()['required_current_source_upsert_action_receipts_next237']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released237()['acknowledged_current_source_upsert_action_receipts_next237'], $receipts237()],
    'missing acknowledged count' => [static fn (): mixed => count($missing237()['acknowledged_current_source_upsert_action_receipts_next237']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing237()['missing_current_source_upsert_action_receipts_next237'], [array_slice($receipts237(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected237()['unexpected_current_source_upsert_action_receipts_next237'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released237()['missing_current_source_upsert_action_receipts_next237'], []],
    'released unexpected empty' => [static fn (): mixed => $released237()['unexpected_current_source_upsert_action_receipts_next237'], []],
    'require order default' => [static fn (): mixed => $released237()['require_current_source_upsert_action_order_next237'], true],
    'order matches released' => [static fn (): mixed => $released237()['current_source_upsert_action_order_matches_next237'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed237()['current_source_upsert_action_order_matches_next237'], false],
    'unordered disables order' => [static fn (): mixed => $unordered237()['require_current_source_upsert_action_order_next237'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered237()['current_source_upsert_action_order_matches_next237'], true],
    'action complete released' => [static fn (): mixed => $released237()['current_source_upsert_action_complete_next237'], true],
    'action incomplete missing' => [static fn (): mixed => $missing237()['current_source_upsert_action_complete_next237'], false],
    'action incomplete unexpected' => [static fn (): mixed => $unexpected237()['current_source_upsert_action_complete_next237'], false],
    'action incomplete reversed' => [static fn (): mixed => $reversed237()['current_source_upsert_action_complete_next237'], false],
    'action incomplete mismatch' => [static fn (): mixed => $tokenMismatch237()['current_source_upsert_action_complete_next237'], false],
    'next visible released' => [static fn (): mixed => $released237()['next_source_visible_after_current_source_upsert_action_next237'], true],
    'next denied missing' => [static fn (): mixed => $missing237()['next_source_visible_after_current_source_upsert_action_next237'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected237()['next_source_visible_after_current_source_upsert_action_next237'], false],
    'next denied reversed' => [static fn (): mixed => $reversed237()['next_source_visible_after_current_source_upsert_action_next237'], false],
    'next denied token mismatch' => [static fn (): mixed => $tokenMismatch237()['next_source_visible_after_current_source_upsert_action_next237'], false],
    'current row count' => [static fn (): mixed => $released237()['current_source_row_count_next237'], 2],
    'attempted next row count' => [static fn (): mixed => $released237()['attempted_next_source_row_count_next237'], 2],
    'visible released count' => [static fn (): mixed => $released237()['visible_row_count_next237'], 4],
    'held released count' => [static fn (): mixed => $released237()['held_next_row_count_next237'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing237()['visible_row_count_next237'], 2],
    'held missing count next only' => [static fn (): mixed => $missing237()['held_next_row_count_next237'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released237()['current_source_rows_next237'], 'upsert_action_phase_next237'))), ['current-action']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released237()['attempted_next_source_rows_next237'], 'upsert_action_phase_next237'))), ['next-source']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing237()['current_source_rows_next237'], 'visible_after_current_source_upsert_action_next237'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released237()['attempted_next_source_rows_next237'], 'visible_after_current_source_upsert_action_next237'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing237()['attempted_next_source_rows_next237'], 'visible_after_current_source_upsert_action_next237'))), [false]],
    'current receipts tagged' => [static fn (): mixed => array_column($released237()['current_source_rows_next237'], 'current_source_upsert_action_receipt_next237'), $receipts237()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released237()['attempted_next_source_rows_next237'], 'current_source_upsert_action_receipt_next237'))), [null]],
    'current actions tagged' => [static fn (): mixed => array_column($released237()['current_source_rows_next237'], 'current_source_upsert_action_next237'), ['insert-recursive', 'insert-recursive']],
    'next actions null' => [static fn (): mixed => array_values(array_unique(array_column($released237()['attempted_next_source_rows_next237'], 'current_source_upsert_action_next237'))), [null]],
    'current token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released237()['current_source_rows_next237'], 'current_source_upsert_action_token_next237'))), ['wp.current.source.upsert.action.237']],
    'next view cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($released237()['attempted_next_source_rows_next237'], 'current_upsert_action_view_cookie_next237'))), ['main@view-cookie-237-current']],
    'next trigger cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($released237()['attempted_next_source_rows_next237'], 'current_upsert_action_trigger_cookie_next237'))), ['main@trigger-cookie-237-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released237()['visible_returning_payloads_next237'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing237()['held_next_returning_payloads_next237'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing237()['blocked_reasons_next237'], ['current-source-upsert-action-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected237()['blocked_reasons_next237'], ['current-source-upsert-action-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed237()['blocked_reasons_next237'], ['current-source-upsert-action-order-mismatch']],
    'blocked reasons token mismatch' => [static fn (): mixed => $tokenMismatch237()['blocked_reasons_next237'], ['current-source-upsert-action-token-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld237()['blocked_reasons_next237'], ['current-source-upsert-missing']],
    'released reasons empty' => [static fn (): mixed => $released237()['blocked_reasons_next237'], []],
    'held next reason tagged' => [static fn (): mixed => $missing237()['attempted_next_source_rows_next237'][0]['held_by_current_source_upsert_action_reasons_next237'], ['current-source-upsert-action-missing']],
    'released next reason empty' => [static fn (): mixed => $released237()['attempted_next_source_rows_next237'][0]['held_by_current_source_upsert_action_reasons_next237'], []],
    'plan decision released' => [static fn (): mixed => $released237()['current_source_upsert_action_plan_next237']['decision'], 'publish-next-source-after-current-recursive-view-upsert-actions'],
    'plan decision missing' => [static fn (): mixed => $missing237()['current_source_upsert_action_plan_next237']['decision'], 'hold-next-source-until-current-recursive-view-upsert-actions'],
    'plan base visible' => [static fn (): mixed => $released237()['current_source_upsert_action_plan_next237']['base_next_source_visible'], true],
    'plan required echoed' => [static fn (): mixed => $released237()['current_source_upsert_action_plan_next237']['required_action_receipts'], $receipts237()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing237()['current_source_upsert_action_plan_next237']['acknowledged_action_receipts'], array_slice($receipts237(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released237()['current_source_upsert_action_plan_next237']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released237()['yield_boundary_next237'], 'recursive-view-upsert-next237-current-actions-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing237()['yield_boundary_next237'], 'recursive-view-upsert-next237-current-actions-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released237()['dependency_closure_next237'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-receipts-and-adds-conflict-action-seals'],
    'dependency includes next237' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next237', $released237()['dependencies_next237'], true), true],
    'dependency includes action seals' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-current-source-upsert-action-seals', $released237()['dependencies_next237'], true), true],
    'dependency includes next234' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next234', $released237()['dependencies_next237'], true), true],
    'non overlap mentions next234' => [static fn (): mixed => str_contains($released237()['non_overlap_next237'], 'next234 conflict-key receipt'), true],
    'bad action token rejected' => [static fn (): mixed => $plan237(['current_source_upsert_action_token_next237' => 'bad token']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $plan237(['current_upsert_action_view_cookie_next237' => 'bad cookie']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $plan237(['current_upsert_action_trigger_cookie_next237' => 'bad cookie']), InvalidArgumentException::class],
    'bad action override list rejected' => [static fn (): mixed => $plan237(['current_source_upsert_actions_next237' => 'bad']), InvalidArgumentException::class],
    'bad action override key rejected' => [static fn (): mixed => $plan237(['current_source_upsert_actions_next237' => ['bad key' => 'insert']]), InvalidArgumentException::class],
    'bad action override value rejected' => [static fn (): mixed => $plan237(['current_source_upsert_actions_next237' => ['template_child' => 'merge']]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan237(['acknowledged_current_source_upsert_action_receipts_next237' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan237(['acknowledged_current_source_upsert_action_receipts_next237' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan237(['acknowledged_current_source_upsert_action_receipts_next237' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases237 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next237 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
