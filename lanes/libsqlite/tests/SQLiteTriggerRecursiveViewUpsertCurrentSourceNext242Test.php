<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows242 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView242 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-242-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-242-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-242',
];
$nextView242 = $currentView242;
$nextView242['source'] = 'main@view-cookie-242-next';
$nextView242['trigger_source'] = 'main@trigger-cookie-242-next';
$postResetView242 = $currentView242;
$postResetView242['source'] = 'main@view-cookie-242-post-reset';
$postResetView242['trigger_source'] = 'main@trigger-cookie-242-post-reset';
$followingView242 = $currentView242;
$followingView242['source'] = 'main@view-cookie-242-following';
$followingView242['trigger_source'] = 'main@trigger-cookie-242-following';
$currentInput242 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput242 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning242 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$baseOptions242 = [
    'key' => 'option_name',
    'savepoint' => 'wp_recursive_view_242',
    'cursor_name' => 'wp_recursive_view_returning_cursor_242',
    'admit_next_source' => true,
    'rollback_token' => 'wp.rollback.current.242',
    'reset_generation' => 'wp-current-reset-242',
    'post_reset_current_source_token' => 'wp.current.source.postreset.242',
    'post_reset_cursor' => 'wp.returning.postreset.cursor.242',
    'post_reset_view' => $postResetView242,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'wp.next.source.242',
    'next_cursor' => 'wp.returning.next.cursor.242',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'wp.returning.next.cursor.242',
    'following_current_source_token' => 'wp.current.source.following.242',
    'following_cursor' => 'wp.returning.following.cursor.242',
    'following_current_view' => $followingView242,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'wp-following-current-242',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'wp.current.source.recursive.child.242',
    'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.242',
    'recursive_child_generation' => 'wp-recursive-child-current-242',
    'current_generation_next203' => 'wp.current.recursive.returning.generation.242',
    'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.242',
    'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.242',
    'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.242',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.242',
    'current_view_cookie_next209' => 'main@view-cookie-242-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-242-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'wp.current.source.yield.242',
    'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.242',
    'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.242',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.242',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.242',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.242',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'wp.current.source.ticket.242',
    'current_view_source_next222' => 'main@view-cookie-242-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-242-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_next231' => 'wp.returning.current.cursor.242',
    'current_source_close_token_next231' => 'wp.current.source.close.242',
    'current_view_cookie_next231' => 'main@view-cookie-242-current',
    'current_trigger_cookie_next231' => 'main@trigger-cookie-242-current',
    'auto_ack_current_source_closures_next231' => true,
    'current_source_upsert_target_next239' => 'option_name',
    'current_source_upsert_policy_next239' => 'do-update-returning',
    'current_source_upsert_cursor_next239' => 'wp.returning.upsert.cursor.242',
    'current_source_upsert_generation_next239' => 'wp.current.source.upsert.generation.242',
    'auto_ack_current_source_upsert_targets_next239' => true,
    'current_source_statement_epoch_next242' => 'wp.current.source.statement.epoch.242',
    'current_source_view_program_next242' => 'main@view-cookie-242-current',
    'current_source_trigger_program_next242' => 'main@trigger-cookie-242-current',
    'current_source_schema_cookie_next242' => 'main.schema.cookie.242',
    'current_source_upsert_sql_hash_next242' => 'insert-into-recursive-view-upsert-242',
];

$plan242 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentStatementEpochReceipt(
    $rows242,
    $currentInput242,
    $nextInput242,
    $currentView242,
    $nextView242,
    $returning242,
    $options + $baseOptions242,
);

