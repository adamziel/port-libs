<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows206 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView206 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-206-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-206-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-206',
];
$nextView206 = $currentView206;
$nextView206['source'] = 'main@view-cookie-206-next';
$nextView206['trigger_source'] = 'main@trigger-cookie-206-next';
$postResetView206 = $currentView206;
$postResetView206['source'] = 'main@view-cookie-206-post-reset';
$postResetView206['trigger_source'] = 'main@trigger-cookie-206-post-reset';
$followingView206 = $currentView206;
$followingView206['source'] = 'main@view-cookie-206-following';
$followingView206['trigger_source'] = 'main@trigger-cookie-206-following';
$currentInput206 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput206 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput206 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$followingInput206 = [
    ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
];
$returning206 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan206 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeYieldWatermarkFence(
    $rows206,
    $currentInput206,
    $nextInput206,
    $currentView206,
    $nextView206,
    $returning206,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_206',
        'cursor_name' => 'wp_recursive_view_returning_cursor_206',
        'current_generation' => 'wp-current-returning-206',
        'next_generation' => 'wp-next-returning-206',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.206',
        'drain_ack_token' => 'wp.returning.drain.206',
        'rollback_token' => 'wp.rollback.current.206',
        'reset_generation' => 'wp-current-reset-206',
        'post_reset_current_source_token' => 'wp.current.source.postreset.206',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.206',
        'post_reset_view' => $postResetView206,
        'post_reset_input' => $postResetInput206,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.206',
        'next_cursor' => 'wp.returning.next.cursor.206',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.206',
        'following_current_source_token' => 'wp.current.source.following.206',
        'following_cursor' => 'wp.returning.following.cursor.206',
        'following_current_view' => $followingView206,
        'following_current_input' => $followingInput206,
        'following_generation' => 'wp-following-current-206',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.206',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.206',
        'recursive_child_generation' => 'wp-recursive-child-current-206',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.206',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.206',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.206',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.206',
        'auto_ack_current_generation_receipts_next203' => true,
        'yield_current_source_token_next206' => 'wp.current.recursive.returning.source.206',
        'yield_current_cursor_next206' => 'wp.returning.current.cursor.206',
        'yield_statement_token_next206' => 'wp.recursive.view.returning.statement.206',
    ],
);

$released206 = static fn (): array => $plan206();
$watermark206 = static fn (): string => $released206()['yield_watermark_next206'];
$staleWatermark206 = static fn (): array => $plan206(['expected_yield_watermark_next206' => '0123456789abcdef0123456789abcdef']);
$staleAck206 = static fn (): array => $plan206(['acknowledged_yield_watermark_next206' => 'fedcba9876543210fedcba9876543210']);
$badCount206 = static fn (): array => $plan206(['expected_yield_row_count_next206' => 3]);
$baseHeld206 = static fn (): array => $plan206([
    'auto_ack_current_generation_receipts_next203' => false,
    'acknowledged_current_generation_receipts_next203' => [],
]);
$custom206 = static fn (): array => $plan206([
    'yield_current_source_token_next206' => 'wp.current.recursive.returning.source.custom.206',
    'yield_current_cursor_next206' => 'wp.returning.current.cursor.custom.206',
    'yield_statement_token_next206' => 'wp.recursive.view.returning.statement.custom.206',
]);

