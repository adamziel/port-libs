<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows208 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView208 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-208-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-208-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-208',
];
$nextView208 = $currentView208;
$nextView208['source'] = 'main@view-cookie-208-next';
$nextView208['trigger_source'] = 'main@trigger-cookie-208-next';
$postResetView208 = $currentView208;
$postResetView208['source'] = 'main@view-cookie-208-post-reset';
$postResetView208['trigger_source'] = 'main@trigger-cookie-208-post-reset';
$followingView208 = $currentView208;
$followingView208['source'] = 'main@view-cookie-208-following';
$followingView208['trigger_source'] = 'main@trigger-cookie-208-following';
$currentInput208 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput208 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput208 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$followingInput208 = [
    ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
];
$returning208 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan208 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentCursorCloseFence(
    $rows208,
    $currentInput208,
    $nextInput208,
    $currentView208,
    $nextView208,
    $returning208,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_208',
        'cursor_name' => 'wp_recursive_view_returning_cursor_208',
        'current_generation' => 'wp-current-returning-208',
        'next_generation' => 'wp-next-returning-208',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.208',
        'drain_ack_token' => 'wp.returning.drain.208',
        'rollback_token' => 'wp.rollback.current.208',
        'reset_generation' => 'wp-current-reset-208',
        'post_reset_current_source_token' => 'wp.current.source.postreset.208',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.208',
        'post_reset_view' => $postResetView208,
        'post_reset_input' => $postResetInput208,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.208',
        'next_cursor' => 'wp.returning.next.cursor.208',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.208',
        'following_current_source_token' => 'wp.current.source.following.208',
        'following_cursor' => 'wp.returning.following.cursor.208',
        'following_current_view' => $followingView208,
        'following_current_input' => $followingInput208,
        'following_generation' => 'wp-following-current-208',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.208',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.208',
        'recursive_child_generation' => 'wp-recursive-child-current-208',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.208',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.208',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.208',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.208',
        'auto_ack_current_generation_receipts_next203' => true,
        'yield_current_source_token_next206' => 'wp.current.recursive.returning.source.208',
        'yield_current_cursor_next206' => 'wp.returning.current.cursor.208',
        'yield_statement_token_next206' => 'wp.recursive.view.returning.statement.208',
        'close_current_cursor_next208' => 'wp.returning.current.cursor.208',
        'expected_close_current_cursor_next208' => 'wp.returning.current.cursor.208',
        'close_statement_token_next208' => 'wp.recursive.view.returning.close.208',
    ],
);

$released208 = static fn (): array => $plan208();
$closedWatermark208 = static fn (): string => $released208()['closed_yield_watermark_next208'];
$cursorHeld208 = static fn (): array => $plan208(['close_current_cursor_next208' => 'wp.returning.current.cursor.stale.208']);
$expectedCursorHeld208 = static fn (): array => $plan208(['expected_close_current_cursor_next208' => 'wp.returning.current.cursor.expected-stale.208']);
$watermarkHeld208 = static fn (): array => $plan208(['expected_closed_yield_watermark_next208' => '0123456789abcdef0123456789abcdef']);
$baseHeld208 = static fn (): array => $plan208([
    'acknowledged_yield_watermark_next206' => 'fedcba9876543210fedcba9876543210',
]);
$custom208 = static fn (): array => $plan208(['close_statement_token_next208' => 'wp.recursive.view.returning.close.custom.208']);

