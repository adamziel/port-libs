<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows240 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView240 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-240-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-240-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-240',
];
$nextView240 = $currentView240;
$nextView240['source'] = 'main@view-cookie-240-next';
$nextView240['trigger_source'] = 'main@trigger-cookie-240-next';
$postResetView240 = $currentView240;
$postResetView240['source'] = 'main@view-cookie-240-post-reset';
$postResetView240['trigger_source'] = 'main@trigger-cookie-240-post-reset';
$followingView240 = $currentView240;
$followingView240['source'] = 'main@view-cookie-240-following';
$followingView240['trigger_source'] = 'main@trigger-cookie-240-following';
$currentInput240 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput240 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning240 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan240 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentCompositeKeyReceipt(
    $rows240,
    $currentInput240,
    $nextInput240,
    $currentView240,
    $nextView240,
    $returning240,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_240',
        'cursor_name' => 'app_recursive_view_returning_cursor_240',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.240',
        'reset_generation' => 'app-current-reset-240',
        'post_reset_current_source_token' => 'app.current.source.postreset.240',
        'post_reset_cursor' => 'app.returning.postreset.cursor.240',
        'post_reset_view' => $postResetView240,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.240',
        'next_cursor' => 'app.returning.next.cursor.240',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.240',
        'following_current_source_token' => 'app.current.source.following.240',
        'following_cursor' => 'app.returning.following.cursor.240',
        'following_current_view' => $followingView240,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-240',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.240',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.240',
        'recursive_child_generation' => 'app-recursive-child-current-240',
        'current_generation_next203' => 'app.current.recursive.returning.generation.240',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.240',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.240',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.240',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.240',
        'current_view_cookie_next209' => 'main@view-cookie-240-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-240-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.240',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.240',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.240',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.240',
        'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.240',
        'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.240',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'app.current.source.ticket.240',
        'current_view_source_next222' => 'main@view-cookie-240-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-240-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'app.returning.current.cursor.240',
        'current_source_close_token_source_close' => 'app.current.source.close.240',
        'current_view_cookie_source_close' => 'main@view-cookie-240-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-240-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_cursor_next240' => 'app.upsert.current.cursor.240',
        'current_view_upsert_cookie_next240' => 'main@view-cookie-240-current',
        'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-240-current',
        'upsert_conflict_columns_next240' => ['name'],
    ],
);

