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
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-245-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-245-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-245',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-245-next';
$nextView['trigger_source'] = 'main@trigger-cookie-245-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-245-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-245-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-245-following';
$followingView['trigger_source'] = 'main@trigger-cookie-245-following';

$plan = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext245(
    [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ],
    [
        ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
    ],
    [
        ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    $view,
    $nextView,
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'ordinal', 'as' => 'ordinal_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_245',
        'cursor_name' => 'wp_recursive_view_returning_cursor_245',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.245',
        'reset_generation' => 'wp-current-reset-245',
        'post_reset_current_source_token' => 'wp.current.source.postreset.245',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.245',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.245',
        'next_cursor' => 'wp.returning.next.cursor.245',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.245',
        'following_current_source_token' => 'wp.current.source.following.245',
        'following_cursor' => 'wp.returning.following.cursor.245',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-245',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.245',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.245',
        'recursive_child_generation' => 'wp-recursive-child-current-245',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.245',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.245',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.245',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.245',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.245',
        'current_view_cookie_next209' => 'main@view-cookie-245-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-245-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.245',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.245',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.245',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.245',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.245',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.245',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.245',
        'current_view_source_next222' => 'main@view-cookie-245-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-245-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.245',
        'current_source_close_token_next231' => 'wp.current.source.close.245',
        'current_view_cookie_next231' => 'main@view-cookie-245-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-245-current',
        'auto_ack_current_source_closures_next231' => true,
        'current_source_upsert_token_next234' => 'wp.current.source.upsert.245',
        'current_upsert_view_cookie_next234' => 'main@view-cookie-245-current',
        'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-245-current',
        'auto_ack_current_source_upserts_next234' => true,
        'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.245',
        'current_upsert_action_view_cookie_next237' => 'main@view-cookie-245-current',
        'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-245-current',
        'auto_ack_current_source_upsert_actions_next237' => true,
        'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.245',
        'current_source_upsert_generation_next241' => 'main@source-generation-245-current',
        'current_upsert_close_view_cookie_next241' => 'main@view-cookie-245-current',
        'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-245-current',
        'auto_ack_current_source_upsert_closes_next241' => true,
        'current_source_upsert_target_token_next245' => 'wp.current.source.upsert.target.245',
        'current_source_upsert_conflict_target_next245' => ['option_name'],
        'current_source_upsert_excluded_columns_next245' => ['option_value', 'autoload'],
        'current_upsert_target_view_cookie_next245' => 'main@view-cookie-245-current',
        'current_upsert_target_trigger_cookie_next245' => 'main@trigger-cookie-245-current',
    ],
);

$receipts = static fn (): array => $plan()['required_current_source_upsert_target_receipts_next245'];
$released = static fn (): array => $plan(['auto_ack_current_source_upsert_targets_next245' => true]);
$missing = static fn (): array => $plan(['acknowledged_current_source_upsert_target_receipts_next245' => array_slice($receipts(), 0, 1)]);
$unexpectedReceipt = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefab';
$unexpected = static fn (): array => $plan(['acknowledged_current_source_upsert_target_receipts_next245' => array_merge($receipts(), [$unexpectedReceipt])]);
$reversed = static fn (): array => $plan(['acknowledged_current_source_upsert_target_receipts_next245' => array_reverse($receipts())]);
$unordered = static fn (): array => $plan(['require_current_source_upsert_target_order_next245' => false, 'acknowledged_current_source_upsert_target_receipts_next245' => array_reverse($receipts())]);
$targetHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_targets_next245' => true, 'expected_current_source_upsert_conflict_target_next245' => ['option_id']]);
$excludedHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_targets_next245' => true, 'expected_current_source_upsert_excluded_columns_next245' => ['option_value']]);
$tokenHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_targets_next245' => true, 'expected_current_source_upsert_target_token_next245' => 'wp.current.source.upsert.target.stale.245']);
$viewHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_targets_next245' => true, 'expected_current_upsert_target_view_cookie_next245' => 'main@view-cookie-245-stale']);
$triggerHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_targets_next245' => true, 'expected_current_upsert_target_trigger_cookie_next245' => 'main@trigger-cookie-245-stale']);
$baseHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_targets_next245' => true, 'auto_ack_current_source_upsert_closes_next241' => false]);