$receipts242 = static fn (): array => $plan242()['required_current_source_statement_receipts_next242'];
$released242 = static fn (): array => $plan242(['auto_ack_current_source_statement_epochs_next242' => true]);
$missing242 = static fn (): array => $plan242(['acknowledged_current_source_statement_receipts_next242' => array_slice($receipts242(), 0, 1)]);
$unexpectedReceipt242 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefab';
$unexpected242 = static fn (): array => $plan242(['acknowledged_current_source_statement_receipts_next242' => array_merge($receipts242(), [$unexpectedReceipt242])]);
$reversed242 = static fn (): array => $plan242(['acknowledged_current_source_statement_receipts_next242' => array_reverse($receipts242())]);
$unordered242 = static fn (): array => $plan242(['require_current_source_statement_epoch_order_next242' => false, 'acknowledged_current_source_statement_receipts_next242' => array_reverse($receipts242())]);
$epochHeld242 = static fn (): array => $plan242(['auto_ack_current_source_statement_epochs_next242' => true, 'expected_current_source_statement_epoch_next242' => 'wp.current.source.statement.epoch.stale.242']);
$baseHeld242 = static fn (): array => $plan242(['auto_ack_current_source_statement_epochs_next242' => true, 'auto_ack_current_source_upsert_targets_next239' => false]);
$custom242 = static fn (): array => $plan242([
    'auto_ack_current_source_statement_epochs_next242' => true,
    'current_source_statement_epoch_next242' => 'wp.current.source.statement.epoch.custom.242',
    'current_source_view_program_next242' => 'main@view-cookie-242-custom',
    'current_source_trigger_program_next242' => 'main@trigger-cookie-242-custom',
    'current_source_schema_cookie_next242' => 'main.schema.cookie.custom.242',
    'current_source_upsert_sql_hash_next242' => 'insert-into-recursive-view-upsert-custom-242',
]);

