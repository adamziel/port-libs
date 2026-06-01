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
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
] as $file) {
    require_once __DIR__ . '/../src/' . $file;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows255 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView255 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-255-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-255-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-returning-drain-255',
];
$nextView255 = $currentView255;
$nextView255['source'] = 'main@view-cookie-255-next';
$nextView255['trigger_source'] = 'main@trigger-cookie-255-next';
$postResetView255 = $currentView255;
$postResetView255['source'] = 'main@view-cookie-255-post-reset';
$postResetView255['trigger_source'] = 'main@trigger-cookie-255-post-reset';
$followingView255 = $currentView255;
$followingView255['source'] = 'main@view-cookie-255-following';
$followingView255['trigger_source'] = 'main@trigger-cookie-255-following';
$currentInput255 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput255 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning255 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];
$baseOptions255 = [
    'key' => 'key_name',
    'savepoint' => 'app_recursive_view_255',
    'cursor_name' => 'app_recursive_view_returning_cursor_255',
    'admit_next_source' => true,
    'rollback_token' => 'app.rollback.current.255',
    'reset_generation' => 'app-current-reset-255',
    'post_reset_current_source_token' => 'app.current.source.postreset.255',
    'post_reset_cursor' => 'app.returning.postreset.cursor.255',
    'post_reset_view' => $postResetView255,
    'post_reset_input' => [
        ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
    ],
    'fresh_acknowledged_ordinals' => [0, 1],
    'next_source_token' => 'app.next.source.255',
    'next_cursor' => 'app.returning.next.cursor.255',
    'next_acknowledged_ordinals' => [0, 1],
    'close_next_cursor' => 'app.returning.next.cursor.255',
    'following_current_source_token' => 'app.current.source.following.255',
    'following_cursor' => 'app.returning.following.cursor.255',
    'following_current_view' => $followingView255,
    'following_current_input' => [
        ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
        ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ],
    'following_generation' => 'app-following-current-255',
    'recursive_child_acknowledged_ordinals' => [0, 1],
    'recursive_child_source_token' => 'app.current.source.recursive.child.255',
    'recursive_child_cursor' => 'app.returning.recursive.child.cursor.255',
    'recursive_child_generation' => 'app-recursive-child-current-255',
    'current_generation_next203' => 'app.current.recursive.returning.generation.255',
    'expected_current_generation_next203' => 'app.current.recursive.returning.generation.255',
    'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.255',
    'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.255',
    'auto_ack_current_generation_receipts_next203' => true,
    'current_source_drain_token_next209' => 'app.current.source.drain.255',
    'current_view_cookie_next209' => 'main@view-cookie-255-current',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-255-current',
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_yield_token_next212' => 'app.current.source.yield.255',
    'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.255',
    'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.255',
    'auto_ack_current_source_yields_next212' => true,
    'current_source_epoch_next218' => 'app.current.source.epoch.255',
    'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.255',
    'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.255',
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_ticket_next222' => 'app.current.source.ticket.255',
    'current_view_source_next222' => 'main@view-cookie-255-current',
    'current_trigger_source_next222' => 'main@trigger-cookie-255-current',
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_cursor_source_close' => 'app.returning.current.cursor.255',
    'current_source_close_token_source_close' => 'app.current.source.close.255',
    'current_view_cookie_source_close' => 'main@view-cookie-255-current',
    'current_trigger_cookie_source_close' => 'main@trigger-cookie-255-current',
    'auto_ack_current_source_closures_source_close' => true,
    'current_source_upsert_cursor_next240' => 'app.upsert.current.cursor.255',
    'current_view_upsert_cookie_next240' => 'main@view-cookie-255-current',
    'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-255-current',
    'upsert_conflict_columns_next240' => ['name'],
    'auto_ack_current_source_upserts_next240' => true,
    'current_source_view_cookie_next243' => 'main@view-cookie-255-current',
    'expected_current_source_view_cookie_next243' => 'main@view-cookie-255-current',
    'current_source_trigger_cookie_next243' => 'main@trigger-cookie-255-current',
    'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-255-current',
    'next_source_view_cookie_next243' => 'main@view-cookie-255-next',
    'upsert_source_cursor_next243' => 'app.upsert.source.cursor.255',
    'current_source_conflict_image_token_next246' => 'app.current.source.conflict.image.255',
    'upsert_conflict_columns_next246' => ['name'],
    'upsert_excluded_columns_next246' => ['value', 'spawn_child'],
    'auto_ack_current_source_conflict_images_next246' => true,
    'current_source_assignment_token_next249' => 'app.current.source.assignment.255',
    'upsert_assignment_columns_next249' => ['value', 'spawn_child'],
    'auto_ack_current_source_assignments_next249' => true,
    'current_source_upsert_where_token_next252' => 'app.current.source.upsert.where.255',
    'auto_ack_current_source_upsert_where_next252' => true,
    'current_source_returning_cursor_next255' => 'app.returning.current.upsert.cursor.255',
    'required_current_source_returning_aliases_next255' => ['name', 'value', 'event_name', 'spawn_child'],
];

