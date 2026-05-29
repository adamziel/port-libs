<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows218 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView218 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-218-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-218-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-218',
];
$nextView218 = $currentView218;
$nextView218['source'] = 'main@view-cookie-218-next';
$nextView218['trigger_source'] = 'main@trigger-cookie-218-next';
$postResetView218 = $currentView218;
$postResetView218['source'] = 'main@view-cookie-218-post-reset';
$postResetView218['trigger_source'] = 'main@trigger-cookie-218-post-reset';
$followingView218 = $currentView218;
$followingView218['source'] = 'main@view-cookie-218-following';
$followingView218['trigger_source'] = 'main@trigger-cookie-218-following';
$currentInput218 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput218 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput218 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$followingInput218 = [
    ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
];
$returning218 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan218 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceEpochFence(
    $rows218,
    $currentInput218,
    $nextInput218,
    $currentView218,
    $nextView218,
    $returning218,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_218',
        'cursor_name' => 'wp_recursive_view_returning_cursor_218',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.218',
        'reset_generation' => 'wp-current-reset-218',
        'post_reset_current_source_token' => 'wp.current.source.postreset.218',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.218',
        'post_reset_view' => $postResetView218,
        'post_reset_input' => $postResetInput218,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.218',
        'next_cursor' => 'wp.returning.next.cursor.218',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.218',
        'following_current_source_token' => 'wp.current.source.following.218',
        'following_cursor' => 'wp.returning.following.cursor.218',
        'following_current_view' => $followingView218,
        'following_current_input' => $followingInput218,
        'following_generation' => 'wp-following-current-218',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.218',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.218',
        'recursive_child_generation' => 'wp-recursive-child-current-218',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.218',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.218',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.218',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.218',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.218',
        'current_view_cookie_next209' => 'main@view-cookie-218-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-218-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.218',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.218',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.218',
    ],
);

$receipts218 = static fn (): array => $plan218()['required_current_source_epochs_next218'];
$released218 = static fn (): array => $plan218(['auto_ack_current_source_epochs_next218' => true]);
$missing218 = static fn (): array => $plan218(['acknowledged_current_source_epochs_next218' => array_slice($receipts218(), 0, 1)]);
$unexpected218 = static fn (): array => $plan218(['acknowledged_current_source_epochs_next218' => array_merge($receipts218(), ['abcdefabcdefabcdefabcdefabcdefabcdef'])]);
$reversed218 = static fn (): array => $plan218(['acknowledged_current_source_epochs_next218' => array_reverse($receipts218())]);
$unordered218 = static fn (): array => $plan218(['require_current_source_epoch_order_next218' => false, 'acknowledged_current_source_epochs_next218' => array_reverse($receipts218())]);
$baseHeld218 = static fn (): array => $plan218(['auto_ack_current_source_epochs_next218' => true, 'auto_ack_current_source_watermarks_next209' => false]);
$custom218 = static fn (): array => $plan218([
    'auto_ack_current_source_epochs_next218' => true,
    'current_source_epoch_next218' => 'wp.current.source.epoch.custom.218',
    'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.custom.218',
    'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.custom.218',
]);