$cases242 = [
    'released status' => [static fn (): mixed => $released242()['status_next242'], 'trigger-recursive-view-upsert-current-source-next242-statement-epoch-released'],
    'missing status' => [static fn (): mixed => $missing242()['status_next242'], 'trigger-recursive-view-upsert-current-source-next242-statement-receipts-held'],
    'unexpected status' => [static fn (): mixed => $unexpected242()['status_next242'], 'trigger-recursive-view-upsert-current-source-next242-statement-receipts-held'],
    'reversed status' => [static fn (): mixed => $reversed242()['status_next242'], 'trigger-recursive-view-upsert-current-source-next242-statement-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered242()['status_next242'], 'trigger-recursive-view-upsert-current-source-next242-statement-epoch-released'],
    'epoch mismatch status' => [static fn (): mixed => $epochHeld242()['status_next242'], 'trigger-recursive-view-upsert-current-source-next242-statement-epoch-held'],
    'base held status' => [static fn (): mixed => $baseHeld242()['status_next242'], 'trigger-recursive-view-upsert-current-source-next242-base-held'],
    'savepoint retained' => [static fn (): mixed => $released242()['savepoint'], 'wp_recursive_view_242'],
    'base next239 released' => [static fn (): mixed => $released242()['base']['status_next239'], 'trigger-recursive-view-upsert-current-source-next239-targets-released'],
    'base next239 held' => [static fn (): mixed => $baseHeld242()['base']['status_next239'], 'trigger-recursive-view-upsert-current-source-next239-targets-held'],
    'base visible released' => [static fn (): mixed => $released242()['base_next_source_visible_next242'], true],
    'base visible held' => [static fn (): mixed => $baseHeld242()['base_next_source_visible_next242'], false],
    'statement epoch retained' => [static fn (): mixed => $released242()['current_source_statement_epoch_next242'], 'wp.current.source.statement.epoch.242'],
    'custom statement epoch retained' => [static fn (): mixed => $custom242()['current_source_statement_epoch_next242'], 'wp.current.source.statement.epoch.custom.242'],
    'statement epoch matches released' => [static fn (): mixed => $released242()['current_source_statement_epoch_matches_next242'], true],
    'statement epoch mismatch detected' => [static fn (): mixed => $epochHeld242()['current_source_statement_epoch_matches_next242'], false],
    'view program retained' => [static fn (): mixed => $released242()['current_source_view_program_next242'], 'main@view-cookie-242-current'],
    'custom view program retained' => [static fn (): mixed => $custom242()['current_source_view_program_next242'], 'main@view-cookie-242-custom'],
    'trigger program retained' => [static fn (): mixed => $released242()['current_source_trigger_program_next242'], 'main@trigger-cookie-242-current'],
    'custom trigger program retained' => [static fn (): mixed => $custom242()['current_source_trigger_program_next242'], 'main@trigger-cookie-242-custom'],
    'schema cookie retained' => [static fn (): mixed => $released242()['current_source_schema_cookie_next242'], 'main.schema.cookie.242'],
    'custom schema cookie retained' => [static fn (): mixed => $custom242()['current_source_schema_cookie_next242'], 'main.schema.cookie.custom.242'],
    'sql hash retained' => [static fn (): mixed => $released242()['current_source_upsert_sql_hash_next242'], 'insert-into-recursive-view-upsert-242'],
    'custom sql hash retained' => [static fn (): mixed => $custom242()['current_source_upsert_sql_hash_next242'], 'insert-into-recursive-view-upsert-custom-242'],
    'required statement receipt count' => [static fn (): mixed => count($released242()['required_current_source_statement_receipts_next242']), 2],
    'receipts are forty four hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{44}$/', $v), $released242()['required_current_source_statement_receipts_next242']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released242()['acknowledged_current_source_statement_receipts_next242'], $receipts242()],
    'missing acknowledged count' => [static fn (): mixed => count($missing242()['acknowledged_current_source_statement_receipts_next242']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing242()['missing_current_source_statement_receipts_next242'], [array_slice($receipts242(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected242()['unexpected_current_source_statement_receipts_next242'], [$unexpectedReceipt242]],
    'released missing empty' => [static fn (): mixed => $released242()['missing_current_source_statement_receipts_next242'], []],
    'released unexpected empty' => [static fn (): mixed => $released242()['unexpected_current_source_statement_receipts_next242'], []],
    'require order default' => [static fn (): mixed => $released242()['require_current_source_statement_epoch_order_next242'], true],
    'order matches released' => [static fn (): mixed => $released242()['current_source_statement_epoch_order_matches_next242'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed242()['current_source_statement_epoch_order_matches_next242'], false],
    'unordered disables order' => [static fn (): mixed => $unordered242()['require_current_source_statement_epoch_order_next242'], false],
    'statement complete released' => [static fn (): mixed => $released242()['current_source_statement_epoch_complete_next242'], true],
    'statement incomplete missing' => [static fn (): mixed => $missing242()['current_source_statement_epoch_complete_next242'], false],
    'statement incomplete unexpected' => [static fn (): mixed => $unexpected242()['current_source_statement_epoch_complete_next242'], false],
    'statement incomplete reversed' => [static fn (): mixed => $reversed242()['current_source_statement_epoch_complete_next242'], false],
    'statement incomplete epoch' => [static fn (): mixed => $epochHeld242()['current_source_statement_epoch_complete_next242'], false],
    'next visible released' => [static fn (): mixed => $released242()['next_source_visible_after_current_source_statement_epoch_next242'], true],
    'next denied missing' => [static fn (): mixed => $missing242()['next_source_visible_after_current_source_statement_epoch_next242'], false],
    'current row count' => [static fn (): mixed => $released242()['current_source_row_count_next242'], 2],
    'attempted next row count' => [static fn (): mixed => $released242()['attempted_next_source_row_count_next242'], 2],
    'visible released count' => [static fn (): mixed => $released242()['visible_row_count_next242'], 4],
    'held released count' => [static fn (): mixed => $released242()['held_next_row_count_next242'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing242()['visible_row_count_next242'], 2],
    'held missing count next only' => [static fn (): mixed => $missing242()['held_next_row_count_next242'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released242()['current_source_rows_next242'], 'statement_epoch_phase_next242'))), ['current-statement']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released242()['attempted_next_source_rows_next242'], 'statement_epoch_phase_next242'))), ['next-source']],
    'current receipts tagged' => [static fn (): mixed => array_column($released242()['current_source_rows_next242'], 'current_source_statement_receipt_next242'), $receipts242()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released242()['attempted_next_source_rows_next242'], 'current_source_statement_receipt_next242'))), [null]],
    'statement epoch stamped on current' => [static fn (): mixed => array_values(array_unique(array_column($released242()['current_source_rows_next242'], 'current_source_statement_epoch_next242'))), ['wp.current.source.statement.epoch.242']],
    'schema cookie stamped on next' => [static fn (): mixed => array_values(array_unique(array_column($released242()['attempted_next_source_rows_next242'], 'current_source_schema_cookie_next242'))), ['main.schema.cookie.242']],
    'visible payload names released' => [static fn (): mixed => array_column($released242()['visible_returning_payloads_next242'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing242()['held_next_returning_payloads_next242'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing242()['blocked_reasons_next242'], ['current-source-statement-receipt-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected242()['blocked_reasons_next242'], ['current-source-statement-receipt-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed242()['blocked_reasons_next242'], ['current-source-statement-receipt-order-mismatch']],
    'blocked reasons epoch' => [static fn (): mixed => $epochHeld242()['blocked_reasons_next242'], ['current-source-statement-epoch-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld242()['blocked_reasons_next242'], ['current-source-upsert-target-missing']],
    'released reasons empty' => [static fn (): mixed => $released242()['blocked_reasons_next242'], []],
    'plan decision released' => [static fn (): mixed => $released242()['current_source_statement_epoch_plan_next242']['decision'], 'publish-next-source-after-current-upsert-statement-epoch'],
    'plan decision missing' => [static fn (): mixed => $missing242()['current_source_statement_epoch_plan_next242']['decision'], 'hold-next-source-until-current-upsert-statement-epoch'],
    'plan view program echoed' => [static fn (): mixed => $released242()['current_source_statement_epoch_plan_next242']['view_program'], 'main@view-cookie-242-current'],
    'plan trigger program echoed' => [static fn (): mixed => $released242()['current_source_statement_epoch_plan_next242']['trigger_program'], 'main@trigger-cookie-242-current'],
    'plan required echoed' => [static fn (): mixed => $released242()['current_source_statement_epoch_plan_next242']['required_receipts'], $receipts242()],
    'yield boundary released' => [static fn (): mixed => $released242()['yield_boundary_next242'], 'recursive-view-upsert-next242-current-statement-epoch-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing242()['yield_boundary_next242'], 'recursive-view-upsert-next242-current-statement-epoch-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released242()['dependency_closure_next242'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-target-receipts-and-adds-statement-epoch-fencing'],
    'dependency includes next242' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next242', $released242()['dependencies_next242'], true), true],
    'dependency includes statement epoch' => [static fn (): mixed => in_array('sqlite-instead-of-view-upsert-current-statement-epoch', $released242()['dependencies_next242'], true), true],
    'dependency includes next239' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next239', $released242()['dependencies_next242'], true), true],
    'non overlap mentions next239' => [static fn (): mixed => str_contains($released242()['non_overlap_next242'], 'next239 UPSERT target receipts'), true],
    'bad statement epoch rejected' => [static fn (): mixed => $plan242(['current_source_statement_epoch_next242' => 'bad epoch']), InvalidArgumentException::class],
    'bad view program rejected' => [static fn (): mixed => $plan242(['current_source_view_program_next242' => 'bad view']), InvalidArgumentException::class],
    'bad trigger program rejected' => [static fn (): mixed => $plan242(['current_source_trigger_program_next242' => 'bad trigger']), InvalidArgumentException::class],
    'bad schema cookie rejected' => [static fn (): mixed => $plan242(['current_source_schema_cookie_next242' => 'bad schema']), InvalidArgumentException::class],
    'bad sql hash rejected' => [static fn (): mixed => $plan242(['current_source_upsert_sql_hash_next242' => 'bad sql']), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan242(['acknowledged_current_source_statement_receipts_next242' => ['x' => $unexpectedReceipt242]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan242(['acknowledged_current_source_statement_receipts_next242' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan242(['acknowledged_current_source_statement_receipts_next242' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases242 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next242 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
