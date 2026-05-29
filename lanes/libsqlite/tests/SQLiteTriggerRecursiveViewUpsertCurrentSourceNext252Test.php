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
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView252 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-252-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-252-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
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
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput252 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning252 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];
$baseOptions252 = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_252',
    'cursor_name' => 'wp_recursive_view_returning_cursor_252',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.252',
    'reset_generation' => 'wp-current-reset-252',
    'post_reset_current_source_token' => 'wp.current.source.postreset.252',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.252',
    'post_reset_view' => $postResetView252,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.252',
    'next_cursor' => 'wp.returning.next.cursor.252',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.252',
    'following_current_source_token' => 'wp.current.source.following.252',
    'following_cursor' => 'wp.returning.following.cursor.252',
    'following_current_view' => $followingView252,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-252',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.252',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.252',
    'recursive_child_generation' => 'wp-recursive-child-current-252',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.252',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.252',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.252',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.252',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.252',
    'current_view_cookie_next209' => 'main@view-cookie-252-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-252-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'wp.current.source.yield.252',
    'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.252',
    'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.252',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.252',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.252',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.252',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'wp.current.source.ticket.252',
    'current_view_source_next222' => 'main@view-cookie-252-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-252-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_source_close' => 'wp.returning.current.cursor.252',
    'current_source_close_token_source_close' => 'wp.current.source.close.252',
    'current_view_cookie_source_close' => 'main@view-cookie-252-current',
    'current_trigger_cookie_source_close' => 'main@trigger-cookie-252-current',
    'auto_ack_current_source_closures_source_close' => true,
    'current_source_upsert_cursor_next240' => 'wp.upsert.current.cursor.252',
    'current_view_upsert_cookie_next240' => 'main@view-cookie-252-current',
    'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-252-current',
    'upsert_conflict_columns_next240' => ['name'],
    'auto_ack_current_source_upserts_next240' => true,
    'current_source_view_cookie_next243' => 'main@view-cookie-252-current',
    'expected_current_source_view_cookie_next243' => 'main@view-cookie-252-current',
    'current_source_trigger_cookie_next243' => 'main@trigger-cookie-252-current',
    'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-252-current',
    'next_source_view_cookie_next243' => 'main@view-cookie-252-next',
    'upsert_source_cursor_next243' => 'wp.upsert.source.cursor.252',
    'current_source_conflict_image_token_next246' => 'wp.current.source.conflict.image.252',
    'upsert_conflict_columns_next246' => ['name'],
    'upsert_excluded_columns_next246' => ['value', 'spawn_child'],
    'auto_ack_current_source_conflict_images_next246' => true,
    'current_source_assignment_token_next249' => 'wp.current.source.assignment.252',
    'upsert_assignment_columns_next249' => ['value', 'spawn_child'],
    'auto_ack_current_source_assignments_next249' => true,
    'current_source_upsert_where_token_next252' => 'wp.current.source.upsert.where.252',
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
$tokenHeld252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'expected_current_source_upsert_where_token_next252' => 'wp.current.source.upsert.where.stale.252']);
$falseAllowed252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'current_source_upsert_where_decisions_next252' => [true, false]]);
$falseHeld252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'current_source_upsert_where_decisions_next252' => [true, false], 'require_current_source_upsert_where_true_next252' => true]);
$baseHeld252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'auto_ack_current_source_assignments_next249' => false]);
$custom252 = static fn (): array => $plan252(['auto_ack_current_source_upsert_where_next252' => true, 'current_source_upsert_where_token_next252' => 'wp.current.source.upsert.where.custom.252']);

$cases252 = [
    'released status' => [static fn (): mixed => $released252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-released'],
    'missing status' => [static fn (): mixed => $missing252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-receipts-held'],
    'unexpected status' => [static fn (): mixed => $unexpected252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-receipts-held'],
    'token held status' => [static fn (): mixed => $tokenHeld252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-token-held'],
    'false allowed status' => [static fn (): mixed => $falseAllowed252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-released'],
    'false held status' => [static fn (): mixed => $falseHeld252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-false-held'],
    'base held status' => [static fn (): mixed => $baseHeld252()['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-base-held'],
    'savepoint retained' => [static fn (): mixed => $released252()['savepoint'], 'wp_recursive_view_252'],
    'base next249 released' => [static fn (): mixed => $released252()['base']['status_next249'], 'trigger-recursive-view-upsert-current-source-next249-assignments-released'],
    'base visible released' => [static fn (): mixed => $released252()['base_next_source_visible_next252'], true],
    'base visible held' => [static fn (): mixed => $baseHeld252()['base_next_source_visible_next252'], false],
    'token retained' => [static fn (): mixed => $released252()['current_source_upsert_where_token_next252'], 'wp.current.source.upsert.where.252'],
    'expected token defaults actual' => [static fn (): mixed => $released252()['expected_current_source_upsert_where_token_next252'], 'wp.current.source.upsert.where.252'],
    'token matches released' => [static fn (): mixed => $released252()['current_source_upsert_where_token_matches_next252'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenHeld252()['current_source_upsert_where_token_matches_next252'], false],
    'custom token retained' => [static fn (): mixed => $custom252()['current_source_upsert_where_token_next252'], 'wp.current.source.upsert.where.custom.252'],
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
    'visible payload names released' => [static fn (): mixed => array_column($released252()['visible_returning_payloads_next252'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing252()['held_next_returning_payloads_next252'], 'name'), ['home', 'next_plugin']],
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
