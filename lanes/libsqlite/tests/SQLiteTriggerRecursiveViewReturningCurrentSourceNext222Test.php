<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows222 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView222 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-222-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-222-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-222',
];
$nextView222 = $currentView222;
$nextView222['source'] = 'main@view-cookie-222-next';
$nextView222['trigger_source'] = 'main@trigger-cookie-222-next';
$postResetView222 = $currentView222;
$postResetView222['source'] = 'main@view-cookie-222-post-reset';
$postResetView222['trigger_source'] = 'main@trigger-cookie-222-post-reset';
$followingView222 = $currentView222;
$followingView222['source'] = 'main@view-cookie-222-following';
$followingView222['trigger_source'] = 'main@trigger-cookie-222-following';
$currentInput222 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput222 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returning222 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan222 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceTicketFence(
    $rows222,
    $currentInput222,
    $nextInput222,
    $currentView222,
    $nextView222,
    $returning222,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_222',
        'cursor_name' => 'app_recursive_view_returning_cursor_222',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.222',
        'reset_generation' => 'app-current-reset-222',
        'post_reset_current_source_token' => 'app.current.source.postreset.222',
        'post_reset_cursor' => 'app.returning.postreset.cursor.222',
        'post_reset_view' => $postResetView222,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.222',
        'next_cursor' => 'app.returning.next.cursor.222',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.222',
        'following_current_source_token' => 'app.current.source.following.222',
        'following_cursor' => 'app.returning.following.cursor.222',
        'following_current_view' => $followingView222,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-222',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.222',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.222',
        'recursive_child_generation' => 'app-recursive-child-current-222',
        'current_generation_next203' => 'app.current.recursive.returning.generation.222',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.222',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.222',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.222',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.222',
        'current_view_cookie_next209' => 'main@view-cookie-222-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-222-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.222',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.222',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.222',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.222',
        'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.222',
        'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.222',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'app.current.source.ticket.222',
        'current_view_source_next222' => 'main@view-cookie-222-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-222-current',
    ],
);