$cases208 = [
    'released status' => [static fn (): mixed => $released208()['status_next208'], 'trigger-recursive-view-returning-current-source-next208-cursor-closed'],
    'cursor held status' => [static fn (): mixed => $cursorHeld208()['status_next208'], 'trigger-recursive-view-returning-current-source-next208-cursor-held'],
    'expected cursor held status' => [static fn (): mixed => $expectedCursorHeld208()['status_next208'], 'trigger-recursive-view-returning-current-source-next208-cursor-held'],
    'watermark held status' => [static fn (): mixed => $watermarkHeld208()['status_next208'], 'trigger-recursive-view-returning-current-source-next208-watermark-held'],
    'base held status' => [static fn (): mixed => $baseHeld208()['status_next208'], 'trigger-recursive-view-returning-current-source-next208-base-held'],
    'base next206 released' => [static fn (): mixed => $released208()['base']['status_next206'], 'trigger-recursive-view-returning-current-source-next206-watermark-released'],
    'base next206 held' => [static fn (): mixed => $baseHeld208()['base']['status_next206'], 'trigger-recursive-view-returning-current-source-next206-watermark-held'],
    'savepoint retained' => [static fn (): mixed => $released208()['savepoint'], 'wp_recursive_view_208'],
    'base visible true' => [static fn (): mixed => $released208()['base_next_source_visible_next208'], true],
    'base visible false' => [static fn (): mixed => $baseHeld208()['base_next_source_visible_next208'], false],
    'yield cursor retained' => [static fn (): mixed => $released208()['yield_current_cursor_next208'], 'wp.returning.current.cursor.208'],
    'close cursor retained' => [static fn (): mixed => $released208()['close_current_cursor_next208'], 'wp.returning.current.cursor.208'],
    'expected close cursor retained' => [static fn (): mixed => $released208()['expected_close_current_cursor_next208'], 'wp.returning.current.cursor.208'],
    'close statement retained' => [static fn (): mixed => $released208()['close_statement_token_next208'], 'wp.recursive.view.returning.close.208'],
    'custom close statement retained' => [static fn (): mixed => $custom208()['close_statement_token_next208'], 'wp.recursive.view.returning.close.custom.208'],
    'closed watermark is hex' => [static fn (): mixed => preg_match('/^[a-f0-9]{32}$/', $released208()['closed_yield_watermark_next208']), 1],
    'expected closed watermark defaults actual' => [static fn (): mixed => $released208()['expected_closed_yield_watermark_next208'], $closedWatermark208()],
    'explicit closed watermark accepted' => [static fn (): mixed => $plan208(['expected_closed_yield_watermark_next208' => $closedWatermark208()])['closed_yield_watermark_matches_next208'], true],
    'cursor matches released' => [static fn (): mixed => $released208()['current_cursor_close_matches_next208'], true],
    'cursor mismatch close' => [static fn (): mixed => $cursorHeld208()['current_cursor_close_matches_next208'], false],
    'cursor mismatch expected' => [static fn (): mixed => $expectedCursorHeld208()['current_cursor_close_matches_next208'], false],
    'watermark matches released' => [static fn (): mixed => $released208()['closed_yield_watermark_matches_next208'], true],
    'watermark mismatch detected' => [static fn (): mixed => $watermarkHeld208()['closed_yield_watermark_matches_next208'], false],
    'next visible released' => [static fn (): mixed => $released208()['next_source_visible_after_current_cursor_close_next208'], true],
    'next held cursor' => [static fn (): mixed => $cursorHeld208()['next_source_visible_after_current_cursor_close_next208'], false],
    'next held watermark' => [static fn (): mixed => $watermarkHeld208()['next_source_visible_after_current_cursor_close_next208'], false],
    'next held base' => [static fn (): mixed => $baseHeld208()['next_source_visible_after_current_cursor_close_next208'], false],
    'current row count' => [static fn (): mixed => count($released208()['current_source_rows_next208']), 2],
    'attempted next row count' => [static fn (): mixed => count($released208()['attempted_next_source_rows_next208']), 2],
    'visible released count' => [static fn (): mixed => count($released208()['visible_returning_rows_next208']), 4],
    'visible held current only' => [static fn (): mixed => count($cursorHeld208()['visible_returning_rows_next208']), 2],
    'held released count' => [static fn (): mixed => count($released208()['held_next_source_rows_next208']), 0],
    'held cursor count' => [static fn (): mixed => count($cursorHeld208()['held_next_source_rows_next208']), 2],
    'visible payload names released' => [static fn (): mixed => array_column($released208()['visible_returning_payloads_next208'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names cursor' => [static fn (): mixed => array_column($cursorHeld208()['held_next_returning_payloads_next208'], 'name'), ['home', 'next_plugin']],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released208()['current_source_rows_next208'], 'cursor_close_phase_next208'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released208()['attempted_next_source_rows_next208'], 'cursor_close_phase_next208'))), ['next']],
    'current visible while cursor held' => [static fn (): mixed => array_values(array_unique(array_column($cursorHeld208()['current_source_rows_next208'], 'visible_after_current_cursor_close_next208'))), [true]],
    'next visible released unique' => [static fn (): mixed => array_values(array_unique(array_column($released208()['attempted_next_source_rows_next208'], 'visible_after_current_cursor_close_next208'))), [true]],
    'next held cursor unique' => [static fn (): mixed => array_values(array_unique(array_column($cursorHeld208()['attempted_next_source_rows_next208'], 'visible_after_current_cursor_close_next208'))), [false]],
    'current close cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released208()['current_source_rows_next208'], 'close_current_cursor_next208'))), ['wp.returning.current.cursor.208']],
    'next close cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($released208()['attempted_next_source_rows_next208'], 'close_current_cursor_next208'))), ['wp.returning.current.cursor.208']],
    'current close statement stamped' => [static fn (): mixed => array_values(array_unique(array_column($released208()['current_source_rows_next208'], 'close_statement_token_next208'))), ['wp.recursive.view.returning.close.208']],
    'next close statement stamped' => [static fn (): mixed => array_values(array_unique(array_column($released208()['attempted_next_source_rows_next208'], 'close_statement_token_next208'))), ['wp.recursive.view.returning.close.208']],
    'current watermark stamped' => [static fn (): mixed => array_values(array_unique(array_column($released208()['current_source_rows_next208'], 'closed_yield_watermark_next208'))), [$closedWatermark208()]],
    'next watermark stamped' => [static fn (): mixed => array_values(array_unique(array_column($released208()['attempted_next_source_rows_next208'], 'closed_yield_watermark_next208'))), [$closedWatermark208()]],
    'blocked reasons cursor' => [static fn (): mixed => $cursorHeld208()['blocked_reasons_next208'], ['current-returning-cursor-close-mismatch']],
    'blocked reasons expected cursor' => [static fn (): mixed => $expectedCursorHeld208()['blocked_reasons_next208'], ['current-returning-cursor-close-mismatch']],
    'blocked reasons watermark' => [static fn (): mixed => $watermarkHeld208()['blocked_reasons_next208'], ['closed-yield-watermark-mismatch']],
    'blocked reasons base' => [static fn (): mixed => $baseHeld208()['blocked_reasons_next208'], ['current-yield-watermark-mismatch']],
    'released reasons empty' => [static fn (): mixed => $released208()['blocked_reasons_next208'], []],
    'held next reason tagged' => [static fn (): mixed => $cursorHeld208()['attempted_next_source_rows_next208'][0]['held_by_current_cursor_close_reasons_next208'], ['current-returning-cursor-close-mismatch']],
    'released next reason empty' => [static fn (): mixed => $released208()['attempted_next_source_rows_next208'][0]['held_by_current_cursor_close_reasons_next208'], []],
    'plan current rows' => [static fn (): mixed => $released208()['current_cursor_close_plan_next208']['current_rows'], 2],
    'plan next rows' => [static fn (): mixed => $released208()['current_cursor_close_plan_next208']['attempted_next_rows'], 2],
    'plan visible rows' => [static fn (): mixed => $released208()['current_cursor_close_plan_next208']['visible_rows'], 4],
    'plan held rows cursor' => [static fn (): mixed => $cursorHeld208()['current_cursor_close_plan_next208']['held_next_rows'], 2],
    'plan base visible' => [static fn (): mixed => $released208()['current_cursor_close_plan_next208']['base_next_source_visible'], true],
    'plan cursor matches' => [static fn (): mixed => $released208()['current_cursor_close_plan_next208']['cursor_matches'], true],
    'plan watermark matches' => [static fn (): mixed => $released208()['current_cursor_close_plan_next208']['closed_watermark_matches'], true],
    'plan decision released' => [static fn (): mixed => $released208()['current_cursor_close_plan_next208']['decision'], 'publish-next-source-after-current-returning-cursor-close'],
    'plan decision held' => [static fn (): mixed => $cursorHeld208()['current_cursor_close_plan_next208']['decision'], 'hold-next-source-until-current-returning-cursor-close'],
    'yield boundary released' => [static fn (): mixed => $released208()['yield_boundary_next208'], 'recursive-view-returning-next208-current-cursor-closed-then-next'],
    'yield boundary held' => [static fn (): mixed => $cursorHeld208()['yield_boundary_next208'], 'recursive-view-returning-next208-current-cursor-close-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released208()['dependency_closure_next208'], 'no new support component needed; reuses next206 current-source yield watermark and adds current RETURNING cursor close fencing'],
    'dependency includes next208' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next208', $released208()['dependencies_next208'], true), true],
    'dependency includes close fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-cursor-close-fence', $released208()['dependencies_next208'], true), true],
    'dependency includes next206' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next206', $released208()['dependencies_next208'], true), true],
    'dependency includes wordpress' => [static fn (): mixed => in_array('wordpress-recursive-view-returning-current-source-next208', $released208()['dependencies_next208'], true), true],
    'non overlap mentions next206' => [static fn (): mixed => str_contains($released208()['non_overlap_next208'], 'next206 yield watermark'), true],
    'bad close cursor rejected' => [static fn (): mixed => $plan208(['close_current_cursor_next208' => 'bad cursor']), InvalidArgumentException::class],
    'bad expected cursor rejected' => [static fn (): mixed => $plan208(['expected_close_current_cursor_next208' => 'bad cursor']), InvalidArgumentException::class],
    'bad close statement rejected' => [static fn (): mixed => $plan208(['close_statement_token_next208' => 'bad statement']), InvalidArgumentException::class],
    'bad closed watermark rejected' => [static fn (): mixed => $plan208(['expected_closed_yield_watermark_next208' => 'bad watermark']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases208 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next208 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