$plan255 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentSourceUpsertReturningDrain(
    $rows255,
    $currentInput255,
    $nextInput255,
    $currentView255,
    $nextView255,
    $returning255,
    $options + $baseOptions255,
);
$receipts255 = static fn (): array => $plan255()['required_current_source_returning_receipts_next255'];
$released255 = static fn (): array => $plan255(['auto_ack_current_source_returning_next255' => true]);
$missing255 = static fn (): array => $plan255(['acknowledged_current_source_returning_receipts_next255' => array_slice($receipts255(), 0, 1)]);
$unexpectedReceipt255 = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef';
$unexpected255 = static fn (): array => $plan255(['acknowledged_current_source_returning_receipts_next255' => array_merge($receipts255(), [$unexpectedReceipt255])]);
$cursorHeld255 = static fn (): array => $plan255(['auto_ack_current_source_returning_next255' => true, 'expected_current_source_returning_cursor_next255' => 'app.returning.current.upsert.cursor.stale.255']);
$aliasHeld255 = static fn (): array => $plan255(['auto_ack_current_source_returning_next255' => true, 'required_current_source_returning_aliases_next255' => ['name', 'missing_alias']]);
$orderHeld255 = static fn (): array => $plan255(['acknowledged_current_source_returning_receipts_next255' => array_reverse($receipts255())]);
$orderAllowed255 = static fn (): array => $plan255(['acknowledged_current_source_returning_receipts_next255' => array_reverse($receipts255()), 'require_current_source_returning_order_next255' => false]);
$baseHeld255 = static fn (): array => $plan255(['auto_ack_current_source_returning_next255' => true, 'auto_ack_current_source_upsert_where_next252' => false]);
$custom255 = static fn (): array => $plan255([
    'auto_ack_current_source_returning_next255' => true,
    'current_source_returning_cursor_next255' => 'app.returning.current.upsert.cursor.custom.255',
    'required_current_source_returning_aliases_next255' => ['name', 'event_name'],
]);

