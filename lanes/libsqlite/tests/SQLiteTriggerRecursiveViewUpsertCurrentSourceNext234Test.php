<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$rows234 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView234 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-234-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-234-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-234',
];
$nextView234 = $currentView234;
$nextView234['source'] = 'main@view-cookie-234-next';
$nextView234['trigger_source'] = 'main@trigger-cookie-234-next';
$postResetView234 = $currentView234;
$postResetView234['source'] = 'main@view-cookie-234-post-reset';
$postResetView234['trigger_source'] = 'main@trigger-cookie-234-post-reset';
$followingView234 = $currentView234;
$followingView234['source'] = 'main@view-cookie-234-following';
$followingView234['trigger_source'] = 'main@trigger-cookie-234-following';
$currentInput234 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput234 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning234 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan234 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeNext234(
    $rows234,
    $currentInput234,
    $nextInput234,
    $currentView234,
    $nextView234,
    $returning234,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_234',
        'cursor_name' => 'wp_recursive_view_returning_cursor_234',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.234',
        'reset_generation' => 'wp-current-reset-234',
        'post_reset_current_source_token' => 'wp.current.source.postreset.234',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.234',
        'post_reset_view' => $postResetView234,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.234',
        'next_cursor' => 'wp.returning.next.cursor.234',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.234',
        'following_current_source_token' => 'wp.current.source.following.234',
        'following_cursor' => 'wp.returning.following.cursor.234',
        'following_current_view' => $followingView234,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-234',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.234',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.234',
        'recursive_child_generation' => 'wp-recursive-child-current-234',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.234',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.234',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.234',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.234',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.234',
        'current_view_cookie_next209' => 'main@view-cookie-234-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-234-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.234',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.234',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.234',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.234',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.234',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.234',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.234',
        'current_view_source_next222' => 'main@view-cookie-234-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-234-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.234',
        'current_source_close_token_next231' => 'wp.current.source.close.234',
        'current_view_cookie_next231' => 'main@view-cookie-234-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-234-current',
        'auto_ack_current_source_closures_next231' => true,
        'current_source_upsert_token_next234' => 'wp.current.source.upsert.234',
        'current_upsert_view_cookie_next234' => 'main@view-cookie-234-current',
        'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-234-current',
    ],
);