$cases = [
    'released status' => [static fn (): mixed => $released()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-target-released'],
    'missing status' => [static fn (): mixed => $missing()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-target-held'],
    'unexpected status' => [static fn (): mixed => $unexpected()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-target-held'],
    'reversed status' => [static fn (): mixed => $reversed()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-target-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-target-released'],
    'target held status' => [static fn (): mixed => $targetHeld()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-conflict-target-held'],
    'excluded held status' => [static fn (): mixed => $excludedHeld()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-excluded-columns-held'],
    'token held status' => [static fn (): mixed => $tokenHeld()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-target-token-held'],
    'view held status' => [static fn (): mixed => $viewHeld()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-view-cookie-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-trigger-cookie-held'],
    'base held status' => [static fn (): mixed => $baseHeld()['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-base-held'],
    'savepoint retained' => [static fn (): mixed => $released()['savepoint'], 'wp_recursive_view_245'],
    'base next241 released' => [static fn (): mixed => $released()['base']['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-released'],
    'base next241 held' => [static fn (): mixed => $baseHeld()['base']['status_next241'], 'trigger-recursive-view-upsert-current-source-next241-close-held'],
    'base visible released' => [static fn (): mixed => $released()['base_next_source_visible_next245'], true],
    'base visible held' => [static fn (): mixed => $baseHeld()['base_next_source_visible_next245'], false],
    'target token retained' => [static fn (): mixed => $released()['current_source_upsert_target_token_next245'], 'wp.current.source.upsert.target.245'],
    'target token matches' => [static fn (): mixed => $released()['current_source_upsert_target_token_matches_next245'], true],
    'target token mismatch' => [static fn (): mixed => $tokenHeld()['current_source_upsert_target_token_matches_next245'], false],
    'conflict target retained' => [static fn (): mixed => $released()['current_source_upsert_conflict_target_next245'], ['option_name']],
    'conflict target matches' => [static fn (): mixed => $released()['current_source_upsert_conflict_target_matches_next245'], true],
    'conflict target mismatch' => [static fn (): mixed => $targetHeld()['current_source_upsert_conflict_target_matches_next245'], false],
    'excluded columns retained' => [static fn (): mixed => $released()['current_source_upsert_excluded_columns_next245'], ['option_value', 'autoload']],
    'excluded columns match' => [static fn (): mixed => $released()['current_source_upsert_excluded_columns_match_next245'], true],
    'excluded columns mismatch' => [static fn (): mixed => $excludedHeld()['current_source_upsert_excluded_columns_match_next245'], false],
    'view cookie matches' => [static fn (): mixed => $released()['current_upsert_target_view_cookie_matches_next245'], true],
    'view cookie mismatch' => [static fn (): mixed => $viewHeld()['current_upsert_target_view_cookie_matches_next245'], false],
    'trigger cookie matches' => [static fn (): mixed => $released()['current_upsert_target_trigger_cookie_matches_next245'], true],
    'trigger cookie mismatch' => [static fn (): mixed => $triggerHeld()['current_upsert_target_trigger_cookie_matches_next245'], false],
    'required receipt count' => [static fn (): mixed => count($released()['required_current_source_upsert_target_receipts_next245']), 2],
    'receipts are fifty six hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{56}$/', $v), $released()['required_current_source_upsert_target_receipts_next245']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released()['acknowledged_current_source_upsert_target_receipts_next245'], $receipts()],
    'missing receipt recorded' => [static fn (): mixed => $missing()['missing_current_source_upsert_target_receipts_next245'], [array_slice($receipts(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected()['unexpected_current_source_upsert_target_receipts_next245'], [$unexpectedReceipt]],
    'released missing empty' => [static fn (): mixed => $released()['missing_current_source_upsert_target_receipts_next245'], []],
    'released unexpected empty' => [static fn (): mixed => $released()['unexpected_current_source_upsert_target_receipts_next245'], []],
    'require order default' => [static fn (): mixed => $released()['require_current_source_upsert_target_order_next245'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed()['current_source_upsert_target_order_matches_next245'], false],
    'unordered considered matched' => [static fn (): mixed => $unordered()['current_source_upsert_target_order_matches_next245'], true],
    'target complete released' => [static fn (): mixed => $released()['current_source_upsert_target_complete_next245'], true],
    'target complete missing false' => [static fn (): mixed => $missing()['current_source_upsert_target_complete_next245'], false],
    'target complete target mismatch false' => [static fn (): mixed => $targetHeld()['current_source_upsert_target_complete_next245'], false],
    'next visible released' => [static fn (): mixed => $released()['next_source_visible_after_current_source_upsert_target_next245'], true],
    'next denied missing' => [static fn (): mixed => $missing()['next_source_visible_after_current_source_upsert_target_next245'], false],
    'next denied excluded mismatch' => [static fn (): mixed => $excludedHeld()['next_source_visible_after_current_source_upsert_target_next245'], false],
    'current row count' => [static fn (): mixed => $released()['current_source_row_count_next245'], 2],
    'attempted next row count' => [static fn (): mixed => $released()['attempted_next_source_row_count_next245'], 2],
    'visible released count' => [static fn (): mixed => $released()['visible_row_count_next245'], 4],
    'held released count' => [static fn (): mixed => $released()['held_next_row_count_next245'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing()['visible_row_count_next245'], 2],
    'held missing count next only' => [static fn (): mixed => $missing()['held_next_row_count_next245'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released()['current_source_rows_next245'], 'upsert_target_phase_next245'))), ['current-upsert-target']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released()['attempted_next_source_rows_next245'], 'upsert_target_phase_next245'))), ['next-source']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing()['current_source_rows_next245'], 'visible_after_current_source_upsert_target_next245'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released()['attempted_next_source_rows_next245'], 'visible_after_current_source_upsert_target_next245'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing()['attempted_next_source_rows_next245'], 'visible_after_current_source_upsert_target_next245'))), [false]],
    'current target receipts tagged' => [static fn (): mixed => array_column($released()['current_source_rows_next245'], 'current_source_upsert_target_receipt_next245'), $receipts()],
    'next target receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released()['attempted_next_source_rows_next245'], 'current_source_upsert_target_receipt_next245'))), [null]],
    'target stamped on current' => [static fn (): mixed => array_values(array_unique(array_map(static fn (array $row): string => implode(',', $row['current_source_upsert_conflict_target_next245']), $released()['current_source_rows_next245']))), ['option_name']],
    'excluded stamped on current' => [static fn (): mixed => array_values(array_unique(array_map(static fn (array $row): string => implode(',', $row['current_source_upsert_excluded_columns_next245']), $released()['current_source_rows_next245']))), ['option_value,autoload']],
    'visible payload names released' => [static fn (): mixed => array_column($released()['visible_returning_payloads_next245'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing()['held_next_returning_payloads_next245'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing()['blocked_reasons_next245'], ['current-source-upsert-target-missing', 'current-source-upsert-target-order-mismatch']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected()['blocked_reasons_next245'], ['current-source-upsert-target-unexpected', 'current-source-upsert-target-order-mismatch']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed()['blocked_reasons_next245'], ['current-source-upsert-target-order-mismatch']],
    'blocked reasons target' => [static fn (): mixed => $targetHeld()['blocked_reasons_next245'], ['current-source-upsert-conflict-target-mismatch']],
    'blocked reasons excluded' => [static fn (): mixed => $excludedHeld()['blocked_reasons_next245'], ['current-source-upsert-excluded-columns-mismatch']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld()['blocked_reasons_next245'], ['current-source-upsert-target-token-mismatch']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld()['blocked_reasons_next245'], ['current-source-upsert-target-view-cookie-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld()['blocked_reasons_next245'], ['current-source-upsert-target-trigger-cookie-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld()['blocked_reasons_next245'], ['current-source-upsert-close-missing']],
    'released reasons empty' => [static fn (): mixed => $released()['blocked_reasons_next245'], []],
    'held next reason tagged' => [static fn (): mixed => $missing()['attempted_next_source_rows_next245'][0]['held_by_current_source_upsert_target_reasons_next245'], ['current-source-upsert-target-missing', 'current-source-upsert-target-order-mismatch']],
    'released next reason empty' => [static fn (): mixed => $released()['attempted_next_source_rows_next245'][0]['held_by_current_source_upsert_target_reasons_next245'], []],
    'plan decision released' => [static fn (): mixed => $released()['current_source_upsert_target_plan_next245']['decision'], 'publish-next-source-after-current-recursive-view-upsert-target'],
    'plan decision missing' => [static fn (): mixed => $missing()['current_source_upsert_target_plan_next245']['decision'], 'hold-next-source-until-current-recursive-view-upsert-target'],
    'plan target matches' => [static fn (): mixed => $released()['current_source_upsert_target_plan_next245']['conflict_target_matches'], true],
    'plan required echoed' => [static fn (): mixed => $released()['current_source_upsert_target_plan_next245']['required_target_receipts'], $receipts()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing()['current_source_upsert_target_plan_next245']['acknowledged_target_receipts'], array_slice($receipts(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released()['current_source_upsert_target_plan_next245']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released()['yield_boundary_next245'], 'recursive-view-upsert-next245-current-target-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing()['yield_boundary_next245'], 'recursive-view-upsert-next245-current-target-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released()['dependency_closure_next245'], 'no-new-support-component-reuses-native-recursive-view-upsert-close-seals-and-adds-conflict-target-receipts'],
    'dependency includes next245' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next245', $released()['dependencies_next245'], true), true],
    'dependency includes target receipts' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-current-source-upsert-conflict-target-receipts', $released()['dependencies_next245'], true), true],
    'dependency includes next241' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next241', $released()['dependencies_next245'], true), true],
    'non overlap mentions next241' => [static fn (): mixed => str_contains($released()['non_overlap_next245'], 'next241 close-seal'), true],
    'malformed receipt rejected' => [static function () use ($plan): bool {
        try {
            $plan(['acknowledged_current_source_upsert_target_receipts_next245' => ['bad']]);
        } catch (InvalidArgumentException $e) {
            return str_contains($e->getMessage(), 'malformed target receipt');
        }
        return false;
    }, true],
    'empty conflict target rejected' => [static function () use ($plan): bool {
        try {
            $plan(['current_source_upsert_conflict_target_next245' => []]);
        } catch (InvalidArgumentException $e) {
            return str_contains($e->getMessage(), 'conflict target must be a non-empty list');
        }
        return false;
    }, true],
    'invalid excluded column rejected' => [static function () use ($plan): bool {
        try {
            $plan(['current_source_upsert_excluded_columns_next245' => ['bad-column']]);
        } catch (InvalidArgumentException $e) {
            return str_contains($e->getMessage(), 'excluded columns contains an invalid identifier');
        }
        return false;
    }, true],
    'empty target token rejected' => [static function () use ($plan): bool {
        try {
            $plan(['current_source_upsert_target_token_next245' => '']);
        } catch (InvalidArgumentException $e) {
            return str_contains($e->getMessage(), 'target token cannot be empty');
        }
        return false;
    }, true],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next245 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
