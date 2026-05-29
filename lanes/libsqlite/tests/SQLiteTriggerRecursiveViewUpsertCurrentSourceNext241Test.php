<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows241 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView241 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-241-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-241-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-241',
];
$nextView241 = $currentView241;
$nextView241['source'] = 'main@view-cookie-241-next';
$nextView241['trigger_source'] = 'main@trigger-cookie-241-next';
$postResetView241 = $currentView241;
$postResetView241['source'] = 'main@view-cookie-241-post-reset';
$postResetView241['trigger_source'] = 'main@trigger-cookie-241-post-reset';
$followingView241 = $currentView241;
$followingView241['source'] = 'main@view-cookie-241-following';
$followingView241['trigger_source'] = 'main@trigger-cookie-241-following';
$currentInput241 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput241 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning241 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan241 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext241(
    $rows241,
    $currentInput241,
    $nextInput241,
    $currentView241,
    $nextView241,
    $returning241,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_241',
        'cursor_name' => 'wp_recursive_view_returning_cursor_241',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.241',
        'reset_generation' => 'wp-current-reset-241',
        'post_reset_current_source_token' => 'wp.current.source.postreset.241',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.241',
        'post_reset_view' => $postResetView241,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.241',
        'next_cursor' => 'wp.returning.next.cursor.241',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.241',
        'following_current_source_token' => 'wp.current.source.following.241',
        'following_cursor' => 'wp.returning.following.cursor.241',
        'following_current_view' => $followingView241,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-241',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.241',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.241',
        'recursive_child_generation' => 'wp-recursive-child-current-241',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.241',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.241',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.241',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.241',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.241',
        'current_view_cookie_next209' => 'main@view-cookie-241-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.241',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.241',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.241',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.241',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.241',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.241',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.241',
        'current_view_source_next222' => 'main@view-cookie-241-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.241',
        'current_source_close_token_next231' => 'wp.current.source.close.241',
        'current_view_cookie_next231' => 'main@view-cookie-241-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_closures_next231' => true,
        'current_source_upsert_token_next234' => 'wp.current.source.upsert.241',
        'current_upsert_view_cookie_next234' => 'main@view-cookie-241-current',
        'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_upserts_next234' => true,
        'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.241',
        'current_upsert_action_view_cookie_next237' => 'main@view-cookie-241-current',
        'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_upsert_actions_next237' => true,
        'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.241',
        'current_source_upsert_generation_next241' => 'main@source-generation-241-current',
        'current_upsert_close_view_cookie_next241' => 'main@view-cookie-241-current',
        'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-241-current',
    ],
);

$receipts241 = static fn (): array => $plan241()['required_current_source_upsert_close_receipts_next241'];
$released241 = static fn (): array => $plan241(['auto_ack_current_source_upsert_closes_next241' => true]);
$missing241 = static fn (): array => $plan241(['acknowledged_current_source_upsert_close_receipts_next241' => array_slice($receipts241(), 0, 1)]);
$unexpectedReceipt241 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd';
$unexpected241 = static fn (): array => $plan241(['acknowledged_current_source_upsert_close_receipts_next241' => array_merge($receipts241(), [$unexpectedReceipt241])]);
$reversed241 = static fn (): array => $plan241(['acknowledged_current_source_upsert_close_receipts_next241' => array_reverse($receipts241())]);
$unordered241 = static fn (): array => $plan241(['require_current_source_upsert_close_order_next241' => false, 'acknowledged_current_source_upsert_close_receipts_next241' => array_reverse($receipts241())]);
$tokenHeld241 = static fn (): array => $plan241(['auto_ack_current_source_upsert_closes_next241' => true, 'expected_current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.stale.241']);
$sourceHeld241 = static fn (): array => $plan241(['auto_ack_current_source_upsert_closes_next241' => true, 'expected_current_source_upsert_generation_next241' => 'main@source-generation-241-stale']);
$viewHeld241 = static fn (): array => $plan241(['auto_ack_current_source_upsert_closes_next241' => true, 'expected_current_upsert_close_view_cookie_next241' => 'main@view-cookie-241-stale']);
$triggerHeld241 = static fn (): array => $plan241(['auto_ack_current_source_upsert_closes_next241' => true, 'expected_current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-241-stale']);
$baseHeld241 = static fn (): array => $plan241(['auto_ack_current_source_upsert_closes_next241' => true, 'auto_ack_current_source_upsert_actions_next237' => false]);
$custom241 = static fn (): array => $plan241([
    'auto_ack_current_source_upsert_closes_next241' => true,
    'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.custom.241',
    'current_source_upsert_generation_next241' => 'main@source-generation-241-custom',
    'current_upsert_close_view_cookie_next241' => 'main@view-cookie-241-custom',
    'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-241-custom',
]);

