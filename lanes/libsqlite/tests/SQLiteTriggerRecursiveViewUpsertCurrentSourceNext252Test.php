<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}
foreach ([
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
] as $file) {
    require_once __DIR__ . '/../src/' . $file;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows252 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView252 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-252-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-252-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-where-252',
];
$nextView252 = $currentView252;
$nextView252['source'] = 'main@view-cookie-252-next';
$nextView252['trigger_source'] = 'main@trigger-cookie-252-next';
$postResetView252 = $currentView252;
$postResetView252['source'] = 'main@view-cookie-252-post-reset';
$postResetView252['trigger_source'] = 'main@trigger-cookie-252-post-reset';
$followingView252 = $currentView252;
$followingView252['source'] = 'main@view-cookie-252-following';
$followingView252['trigger_source'] = 'main@trigger-cookie-252-following';
$currentInput252 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput252 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning252 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];
$baseOptions252 = [
    'key' => 'key_name',
    'savepoint' => 'app_recursive_view_252',
    'cursor_name' => 'app_recursive_view_returning_cursor_252',
    'admit_next_source' => true,
    'rollback_token' => 'app.rollback.current.252',
    'reset_generation' => 'app-current-reset-252',
    'post_reset_current_source_token' => 'app.current.source.postreset.252',
    'post_reset_cursor' => 'app.returning.postreset.cursor.252',
    'post_reset_view' => $postResetView252,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'app.next.source.252',
    'next_cursor' => 'app.returning.next.cursor.252',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'app.returning.next.cursor.252',
    'following_current_source_token' => 'app.current.source.following.252',
    'following_cursor' => 'app.returning.following.cursor.252',
    'following_current_view' => $followingView252,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'app-following-current-252',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'app.current.source.recursive.child.252',
    'recursive_child_cursor' => 'app.returning.recursive.child.cursor.252',
    'recursive_child_generation' => 'app-recursive-child-current-252',
    'current_generation_next203' => 'app.current.recursive.returning.generation.252',
    'expected_current_generation_next203' => 'app.current.recursive.returning.generation.252',
    'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.252',
    'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.252',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'app.current.source.drain.252',
    'current_view_cookie_next209' => 'main@view-cookie-252-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-252-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'app.current.source.yield.252',
    'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.252',
    'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.252',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'app.current.source.epoch.252',
    'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.252',
    'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.252',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'app.current.source.ticket.252',
    'current_view_source_next222' => 'main@view-cookie-252-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-252-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_source_close' => 'app.returning.current.cursor.252',
    'current_source_close_token_source_close' => 'app.current.source.close.252',
    'current_view_cookie_source_close' => 'main@view-cookie-252-current',
    'current_trigger_cookie_source_close' => 'main@trigger-cookie-252-current',
    'auto_ack_current_source_closures_source_close' => true,
    'current_source_upsert_cursor_next240' => 'app.upsert.current.cursor.252',
    'current_view_upsert_cookie_next240' => 'main@view-cookie-252-current',
    'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-252-current',
    'upsert_conflict_columns_next240' => ['name'],
    'auto_ack_current_source_upserts_next240' => true,
    'current_source_view_cookie_next243' => 'main@view-cookie-252-current',
    'expected_current_source_view_cookie_next243' => 'main@view-cookie-252-current',
    'current_source_trigger_cookie_next243' => 'main@trigger-cookie-252-current',
    'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-252-current',
    'next_source_view_cookie_next243' => 'main@view-cookie-252-next',
    'upsert_source_cursor_next243' => 'app.upsert.source.cursor.252',
    'current_source_conflict_image_token_next246' => 'app.current.source.conflict.image.252',
    'upsert_conflict_columns_next246' => ['name'],
    'upsert_excluded_columns_next246' => ['value', 'spawn_child'],
    'auto_ack_current_source_conflict_images_next246' => true,
    'current_source_assignment_token_next249' => 'app.current.source.assignment.252',
    'upsert_assignment_columns_next249' => ['value', 'spawn_child'],
    'auto_ack_current_source_assignments_next249' => true,
    'current_source_upsert_where_token_next252' => 'app.current.source.upsert.where.252',
];

$plan252 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentPredicateDecisionReceipt(
    $rows252,
    $currentInput252,
    $nextInput252,
    $currentView252,
    $nextView252,
    $returning252,
    $options + $baseOptions252,
);
$receipts252 = static fn (): array => $plan252()['required_current_source_upsert_where_receipts_next252'];
$released252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true]);
$missing252 = static fn (): array => $plan252(['acknowledged_current_source_upsert_where_receipts_next252' => array_slice($receipts252(), 0, 1)]);
$unexpectedReceipt252 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefab';
$unexpected252 = static fn (): array => $plan252(['acknowledged_current_source_upsert_where_receipts_next252' => array_merge($receipts252(), [$unexpectedReceipt252])]);
$tokenHeld252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'expected_current_source_upsert_where_token_next252' => 'app.current.source.upsert.where.stale.252']);
$falseAllowed252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'current_source_upsert_where_decisions_next252' => [true, false]]);
$falseHeld252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'current_source_upsert_where_decisions_next252' => [true, false], 'require_current_source_upsert_where_true_next252' => true]);
$baseHeld252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'auto_ack_current_source_assignments_next249' => false]);
$custom252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'current_source_upsert_where_token_next252' => 'app.current.source.upsert.where.custom.252']);