$receipts240 = static fn (): array => $plan240()['required_current_source_upsert_receipts_next240'];
$released240 = static fn (): array => $plan240(['auto_ack_current_source_upserts_next240' => true]);
$missing240 = static fn (): array => $plan240(['acknowledged_current_source_upserts_next240' => array_slice($receipts240(), 0, 1)]);
$unexpected240 = static fn (): array => $plan240(['acknowledged_current_source_upserts_next240' => array_merge($receipts240(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'])]);
$reversed240 = static fn (): array => $plan240(['acknowledged_current_source_upserts_next240' => array_reverse($receipts240())]);
$unordered240 = static fn (): array => $plan240(['require_current_source_upsert_order_next240' => false, 'acknowledged_current_source_upserts_next240' => array_reverse($receipts240())]);
$baseHeld240 = static fn (): array => $plan240(['auto_ack_current_source_upserts_next240' => true, 'auto_ack_current_source_closures_source_close' => false]);
$custom240 = static fn (): array => $plan240([
    'auto_ack_current_source_upserts_next240' => true,
    'current_source_upsert_cursor_next240' => 'app.upsert.current.cursor.custom.240',
    'current_view_upsert_cookie_next240' => 'main@view-cookie-240-custom',
    'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-240-custom',
]);
$composite240 = static fn (): array => $plan240([
    'auto_ack_current_source_upserts_next240' => true,
    'upsert_conflict_columns_next240' => ['name', 'trigger_source_alias'],
]);

$cases240 = [
    'released status' => [static fn (): mixed => $released240()['status_next240'], 'trigger-recursive-view-upsert-current-source-next240-conflict-source-released'],
    'missing status' => [static fn (): mixed => $missing240()['status_next240'], 'trigger-recursive-view-upsert-current-source-next240-conflict-source-held'],
    'unexpected status' => [static fn (): mixed => $unexpected240()['status_next240'], 'trigger-recursive-view-upsert-current-source-next240-conflict-source-held'],
    'reversed status' => [static fn (): mixed => $reversed240()['status_next240'], 'trigger-recursive-view-upsert-current-source-next240-conflict-source-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered240()['status_next240'], 'trigger-recursive-view-upsert-current-source-next240-conflict-source-released'],
    'base held status' => [static fn (): mixed => $baseHeld240()['status_next240'], 'trigger-recursive-view-upsert-current-source-next240-base-held'],
    'savepoint retained' => [static fn (): mixed => $released240()['savepoint'], 'app_recursive_view_240'],
    'base next231 released' => [static fn (): mixed => $released240()['base']['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-cursor-close-released'],
    'base next231 held' => [static fn (): mixed => $baseHeld240()['base']['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-cursor-close-held'],
    'base visible released' => [static fn (): mixed => $released240()['base_next_source_visible_next240'], true],
    'base visible held' => [static fn (): mixed => $baseHeld240()['base_next_source_visible_next240'], false],
    'cursor retained' => [static fn (): mixed => $released240()['current_source_upsert_cursor_next240'], 'app.upsert.current.cursor.240'],
    'custom cursor retained' => [static fn (): mixed => $custom240()['current_source_upsert_cursor_next240'], 'app.upsert.current.cursor.custom.240'],
    'view cookie retained' => [static fn (): mixed => $released240()['current_view_upsert_cookie_next240'], 'main@view-cookie-240-current'],
    'custom view cookie retained' => [static fn (): mixed => $custom240()['current_view_upsert_cookie_next240'], 'main@view-cookie-240-custom'],
    'trigger cookie retained' => [static fn (): mixed => $released240()['current_trigger_upsert_cookie_next240'], 'main@trigger-cookie-240-current'],
    'custom trigger cookie retained' => [static fn (): mixed => $custom240()['current_trigger_upsert_cookie_next240'], 'main@trigger-cookie-240-custom'],
    'conflict columns default' => [static fn (): mixed => $released240()['upsert_conflict_columns_next240'], ['name']],
    'composite columns retained' => [static fn (): mixed => $composite240()['upsert_conflict_columns_next240'], ['name', 'trigger_source_alias']],
    'current keys default' => [static fn (): mixed => $released240()['current_upsert_conflict_keys_next240'], ['name=TEXT:app_summary_child', 'name=TEXT:template_child']],
    'next keys default' => [static fn (): mixed => $released240()['attempted_next_upsert_conflict_keys_next240'], ['name=TEXT:landing_url', 'name=TEXT:next_module']],
    'no conflicting next keys' => [static fn (): mixed => $released240()['conflicting_next_upsert_keys_next240'], []],
    'composite key includes trigger source' => [static fn (): mixed => $composite240()['current_upsert_conflict_keys_next240'][0], 'name=TEXT:app_summary_child|trigger_source_alias=TEXT:main@trigger-cookie-240-current'],
    'required receipt count' => [static fn (): mixed => count($released240()['required_current_source_upsert_receipts_next240']), 2],
    'receipts are forty eight hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{48}$/', $v), $released240()['required_current_source_upsert_receipts_next240']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released240()['acknowledged_current_source_upsert_receipts_next240'], $receipts240()],
    'missing acknowledged count' => [static fn (): mixed => count($missing240()['acknowledged_current_source_upsert_receipts_next240']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing240()['missing_current_source_upsert_receipts_next240'], [array_slice($receipts240(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected240()['unexpected_current_source_upsert_receipts_next240'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef']],
    'released missing empty' => [static fn (): mixed => $released240()['missing_current_source_upsert_receipts_next240'], []],
    'released unexpected empty' => [static fn (): mixed => $released240()['unexpected_current_source_upsert_receipts_next240'], []],
    'require order default' => [static fn (): mixed => $released240()['require_current_source_upsert_order_next240'], true],
    'order matches released' => [static fn (): mixed => $released240()['current_source_upsert_order_matches_next240'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed240()['current_source_upsert_order_matches_next240'], false],
    'unordered disables order' => [static fn (): mixed => $unordered240()['require_current_source_upsert_order_next240'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered240()['current_source_upsert_order_matches_next240'], true],
    'upsert complete released' => [static fn (): mixed => $released240()['current_source_upsert_complete_next240'], true],
    'upsert incomplete missing' => [static fn (): mixed => $missing240()['current_source_upsert_complete_next240'], false],
    'upsert incomplete unexpected' => [static fn (): mixed => $unexpected240()['current_source_upsert_complete_next240'], false],
    'upsert incomplete reversed' => [static fn (): mixed => $reversed240()['current_source_upsert_complete_next240'], false],
    'next visible released' => [static fn (): mixed => $released240()['next_source_visible_after_current_source_upsert_next240'], true],
    'next denied missing' => [static fn (): mixed => $missing240()['next_source_visible_after_current_source_upsert_next240'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected240()['next_source_visible_after_current_source_upsert_next240'], false],
    'next denied reversed' => [static fn (): mixed => $reversed240()['next_source_visible_after_current_source_upsert_next240'], false],
    'next denied base held' => [static fn (): mixed => $baseHeld240()['next_source_visible_after_current_source_upsert_next240'], false],
    'current row count' => [static fn (): mixed => $released240()['current_source_row_count_next240'], 2],
    'attempted next row count' => [static fn (): mixed => $released240()['attempted_next_source_row_count_next240'], 2],
    'visible released count' => [static fn (): mixed => $released240()['visible_row_count_next240'], 4],
    'held released count' => [static fn (): mixed => $released240()['held_next_row_count_next240'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing240()['visible_row_count_next240'], 2],
    'held missing count next only' => [static fn (): mixed => $missing240()['held_next_row_count_next240'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released240()['current_source_rows_next240'], 'upsert_source_phase_next240'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released240()['attempted_next_source_rows_next240'], 'upsert_source_phase_next240'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing240()['current_source_rows_next240'], 'visible_after_current_source_upsert_next240'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released240()['attempted_next_source_rows_next240'], 'visible_after_current_source_upsert_next240'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing240()['attempted_next_source_rows_next240'], 'visible_after_current_source_upsert_next240'))), [false]],
    'current receipts tagged' => [static fn (): mixed => array_column($released240()['current_source_rows_next240'], 'current_source_upsert_receipt_next240'), $receipts240()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released240()['attempted_next_source_rows_next240'], 'current_source_upsert_receipt_next240'))), [null]],
    'current cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released240()['current_source_rows_next240'], 'current_source_upsert_cursor_next240'))), ['app.upsert.current.cursor.240']],
    'next conflict columns stamped' => [static fn (): mixed => array_values(array_unique(array_map(static fn (array $row): string => implode(',', $row['upsert_conflict_columns_next240']), $released240()['attempted_next_source_rows_next240']))), ['name']],
    'current key stamped' => [static fn (): mixed => array_column($released240()['current_source_rows_next240'], 'upsert_conflict_key_next240'), ['name=TEXT:app_summary_child', 'name=TEXT:template_child']],
    'next key stamped' => [static fn (): mixed => array_column($released240()['attempted_next_source_rows_next240'], 'upsert_conflict_key_next240'), ['name=TEXT:landing_url', 'name=TEXT:next_module']],
    'visible payload names released' => [static fn (): mixed => array_column($released240()['visible_returning_payloads_next240'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'held payload names missing' => [static fn (): mixed => array_column($missing240()['held_next_returning_payloads_next240'], 'name'), ['landing_url', 'next_module']],
    'blocked reasons missing' => [static fn (): mixed => $missing240()['blocked_reasons_next240'], ['current-source-upsert-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected240()['blocked_reasons_next240'], ['current-source-upsert-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed240()['blocked_reasons_next240'], ['current-source-upsert-order-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld240()['blocked_reasons_next240'], ['current-source-close-missing']],
    'released reasons empty' => [static fn (): mixed => $released240()['blocked_reasons_next240'], []],
    'plan decision released' => [static fn (): mixed => $released240()['current_source_upsert_plan_next240']['decision'], 'publish-next-source-after-current-view-upsert-conflict-source'],
    'plan decision missing' => [static fn (): mixed => $missing240()['current_source_upsert_plan_next240']['decision'], 'hold-next-source-until-current-view-upsert-conflict-source'],
    'plan current keys echoed' => [static fn (): mixed => $released240()['current_source_upsert_plan_next240']['current_keys'], ['name=TEXT:app_summary_child', 'name=TEXT:template_child']],
    'plan next visible echoed' => [static fn (): mixed => $released240()['current_source_upsert_plan_next240']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released240()['yield_boundary_next240'], 'recursive-view-upsert-next240-current-conflict-source-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing240()['yield_boundary_next240'], 'recursive-view-upsert-next240-current-conflict-source-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released240()['dependency_closure_next240'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-handoff'],
    'dependency includes next240' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next240', $released240()['dependencies_next240'], true), true],
    'dependency includes view upsert' => [static fn (): mixed => in_array('sqlite-instead-of-view-upsert-conflict-source', $released240()['dependencies_next240'], true), true],
    'dependency includes next231' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-source_close', $released240()['dependencies_next240'], true), true],
    'non overlap mentions next231' => [static fn (): mixed => str_contains($released240()['non_overlap_next240'], 'source_close cursor-close'), true],
    'bad cursor rejected' => [static fn (): mixed => $plan240(['current_source_upsert_cursor_next240' => 'bad cursor']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $plan240(['current_view_upsert_cookie_next240' => 'bad cookie']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $plan240(['current_trigger_upsert_cookie_next240' => 'bad cookie']), InvalidArgumentException::class],
    'bad conflict column list rejected' => [static fn (): mixed => $plan240(['upsert_conflict_columns_next240' => []]), InvalidArgumentException::class],
    'bad conflict column whitespace rejected' => [static fn (): mixed => $plan240(['upsert_conflict_columns_next240' => ['bad column']]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan240(['acknowledged_current_source_upserts_next240' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef']]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan240(['acknowledged_current_source_upserts_next240' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan240(['acknowledged_current_source_upserts_next240' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
    'missing conflict column rejected' => [static fn (): mixed => $plan240(['auto_ack_current_source_upserts_next240' => true, 'upsert_conflict_columns_next240' => ['missing']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases240 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next240 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