$cases255 = [
    'released status' => [static fn (): mixed => $released255()['status_next255'], 'trigger-recursive-view-upsert-current-source-next255-returning-released'],
    'missing status' => [static fn (): mixed => $missing255()['status_next255'], 'trigger-recursive-view-upsert-current-source-next255-returning-receipts-held'],
    'unexpected status' => [static fn (): mixed => $unexpected255()['status_next255'], 'trigger-recursive-view-upsert-current-source-next255-returning-receipts-held'],
    'cursor held status' => [static fn (): mixed => $cursorHeld255()['status_next255'], 'trigger-recursive-view-upsert-current-source-next255-returning-cursor-held'],
    'alias held status' => [static fn (): mixed => $aliasHeld255()['status_next255'], 'trigger-recursive-view-upsert-current-source-next255-returning-alias-held'],
    'order held status' => [static fn (): mixed => $orderHeld255()['status_next255'], 'trigger-recursive-view-upsert-current-source-next255-returning-order-held'],
    'order allowed status' => [static fn (): mixed => $orderAllowed255()['status_next255'], 'trigger-recursive-view-upsert-current-source-next255-returning-released'],
    'base held status' => [static fn (): mixed => $baseHeld255()['status_next255'], 'trigger-recursive-view-upsert-current-source-next255-base-held'],
    'savepoint retained' => [static fn (): mixed => $released255()['savepoint'], 'app_recursive_view_255'],
    'base next252 released' => [static fn (): mixed => $released255()['base']['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-released'],
    'base next252 held' => [static fn (): mixed => $baseHeld255()['base']['status_next252'], 'trigger-recursive-view-upsert-current-source-next252-where-receipts-held'],
    'base visible released' => [static fn (): mixed => $released255()['base_next_source_visible_next255'], true],
    'base visible held' => [static fn (): mixed => $baseHeld255()['base_next_source_visible_next255'], false],
    'cursor retained' => [static fn (): mixed => $released255()['current_source_returning_cursor_next255'], 'app.returning.current.upsert.cursor.255'],
    'custom cursor retained' => [static fn (): mixed => $custom255()['current_source_returning_cursor_next255'], 'app.returning.current.upsert.cursor.custom.255'],
    'expected cursor defaults actual' => [static fn (): mixed => $released255()['expected_current_source_returning_cursor_next255'], 'app.returning.current.upsert.cursor.255'],
    'cursor matches released' => [static fn (): mixed => $released255()['current_source_returning_cursor_matches_next255'], true],
    'cursor mismatch detected' => [static fn (): mixed => $cursorHeld255()['current_source_returning_cursor_matches_next255'], false],
    'aliases retained' => [static fn (): mixed => $released255()['required_current_source_returning_aliases_next255'], ['name', 'value', 'event_name', 'spawn_child']],
    'custom aliases retained' => [static fn (): mixed => $custom255()['required_current_source_returning_aliases_next255'], ['name', 'event_name']],
    'missing aliases empty' => [static fn (): mixed => $released255()['missing_current_source_returning_aliases_next255'], []],
    'missing alias detected' => [static fn (): mixed => $aliasHeld255()['missing_current_source_returning_aliases_next255'], ['missing_alias']],
    'payload count' => [static fn (): mixed => count($released255()['current_source_returning_payloads_next255']), 2],
    'payload names' => [static fn (): mixed => array_column($released255()['current_source_returning_payloads_next255'], 'name'), ['app_summary_child', 'template_child']],
    'receipt count' => [static fn (): mixed => count($released255()['required_current_source_returning_receipts_next255']), 2],
    'receipts are forty eight hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{48}$/', $v), $released255()['required_current_source_returning_receipts_next255']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released255()['acknowledged_current_source_returning_receipts_next255'], $receipts255()],
    'missing acknowledged count' => [static fn (): mixed => count($missing255()['acknowledged_current_source_returning_receipts_next255']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing255()['missing_current_source_returning_receipts_next255'], [array_slice($receipts255(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected255()['unexpected_current_source_returning_receipts_next255'], [$unexpectedReceipt255]],
    'released missing empty' => [static fn (): mixed => $released255()['missing_current_source_returning_receipts_next255'], []],
    'released unexpected empty' => [static fn (): mixed => $released255()['unexpected_current_source_returning_receipts_next255'], []],
    'order required default' => [static fn (): mixed => $released255()['require_current_source_returning_order_next255'], true],
    'order matches released' => [static fn (): mixed => $released255()['current_source_returning_order_matches_next255'], true],
    'order mismatch detected' => [static fn (): mixed => $orderHeld255()['current_source_returning_order_matches_next255'], false],
    'order disabled retained' => [static fn (): mixed => $orderAllowed255()['require_current_source_returning_order_next255'], false],
    'returning complete released' => [static fn (): mixed => $released255()['current_source_returning_complete_next255'], true],
    'returning incomplete missing' => [static fn (): mixed => $missing255()['current_source_returning_complete_next255'], false],
    'returning incomplete unexpected' => [static fn (): mixed => $unexpected255()['current_source_returning_complete_next255'], false],
    'returning incomplete cursor' => [static fn (): mixed => $cursorHeld255()['current_source_returning_complete_next255'], false],
    'returning incomplete alias' => [static fn (): mixed => $aliasHeld255()['current_source_returning_complete_next255'], false],
    'returning incomplete order' => [static fn (): mixed => $orderHeld255()['current_source_returning_complete_next255'], false],
    'next visible released' => [static fn (): mixed => $released255()['next_source_visible_after_current_source_returning_next255'], true],
    'next denied missing' => [static fn (): mixed => $missing255()['next_source_visible_after_current_source_returning_next255'], false],
    'next denied cursor' => [static fn (): mixed => $cursorHeld255()['next_source_visible_after_current_source_returning_next255'], false],
    'visible released count' => [static fn (): mixed => $released255()['visible_row_count_next255'], 4],
    'held released count' => [static fn (): mixed => $released255()['held_next_row_count_next255'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing255()['visible_row_count_next255'], 2],
    'held missing count next only' => [static fn (): mixed => $missing255()['held_next_row_count_next255'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released255()['current_source_rows_next255'], 'returning_drain_phase_next255'))), ['current-returning-drain']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released255()['attempted_next_source_rows_next255'], 'returning_drain_phase_next255'))), ['next-source']],
    'current receipts tagged' => [static fn (): mixed => array_column($released255()['current_source_rows_next255'], 'current_source_returning_receipt_next255'), $receipts255()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released255()['attempted_next_source_rows_next255'], 'current_source_returning_receipt_next255'))), [null]],
    'current aliases tagged' => [static fn (): mixed => array_values(array_unique(array_map(static fn (array $row): string => implode(',', $row['current_source_returning_aliases_next255']), $released255()['current_source_rows_next255']))), ['name,value,event_name,spawn_child']],
    'current rows visible while held' => [static fn (): mixed => array_values(array_unique(array_column($missing255()['current_source_rows_next255'], 'visible_after_current_source_returning_next255'))), [true]],
    'next rows held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing255()['attempted_next_source_rows_next255'], 'visible_after_current_source_returning_next255'))), [false]],
    'visible payload names released' => [static fn (): mixed => array_column($released255()['visible_returning_payloads_next255'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'held payload names missing' => [static fn (): mixed => array_column($missing255()['held_next_returning_payloads_next255'], 'name'), ['landing_url', 'next_module']],
    'blocked reasons released' => [static fn (): mixed => $released255()['blocked_reasons_next255'], []],
    'blocked reasons missing' => [static fn (): mixed => $missing255()['blocked_reasons_next255'], ['current-source-returning-receipt-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected255()['blocked_reasons_next255'], ['current-source-returning-receipt-unexpected']],
    'blocked reasons cursor' => [static fn (): mixed => $cursorHeld255()['blocked_reasons_next255'], ['current-source-returning-cursor-mismatch']],
    'blocked reasons alias' => [static fn (): mixed => $aliasHeld255()['blocked_reasons_next255'], ['current-source-returning-alias-missing']],
    'blocked reasons order' => [static fn (): mixed => $orderHeld255()['blocked_reasons_next255'], ['current-source-returning-order-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld255()['blocked_reasons_next255'], ['current-source-upsert-where-receipt-missing']],
    'held row reason copied' => [static fn (): mixed => $missing255()['held_next_source_rows_next255'][0]['held_by_current_source_returning_reasons_next255'], ['current-source-returning-receipt-missing']],
    'plan decision released' => [static fn (): mixed => $released255()['current_source_returning_drain_plan_next255']['decision'], 'publish-next-source-after-current-upsert-returning-drain'],
    'plan decision held' => [static fn (): mixed => $missing255()['current_source_returning_drain_plan_next255']['decision'], 'hold-next-source-until-current-upsert-returning-drain'],
    'plan required echoed' => [static fn (): mixed => $released255()['current_source_returning_drain_plan_next255']['required_receipts'], $receipts255()],
    'plan aliases echoed' => [static fn (): mixed => $released255()['current_source_returning_drain_plan_next255']['required_aliases'], ['name', 'value', 'event_name', 'spawn_child']],
    'yield boundary released' => [static fn (): mixed => $released255()['yield_boundary_next255'], 'recursive-view-upsert-next255-current-returning-drain-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing255()['yield_boundary_next255'], 'recursive-view-upsert-next255-current-returning-drain-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released255()['dependency_closure_next255'], 'no-new-support-component-reuses-native-recursive-view-upsert-where-receipts-and-adds-returning-cursor-drain-fencing'],
    'dependency includes next255' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next255', $released255()['dependencies_next255'], true), true],
    'dependency includes returning drain' => [static fn (): mixed => in_array('sqlite-instead-of-view-upsert-returning-cursor-drain', $released255()['dependencies_next255'], true), true],
    'dependency includes next252' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next252', $released255()['dependencies_next255'], true), true],
    'non overlap mentions next252' => [static fn (): mixed => str_contains($released255()['non_overlap_next255'], 'next252 DO UPDATE WHERE decisions'), true],
    'bad cursor rejected' => [static fn (): mixed => $plan255(['current_source_returning_cursor_next255' => 'bad cursor']), InvalidArgumentException::class],
    'bad expected cursor rejected' => [static fn (): mixed => $plan255(['expected_current_source_returning_cursor_next255' => 'bad cursor']), InvalidArgumentException::class],
    'bad aliases rejected' => [static fn (): mixed => $plan255(['required_current_source_returning_aliases_next255' => []]), InvalidArgumentException::class],
    'bad alias whitespace rejected' => [static fn (): mixed => $plan255(['required_current_source_returning_aliases_next255' => ['bad alias']]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan255(['acknowledged_current_source_returning_receipts_next255' => ['x' => $unexpectedReceipt255]]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan255(['acknowledged_current_source_returning_receipts_next255' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan255(['acknowledged_current_source_returning_receipts_next255' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases255 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next255 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
