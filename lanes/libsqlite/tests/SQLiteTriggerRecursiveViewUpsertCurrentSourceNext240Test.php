<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows240 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView240 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-240-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-240-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
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
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput240 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning240 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
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
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_240',
        'cursor_name' => 'wp_recursive_view_returning_cursor_240',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.240',
        'reset_generation' => 'wp-current-reset-240',
        'post_reset_current_source_token' => 'wp.current.source.postreset.240',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.240',
        'post_reset_view' => $postResetView240,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.240',
        'next_cursor' => 'wp.returning.next.cursor.240',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.240',
        'following_current_source_token' => 'wp.current.source.following.240',
        'following_cursor' => 'wp.returning.following.cursor.240',
        'following_current_view' => $followingView240,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-240',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.240',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.240',
        'recursive_child_generation' => 'wp-recursive-child-current-240',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.240',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.240',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.240',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.240',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.240',
        'current_view_cookie_next209' => 'main@view-cookie-240-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-240-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.240',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.240',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.240',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.240',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.240',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.240',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.240',
        'current_view_source_next222' => 'main@view-cookie-240-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-240-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.240',
        'current_source_close_token_next231' => 'wp.current.source.close.240',
        'current_view_cookie_next231' => 'main@view-cookie-240-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-240-current',
        'auto_ack_current_source_closures_next231' => true,
        'current_source_upsert_cursor_next240' => 'wp.upsert.current.cursor.240',
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
$baseHeld240 = static fn (): array => $plan240(['auto_ack_current_source_upserts_next240' => true, 'auto_ack_current_source_closures_next231' => false]);
$custom240 = static fn (): array => $plan240([
    'auto_ack_current_source_upserts_next240' => true,
    'current_source_upsert_cursor_next240' => 'wp.upsert.current.cursor.custom.240',
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
    'savepoint retained' => [static fn (): mixed => $released240()['savepoint'], 'wp_recursive_view_240'],
    'base next231 released' => [static fn (): mixed => $released240()['base']['status_next231'], 'trigger-recursive-view-returning-current-source-next231-cursor-close-released'],
    'base next231 held' => [static fn (): mixed => $baseHeld240()['base']['status_next231'], 'trigger-recursive-view-returning-current-source-next231-cursor-close-held'],
    'base visible released' => [static fn (): mixed => $released240()['base_next_source_visible_next240'], true],
    'base visible held' => [static fn (): mixed => $baseHeld240()['base_next_source_visible_next240'], false],
    'cursor retained' => [static fn (): mixed => $released240()['current_source_upsert_cursor_next240'], 'wp.upsert.current.cursor.240'],
    'custom cursor retained' => [static fn (): mixed => $custom240()['current_source_upsert_cursor_next240'], 'wp.upsert.current.cursor.custom.240'],
    'view cookie retained' => [static fn (): mixed => $released240()['current_view_upsert_cookie_next240'], 'main@view-cookie-240-current'],
    'custom view cookie retained' => [static fn (): mixed => $custom240()['current_view_upsert_cookie_next240'], 'main@view-cookie-240-custom'],
    'trigger cookie retained' => [static fn (): mixed => $released240()['current_trigger_upsert_cookie_next240'], 'main@trigger-cookie-240-current'],
    'custom trigger cookie retained' => [static fn (): mixed => $custom240()['current_trigger_upsert_cookie_next240'], 'main@trigger-cookie-240-custom'],
    'conflict columns default' => [static fn (): mixed => $released240()['upsert_conflict_columns_next240'], ['name']],
    'composite columns retained' => [static fn (): mixed => $composite240()['upsert_conflict_columns_next240'], ['name', 'trigger_source_alias']],
    'current keys default' => [static fn (): mixed => $released240()['current_upsert_conflict_keys_next240'], ['name=TEXT:blogdescription_child', 'name=TEXT:template_child']],
    'next keys default' => [static fn (): mixed => $released240()['attempted_next_upsert_conflict_keys_next240'], ['name=TEXT:home', 'name=TEXT:next_plugin']],
    'no conflicting next keys' => [static fn (): mixed => $released240()['conflicting_next_upsert_keys_next240'], []],
    'composite key includes trigger source' => [static fn (): mixed => $composite240()['current_upsert_conflict_keys_next240'][0], 'name=TEXT:blogdescription_child|trigger_source_alias=TEXT:main@trigger-cookie-240-current'],
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
    'current cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released240()['current_source_rows_next240'], 'current_source_upsert_cursor_next240'))), ['wp.upsert.current.cursor.240']],
    'next conflict columns stamped' => [static fn (): mixed => array_values(array_unique(array_map(static fn (array $row): string => implode(',', $row['upsert_conflict_columns_next240']), $released240()['attempted_next_source_rows_next240']))), ['name']],
    'current key stamped' => [static fn (): mixed => array_column($released240()['current_source_rows_next240'], 'upsert_conflict_key_next240'), ['name=TEXT:blogdescription_child', 'name=TEXT:template_child']],
    'next key stamped' => [static fn (): mixed => array_column($released240()['attempted_next_source_rows_next240'], 'upsert_conflict_key_next240'), ['name=TEXT:home', 'name=TEXT:next_plugin']],
    'visible payload names released' => [static fn (): mixed => array_column($released240()['visible_returning_payloads_next240'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing240()['held_next_returning_payloads_next240'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing240()['blocked_reasons_next240'], ['current-source-upsert-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected240()['blocked_reasons_next240'], ['current-source-upsert-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed240()['blocked_reasons_next240'], ['current-source-upsert-order-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld240()['blocked_reasons_next240'], ['current-source-close-missing']],
    'released reasons empty' => [static fn (): mixed => $released240()['blocked_reasons_next240'], []],
    'plan decision released' => [static fn (): mixed => $released240()['current_source_upsert_plan_next240']['decision'], 'publish-next-source-after-current-view-upsert-conflict-source'],
    'plan decision missing' => [static fn (): mixed => $missing240()['current_source_upsert_plan_next240']['decision'], 'hold-next-source-until-current-view-upsert-conflict-source'],
    'plan current keys echoed' => [static fn (): mixed => $released240()['current_source_upsert_plan_next240']['current_keys'], ['name=TEXT:blogdescription_child', 'name=TEXT:template_child']],
    'plan next visible echoed' => [static fn (): mixed => $released240()['current_source_upsert_plan_next240']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released240()['yield_boundary_next240'], 'recursive-view-upsert-next240-current-conflict-source-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing240()['yield_boundary_next240'], 'recursive-view-upsert-next240-current-conflict-source-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released240()['dependency_closure_next240'], 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-handoff'],
    'dependency includes next240' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next240', $released240()['dependencies_next240'], true), true],
    'dependency includes view upsert' => [static fn (): mixed => in_array('sqlite-instead-of-view-upsert-conflict-source', $released240()['dependencies_next240'], true), true],
    'dependency includes next231' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next231', $released240()['dependencies_next240'], true), true],
    'non overlap mentions next231' => [static fn (): mixed => str_contains($released240()['non_overlap_next240'], 'next231 cursor-close'), true],
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
