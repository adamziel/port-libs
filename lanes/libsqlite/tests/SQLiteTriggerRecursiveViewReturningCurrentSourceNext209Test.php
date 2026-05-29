<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows209 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView209 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-209-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-209-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-209',
];
$nextView209 = $currentView209;
$nextView209['source'] = 'main@view-cookie-209-next';
$nextView209['trigger_source'] = 'main@trigger-cookie-209-next';
$postResetView209 = $currentView209;
$postResetView209['source'] = 'main@view-cookie-209-post-reset';
$postResetView209['trigger_source'] = 'main@trigger-cookie-209-post-reset';
$followingView209 = $currentView209;
$followingView209['source'] = 'main@view-cookie-209-following';
$followingView209['trigger_source'] = 'main@trigger-cookie-209-following';
$currentInput209 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput209 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput209 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$followingInput209 = [
    ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
];
$returning209 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan209 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceWatermarkDrain(
    $rows209,
    $currentInput209,
    $nextInput209,
    $currentView209,
    $nextView209,
    $returning209,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_209',
        'cursor_name' => 'wp_recursive_view_returning_cursor_209',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.209',
        'reset_generation' => 'wp-current-reset-209',
        'post_reset_current_source_token' => 'wp.current.source.postreset.209',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.209',
        'post_reset_view' => $postResetView209,
        'post_reset_input' => $postResetInput209,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.209',
        'next_cursor' => 'wp.returning.next.cursor.209',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.209',
        'following_current_source_token' => 'wp.current.source.following.209',
        'following_cursor' => 'wp.returning.following.cursor.209',
        'following_current_view' => $followingView209,
        'following_current_input' => $followingInput209,
        'following_generation' => 'wp-following-current-209',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.209',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.209',
        'recursive_child_generation' => 'wp-recursive-child-current-209',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.209',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.209',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.209',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.209',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.209',
        'current_view_cookie_next209' => 'main@view-cookie-209-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-209-current',
    ],
);

$watermarks209 = static fn (): array => $plan209()['required_current_source_watermarks_next209'];
$released209 = static fn (): array => $plan209(['auto_ack_current_source_watermarks_next209' => true]);
$missing209 = static fn (): array => $plan209(['acknowledged_current_source_watermarks_next209' => array_slice($watermarks209(), 0, 1)]);
$unexpected209 = static fn (): array => $plan209(['acknowledged_current_source_watermarks_next209' => array_merge($watermarks209(), ['abcdefabcdefabcdefabcdefabcdefab'])]);
$viewHeld209 = static fn (): array => $plan209(['auto_ack_current_source_watermarks_next209' => true, 'expected_current_view_cookie_next209' => 'main@view-cookie-209-stale']);
$triggerHeld209 = static fn (): array => $plan209(['auto_ack_current_source_watermarks_next209' => true, 'expected_current_trigger_cookie_next209' => 'main@trigger-cookie-209-stale']);
$baseHeld209 = static fn (): array => $plan209(['auto_ack_current_source_watermarks_next209' => true, 'auto_ack_current_generation_receipts_next203' => false]);
$custom209 = static fn (): array => $plan209([
    'auto_ack_current_source_watermarks_next209' => true,
    'current_source_drain_token_next209' => 'wp.current.source.drain.custom.209',
    'current_view_cookie_next209' => 'main@view-cookie-209-custom',
    'expected_current_view_cookie_next209' => 'main@view-cookie-209-custom',
    'current_trigger_cookie_next209' => 'main@trigger-cookie-209-custom',
    'expected_current_trigger_cookie_next209' => 'main@trigger-cookie-209-custom',
]);