$cases218 = [
    'released status' => [static fn (): mixed => $released218()['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-released'],
    'missing status' => [static fn (): mixed => $missing218()['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-held'],
    'unexpected status' => [static fn (): mixed => $unexpected218()['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-held'],
    'reversed status' => [static fn (): mixed => $reversed218()['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unordered218()['status_next218'], 'trigger-recursive-view-returning-current-source-next218-epoch-released'],
    'base held status' => [static fn (): mixed => $baseHeld218()['status_next218'], 'trigger-recursive-view-returning-current-source-next218-base-held'],
    'savepoint retained' => [static fn (): mixed => $released218()['savepoint'], 'wp_recursive_view_218'],
    'base next212 released' => [static fn (): mixed => $released218()['base']['status_next212'], 'trigger-recursive-view-returning-current-source-next212-yield-released'],
    'base next212 held' => [static fn (): mixed => $baseHeld218()['base']['status_next212'], 'trigger-recursive-view-returning-current-source-next212-base-held'],
    'base visible released' => [static fn (): mixed => $released218()['base_next_source_visible_next218'], true],
    'base visible held' => [static fn (): mixed => $baseHeld218()['base_next_source_visible_next218'], false],
    'epoch token retained' => [static fn (): mixed => $released218()['current_source_epoch_next218'], 'wp.current.source.epoch.218'],
    'custom epoch token retained' => [static fn (): mixed => $custom218()['current_source_epoch_next218'], 'wp.current.source.epoch.custom.218'],
    'view epoch cursor retained' => [static fn (): mixed => $released218()['current_view_epoch_next218'], 'wp.returning.view.epoch.cursor.218'],
    'custom view epoch cursor retained' => [static fn (): mixed => $custom218()['current_view_epoch_next218'], 'wp.returning.view.epoch.cursor.custom.218'],
    'trigger epoch cursor retained' => [static fn (): mixed => $released218()['current_trigger_epoch_next218'], 'wp.returning.trigger.epoch.cursor.218'],
    'custom trigger epoch cursor retained' => [static fn (): mixed => $custom218()['current_trigger_epoch_next218'], 'wp.returning.trigger.epoch.cursor.custom.218'],
    'required epoch count' => [static fn (): mixed => count($released218()['required_current_source_epochs_next218']), 2],
    'epoch receipts are 36 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{36}$/', $v), $released218()['required_current_source_epochs_next218']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released218()['acknowledged_current_source_epochs_next218'], $receipts218()],
    'missing acknowledged count' => [static fn (): mixed => count($missing218()['acknowledged_current_source_epochs_next218']), 1],
    'missing epoch recorded' => [static fn (): mixed => $missing218()['missing_current_source_epochs_next218'], [array_slice($receipts218(), -1)[0]]],
    'unexpected epoch recorded' => [static fn (): mixed => $unexpected218()['unexpected_current_source_epochs_next218'], ['abcdefabcdefabcdefabcdefabcdefabcdef']],
    'released missing empty' => [static fn (): mixed => $released218()['missing_current_source_epochs_next218'], []],
    'released unexpected empty' => [static fn (): mixed => $released218()['unexpected_current_source_epochs_next218'], []],
    'require order default' => [static fn (): mixed => $released218()['require_current_source_epoch_order_next218'], true],
    'order matches released' => [static fn (): mixed => $released218()['current_source_epoch_order_matches_next218'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversed218()['current_source_epoch_order_matches_next218'], false],
    'unordered disables order' => [static fn (): mixed => $unordered218()['require_current_source_epoch_order_next218'], false],
    'unordered order considered matched' => [static fn (): mixed => $unordered218()['current_source_epoch_order_matches_next218'], true],
    'epoch complete released' => [static fn (): mixed => $released218()['current_source_epoch_complete_next218'], true],
    'epoch incomplete missing' => [static fn (): mixed => $missing218()['current_source_epoch_complete_next218'], false],
    'epoch incomplete unexpected' => [static fn (): mixed => $unexpected218()['current_source_epoch_complete_next218'], false],
    'epoch incomplete reversed' => [static fn (): mixed => $reversed218()['current_source_epoch_complete_next218'], false],
    'next visible released' => [static fn (): mixed => $released218()['next_source_visible_after_current_source_epoch_next218'], true],
    'next denied missing' => [static fn (): mixed => $missing218()['next_source_visible_after_current_source_epoch_next218'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpected218()['next_source_visible_after_current_source_epoch_next218'], false],
    'next denied reversed' => [static fn (): mixed => $reversed218()['next_source_visible_after_current_source_epoch_next218'], false],
    'next denied base held' => [static fn (): mixed => $baseHeld218()['next_source_visible_after_current_source_epoch_next218'], false],
    'current row count' => [static fn (): mixed => $released218()['current_source_row_count_next218'], 2],
    'attempted next row count' => [static fn (): mixed => $released218()['attempted_next_source_row_count_next218'], 2],
    'visible released count' => [static fn (): mixed => $released218()['visible_row_count_next218'], 4],
    'held released count' => [static fn (): mixed => $released218()['held_next_row_count_next218'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing218()['visible_row_count_next218'], 2],
    'held missing count next only' => [static fn (): mixed => $missing218()['held_next_row_count_next218'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released218()['current_source_rows_next218'], 'source_epoch_phase_next218'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released218()['attempted_next_source_rows_next218'], 'source_epoch_phase_next218'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing218()['current_source_rows_next218'], 'visible_after_current_source_epoch_next218'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released218()['attempted_next_source_rows_next218'], 'visible_after_current_source_epoch_next218'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing218()['attempted_next_source_rows_next218'], 'visible_after_current_source_epoch_next218'))), [false]],
    'current epoch receipts tagged' => [static fn (): mixed => array_column($released218()['current_source_rows_next218'], 'current_source_epoch_receipt_next218'), $receipts218()],
    'next epoch receipts null' => [static fn (): mixed => array_values(array_unique(array_column($released218()['attempted_next_source_rows_next218'], 'current_source_epoch_receipt_next218'))), [null]],
    'current epoch token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released218()['current_source_rows_next218'], 'current_source_epoch_next218'))), ['wp.current.source.epoch.218']],
    'next epoch token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released218()['attempted_next_source_rows_next218'], 'current_source_epoch_next218'))), ['wp.current.source.epoch.218']],
    'current view cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released218()['current_source_rows_next218'], 'current_view_epoch_next218'))), ['wp.returning.view.epoch.cursor.218']],
    'next trigger cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released218()['attempted_next_source_rows_next218'], 'current_trigger_epoch_next218'))), ['wp.returning.trigger.epoch.cursor.218']],
    'visible payload names released' => [static fn (): mixed => array_column($released218()['visible_returning_payloads_next218'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing218()['held_next_returning_payloads_next218'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing218()['blocked_reasons_next218'], ['current-source-epoch-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected218()['blocked_reasons_next218'], ['current-source-epoch-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversed218()['blocked_reasons_next218'], ['current-source-epoch-order-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeld218()['blocked_reasons_next218'], ['current-source-watermark-missing']],
    'released reasons empty' => [static fn (): mixed => $released218()['blocked_reasons_next218'], []],
    'plan decision released' => [static fn (): mixed => $released218()['current_source_epoch_plan_next218']['decision'], 'publish-next-source-after-current-source-epoch'],
    'plan decision missing' => [static fn (): mixed => $missing218()['current_source_epoch_plan_next218']['decision'], 'hold-next-source-until-current-source-epoch'],
    'plan base visible' => [static fn (): mixed => $released218()['current_source_epoch_plan_next218']['base_next_source_visible'], true],
    'plan base held' => [static fn (): mixed => $baseHeld218()['current_source_epoch_plan_next218']['base_next_source_visible'], false],
    'plan required echoed' => [static fn (): mixed => $released218()['current_source_epoch_plan_next218']['required_epochs'], $receipts218()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing218()['current_source_epoch_plan_next218']['acknowledged_epochs'], array_slice($receipts218(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released218()['current_source_epoch_plan_next218']['next_source_visible'], true],
    'epoch boundary released' => [static fn (): mixed => $released218()['yield_boundary_next218'], 'recursive-view-returning-next218-current-source-epoch-then-next'],
    'epoch boundary held' => [static fn (): mixed => $missing218()['yield_boundary_next218'], 'recursive-view-returning-next218-current-source-epoch-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released218()['dependency_closure_next218'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-epoch-handoff'],
    'dependency includes next218' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next218', $released218()['dependencies_next218'], true), true],
    'dependency includes epoch receipt' => [static fn (): mixed => in_array('sqlite-returning-current-source-epoch-handoff', $released218()['dependencies_next218'], true), true],
    'dependency includes next212' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next212', $released218()['dependencies_next218'], true), true],
    'non overlap mentions next212' => [static fn (): mixed => str_contains($released218()['non_overlap_next218'], 'next212 yield receipts'), true],
    'bad epoch token rejected' => [static fn (): mixed => $plan218(['current_source_epoch_next218' => 'bad token']), InvalidArgumentException::class],
    'bad view epoch cursor rejected' => [static fn (): mixed => $plan218(['current_view_epoch_next218' => 'bad cursor']), InvalidArgumentException::class],
    'bad trigger epoch cursor rejected' => [static fn (): mixed => $plan218(['current_trigger_epoch_next218' => 'bad cursor']), InvalidArgumentException::class],
    'bad epoch list rejected' => [static fn (): mixed => $plan218(['acknowledged_current_source_epochs_next218' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdef']]), InvalidArgumentException::class],
    'bad short epoch rejected' => [static fn (): mixed => $plan218(['acknowledged_current_source_epochs_next218' => ['abc']]), InvalidArgumentException::class],
    'bad non hex epoch rejected' => [static fn (): mixed => $plan218(['acknowledged_current_source_epochs_next218' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases218 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next218 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
