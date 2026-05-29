<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rowsSourceClose = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentViewSourceClose = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-source-close-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-source-close-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-source-close',
];
$nextViewSourceClose = $currentViewSourceClose;
$nextViewSourceClose['source'] = 'main@view-cookie-source-close-next';
$nextViewSourceClose['trigger_source'] = 'main@trigger-cookie-source-close-next';
$postResetViewSourceClose = $currentViewSourceClose;
$postResetViewSourceClose['source'] = 'main@view-cookie-source-close-post-reset';
$postResetViewSourceClose['trigger_source'] = 'main@trigger-cookie-source-close-post-reset';
$followingViewSourceClose = $currentViewSourceClose;
$followingViewSourceClose['source'] = 'main@view-cookie-source-close-following';
$followingViewSourceClose['trigger_source'] = 'main@trigger-cookie-source-close-following';
$currentInputSourceClose = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInputSourceClose = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returningSourceClose = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$planSourceClose = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceCursorClose(
    $rowsSourceClose,
    $currentInputSourceClose,
    $nextInputSourceClose,
    $currentViewSourceClose,
    $nextViewSourceClose,
    $returningSourceClose,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_source_close',
        'cursor_name' => 'wp_recursive_view_returning_cursor_source_close',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.source.close',
        'reset_generation' => 'wp-current-reset-source-close',
        'post_reset_current_source_token' => 'wp.current.source.postreset.source.close',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.source.close',
        'post_reset_view' => $postResetViewSourceClose,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.source.close',
        'next_cursor' => 'wp.returning.next.cursor.source.close',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.source.close',
        'following_current_source_token' => 'wp.current.source.following.source.close',
        'following_cursor' => 'wp.returning.following.cursor.source.close',
        'following_current_view' => $followingViewSourceClose,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-source-close',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.source.close',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.source.close',
        'recursive_child_generation' => 'wp-recursive-child-current-source-close',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.source.close',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.source.close',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.source.close',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.source.close',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.source.close',
        'current_view_cookie_next209' => 'main@view-cookie-source-close-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-source-close-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.source.close',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.source.close',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.source.close',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.source.close',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.source.close',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.source.close',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.source.close',
        'current_view_source_next222' => 'main@view-cookie-source-close-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-source-close-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'wp.returning.current.cursor.source.close',
        'current_source_close_token_source_close' => 'wp.current.source.close.source.close',
        'current_view_cookie_source_close' => 'main@view-cookie-source-close-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-source-close-current',
    ],
);