$cases209 = [
    'released status' => [static fn (): mixed => $released209()['status_next209'], 'trigger-recursive-view-returning-current-source-next209-drain-released'],
    'missing status' => [static fn (): mixed => $missing209()['status_next209'], 'trigger-recursive-view-returning-current-source-next209-drain-held'],
    'unexpected status' => [static fn (): mixed => $unexpected209()['status_next209'], 'trigger-recursive-view-returning-current-source-next209-drain-held'],
    'view held status' => [static fn (): mixed => $viewHeld209()['status_next209'], 'trigger-recursive-view-returning-current-source-next209-view-cookie-held'],
    'trigger held status' => [static fn (): mixed => $triggerHeld209()['status_next209'], 'trigger-recursive-view-returning-current-source-next209-trigger-cookie-held'],
    'base held status' => [static fn (): mixed => $baseHeld209()['status_next209'], 'trigger-recursive-view-returning-current-source-next209-base-held'],
    'savepoint retained' => [static fn (): mixed => $released209()['savepoint'], 'wp_recursive_view_209'],
    'base next203 visible' => [static fn (): mixed => $released209()['base']['status_next203'], 'trigger-recursive-view-returning-current-source-next203-generation-released'],
    'base held keeps next203 waiting' => [static fn (): mixed => $baseHeld209()['base']['status_next203'], 'trigger-recursive-view-returning-current-source-next203-receipts-held'],
    'base visible flag released' => [static fn (): mixed => $released209()['base_next_source_visible_next209'], true],
    'base visible flag held' => [static fn (): mixed => $baseHeld209()['base_next_source_visible_next209'], false],
    'drain token retained' => [static fn (): mixed => $released209()['current_source_drain_token_next209'], 'wp.current.source.drain.209'],
    'custom drain token retained' => [static fn (): mixed => $custom209()['current_source_drain_token_next209'], 'wp.current.source.drain.custom.209'],
    'view cookie retained' => [static fn (): mixed => $released209()['current_view_cookie_next209'], 'main@view-cookie-209-current'],
    'expected view cookie retained' => [static fn (): mixed => $released209()['expected_current_view_cookie_next209'], 'main@view-cookie-209-current'],
    'view cookie matches' => [static fn (): mixed => $released209()['current_view_cookie_matches_next209'], true],
    'view cookie mismatch' => [static fn (): mixed => $viewHeld209()['current_view_cookie_matches_next209'], false],
    'custom view cookie retained' => [static fn (): mixed => $custom209()['current_view_cookie_next209'], 'main@view-cookie-209-custom'],
    'trigger cookie retained' => [static fn (): mixed => $released209()['current_trigger_cookie_next209'], 'main@trigger-cookie-209-current'],
    'expected trigger cookie retained' => [static fn (): mixed => $released209()['expected_current_trigger_cookie_next209'], 'main@trigger-cookie-209-current'],
    'trigger cookie matches' => [static fn (): mixed => $released209()['current_trigger_cookie_matches_next209'], true],
    'trigger cookie mismatch' => [static fn (): mixed => $triggerHeld209()['current_trigger_cookie_matches_next209'], false],
    'custom trigger cookie retained' => [static fn (): mixed => $custom209()['current_trigger_cookie_next209'], 'main@trigger-cookie-209-custom'],
    'required watermark count' => [static fn (): mixed => count($released209()['required_current_source_watermarks_next209']), 2],
    'watermarks are 32 hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{32}$/', $v), $released209()['required_current_source_watermarks_next209']), [1, 1]],
    'auto acknowledged equals required' => [static fn (): mixed => $released209()['acknowledged_current_source_watermarks_next209'], $watermarks209()],
    'missing acknowledged count' => [static fn (): mixed => count($missing209()['acknowledged_current_source_watermarks_next209']), 1],
    'missing watermark recorded' => [static fn (): mixed => $missing209()['missing_current_source_watermarks_next209'], [array_slice($watermarks209(), -1)[0]]],
    'unexpected watermark recorded' => [static fn (): mixed => $unexpected209()['unexpected_current_source_watermarks_next209'], ['abcdefabcdefabcdefabcdefabcdefab']],
    'released missing empty' => [static fn (): mixed => $released209()['missing_current_source_watermarks_next209'], []],
    'released unexpected empty' => [static fn (): mixed => $released209()['unexpected_current_source_watermarks_next209'], []],
    'drain complete released' => [static fn (): mixed => $released209()['current_source_drain_complete_next209'], true],
    'drain incomplete missing' => [static fn (): mixed => $missing209()['current_source_drain_complete_next209'], false],
    'drain incomplete unexpected' => [static fn (): mixed => $unexpected209()['current_source_drain_complete_next209'], false],
    'next visible released' => [static fn (): mixed => $released209()['next_source_visible_after_current_source_drain_next209'], true],
    'next denied missing' => [static fn (): mixed => $missing209()['next_source_visible_after_current_source_drain_next209'], false],
    'next denied view cookie' => [static fn (): mixed => $viewHeld209()['next_source_visible_after_current_source_drain_next209'], false],
    'next denied trigger cookie' => [static fn (): mixed => $triggerHeld209()['next_source_visible_after_current_source_drain_next209'], false],
    'next denied base held' => [static fn (): mixed => $baseHeld209()['next_source_visible_after_current_source_drain_next209'], false],
    'current row count' => [static fn (): mixed => $released209()['current_source_row_count_next209'], 2],
    'attempted next row count' => [static fn (): mixed => $released209()['attempted_next_source_row_count_next209'], 2],
    'visible released count' => [static fn (): mixed => $released209()['visible_row_count_next209'], 4],
    'held released count' => [static fn (): mixed => $released209()['held_next_row_count_next209'], 0],
    'visible missing count current only' => [static fn (): mixed => $missing209()['visible_row_count_next209'], 2],
    'held missing count next only' => [static fn (): mixed => $missing209()['held_next_row_count_next209'], 2],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released209()['current_source_rows_next209'], 'source_drain_phase_next209'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released209()['attempted_next_source_rows_next209'], 'source_drain_phase_next209'))), ['next']],
    'current visible while missing' => [static fn (): mixed => array_values(array_unique(array_column($missing209()['current_source_rows_next209'], 'visible_after_current_source_drain_next209'))), [true]],
    'next visible released' => [static fn (): mixed => array_values(array_unique(array_column($released209()['attempted_next_source_rows_next209'], 'visible_after_current_source_drain_next209'))), [true]],
    'next held missing' => [static fn (): mixed => array_values(array_unique(array_column($missing209()['attempted_next_source_rows_next209'], 'visible_after_current_source_drain_next209'))), [false]],
    'current watermarks tagged' => [static fn (): mixed => array_column($released209()['current_source_rows_next209'], 'current_source_watermark_next209'), $watermarks209()],
    'next watermarks null' => [static fn (): mixed => array_values(array_unique(array_column($released209()['attempted_next_source_rows_next209'], 'current_source_watermark_next209'))), [null]],
    'current drain token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released209()['current_source_rows_next209'], 'current_source_drain_token_next209'))), ['wp.current.source.drain.209']],
    'next drain token stamped' => [static fn (): mixed => array_values(array_unique(array_column($released209()['attempted_next_source_rows_next209'], 'current_source_drain_token_next209'))), ['wp.current.source.drain.209']],
    'current view cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($released209()['current_source_rows_next209'], 'current_view_cookie_next209'))), ['main@view-cookie-209-current']],
    'next trigger cookie stamped' => [static fn (): mixed => array_values(array_unique(array_column($released209()['attempted_next_source_rows_next209'], 'current_trigger_cookie_next209'))), ['main@trigger-cookie-209-current']],
    'visible payload names released' => [static fn (): mixed => array_column($released209()['visible_returning_payloads_next209'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names missing' => [static fn (): mixed => array_column($missing209()['held_next_returning_payloads_next209'], 'name'), ['home', 'next_plugin']],
    'blocked reasons missing' => [static fn (): mixed => $missing209()['blocked_reasons_next209'], ['current-source-watermark-missing']],
    'blocked reasons unexpected' => [static fn (): mixed => $unexpected209()['blocked_reasons_next209'], ['current-source-watermark-unexpected']],
    'blocked reasons view' => [static fn (): mixed => $viewHeld209()['blocked_reasons_next209'], ['current-view-cookie-mismatch']],
    'blocked reasons trigger' => [static fn (): mixed => $triggerHeld209()['blocked_reasons_next209'], ['current-trigger-cookie-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld209()['blocked_reasons_next209'], ['current-generation-receipt-missing', 'current-generation-receipt-order-mismatch']],
    'released reasons empty' => [static fn (): mixed => $released209()['blocked_reasons_next209'], []],
    'plan decision released' => [static fn (): mixed => $released209()['current_source_drain_plan_next209']['decision'], 'publish-next-source-after-current-source-drain'],
    'plan decision missing' => [static fn (): mixed => $missing209()['current_source_drain_plan_next209']['decision'], 'hold-next-source-until-current-source-drain'],
    'plan base visible' => [static fn (): mixed => $released209()['current_source_drain_plan_next209']['base_next_source_visible'], true],
    'plan base held' => [static fn (): mixed => $baseHeld209()['current_source_drain_plan_next209']['base_next_source_visible'], false],
    'plan required echoed' => [static fn (): mixed => $released209()['current_source_drain_plan_next209']['required_watermarks'], $watermarks209()],
    'plan acknowledged echoed' => [static fn (): mixed => $missing209()['current_source_drain_plan_next209']['acknowledged_watermarks'], array_slice($watermarks209(), 0, 1)],
    'plan next visible echoed' => [static fn (): mixed => $released209()['current_source_drain_plan_next209']['next_source_visible'], true],
    'yield boundary released' => [static fn (): mixed => $released209()['yield_boundary_next209'], 'recursive-view-returning-next209-current-source-drain-then-next'],
    'yield boundary held' => [static fn (): mixed => $missing209()['yield_boundary_next209'], 'recursive-view-returning-next209-current-source-drain-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released209()['dependency_closure_next209'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-drain-watermarks'],
    'dependency includes next209' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next209', $released209()['dependencies_next209'], true), true],
    'dependency includes watermark' => [static fn (): mixed => in_array('sqlite-returning-current-source-drain-watermark', $released209()['dependencies_next209'], true), true],
    'dependency includes next203' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next203', $released209()['dependencies_next209'], true), true],
    'non overlap mentions next203' => [static fn (): mixed => str_contains($released209()['non_overlap_next209'], 'next203 generation handoff'), true],
    'bad drain token rejected' => [static fn (): mixed => $plan209(['current_source_drain_token_next209' => 'bad token']), InvalidArgumentException::class],
    'bad view cookie rejected' => [static fn (): mixed => $plan209(['current_view_cookie_next209' => 'bad cookie']), InvalidArgumentException::class],
    'bad trigger cookie rejected' => [static fn (): mixed => $plan209(['current_trigger_cookie_next209' => 'bad cookie']), InvalidArgumentException::class],
    'bad expected view cookie rejected' => [static fn (): mixed => $plan209(['expected_current_view_cookie_next209' => 'bad cookie']), InvalidArgumentException::class],
    'bad expected trigger cookie rejected' => [static fn (): mixed => $plan209(['expected_current_trigger_cookie_next209' => 'bad cookie']), InvalidArgumentException::class],
    'bad watermark list rejected' => [static fn (): mixed => $plan209(['acknowledged_current_source_watermarks_next209' => ['x' => 'abcdefabcdefabcdefabcdefabcdefab']]), InvalidArgumentException::class],
    'bad short watermark rejected' => [static fn (): mixed => $plan209(['acknowledged_current_source_watermarks_next209' => ['abc']]), InvalidArgumentException::class],
    'bad non hex watermark rejected' => [static fn (): mixed => $plan209(['acknowledged_current_source_watermarks_next209' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases209 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next209 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
