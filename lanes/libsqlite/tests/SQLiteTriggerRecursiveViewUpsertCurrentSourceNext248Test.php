<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext172Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext177Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext180Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext183Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext186Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext189Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext196Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext203Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext209Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext212Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext218Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext222Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext234Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext237Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext245Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Plan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Plan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-248-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-248-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-248',
];
$nextView = $view + [];
$nextView['source'] = 'main@view-cookie-248-next';
$nextView['trigger_source'] = 'main@trigger-cookie-248-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-248-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-248-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-248-following';
$followingView['trigger_source'] = 'main@trigger-cookie-248-following';

$options = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_248',
    'cursor_name' => 'wp_recursive_view_returning_cursor_248',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.248',
    'reset_generation' => 'wp-current-reset-248',
    'post_reset_current_source_token' => 'wp.current.source.postreset.248',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.248',
    'post_reset_view' => $postResetView,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.248',
    'next_cursor' => 'wp.returning.next.cursor.248',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.248',
    'following_current_source_token' => 'wp.current.source.following.248',
    'following_cursor' => 'wp.returning.following.cursor.248',
    'following_current_view' => $followingView,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-248',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.248',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.248',
    'recursive_child_generation' => 'wp-recursive-child-current-248',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.248',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.248',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.248',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.248',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.248',
    'current_view_cookie_next209' => 'main@view-cookie-248-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-248-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'wp.current.source.yield.248',
    'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.248',
    'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.248',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.248',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.248',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.248',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'wp.current.source.ticket.248',
    'current_view_source_next222' => 'main@view-cookie-248-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-248-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_next231' => 'wp.returning.current.cursor.248',
    'current_source_close_token_next231' => 'wp.current.source.close.248',
    'current_view_cookie_next231' => 'main@view-cookie-248-current',
    'current_trigger_cookie_next231' => 'main@trigger-cookie-248-current',
    'auto_ack_current_source_closures_next231' => true,
    'current_source_upsert_token_next234' => 'wp.current.source.upsert.248',
    'current_upsert_view_cookie_next234' => 'main@view-cookie-248-current',
    'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-248-current',
    'auto_ack_current_source_upserts_next234' => true,
    'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.248',
    'current_upsert_action_view_cookie_next237' => 'main@view-cookie-248-current',
    'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-248-current',
    'auto_ack_current_source_upsert_actions_next237' => true,
    'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.248',
    'current_source_upsert_generation_next241' => 'main@source-generation-248-current',
    'current_upsert_close_view_cookie_next241' => 'main@view-cookie-248-current',
    'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-248-current',
    'auto_ack_current_source_upsert_closes_next241' => true,
    'current_source_upsert_target_token_next245' => 'wp.current.source.upsert.target.248',
    'current_source_upsert_conflict_target_next245' => ['option_name'],
    'current_source_upsert_excluded_columns_next245' => ['option_value', 'autoload'],
    'current_upsert_target_view_cookie_next245' => 'main@view-cookie-248-current',
    'current_upsert_target_trigger_cookie_next245' => 'main@trigger-cookie-248-current',
    'auto_ack_current_source_upsert_targets_next245' => true,
    'current_source_upsert_where_token_next248' => 'wp.current.source.upsert.where.248',
    'current_source_upsert_where_columns_next248' => ['option_value', 'autoload'],
];