$receipts234 = static fn (): array => $plan234()['required_current_source_upsert_receipts_next234'];
$released234 = static fn (): array => $plan234(['auto_ack_current_source_upserts_next234' => true]);
$missing234 = static fn (): array => $plan234(['acknowledged_current_source_upsert_receipts_next234' => array_slice($receipts234(), 0, 1)]);
$unexpected234 = static fn (): array => $plan234(['acknowledged_current_source_upsert_receipts_next234' => array_merge($receipts234(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcd'])]);
$reversed234 = static fn (): array => $plan234(['acknowledged_current_source_upsert_receipts_next234' => array_reverse($receipts234())]);
$unordered234 = static fn (): array => $plan234(['require_current_source_upsert_order_next234' => false, 'acknowledged_current_source_upsert_receipts_next234' => array_reverse($receipts234())]);
$tokenMismatch234 = static fn (): array => $plan234(['auto_ack_current_source_upserts_next234' => true, 'expected_current_source_upsert_token_next234' => 'wp.current.source.upsert.stale.234']);
$baseHeld234 = static fn (): array => $plan234(['auto_ack_current_source_upserts_next234' => true, 'auto_ack_current_source_closures_next231' => false]);
$custom234 = static fn (): array => $plan234([
    'auto_ack_current_source_upserts_next234' => true,
    'upsert_conflict_columns_next234' => ['option_name', 'autoload'],
    'current_source_upsert_token_next234' => 'wp.current.source.upsert.custom.234',
    'current_upsert_view_cookie_next234' => 'main@view-cookie-234-custom',
    'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-234-custom',
]);

$cases234 = [
    'released status' => [static fn (): mixed => $released234()['status_next234'], 'trigger-recursive-view-upsert-current-source-next234-upsert-released'],
    'missing status' => [static fn (): mixed => $missing234()['status_next234'], 'trigger-recursive-view-upsert-current-source-next234-upsert-held'],
    'unexpected status' => [static fn (): mixed => $unexpected234()['status_next234'], 'trigger-recursive-view-upsert-current-source-next234-upsert-held'],
    'reversed status' => [static fn (): mixed => $reversed234()['status_next234'], 'trigger-recursive-view-upsert-current-source-next234-upsert-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered234()['status_next234'], 'trigger-recursive-view-upsert-current-source-next234-upsert-released'],
    'token mismatch status' => [static fn (): mixed => $tokenMismatch234()['status_next234'], 'trigger-recursive-view-upsert-current-source-next234-upsert-token-held'],
    'base held status' => [static fn (): mixed => $baseHeld234()['status_next234'], 'trigger-recursive-view-upsert-current-source-next234-base-held'],
    'savepoint retained' => [static fn (): mixed => $released234()['savepoint'], 'wp_recursive_view_234'],
    'base next231 released' => [static fn (): mixed => $released234()['base']['status_next231'], 'trigger-recursive-view-returning-current-source-next231-cursor-close-released'],
    'base next231 held' => [static fn (): mixed => $baseHeld234()['base']['status_next231'], 'trigger-recursive-view-returning-current-source-next231-cursor-close-held'],
    'base visible released' => [static fn (): mixed => $released234()['base_next_source_visible_next234'], true],
    'base visible held' => [static fn (): mixed => $baseHeld234()['base_next_source_visible_next234'], false],
    'conflict columns default' => [static fn (): mixed => $released234()['upsert_conflict_columns_next234'], ['option_name']],
    'conflict columns custom' => [static fn (): mixed => $custom234()['upsert_conflict_columns_next234'], ['option_name', 'autoload']],
    'upsert token retained' => [static fn (): mixed => $released234()['current_source_upsert_token_next234'], 'wp.current.source.upsert.234'],
    'custom upsert token retained' => [static fn (): mixed => $custom234()['current_source_upsert_token_next234'], 'wp.current.source.upsert.custom.234'],
    'view cookie retained' => [static fn (): mixed => $released234()['current_upsert_view_cookie_next234'], 'main@view-cookie-234-current'],
    'custom view cookie retained' => [static fn (): mixed => $custom234()['current_upsert_view_cookie_next234'], 'main@view-cookie-234-custom'],
    'trigger cookie retained' => [static fn (): mixed => $released234()['current_upsert_trigger_cookie_next234'], 'main@trigger-cookie-234-current'],
    'custom trigger cookie retained' => [static fn (): mixed => $custom234()['current_upsert_trigger_cookie_next234'], 'main@trigger-cookie-234-custom'],
    'token matches released' => [static fn (): mixed => $released234()['current_source_upsert_token_matches_next234'], true],
    'token mismatch detected' => [static fn (): mixed => $tokenMismatch234()['current_source_upsert_token_matches_next234'], false],
    'required receipt count' => [static fn (): mixed => count($released234()['required_current_source_upsert_receipts_next234']), 2],
    'receipts are forty hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{40}$/', $v), $released234()['required_current_source_upsert_receipts_next234']), [1, 1]],
    'custom receipts differ' => [static fn (): mixed => $custom234()['required_current_source_upsert_receipts_next234'] === $released234()['required_current_source_upsert_receipts_next234'], false],
    'auto acknowledged equals required' => [static fn (): mixed => $released234()['acknowledged_current_source_upsert_receipts_next234'], $receipts234()],
    'missing acknowledged count' => [static fn (): mixed => count($missing234()['acknowledged_current_source_upsert_receipts_next234']), 1],
    'missing receipt recorded' => [static fn (): mixed => $missing234()['missing_current_source_upsert_receipts_next234'], [array_slice($receipts234(), -1)[0]]],
    'unexpected receipt recorded' => [static fn (): mixed => $unexpected234()['unexpected_current_source_upsert_receipts_next234'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcd']],
    'released missing empty' => [static fn (): mixed => $released234()['missing_current_source_upsert_receipts_next234'], []],
    'released unexpected empty' => [static fn (): mixed => $released234()['unexpected_current_source_upsert_receipts_next234'], []],
    'require order default' => [static fn (): mixed => $released234()['require_current_source_upsert_order_next234'], true],
    'order matches released' => [static fn (): mixed => $released234()['current_source_upsert_order_matches_next234'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed234()['current_source_upsert_order_matches_next234'], false],
    'unordered disables order' => [static fn (): mixed => $unordered234()['require_current_source_upsert_order_next234'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered234()['current_source_upsert_order_matches_next234'], true],
    'upsert complete released' => [static fn (): mixed => $released234()['current_source_upsert_complete_next234'], true],
    'upsert incomplete missing' => [static fn (): mixed => $missing234()['current_source_upsert_complete_next234'], false],
    'upsert incomplete unexpected' => [static fn (): mixed => $unexpected234()['current_source_upsert_complete_next234'], false],
    'upsert incomplete reversed' => [static fn (): mixed => $reversed234()['current_source_upsert_complete_next234'], false],
    'upsert incomplete mismatch' => [static fn (): mixed => $tokenMismatch234()['current_source_upsert_complete_next234'], false],
    'next visible released' => [static fn (): mixed => $released234()['next_source_visible_after_current_source_upsert_next234'], true],
    'next denied missing' => [static fn (): mixed => $missing234()['next_source_visible_after_current_source_upsert_next234'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected234()['next_source_visible_after_current_source_upsert_next234'], false],
    'next denied reversed' => [static fn (): mixed => $reversed234()['next_source_visible_after_current_source_upsert_next234'], false],
    'next denied token mismatch' => [static fn (): mixed => $tokenMismatch234()['next_source_visible_after_current_source_upsert_next234'], false],
    'current row count' => [static fn (): mixed => $released234()['current_source_row_count_next234'], 2],
    'attempted next row count' => [static fn (): mixed => $released234()['attempted_next_source_row_count_next234'], 2],
    'visible released count' => [static fn (): mixed => $released234()['visible_row_count_next234'], 4],
    'held released count' => [static fn (): mixed => $released234()['held_next_row_count_next234'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing234()['visible_row_count_next234'], 2],
    'held missing count next only' => [static fn (): mixed => $missing234()['held_next_row_count_next234'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released234()['current_source_rows_next234'], 'upsert_phase_next234'))), ['current-upsert']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released234()['attempted_next_source_rows_next234'], 'upsert_phase_next234'))), ['next-source']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing234()['current_source_rows_next234'], 'visible_after_current_source_upsert_next234'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released234()['attempted_next_source_rows_next234'], 'visible_after_current_source_upsert_next234'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing234()['attempted_next_source_rows_next234'], 'visible_after_current_source_upsert_next234'))), [false]],
    'current receipts tagged' => [static fn (): mixed => array_column($released234()['current_source_rows_next234'], 'current_source_upsert_receipt_next234'), $receipts234()],
    'next receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released234()['attempted_next_source_rows_next234'], 'current_source_upsert_receipt_next234'))), [null]],
    'current conflict columns stamped' => [static fn (): mixed => array_values(array_unique(array_map(static fn (array $row): string => implode(',', $row['upsert_conflict_columns_next234']), $released234()['current_source_rows_next234']))), ['option_name']],
    'current token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released234()['current_source_rows_next234'], 'current_source_upsert_token_next234'))), ['wp.current.source.upsert.234']],
    'next view cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($released234()['attempted_next_source_rows_next234'], 'current_upsert_view_cookie_next234'))), ['main@view-cookie-234-current']],
    'next trigger cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($released234()['attempted_next_source_rows_next234'], 'current_upsert_trigger_cookie_next234'))), ['main@trigger-cookie-234-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released234()['visible_returning_payloads_next234'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing234()['held_next_returning_payloads_next234'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing234()['blocked_reasons_next234'], ['current-source-upsert-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected234()['blocked_reasons_next234'], ['current-source-upsert-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed234()['blocked_reasons_next234'], ['current-source-upsert-order-mismatch']],
    'blocked reasons token mismatch' => [static fn (): mixed => $tokenMismatch234()['blocked_reasons_next234'], ['current-source-upsert-token-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld234()['blocked_reasons_next234'], ['current-source-close-missing']],
    'released reasons empty' => [static fn (): mixed => $released234()['blocked_reasons_next234'], []],
    'plan decision released' => [static fn (): mixed => $released234()['current_source_upsert_plan_next234']['decision'], 'publish-next-source-after-current-recursive-view-upsert'],
    'plan decision missing' => [static fn (): mixed => $missing234()['current_source_upsert_plan_next234']['decision'], 'hold-next-source-until-current-recursive-view-upsert'],
    'plan base visible' => [static fn (): mixed => $released234()['current_source_upsert_plan_next234']['base_next_source_visible'], true],
    'plan required echoed' => [static fn (): mixed => $released234()['current_source_upsert_plan_next234']['required_upsert_receipts'], $receipts234()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing234()['current_source_upsert_plan_next234']['acknowledged_upsert_receipts'], array_slice($receipts234(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released234()['current_source_upsert_plan_next234']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released234()['yield_boundary_next234'], 'recursive-view-upsert-next234-current-upsert-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing234()['yield_boundary_next234'], 'recursive-view-upsert-next234-current-upsert-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released234()['dependency_closure_next234'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-close-and-adds-upsert-conflict-receipts'],
    'dependency includes next234' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-upsert-current-source-next234', $released234()['dependencies_next234'], true), true],
    'dependency includes upsert receipts' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-current-source-upsert-receipts', $released234()['dependencies_next234'], true), true],
    'dependency includes next231' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next231', $released234()['dependencies_next234'], true), true],
    'non overlap mentions next231' => [static fn (): mixed => str_contains($released234()['non_overlap_next234'], 'next231 cursor-close handoff'), true],
    'bad upsert token rejected' => [static fn (): mixed => $plan234(['current_source_upsert_token_next234' => 'bad token']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $plan234(['current_upsert_view_cookie_next234' => 'bad cookie']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $plan234(['current_upsert_trigger_cookie_next234' => 'bad cookie']), InvalidArgumentException::class],
    'bad conflict columns rejected' => [static fn (): mixed => $plan234(['upsert_conflict_columns_next234' => []]), InvalidArgumentException::class],
    'bad conflict column name rejected' => [static fn (): mixed => $plan234(['upsert_conflict_columns_next234' => ['bad-column']]), InvalidArgumentException::class],
    'bad receipt list rejected' => [static fn (): mixed => $plan234(['acknowledged_current_source_upsert_receipts_next234' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd']]), InvalidArgumentException::class],
    'bad short receipt rejected' => [static fn (): mixed => $plan234(['acknowledged_current_source_upsert_receipts_next234' => ['abc']]), InvalidArgumentException::class],
    'bad non hex receipt rejected' => [static fn (): mixed => $plan234(['acknowledged_current_source_upsert_receipts_next234' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases234 as $name => [$callback, $expected]) {
    $tests['trigger recursive view upsert current source next234 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