$closuresSourceClose = static fn (): array => $planSourceClose()['required_current_source_closures_source_close'];
$releasedSourceClose = static fn (): array => $planSourceClose(['auto_ack_current_source_closures_source_close' => true]);
$missingSourceClose = static fn (): array => $planSourceClose(['acknowledged_current_source_closures_source_close' => array_slice($closuresSourceClose(), 0, 1)]);
$unexpectedSourceClose = static fn (): array => $planSourceClose(['acknowledged_current_source_closures_source_close' => array_merge($closuresSourceClose(), ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'])]);
$reversedSourceClose = static fn (): array => $planSourceClose(['acknowledged_current_source_closures_source_close' => array_reverse($closuresSourceClose())]);
$unorderedSourceClose = static fn (): array => $planSourceClose(['require_current_source_close_order_source_close' => false, 'acknowledged_current_source_closures_source_close' => array_reverse($closuresSourceClose())]);
$closeMismatchSourceClose = static fn (): array => $planSourceClose(['auto_ack_current_source_closures_source_close' => true, 'expected_current_source_close_token_source_close' => 'wp.current.source.close.stale.source.close']);
$baseHeldSourceClose = static fn (): array => $planSourceClose(['auto_ack_current_source_closures_source_close' => true, 'auto_ack_current_source_tickets_next222' => false]);
$customSourceClose = static fn (): array => $planSourceClose([
    'auto_ack_current_source_closures_source_close' => true,
    'current_source_cursor_source_close' => 'wp.returning.current.cursor.custom.source.close',
    'current_source_close_token_source_close' => 'wp.current.source.close.custom.source.close',
    'current_view_cookie_source_close' => 'main@view-cookie-source-close-custom',
    'current_trigger_cookie_source_close' => 'main@trigger-cookie-source-close-custom',
]);

$casesSourceClose = [
    'released status' => [static fn (): mixed => $releasedSourceClose()['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-cursor-close-released'],
    'missing status' => [static fn (): mixed => $missingSourceClose()['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-cursor-close-held'],
    'unexpected status' => [static fn (): mixed => $unexpectedSourceClose()['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-cursor-close-held'],
    'reversed status' => [static fn (): mixed => $reversedSourceClose()['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-cursor-close-order-held'],
    'unordered reversed releases' => [static fn (): mixed => $unorderedSourceClose()['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-cursor-close-released'],
    'close mismatch status' => [static fn (): mixed => $closeMismatchSourceClose()['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-close-token-held'],
    'base held status' => [static fn (): mixed => $baseHeldSourceClose()['status_source_close'], 'trigger-recursive-view-returning-current-source-source_close-base-held'],
    'savepoint retained' => [static fn (): mixed => $releasedSourceClose()['savepoint'], 'wp_recursive_view_source_close'],
    'base next222 released' => [static fn (): mixed => $releasedSourceClose()['base']['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-ticket-released'],
    'base next222 held' => [static fn (): mixed => $baseHeldSourceClose()['base']['status_next222'], 'trigger-recursive-view-returning-current-source-next222-source-ticket-held'],
    'base visible released' => [static fn (): mixed => $releasedSourceClose()['base_next_source_visible_source_close'], true],
    'base visible held' => [static fn (): mixed => $baseHeldSourceClose()['base_next_source_visible_source_close'], false],
    'cursor retained' => [static fn (): mixed => $releasedSourceClose()['current_source_cursor_source_close'], 'wp.returning.current.cursor.source.close'],
    'custom cursor retained' => [static fn (): mixed => $customSourceClose()['current_source_cursor_source_close'], 'wp.returning.current.cursor.custom.source.close'],
    'close token retained' => [static fn (): mixed => $releasedSourceClose()['current_source_close_token_source_close'], 'wp.current.source.close.source.close'],
    'custom close token retained' => [static fn (): mixed => $customSourceClose()['current_source_close_token_source_close'], 'wp.current.source.close.custom.source.close'],
    'view cookie retained' => [static fn (): mixed => $releasedSourceClose()['current_view_cookie_source_close'], 'main@view-cookie-source-close-current'],
    'custom view cookie retained' => [static fn (): mixed => $customSourceClose()['current_view_cookie_source_close'], 'main@view-cookie-source-close-custom'],
    'trigger cookie retained' => [static fn (): mixed => $releasedSourceClose()['current_trigger_cookie_source_close'], 'main@trigger-cookie-source-close-current'],
    'custom trigger cookie retained' => [static fn (): mixed => $customSourceClose()['current_trigger_cookie_source_close'], 'main@trigger-cookie-source-close-custom'],
    'close matches released' => [static fn (): mixed => $releasedSourceClose()['current_source_close_matches_source_close'], true],
    'close mismatch detected' => [static fn (): mixed => $closeMismatchSourceClose()['current_source_close_matches_source_close'], false],
    'required closure count' => [static fn (): mixed => count($releasedSourceClose()['required_current_source_closures_source_close']), 2],
    'closures are forty eight hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{48}$/', $v), $releasedSourceClose()['required_current_source_closures_source_close']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $releasedSourceClose()['acknowledged_current_source_closures_source_close'], $closuresSourceClose()],
    'missing acknowledged count' => [static fn (): mixed => count($missingSourceClose()['acknowledged_current_source_closures_source_close']), 1],
    'missing closure recorded' => [static fn (): mixed => $missingSourceClose()['missing_current_source_closures_source_close'], [array_slice($closuresSourceClose(), -1)[0]]],
    'unexpected closure recorded' => [static fn (): mixed => $unexpectedSourceClose()['unexpected_current_source_closures_source_close'], ['abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef']],
    'released missing empty' => [static fn (): mixed => $releasedSourceClose()['missing_current_source_closures_source_close'], []],
    'released unexpected empty' => [static fn (): mixed => $releasedSourceClose()['unexpected_current_source_closures_source_close'], []],
    'require order default' => [static fn (): mixed => $releasedSourceClose()['require_current_source_close_order_source_close'], true],
    'order matches released' => [static fn (): mixed => $releasedSourceClose()['current_source_close_order_matches_source_close'], true],
    'order mismatch reversed' => [static fn (): mixed => $reversedSourceClose()['current_source_close_order_matches_source_close'], false],
    'unordered disables order' => [static fn (): mixed => $unorderedSourceClose()['require_current_source_close_order_source_close'], false],
    'unordered order considered matched' => [static fn (): mixed => $unorderedSourceClose()['current_source_close_order_matches_source_close'], true],
    'close complete released' => [static fn (): mixed => $releasedSourceClose()['current_source_close_complete_source_close'], true],
    'close incomplete missing' => [static fn (): mixed => $missingSourceClose()['current_source_close_complete_source_close'], false],
    'close incomplete unexpected' => [static fn (): mixed => $unexpectedSourceClose()['current_source_close_complete_source_close'], false],
    'close incomplete reversed' => [static fn (): mixed => $reversedSourceClose()['current_source_close_complete_source_close'], false],
    'close incomplete mismatch' => [static fn (): mixed => $closeMismatchSourceClose()['current_source_close_complete_source_close'], false],
    'next visible released' => [static fn (): mixed => $releasedSourceClose()['next_source_visible_after_current_source_close_source_close'], true],
    'next denied missing' => [static fn (): mixed => $missingSourceClose()['next_source_visible_after_current_source_close_source_close'], false],
    'next denied unexpected' => [static fn (): mixed => $unexpectedSourceClose()['next_source_visible_after_current_source_close_source_close'], false],
    'next denied reversed' => [static fn (): mixed => $reversedSourceClose()['next_source_visible_after_current_source_close_source_close'], false],
    'next denied close mismatch' => [static fn (): mixed => $closeMismatchSourceClose()['next_source_visible_after_current_source_close_source_close'], false],
    'current row count' => [static fn (): mixed => $releasedSourceClose()['current_source_row_count_source_close'], 2],
    'attempted next row count' => [static fn (): mixed => $releasedSourceClose()['attempted_next_source_row_count_source_close'], 2],
    'visible released count' => [static fn (): mixed => $releasedSourceClose()['visible_row_count_source_close'], 4],
    'held released count' => [static fn (): mixed => $releasedSourceClose()['held_next_row_count_source_close'], 0],
    'visible missing count current only' => [static fn (): mixed => $missingSourceClose()['visible_row_count_source_close'], 2],
    'held missing count next only' => [static fn (): mixed => $missingSourceClose()['held_next_row_count_source_close'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceClose()['current_source_rows_source_close'], 'source_close_phase_source_close'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceClose()['attempted_next_source_rows_source_close'], 'source_close_phase_source_close'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missingSourceClose()['current_source_rows_source_close'], 'visible_after_current_source_close_source_close'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceClose()['attempted_next_source_rows_source_close'], 'visible_after_current_source_close_source_close'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missingSourceClose()['attempted_next_source_rows_source_close'], 'visible_after_current_source_close_source_close'))), [false]],
    'current closures tagged' => [static fn (): mixed => array_column($releasedSourceClose()['current_source_rows_source_close'], 'current_source_close_receipt_source_close'), $closuresSourceClose()],
    'next closures null' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceClose()['attempted_next_source_rows_source_close'], 'current_source_close_receipt_source_close'))), [null]],
    'current cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceClose()['current_source_rows_source_close'], 'current_source_cursor_source_close'))), ['wp.returning.current.cursor.source.close']],
    'next close token stamped' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceClose()['attempted_next_source_rows_source_close'], 'current_source_close_token_source_close'))), ['wp.current.source.close.source.close']],
    'current view cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceClose()['current_source_rows_source_close'], 'current_view_cookie_source_close'))), ['main@view-cookie-source-close-current']],
    'next trigger cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($releasedSourceClose()['attempted_next_source_rows_source_close'], 'current_trigger_cookie_source_close'))), ['main@trigger-cookie-source-close-current']],
    'visible payload names released' => [static fn (): mixed => array_column($releasedSourceClose()['visible_returning_payloads_source_close'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missingSourceClose()['held_next_returning_payloads_source_close'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missingSourceClose()['blocked_reasons_source_close'], ['current-source-close-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpectedSourceClose()['blocked_reasons_source_close'], ['current-source-close-unexpected']],
    'blocked reasons reversed' => [static fn (): mixed => $reversedSourceClose()['blocked_reasons_source_close'], ['current-source-close-order-mismatch']],
    'blocked reasons close mismatch' => [static fn (): mixed => $closeMismatchSourceClose()['blocked_reasons_source_close'], ['current-source-close-token-mismatch']],
    'blocked reasons base held' => [static fn (): mixed => $baseHeldSourceClose()['blocked_reasons_source_close'], ['current-source-ticket-missing']],
    'released reasons empty' => [static fn (): mixed => $releasedSourceClose()['blocked_reasons_source_close'], []],
    'plan decision released' => [static fn (): mixed => $releasedSourceClose()['current_source_close_plan_source_close']['decision'], 'publish-next-source-after-current-returning-cursor-close'],
    'plan decision missing' => [static fn (): mixed => $missingSourceClose()['current_source_close_plan_source_close']['decision'], 'hold-next-source-until-current-returning-cursor-close'],
    'plan base visible' => [static fn (): mixed => $releasedSourceClose()['current_source_close_plan_source_close']['base_next_source_visible'], true],
    'plan required echoed' => [static fn (): mixed => $releasedSourceClose()['current_source_close_plan_source_close']['required_closures'], $closuresSourceClose()],
    'plan acknowledged echoed' => [static fn (): mixed => $missingSourceClose()['current_source_close_plan_source_close']['acknowledged_closures'], array_slice($closuresSourceClose(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $releasedSourceClose()['current_source_close_plan_source_close']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $releasedSourceClose()['yield_boundary_source_close'], 'recursive-view-returning-source_close-current-cursor-close-then-next'],
    'yield boundary held' => [static fn (): mixed => $missingSourceClose()['yield_boundary_source_close'], 'recursive-view-returning-source_close-current-cursor-close-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $releasedSourceClose()['dependency_closure_source_close'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-close-handoff'],
    'dependency includes source_close' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-source_close', $releasedSourceClose()['dependencies_source_close'], true), true],
    'dependency includes close receipt' => [static fn (): mixed => in_array('sqlite-returning-current-source-cursor-close-handoff', $releasedSourceClose()['dependencies_source_close'], true), true],
    'dependency includes next222' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next222', $releasedSourceClose()['dependencies_source_close'], true), true],
    'non overlap mentions next222' => [static fn (): mixed => str_contains($releasedSourceClose()['non_overlap_source_close'], 'next222 source-ticket handoff'), true],
    'bad cursor rejected' => [static fn (): mixed => $planSourceClose(['current_source_cursor_source_close' => 'bad cursor']), InvalidArgumentException::class],
    'bad close token rejected' => [static fn (): mixed => $planSourceClose(['current_source_close_token_source_close' => 'bad token']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $planSourceClose(['current_view_cookie_source_close' => 'bad cookie']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $planSourceClose(['current_trigger_cookie_source_close' => 'bad cookie']), InvalidArgumentException::class],
    'bad closure list rejected' => [static fn (): mixed => $planSourceClose(['acknowledged_current_source_closures_source_close' => ['x' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef']]), InvalidArgumentException::class],
    'bad short closure rejected' => [static fn (): mixed => $planSourceClose(['acknowledged_current_source_closures_source_close' => ['abc']]), InvalidArgumentException::class],
    'bad non hex closure rejected' => [static fn (): mixed => $planSourceClose(['acknowledged_current_source_closures_source_close' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($casesSourceClose as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source source_close ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