$tickets222 = static fn (): array => $plan222()['required_current_source_tickets_next222'];
$released222 = static fn (): array => $plan222(['auto_ack_current_source_tickets_next222' => true]);
$missing222 = static fn (): array => $plan222(['acknowledged_current_source_tickets_next222' => array_slice($tickets222(), 0, 1)]);
$unexpected222 = static fn (): array => $plan222(['acknowledged_current_source_tickets_next222' => array_merge($tickets222(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcdef'])]);
$reversed222 = static fn (): array => $plan222(['acknowledged_current_source_tickets_next222' => array_reverse($tickets222())]);
$unordered222 = static fn (): array => $plan222(['require_current_source_ticket_order_next222' => false, 'acknowledged_current_source_tickets_next222' => array_reverse($tickets222())]);
$sourceMismatch222 = static fn (): array => $plan222(['auto_ack_current_source_tickets_next222' => true, 'expected_current_view_source_next222' => 'main@view-cookie-222-stale']);
$baseHeld222 = static fn (): array => $plan222(['auto_ack_current_source_tickets_next222' => true, 'auto_ack_current_source_epochs_next218' => false]);
$custom222 = static fn (): array => $plan222([
    'auto_ack_current_source_tickets_next222' => true,
    'current_source_ticket_next222' => 'app.current.source.ticket.custom.222',
    'current_view_source_next222' => 'main@view-cookie-222-custom',
    'current_trigger_source_next222' => 'main@trigger-cookie-222-custom',
]);
$canonical222 = static fn (): array => $plan222([
    'auto_ack_current_source_tickets' => true,
    'current_source_ticket' => 'app.current.source.ticket.canonical',
    'current_view_source_ticket' => 'main@view-cookie-canonical-current',
    'current_trigger_source_ticket' => 'main@trigger-cookie-canonical-current',
]);

$cases222 = [
    'released status' => [static fn (): mixed => $released222()['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-ticket-released'],
    'missing status' => [static fn (): mixed => $missing222()['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-ticket-held'],
    'unexpected status' => [static fn (): mixed => $unexpected222()['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-ticket-held'],
    'reversed status' => [static fn (): mixed => $reversed222()['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-ticket-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered222()['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-ticket-released'],
    'source mismatch status' => [static fn (): mixed => $sourceMismatch222()['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-mismatch-held'],
    'base held status' => [static fn (): mixed => $baseHeld222()['status_next222'], 'trigger-recursive-view-returning-current-source-next222-base-held'],
    'savepoint retained' => [static fn (): mixed => $released222()['savepoint'], 'app_recursive_view_222'],
    'base next218 released' => [static fn (): mixed => $released222()['base']['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-released'],
    'base next218 held' => [static fn (): mixed => $baseHeld222()['base']['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-held'],
    'base visible released' => [static fn (): mixed => $released222()['base_next_source_visible_next222'], true],
    'base visible held' => [static fn (): mixed => $baseHeld222()['base_next_source_visible_next222'], false],
    'ticket token retained' => [static fn (): mixed => $released222()['current_source_ticket_next222'], 'app.current.source.ticket.222'],
    'custom ticket token retained' => [static fn (): mixed => $custom222()['current_source_ticket_next222'], 'app.current.source.ticket.custom.222'],
    'canonical ticket setting retained' => [static fn (): mixed => $canonical222()['current_source_ticket'], 'app.current.source.ticket.canonical'],
    'canonical ticket legacy alias retained' => [static fn (): mixed => $canonical222()['current_source_ticket_next222'], 'app.current.source.ticket.canonical'],
    'view source retained' => [static fn (): mixed => $released222()['current_view_source_next222'], 'main@view-cookie-222-current'],
    'canonical view source setting retained' => [static fn (): mixed => $canonical222()['current_view_source_ticket'], 'main@view-cookie-canonical-current'],
    'custom view source retained' => [static fn (): mixed => $custom222()['current_view_source_next222'], 'main@view-cookie-222-custom'],
    'trigger source retained' => [static fn (): mixed => $released222()['current_trigger_source_next222'], 'main@trigger-cookie-222-current'],
    'canonical trigger source setting retained' => [static fn (): mixed => $canonical222()['current_trigger_source_ticket'], 'main@trigger-cookie-canonical-current'],
    'custom trigger source retained' => [static fn (): mixed => $custom222()['current_trigger_source_next222'], 'main@trigger-cookie-222-custom'],
    'source matches released' => [static fn (): mixed => $released222()['current_source_matches_next222'], true],
    'source mismatch detected' => [static fn (): mixed => $sourceMismatch222()['current_source_matches_next222'], false],
    'required ticket count' => [static fn (): mixed => count($released222()['required_current_source_tickets_next222']), 2],
    'canonical required tickets alias' => [static fn (): mixed => $canonical222()['required_current_source_tickets'], $canonical222()['required_current_source_tickets_next222']],
    'source tickets are forty two hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{42}$/', $v), $released222()['required_current_source_tickets_next222']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released222()['acknowledged_current_source_tickets_next222'], $tickets222()],
    'canonical acknowledged tickets alias' => [static fn (): mixed => $canonical222()['acknowledged_current_source_tickets'], $canonical222()['required_current_source_tickets']],
    'missing acknowledged count' => [static fn (): mixed => count($missing222()['acknowledged_current_source_tickets_next222']), 1],
    'missing ticket recorded' => [static fn (): mixed => $missing222()['missing_current_source_tickets_next222'], [array_slice($tickets222(), -1)[0]]],
    'unexpected ticket recorded' => [static fn (): mixed => $unexpected222()['unexpected_current_source_tickets_next222'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcdef']],
    'released missing empty' => [static fn (): mixed => $released222()['missing_current_source_tickets_next222'], []],
    'released unexpected empty' => [static fn (): mixed => $released222()['unexpected_current_source_tickets_next222'], []],
    'require order default' => [static fn (): mixed => $released222()['require_current_source_ticket_order_next222'], true],
    'order matches released' => [static fn (): mixed => $released222()['current_source_ticket_order_matches_next222'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed222()['current_source_ticket_order_matches_next222'], false],
    'unordered disables order' => [static fn (): mixed => $unordered222()['require_current_source_ticket_order_next222'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered222()['current_source_ticket_order_matches_next222'], true],
    'ticket complete released' => [static fn (): mixed => $released222()['current_source_ticket_complete_next222'], true],
    'canonical complete alias released' => [static fn (): mixed => $canonical222()['current_source_ticket_complete'], true],
    'ticket incomplete missing' => [static fn (): mixed => $missing222()['current_source_ticket_complete_next222'], false],
    'ticket incomplete unexpected' => [static fn (): mixed => $unexpected222()['current_source_ticket_complete_next222'], false],
    'ticket incomplete reversed' => [static fn (): mixed => $reversed222()['current_source_ticket_complete_next222'], false],
    'ticket incomplete mismatch' => [static fn (): mixed => $sourceMismatch222()['current_source_ticket_complete_next222'], false],
    'next visible released' => [static fn (): mixed => $released222()['next_source_visible_after_current_source_ticket_next222'], true],
    'canonical next visible alias released' => [static fn (): mixed => $canonical222()['next_source_visible_after_current_source_ticket'], true],
    'next denied missing' => [static fn (): mixed => $missing222()['next_source_visible_after_current_source_ticket_next222'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected222()['next_source_visible_after_current_source_ticket_next222'], false],
    'next denied reversed' => [static fn (): mixed => $reversed222()['next_source_visible_after_current_source_ticket_next222'], false],
    'next denied source mismatch' => [static fn (): mixed => $sourceMismatch222()['next_source_visible_after_current_source_ticket_next222'], false],
    'current row count' => [static fn (): mixed => $released222()['current_source_row_count_next222'], 2],
    'attempted next row count' => [static fn (): mixed => $released222()['attempted_next_source_row_count_next222'], 2],
    'visible released count' => [static fn (): mixed => $released222()['visible_row_count_next222'], 4],
    'held released count' => [static fn (): mixed => $released222()['held_next_row_count_next222'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing222()['visible_row_count_next222'], 2],
    'held missing count next only' => [static fn (): mixed => $missing222()['held_next_row_count_next222'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released222()['current_source_rows_next222'], 'source_ticket_phase_next222'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released222()['attempted_next_source_rows_next222'], 'source_ticket_phase_next222'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing222()['current_source_rows_next222'], 'visible_after_current_source_ticket_next222'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released222()['attempted_next_source_rows_next222'], 'visible_after_current_source_ticket_next222'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing222()['attempted_next_source_rows_next222'], 'visible_after_current_source_ticket_next222'))), [false]],
    'current tickets tagged' => [static fn (): mixed => array_column($released222()['current_source_rows_next222'], 'current_source_ticket_receipt_next222'), $tickets222()],
    'canonical row alias preserves ticket receipts' => [static fn (): mixed => array_column($canonical222()['current_source_rows_current_source_ticket'], 'current_source_ticket_receipt'), $canonical222()['required_current_source_tickets']],
    'next tickets null' => [static fn (): mixed => array_values(array_unique(array_column($released222()['attempted_next_source_rows_next222'], 'current_source_ticket_receipt_next222'))), [null]],
    'current ticket token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released222()['current_source_rows_next222'], 'current_source_ticket_next222'))), ['app.current.source.ticket.222']],
    'next ticket token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released222()['attempted_next_source_rows_next222'], 'current_source_ticket_next222'))), ['app.current.source.ticket.222']],
    'current view source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released222()['current_source_rows_next222'], 'current_view_source_next222'))), ['main@view-cookie-222-current']],
    'next trigger source stamped' => [static fn (): mixed => array_values(array_unique(array_column($released222()['attempted_next_source_rows_next222'], 'current_trigger_source_next222'))), ['main@trigger-cookie-222-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released222()['visible_returning_payloads_next222'], 'name'), ['app_summary_child', 'template_child', 'landing_url', 'next_module']],
    'held payload names missing' => [static fn (): mixed => array_column($missing222()['held_next_returning_payloads_next222'], 'name'), ['landing_url', 'next_module']],
    'blocked reasons missing' => [static fn (): mixed => $missing222()['blocked_reasons_next222'], ['current-source-ticket-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected222()['blocked_reasons_next222'], ['current-source-ticket-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed222()['blocked_reasons_next222'], ['current-source-ticket-order-mismatch']],
    'blocked reasons source mismatch' => [static fn (): mixed => $sourceMismatch222()['blocked_reasons_next222'], ['current-source-ticket-source-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld222()['blocked_reasons_next222'], ['current-source-epoch-missing']],
    'released reasons empty' => [static fn (): mixed => $released222()['blocked_reasons_next222'], []],
    'plan decision released' => [static fn (): mixed => $released222()['current_source_ticket_plan_next222']['decision'], 'publish-next-source-after-current-source-ticket'],
    'plan decision missing' => [static fn (): mixed => $missing222()['current_source_ticket_plan_next222']['decision'], 'hold-next-source-until-current-source-ticket'],
    'plan base visible' => [static fn (): mixed => $released222()['current_source_ticket_plan_next222']['base_next_source_visible'], true],
    'plan required echoed' => [static fn (): mixed => $released222()['current_source_ticket_plan_next222']['required_tickets'], $tickets222()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing222()['current_source_ticket_plan_next222']['acknowledged_tickets'], array_slice($tickets222(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released222()['current_source_ticket_plan_next222']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released222()['yield_boundary_next222'], 'recursive-view-returning-next222-current-source-ticket-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing222()['yield_boundary_next222'], 'recursive-view-returning-next222-current-source-ticket-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released222()['dependency_closure_next222'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-ticket-handoff'],
    'canonical dependency closure alias' => [static fn (): mixed => $canonical222()['dependency_closure_current_source_ticket'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-ticket-handoff'],
    'dependency includes next222' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next222', $released222()['dependencies_next222'], true), true],
    'dependency includes ticket receipt' => [static fn (): mixed => in_array('sqlite-returning-current-source-ticket-handoff', $released222()['dependencies_next222'], true), true],
    'dependency includes next218' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next218', $released222()['dependencies_next222'], true), true],
    'non overlap mentions next218' => [static fn (): mixed => str_contains($released222()['non_overlap_next222'], 'next218 epoch handoff'), true],
    'bad ticket token rejected' => [static fn (): mixed => $plan222(['current_source_ticket_next222' => 'bad token']), InvalidArgumentException::class],
    'bad view source rejected' => [static fn (): mixed => $plan222(['current_view_source_next222' => 'bad source']), InvalidArgumentException::class],
    'bad trigger source rejected' => [static fn (): mixed => $plan222(['current_trigger_source_next222' => 'bad source']), InvalidArgumentException::class],
    'bad ticket list rejected' => [static fn (): mixed => $plan222(['acknowledged_current_source_tickets_next222' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcdef']]), InvalidArgumentException::class],
    'bad short ticket rejected' => [static fn (): mixed => $plan222(['acknowledged_current_source_tickets_next222' => ['abc']]), InvalidArgumentException::class],
    'bad non hex ticket rejected' => [static fn (): mixed => $plan222(['acknowledged_current_source_tickets_next222' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases222 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next222 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