$cases252 = [
    'released status' => [static fn (): mixed => $released252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-released'],
    'missing status' => [static fn (): mixed => $missing252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-receipts-held'],
    'unexpected status' => [static fn (): mixed => $unexpected252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-receipts-held'],
    'token held status' => [static fn (): mixed => $tokenHeld252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-token-held'],
    'false allowed status' => [static fn (): mixed => $falseAllowed252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-released'],
    'false held status' => [static fn (): mixed => $falseHeld252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-false-held'],
    'base held status' => [static fn (): mixed => $baseHeld252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-base-held'],
    'savepoint retained' => [static fn (): mixed => $released252()['savepoint'], 'app_recursive_view_252'],
    'base next249 released' => [static fn (): mixed => $released252()['base']['status_next249'], 'trigger-recursive-view-upsert-current-source-next249-assignments-released'],
    'base visible released' => [static fn (): mixed => $released252()['base_next_source_visible_next252'], true],
    'base visible held' => [static fn (): mixed => $baseHeld252()['base_next_source_visible_next252'], false],
    'token retained' => [static fn (): mixed => $released252()['current_source_upsert_where_token_next252'], 'app.current.source.upsert.where.252'],
    'expected token defaults actual' => [static fn (): mixed => $released252()['expected_current_source_upsert_where_token_next252'], 'app.current.source.upsert.where.252'],
    'token matches released' => [static fn (): mixed => $released252()['current_source_upsert_where_token_matches_next252'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld252()['current_source_upsert_where_token_matches_next252'], false],
    'custom token retained' => [static fn (): mixed => $custom252()['current_source_upsert_where_token_next252'], 'app.current.source.upsert.where.custom.252'],
    'default decisions true' => [static fn (): mixed => $released252()['current_source_upsert_where_decisions_next252'], [true, true]],
    'custom decisions retained' => [static fn (): mixed => $falseAllowed252()['current_source_upsert_where_decisions_next252'], [true, false]],
    'all true released' => [static fn (): mixed => $released252()['current_source_upsert_where_all_true_next252'], true],
    'all true false detected' => [static fn (): mixed => $falseAllowed252()['current_source_upsert_where_all_true_next252'], false],
    'require true default' => [static fn (): mixed => $released252()['require_current_source_upsert_where_true_next252'], false],
    'require true retained' => [static fn (): mixed => $falseHeld252()['require_current_source_upsert_where_true_next252'], true],
    'receipt count' => [static fn (): mixed => count($released252()['required_current_source_upsert_where_receipts_next252']), 2],
    'receipts are forty four hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{44}$/', $v), $released252()['required_current_source_upsert_where_receipts_next252']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released252()['acknowledged_current_source_upsert_where_receipts_next252'], $receipts252()],
    'missing acknowledged count' => [static fn (): mixed => count($missing252()['acknowledged_current_source_upsert_where_receipts_next252']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing252()['missing_current_source_upsert_where_receipts_next252'], [array_slice($receipts252(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected252()['unexpected_current_source_upsert_where_receipts_next252'], [$unexpectedReceipt252]],
    'released missing empty' => [static fn (): mixed => $released252()['missing_current_source_upsert_where_receipts_next252'], []],
    'released unexpected empty' => [static fn (): mixed => $released252()['unexpected_current_source_upsert_where_receipts_next252'], []],
    'where complete released' => [static fn (): mixed => $released252()['current_source_upsert_where_complete_next252'], true],
    'where incomplete missing' => [static fn (): mixed => $missing252()['current_source_upsert_where_complete_next252'], false],
    'where incomplete unexpected' => [static fn (): mixed => $unexpected252()['current_source_upsert_where_complete_next252'], false],
    'where incomplete token' => [static fn (): mixed => $tokenHeld252()['current_source_upsert_where_complete_next252'], false],
    'where complete false allowed' => [static fn (): mixed => $falseAllowed252()['current_source_upsert_where_complete_next252'], true],
    'where incomplete false required' => [static fn (): mixed => $falseHeld252()['current_source_upsert_where_complete_next252'], false],
    'next visible released' => [static fn (): mixed => $released252()['next_source_visible_after_current_source_upsert_where_next252'], true],
    'next denied missing' => [static fn (): mixed => $missing252()['next_source_visible_after_current_source_upsert_where_next252'], false],
    'next denied false required' => [static fn (): mixed => $falseHeld252()['next_source_visible_after_current_source_upsert_where_next252'], false],
    'visible released count' => [static fn (): mixed => $released252()['visible_row_count_next252'], 4],
    'held released count' => [static fn (): mixed => $released252()['held_next_row_count_next252'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing252()['visible_row_count_next252'], 2],
    'held missing count next only' => [static fn (): mixed => $missing252()['held_next_row_count_next252'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released252()['current_source_rows_next252'], 'upsert_where_phase_next252'))), ['current-where']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released252()['attempted_next_source_rows_next252'], 'upsert_where_phase_next252'))), ['next-source']],
    'current receipts tagged' => [static fn (): mixed => array_column($released252()['current_source_rows_next252'], 'current_source_upsert_where_receipt_next252'), $receipts252()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released252()['attempted_next_source_rows_next252'], 'current_source_upsert_where_receipt_next252'))), [null]],
    'current decisions tagged' => [static fn (): mixed => array_column($falseAllowed252()['current_source_rows_next252'], 'current_source_upsert_where_decision_next252'), [true, false]],
    'next decisions null' => [static fn (): mixed => array_values(array_unique(array_column($released252()['attempted_next_source_rows_next252'], 'current_source_upsert_where_decision_next252'))), [null]],
    'current rows visible while held' => [static fn (): mixed => array_values(array_unique(array_column($missing252()['current_source_rows_next252'], 'visible_after_current_source_upsert_where_next252'))), [true]],
    'next rows held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing252()['attempted_next_source_rows_next252'], 'visible_after_current_source_upsert_where_next252'))), [false]],
    'visible payload names released' => [static fn (): mixed => array_column($released252()['visible_returning_payloads_next252'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'held payload names missing' => [static fn (): mixed => array_column($missing252()['held_next_returning_payloads_next252'], 'name'), ['landing_url', 'next_module']],
    'blocked reasons released' => [static fn (): mixed => $released252()['blocked_reasons_next252'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing252()['blocked_reasons_next252'], ['current-source-upsert-where-receipt-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected252()['blocked_reasons_next252'], ['current-source-upsert-where-receipt-unexpected']],
    'blocked reasons token' => [static fn (): mixed => $tokenHeld252()['blocked_reasons_next252'], ['current-source-upsert-where-token-mismatch']],
    'blocked reasons false' => [static fn (): mixed => $falseHeld252()['blocked_reasons_next252'], ['current-source-upsert-where-false']],
    'held row reason copied' => [static fn (): mixed => $missing252()['held_next_source_rows_next252'][0]['held_by_current_source_upsert_where_reasons_next252'], ['current-source-upsert-where-receipt-missing']],
    'plan decision released' => [static fn (): mixed => $released252()['current_source_upsert_where_plan_next252']['decision'], 'publish-next-source-after-current-upsert-where'],
    'plan decision held' => [static fn (): mixed => $missing252()['current_source_upsert_where_plan_next252']['decision'], 'hold-next-source-until-current-upsert-where'],
    'plan required echoed' => [static fn (): mixed => $released252()['current_source_upsert_where_plan_next252']['required_receipts'], $receipts252()],
    'plan decisions echoed' => [static fn (): mixed => $falseAllowed252()['current_source_upsert_where_plan_next252']['decisions'], [true, false]],
    'yield boundary released' => [static fn (): mixed => $released252()['yield_boundary_next252'], 'recursive-view-upsert-next252-current-where-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing252()['yield_boundary_next252'], 'recursive-view-upsert-next252-current-where-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released252()['dependency_closure_next252'], 'no-new-support-component-reuses-native-recursive-view-upsert-assignment-receipts-and-adds-do-update-where-decision-receipts'],
    'dependency includes next252' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next252', $released252()['dependencies_next252'], true), true],
    'dependency includes where receipts' => [static fn (): mixed => in_array('sqlite-instead-of-view-upsert-do-update-where-receipts', $released252()['dependencies_next252'], true), true],
    'dependency includes next249' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next249', $released252()['dependencies_next252'], true), true],
    'non overlap mentions next249' => [static fn (): mixed => str_contains($released252()['non_overlap_next252'], 'next249 assignment receipts'), true],
    'bad token rejected' => [static fn (): mixed => $plan252(['current_source_upsert_where_token_next252' => 'bad token']), InvalidArgumentException::class],
    'bad expected token rejected' => [static fn (): mixed => $plan252(['expected_current_source_upsert_where_token_next252' => 'bad token']), InvalidArgumentException::class],
    'bad decision count rejected' => [static fn (): mixed => $plan252(['current_source_upsert_where_decisions_next252' => [true]]), InvalidArgumentException::class],
    'bad decision value rejected' => [static fn (): mixed => $plan252(['current_source_upsert_where_decisions_next252' => [true, 1]]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan252(['acknowledged_current_source_upsert_where_receipts_next252' => ['x' => $unexpectedReceipt252]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan252(['acknowledged_current_source_upsert_where_receipts_next252' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan252(['acknowledged_current_source_upsert_where_receipts_next252' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases252 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next252 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