$cases241 = [
    'released status' => [static fn (): mixed => $released241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-released'],
    'missing status' => [static fn (): mixed => $missing241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-held'],
    'unexpected status' => [static fn (): mixed => $unexpected241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-held'],
    'reversed status' => [static fn (): mixed => $reversed241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-released'],
    'token held status' => [static fn (): mixed => $tokenHeld241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-token-held'],
    'source held status' => [static fn (): mixed => $sourceHeld241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-source-generation-held'],
    'view held status' => [static fn (): mixed => $viewHeld241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-view-cookie-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-trigger-cookie-held'],
    'base held status' => [static fn (): mixed => $baseHeld241()['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-base-held'],
    'savepoint retained' => [static fn (): mixed => $released241()['savepoint'], 'wp_recursive_view_241'],
    'base next237 released' => [static fn (): mixed => $released241()['base']['status_next237'], 'trigger-recursive-view-upsert-current-source-next237-actions-released'],
    'base next237 held' => [static fn (): mixed => $baseHeld241()['base']['status_next237'], 'trigger-recursive-view-upsert-current-source-next237-actions-held'],
    'base visible released' => [static fn (): mixed => $released241()['base_next_source_visible_next241'], true],
    'base visible held' => [static fn (): mixed => $baseHeld241()['base_next_source_visible_next241'], false],
    'close token retained' => [static fn (): mixed => $released241()['current_source_upsert_close_token_next241'], 'wp.current.source.upsert.close.241'],
    'custom close token retained' => [static fn (): mixed => $custom241()['current_source_upsert_close_token_next241'], 'wp.current.source.upsert.close.custom.241'],
    'source generation retained' => [static fn (): mixed => $released241()['current_source_upsert_generation_next241'], 'main@source-generation-241-current'],
    'custom source generation retained' => [static fn (): mixed => $custom241()['current_source_upsert_generation_next241'], 'main@source-generation-241-custom'],
    'view cookie retained' => [static fn (): mixed => $released241()['current_upsert_close_view_cookie_next241'], 'main@view-cookie-241-current'],
    'custom view cookie retained' => [static fn (): mixed => $custom241()['current_upsert_close_view_cookie_next241'], 'main@view-cookie-241-custom'],
    'trigger cookie retained' => [static fn (): mixed => $released241()['current_upsert_close_trigger_cookie_next241'], 'main@trigger-cookie-241-current'],
    'custom trigger cookie retained' => [static fn (): mixed => $custom241()['current_upsert_close_trigger_cookie_next241'], 'main@trigger-cookie-241-custom'],
    'token matches released' => [static fn (): mixed => $released241()['current_source_upsert_close_token_matches_next241'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld241()['current_source_upsert_close_token_matches_next241'], false],
    'source matches released' => [static fn (): mixed => $released241()['current_source_upsert_generation_matches_next241'], true],
    'source mismatch detected' => [static fn (): mixed => $sourceHeld241()['current_source_upsert_generation_matches_next241'], false],
    'view matches released' => [static fn (): mixed => $released241()['current_upsert_close_view_cookie_matches_next241'], true],
    'view mismatch detected' => [static fn (): mixed => $viewHeld241()['current_upsert_close_view_cookie_matches_next241'], false],
    'trigger matches released' => [static fn (): mixed => $released241()['current_upsert_close_trigger_cookie_matches_next241'], true],
    'trigger mismatch detected' => [static fn (): mixed => $triggerHeld241()['current_upsert_close_trigger_cookie_matches_next241'], false],
    'required receipt count' => [static fn (): mixed => count($released241()['required_current_source_upsert_close_receipts_next241']), 2],
    'receipts are fifty two hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{52}$/', $v), $released241()['required_current_source_upsert_close_receipts_next241']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released241()['acknowledged_current_source_upsert_close_receipts_next241'], $receipts241()],
    'missing acknowledged count' => [static fn (): mixed => count($missing241()['acknowledged_current_source_upsert_close_receipts_next241']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing241()['missing_current_source_upsert_close_receipts_next241'], [array_slice($receipts241(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected241()['unexpected_current_source_upsert_close_receipts_next241'], [$unexpectedReceipt241]],
    'released missing empty' => [static fn (): mixed => $released241()['missing_current_source_upsert_close_receipts_next241'], []],
    'released unexpected empty' => [static fn (): mixed => $released241()['unexpected_current_source_upsert_close_receipts_next241'], []],
    'require order default' => [static fn (): mixed => $released241()['require_current_source_upsert_close_order_next241'], true],
    'order matches released' => [static fn (): mixed => $released241()['current_source_upsert_close_order_matches_next241'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed241()['current_source_upsert_close_order_matches_next241'], false],
    'unordered disables order' => [static fn (): mixed => $unordered241()['require_current_source_upsert_close_order_next241'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered241()['current_source_upsert_close_order_matches_next241'], true],
    'close complete released' => [static fn (): mixed => $released241()['current_source_upsert_close_complete_next241'], true],
    'close complete missing false' => [static fn (): mixed => $missing241()['current_source_upsert_close_complete_next241'], false],
    'close complete unexpected false' => [static fn (): mixed => $unexpected241()['current_source_upsert_close_complete_next241'], false],
    'close complete reversed false' => [static fn (): mixed => $reversed241()['current_source_upsert_close_complete_next241'], false],
    'next visible released' => [static fn (): mixed => $released241()['next_source_visible_after_current_source_upsert_close_next241'], true],
    'next denied missing' => [static fn (): mixed => $missing241()['next_source_visible_after_current_source_upsert_close_next241'], false],
    'next denied token mismatch' => [static fn (): mixed => $tokenHeld241()['next_source_visible_after_current_source_upsert_close_next241'], false],
    'next denied source mismatch' => [static fn (): mixed => $sourceHeld241()['next_source_visible_after_current_source_upsert_close_next241'], false],
    'current row count' => [static fn (): mixed => $released241()['current_source_row_count_next241'], 2],
    'attempted next row count' => [static fn (): mixed => $released241()['attempted_next_source_row_count_next241'], 2],
    'visible released count' => [static fn (): mixed => $released241()['visible_row_count_next241'], 4],
    'held released count' => [static fn (): mixed => $released241()['held_next_row_count_next241'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing241()['visible_row_count_next241'], 2],
    'held missing count next only' => [static fn (): mixed => $missing241()['held_next_row_count_next241'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released241()['current_source_rows_next241'], 'upsert_close_phase_next241'))), ['current-close']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released241()['attempted_next_source_rows_next241'], 'upsert_close_phase_next241'))), ['next-source']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing241()['current_source_rows_next241'], 'visible_after_current_source_upsert_close_next241'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released241()['attempted_next_source_rows_next241'], 'visible_after_current_source_upsert_close_next241'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing241()['attempted_next_source_rows_next241'], 'visible_after_current_source_upsert_close_next241'))), [false]],
    'current close receipts tagged' => [static fn (): mixed => array_column($released241()['current_source_rows_next241'], 'current_source_upsert_close_receipt_next241'), $receipts241()],
    'next close receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released241()['attempted_next_source_rows_next241'], 'current_source_upsert_close_receipt_next241'))), [null]],
    'current generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released241()['current_source_rows_next241'], 'current_source_upsert_generation_next241'))), ['main@source-generation-241-current']],
    'next generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released241()['attempted_next_source_rows_next241'], 'current_source_upsert_generation_next241'))), ['main@source-generation-241-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released241()['visible_returning_payloads_next241'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing241()['held_next_returning_payloads_next241'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing241()['blocked_reasons_next241'], ['current-source-upsert-close-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected241()['blocked_reasons_next241'], ['current-source-upsert-close-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed241()['blocked_reasons_next241'], ['current-source-upsert-close-order-mismatch']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld241()['blocked_reasons_next241'], ['current-source-upsert-close-token-mismatch']],
    'blocked reasons source' => [static fn (): mixed => $sourceHeld241()['blocked_reasons_next241'], ['current-source-upsert-generation-mismatch']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld241()['blocked_reasons_next241'], ['current-source-upsert-close-view-cookie-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld241()['blocked_reasons_next241'], ['current-source-upsert-close-trigger-cookie-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld241()['blocked_reasons_next241'], ['current-source-upsert-action-missing']],
    'released reasons empty' => [static fn (): mixed => $released241()['blocked_reasons_next241'], []],
    'held next reason tagged' => [static fn (): mixed => $missing241()['attempted_next_source_rows_next241'][0]['held_by_current_source_upsert_close_reasons_next241'], ['current-source-upsert-close-missing']],
    'released next reason empty' => [static fn (): mixed => $released241()['attempted_next_source_rows_next241'][0]['held_by_current_source_upsert_close_reasons_next241'], []],
    'plan decision released' => [static fn (): mixed => $released241()['current_source_upsert_close_plan_next241']['decision'], 'publish-next-source-after-current-recursive-view-upsert-close'],
    'plan decision missing' => [static fn (): mixed => $missing241()['current_source_upsert_close_plan_next241']['decision'], 'hold-next-source-until-current-recursive-view-upsert-close'],
    'plan base visible' => [static fn (): mixed => $released241()['current_source_upsert_close_plan_next241']['base_next_source_visible'], true],
    'plan required echoed' => [static fn (): mixed => $released241()['current_source_upsert_close_plan_next241']['required_close_receipts'], $receipts241()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing241()['current_source_upsert_close_plan_next241']['acknowledged_close_receipts'], array_slice($receipts241(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released241()['current_source_upsert_close_plan_next241']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released241()['yield_boundary_next241'], 'recursive-view-upsert-next241-current-close-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing241()['yield_boundary_next241'], 'recursive-view-upsert-next241-current-close-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released241()['dependency_closure_next241'], 'no-new-support-component-reuses-native-recursive-view-upsert-action-receipts-and-adds-current-source-close-seals'],
    'dependency includes next241' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next241', $released241()['dependencies_next241'], true), true],
    'dependency includes close seals' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-current-source-upsert-close-seals', $released241()['dependencies_next241'], true), true],
    'dependency includes next237' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next237', $released241()['dependencies_next241'], true), true],
    'non overlap mentions next237' => [static fn (): mixed => str_contains($released241()['non_overlap_next241'], 'next237 action receipt'), true],
    'bad close token rejected' => [static fn (): mixed => $plan241(['current_source_upsert_close_token_next241' => 'bad token']), InvalidArgumentException::class],
    'bad source generation rejected' => [static fn (): mixed => $plan241(['current_source_upsert_generation_next241' => 'bad source']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $plan241(['current_upsert_close_view_cookie_next241' => 'bad cookie']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $plan241(['current_upsert_close_trigger_cookie_next241' => 'bad cookie']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan241(['acknowledged_current_source_upsert_close_receipts_next241' => ['x' => $unexpectedReceipt241]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan241(['acknowledged_current_source_upsert_close_receipts_next241' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan241(['acknowledged_current_source_upsert_close_receipts_next241' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases241 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next241 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