$cases206 = [
    'released status' => [static fn (): mixed => $released206()['status_next206'], 'trigger-recursive-view-returning-current-source-next206-watermark-released'],
    'stale expected status' => [static fn (): mixed => $staleWatermark206()['status_next206'], 'trigger-recursive-view-returning-current-source-next206-watermark-held'],
    'stale ack status' => [static fn (): mixed => $staleAck206()['status_next206'], 'trigger-recursive-view-returning-current-source-next206-watermark-held'],
    'bad count status' => [static fn (): mixed => $badCount206()['status_next206'], 'trigger-recursive-view-returning-current-source-next206-row-count-held'],
    'base held status' => [static fn (): mixed => $baseHeld206()['status_next206'], 'trigger-recursive-view-returning-current-source-next206-base-held'],
    'base next203 released' => [static fn (): mixed => $released206()['base']['status_next203'], 'trigger-recursive-view-returning-current-source-next203-generation-released'],
    'base next203 held' => [static fn (): mixed => $baseHeld206()['base']['status_next203'], 'trigger-recursive-view-returning-current-source-next203-receipts-held'],
    'savepoint retained' => [static fn (): mixed => $released206()['savepoint'], 'wp_recursive_view_206'],
    'base visible true' => [static fn (): mixed => $released206()['base_next_source_visible_next206'], true],
    'base visible false' => [static fn (): mixed => $baseHeld206()['base_next_source_visible_next206'], false],
    'source token retained' => [static fn (): mixed => $released206()['yield_current_source_token_next206'], 'wp.current.recursive.returning.source.206'],
    'cursor retained' => [static fn (): mixed => $released206()['yield_current_cursor_next206'], 'wp.returning.current.cursor.206'],
    'statement retained' => [static fn (): mixed => $released206()['yield_statement_token_next206'], 'wp.recursive.view.returning.statement.206'],
    'custom source token retained' => [static fn (): mixed => $custom206()['yield_current_source_token_next206'], 'wp.current.recursive.returning.source.custom.206'],
    'custom cursor retained' => [static fn (): mixed => $custom206()['yield_current_cursor_next206'], 'wp.returning.current.cursor.custom.206'],
    'custom statement retained' => [static fn (): mixed => $custom206()['yield_statement_token_next206'], 'wp.recursive.view.returning.statement.custom.206'],
    'batch key count' => [static fn (): mixed => count($released206()['yield_batch_keys_next206']), 2],
    'batch keys are hex' => [static fn (): mixed => array_map(static fn (string $v): int => preg_match('/^[a-f0-9]{32}$/', $v), $released206()['yield_batch_keys_next206']), [1, 1]],
    'watermark is hex' => [static fn (): mixed => preg_match('/^[a-f0-9]{32}$/', $released206()['yield_watermark_next206']), 1],
    'expected watermark defaults to actual' => [static fn (): mixed => $released206()['expected_yield_watermark_next206'], $watermark206()],
    'ack watermark defaults to actual' => [static fn (): mixed => $released206()['acknowledged_yield_watermark_next206'], $watermark206()],
    'watermark matches released' => [static fn (): mixed => $released206()['yield_watermark_matches_next206'], true],
    'watermark mismatch expected' => [static fn (): mixed => $staleWatermark206()['yield_watermark_matches_next206'], false],
    'watermark mismatch ack' => [static fn (): mixed => $staleAck206()['yield_watermark_matches_next206'], false],
    'row count' => [static fn (): mixed => $released206()['yield_row_count_next206'], 2],
    'expected row count' => [static fn (): mixed => $released206()['expected_yield_row_count_next206'], 2],
    'row count matches' => [static fn (): mixed => $released206()['yield_row_count_matches_next206'], true],
    'row count mismatch' => [static fn (): mixed => $badCount206()['yield_row_count_matches_next206'], false],
    'next visible released' => [static fn (): mixed => $released206()['next_source_visible_after_yield_watermark_next206'], true],
    'next held expected watermark' => [static fn (): mixed => $staleWatermark206()['next_source_visible_after_yield_watermark_next206'], false],
    'next held ack watermark' => [static fn (): mixed => $staleAck206()['next_source_visible_after_yield_watermark_next206'], false],
    'next held bad count' => [static fn (): mixed => $badCount206()['next_source_visible_after_yield_watermark_next206'], false],
    'current row count' => [static fn (): mixed => count($released206()['current_source_rows_next206']), 2],
    'attempted next count' => [static fn (): mixed => count($released206()['attempted_next_source_rows_next206']), 2],
    'visible count released' => [static fn (): mixed => count($released206()['visible_returning_rows_next206']), 4],
    'visible count held' => [static fn (): mixed => count($staleWatermark206()['visible_returning_rows_next206']), 2],
    'held count released' => [static fn (): mixed => count($released206()['held_next_source_rows_next206']), 0],
    'held count stale' => [static fn (): mixed => count($staleWatermark206()['held_next_source_rows_next206']), 2],
    'visible payload names released' => [static fn (): mixed => array_column($released206()['visible_returning_payloads_next206'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'visible payload names held current only' => [static fn (): mixed => array_column($staleWatermark206()['visible_returning_payloads_next206'], 'name'), ['blogdescription_child', 'template_child']],
    'held payload names' => [static fn (): mixed => array_column($staleWatermark206()['held_next_returning_payloads_next206'], 'name'), ['home', 'next_plugin']],
    'current phases' => [static fn (): mixed => array_values(array_unique(array_column($released206()['current_source_rows_next206'], 'yield_phase_next206'))), ['current']],
    'next phases' => [static fn (): mixed => array_values(array_unique(array_column($released206()['attempted_next_source_rows_next206'], 'yield_phase_next206'))), ['next']],
    'current visible always' => [static fn (): mixed => array_values(array_unique(array_column($staleWatermark206()['current_source_rows_next206'], 'visible_after_yield_watermark_next206'))), [true]],
    'next visible released unique' => [static fn (): mixed => array_values(array_unique(array_column($released206()['attempted_next_source_rows_next206'], 'visible_after_yield_watermark_next206'))), [true]],
    'next visible held unique' => [static fn (): mixed => array_values(array_unique(array_column($staleWatermark206()['attempted_next_source_rows_next206'], 'visible_after_yield_watermark_next206'))), [false]],
    'current batch keys tagged' => [static fn (): mixed => array_column($released206()['current_source_rows_next206'], 'yield_batch_key_next206'), $released206()['yield_batch_keys_next206']],
    'next batch key null' => [static fn (): mixed => array_values(array_unique(array_column($released206()['attempted_next_source_rows_next206'], 'yield_batch_key_next206'))), [null]],
    'current watermark tagged' => [static fn (): mixed => array_values(array_unique(array_column($released206()['current_source_rows_next206'], 'yield_watermark_next206'))), [$watermark206()]],
    'next watermark tagged' => [static fn (): mixed => array_values(array_unique(array_column($released206()['attempted_next_source_rows_next206'], 'yield_watermark_next206'))), [$watermark206()]],
    'held reason watermark' => [static fn (): mixed => $staleWatermark206()['blocked_reasons_next206'], ['current-yield-watermark-mismatch']],
    'held reason ack watermark' => [static fn (): mixed => $staleAck206()['blocked_reasons_next206'], ['current-yield-watermark-mismatch']],
    'held reason count' => [static fn (): mixed => $badCount206()['blocked_reasons_next206'], ['current-yield-row-count-mismatch']],
    'held reason base' => [static fn (): mixed => $baseHeld206()['blocked_reasons_next206'], ['current-generation-receipt-missing', 'current-generation-receipt-order-mismatch']],
    'released reasons empty' => [static fn (): mixed => $released206()['blocked_reasons_next206'], []],
    'held next reason tagged' => [static fn (): mixed => $staleWatermark206()['attempted_next_source_rows_next206'][0]['held_by_yield_watermark_reasons_next206'], ['current-yield-watermark-mismatch']],
    'released next reason empty' => [static fn (): mixed => $released206()['attempted_next_source_rows_next206'][0]['held_by_yield_watermark_reasons_next206'], []],
    'plan current rows' => [static fn (): mixed => $released206()['yield_watermark_plan_next206']['current_rows'], 2],
    'plan next rows' => [static fn (): mixed => $released206()['yield_watermark_plan_next206']['attempted_next_rows'], 2],
    'plan visible rows' => [static fn (): mixed => $released206()['yield_watermark_plan_next206']['visible_rows'], 4],
    'plan held rows stale' => [static fn (): mixed => $staleWatermark206()['yield_watermark_plan_next206']['held_next_rows'], 2],
    'plan base visible' => [static fn (): mixed => $released206()['yield_watermark_plan_next206']['base_next_source_visible'], true],
    'plan watermark matches' => [static fn (): mixed => $released206()['yield_watermark_plan_next206']['watermark_matches'], true],
    'plan row count matches' => [static fn (): mixed => $released206()['yield_watermark_plan_next206']['row_count_matches'], true],
    'plan decision released' => [static fn (): mixed => $released206()['yield_watermark_plan_next206']['decision'], 'publish-next-source-after-current-yield-watermark'],
    'plan decision held' => [static fn (): mixed => $staleWatermark206()['yield_watermark_plan_next206']['decision'], 'hold-next-source-until-current-yield-watermark'],
    'yield boundary released' => [static fn (): mixed => $released206()['yield_boundary_next206'], 'recursive-view-returning-next206-current-watermark-then-next'],
    'yield boundary held' => [static fn (): mixed => $staleWatermark206()['yield_boundary_next206'], 'recursive-view-returning-next206-current-watermark-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released206()['dependency_closure_next206'], 'no new support component needed; reuses next203 recursive view RETURNING generation receipts and adds current-source yield watermark fencing'],
    'dependency includes next206' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next206', $released206()['dependencies_next206'], true), true],
    'dependency includes watermark' => [static fn (): mixed => in_array('sqlite-returning-current-source-yield-watermark', $released206()['dependencies_next206'], true), true],
    'dependency includes next203' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next203', $released206()['dependencies_next206'], true), true],
    'dependency includes wordpress' => [static fn (): mixed => in_array('wordpress-recursive-view-returning-current-source-next206', $released206()['dependencies_next206'], true), true],
    'non overlap mentions next203' => [static fn (): mixed => str_contains($released206()['non_overlap_next206'], 'next203 generation handoff'), true],
    'explicit expected watermark accepted' => [static fn (): mixed => $plan206(['expected_yield_watermark_next206' => $watermark206()])['yield_watermark_matches_next206'], true],
    'explicit acknowledged watermark accepted' => [static fn (): mixed => $plan206(['acknowledged_yield_watermark_next206' => $watermark206()])['yield_watermark_matches_next206'], true],
    'explicit row count accepted' => [static fn (): mixed => $plan206(['expected_yield_row_count_next206' => 2])['yield_row_count_matches_next206'], true],
    'bad source token rejected' => [static fn (): mixed => $plan206(['yield_current_source_token_next206' => 'bad token']), InvalidArgumentException::class],
    'bad cursor rejected' => [static fn (): mixed => $plan206(['yield_current_cursor_next206' => 'bad cursor']), InvalidArgumentException::class],
    'bad statement rejected' => [static fn (): mixed => $plan206(['yield_statement_token_next206' => 'bad statement']), InvalidArgumentException::class],
    'bad expected watermark rejected' => [static fn (): mixed => $plan206(['expected_yield_watermark_next206' => 'bad watermark']), InvalidArgumentException::class],
    'bad ack watermark rejected' => [static fn (): mixed => $plan206(['acknowledged_yield_watermark_next206' => 'bad watermark']), InvalidArgumentException::class],
    'bad expected count rejected' => [static fn (): mixed => $plan206(['expected_yield_row_count_next206' => -1]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases206 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next206 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