$plan = static fn (array $extra = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNext248Plan::execute(
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
    $extra + $options,
);

$receipts = static fn (): array => $plan()['required_current_source_upsert_where_receipts_next248'];
$released = static fn (): array => $plan(['auto_ack_current_source_upsert_where_next248' => true]);
$missing = static fn (): array => $plan(['acknowledged_current_source_upsert_where_receipts_next248' => array_slice($receipts(), 0, 1)]);
$unexpectedReceipt = str_repeat('a', 64);
$unexpected = static fn (): array => $plan(['acknowledged_current_source_upsert_where_receipts_next248' => array_merge($receipts(), [$unexpectedReceipt])]);
$reversed = static fn (): array => $plan(['acknowledged_current_source_upsert_where_receipts_next248' => array_reverse($receipts())]);
$unordered = static fn (): array => $plan(['require_current_source_upsert_where_order_next248' => false, 'acknowledged_current_source_upsert_where_receipts_next248' => array_reverse($receipts())]);
$tokenHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_where_next248' => true, 'expected_current_source_upsert_where_token_next248' => 'wp.current.source.upsert.where.stale.248']);
$columnsHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_where_next248' => true, 'expected_current_source_upsert_where_columns_next248' => ['autoload']]);
$outcomesHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_where_next248' => true, 'expected_current_source_upsert_where_outcomes_next248' => [true, false]]);
$baseHeld = static fn (): array => $plan(['auto_ack_current_source_upsert_where_next248' => true, 'auto_ack_current_source_upsert_targets_next245' => false]);

$cases = [
    'released status' => [static fn (): mixed => $released()['status_next248'], 'trigger-recursive-view-upsert-current-source-next248-where-released'],
    'missing status' => [static fn (): mixed => $missing()['status_next248'], 'trigger-recursive-view-upsert-current-source-next248-where-held'],
    'unexpected status' => [static fn (): mixed => $unexpected()['status_next248'], 'trigger-recursive-view-upsert-current-source-next248-where-held'],
    'reversed status' => [static fn (): mixed => $reversed()['status_next248'], 'trigger-recursive-view-upsert-current-source-next248-where-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered()['status_next248'], 'trigger-recursive-view-upsert-current-source-next248-where-released'],
    'token held status' => [static fn (): mixed => $tokenHeld()['status_next248'], 'trigger-recursive-view-upsert-current-source-next248-where-token-held'],
    'columns held status' => [static fn (): mixed => $columnsHeld()['status_next248'], 'trigger-recursive-view-upsert-current-source-next248-where-columns-held'],
    'outcomes held status' => [static fn (): mixed => $outcomesHeld()['status_next248'], 'trigger-recursive-view-upsert-current-source-next248-where-outcomes-held'],
    'base held status' => [static fn (): mixed => $baseHeld()['status_next248'], 'trigger-recursive-view-upsert-current-source-next248-base-held'],
    'savepoint retained' => [static fn (): mixed => $released()['savepoint'], 'wp_recursive_view_248'],
    'base next245 released' => [static fn (): mixed => $released()['base']['status_next245'], 'trigger-recursive-view-upsert-current-source-next245-target-released'],
    'base visible released' => [static fn (): mixed => $released()['base_next_source_visible_next248'], true],
    'base visible held' => [static fn (): mixed => $baseHeld()['base_next_source_visible_next248'], false],
    'where token retained' => [static fn (): mixed => $released()['current_source_upsert_where_token_next248'], 'wp.current.source.upsert.where.248'],
    'where token matches' => [static fn (): mixed => $released()['current_source_upsert_where_token_matches_next248'], true],
    'where token mismatch' => [static fn (): mixed => $tokenHeld()['current_source_upsert_where_token_matches_next248'], false],
    'where columns retained' => [static fn (): mixed => $released()['current_source_upsert_where_columns_next248'], ['option_value', 'autoload']],
    'where columns match' => [static fn (): mixed => $released()['current_source_upsert_where_columns_match_next248'], true],
    'where columns mismatch' => [static fn (): mixed => $columnsHeld()['current_source_upsert_where_columns_match_next248'], false],
    'where outcomes retained' => [static fn (): mixed => $released()['current_source_upsert_where_outcomes_next248'], [true, true]],
    'where outcomes match' => [static fn (): mixed => $released()['current_source_upsert_where_outcomes_match_next248'], true],
    'where outcomes mismatch' => [static fn (): mixed => $outcomesHeld()['current_source_upsert_where_outcomes_match_next248'], false],
    'required receipt count' => [static fn (): mixed => count($released()['required_current_source_upsert_where_receipts_next248']), 2],
    'receipts are sixty four hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{64}$/', $v), $released()['required_current_source_upsert_where_receipts_next248']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released()['acknowledged_current_source_upsert_where_receipts_next248'], $receipts()],
    'missing receipt recorded' => [static fn (): mixed => $missing()['missing_current_source_upsert_where_receipts_next248'], [array_slice($receipts(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected()['unexpected_current_source_upsert_where_receipts_next248'], [$unexpectedReceipt]],
    'released missing empty' => [static fn (): mixed => $released()['missing_current_source_upsert_where_receipts_next248'], []],
    'released unexpected empty' => [static fn (): mixed => $released()['unexpected_current_source_upsert_where_receipts_next248'], []],
    'require order default' => [static fn (): mixed => $released()['require_current_source_upsert_where_order_next248'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed()['current_source_upsert_where_order_matches_next248'], false],
    'unordered considered matched' => [static fn (): mixed => $unordered()['current_source_upsert_where_order_matches_next248'], true],
    'where complete released' => [static fn (): mixed => $released()['current_source_upsert_where_complete_next248'], true],
    'where complete missing false' => [static fn (): mixed => $missing()['current_source_upsert_where_complete_next248'], false],
    'where complete outcome mismatch false' => [static fn (): mixed => $outcomesHeld()['current_source_upsert_where_complete_next248'], false],
    'next visible released' => [static fn (): mixed => $released()['next_source_visible_after_current_source_upsert_where_next248'], true],
    'next denied missing' => [static fn (): mixed => $missing()['next_source_visible_after_current_source_upsert_where_next248'], false],
    'next denied columns mismatch' => [static fn (): mixed => $columnsHeld()['next_source_visible_after_current_source_upsert_where_next248'], false],
    'current row count' => [static fn (): mixed => $released()['current_source_row_count_next248'], 2],
    'attempted next row count' => [static fn (): mixed => $released()['attempted_next_source_row_count_next248'], 2],
    'visible released count' => [static fn (): mixed => $released()['visible_row_count_next248'], 4],
    'held released count' => [static fn (): mixed => $released()['held_next_row_count_next248'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing()['visible_row_count_next248'], 2],
    'held missing count next only' => [static fn (): mixed => $missing()['held_next_row_count_next248'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released()['current_source_rows_next248'], 'upsert_where_phase_next248'))), ['current-upsert-where']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released()['attempted_next_source_rows_next248'], 'upsert_where_phase_next248'))), ['next-source']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing()['current_source_rows_next248'], 'visible_after_current_source_upsert_where_next248'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released()['attempted_next_source_rows_next248'], 'visible_after_current_source_upsert_where_next248'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing()['attempted_next_source_rows_next248'], 'visible_after_current_source_upsert_where_next248'))), [false]],
    'current where receipts tagged' => [static fn (): mixed => array_column($released()['current_source_rows_next248'], 'current_source_upsert_where_receipt_next248'), $receipts()],
    'next where receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released()['attempted_next_source_rows_next248'], 'current_source_upsert_where_receipt_next248'))), [null]],
    'current where outcomes tagged' => [static fn (): mixed => array_column($released()['current_source_rows_next248'], 'current_source_upsert_where_outcome_next248'), [true, true]],
    'next where outcomes null' => [static fn (): mixed => array_values(array_unique(array_column($released()['attempted_next_source_rows_next248'], 'current_source_upsert_where_outcome_next248'))), [null]],
    'held reasons missing' => [static fn (): mixed => in_array('where-receipts-missing', $missing()['blocked_reasons_next248'], true), true],
    'held reasons unexpected' => [static fn (): mixed => in_array('where-receipts-unexpected', $unexpected()['blocked_reasons_next248'], true), true],
    'held reasons order' => [static fn (): mixed => in_array('where-receipts-out-of-order', $reversed()['blocked_reasons_next248'], true), true],
    'held reasons token' => [static fn (): mixed => in_array('where-token-mismatch', $tokenHeld()['blocked_reasons_next248'], true), true],
    'held reasons columns' => [static fn (): mixed => in_array('where-columns-mismatch', $columnsHeld()['blocked_reasons_next248'], true), true],
    'held reasons outcomes' => [static fn (): mixed => in_array('where-outcomes-mismatch', $outcomesHeld()['blocked_reasons_next248'], true), true],
    'decision released' => [static fn (): mixed => $released()['current_source_upsert_where_plan_next248']['decision'], 'publish-next-source-after-current-recursive-view-upsert-where'],
    'decision held' => [static fn (): mixed => $missing()['current_source_upsert_where_plan_next248']['decision'], 'hold-next-source-until-current-recursive-view-upsert-where'],
    'yield released' => [static fn (): mixed => $released()['yield_boundary_next248'], 'recursive-view-upsert-next248-current-where-then-next'],
    'yield held' => [static fn (): mixed => $missing()['yield_boundary_next248'], 'recursive-view-upsert-next248-current-where-fence-next'],
    'dependency includes next248' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next248', $released()['dependencies_next248'], true), true],
    'non overlap mentions next245' => [static fn (): mixed => str_contains($released()['non_overlap_next248'], 'next245'), true],
    'visible names released' => [static fn (): mixed => array_column($released()['visible_returning_payloads_next248'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held names missing' => [static fn (): mixed => array_column($missing()['held_next_returning_payloads_next248'], 'name'), ['home', 'next_plugin']],
];

$tests = [];
foreach ($cases as $name => [$actual, $expected]) {
    $tests['trigger recursive view upsert current source next248 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

return $tests;
